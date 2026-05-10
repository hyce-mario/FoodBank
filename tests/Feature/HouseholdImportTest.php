<?php

namespace Tests\Feature;

use App\Exceptions\HouseholdImportValidationException;
use App\Models\AuditLog;
use App\Models\Household;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Services\HouseholdImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 6.5.e — pins the household bulk-import contract.
 *
 *   - Parser refuses any row-level error → all-or-nothing.
 *   - Excel auto-format gotchas (scientific-notation phone, date-coerced ZIP)
 *     surface row-level errors with operator-friendly copy.
 *   - In-file duplicate (same email or same phone twice in one file) is
 *     refused as a paste error — admin fixes the source.
 *   - Duplicate flagging exposes match status and candidate ids for the
 *     preview UI.
 *   - Commit applies decisions inside one DB::transaction.
 *   - Per-row Auditable emissions are suppressed; one
 *     'household.bulk_imported' rollup audit row is written.
 */
class HouseholdImportTest extends TestCase
{
    use RefreshDatabase;

    private HouseholdImportService $service;
    private User $admin;
    private User $createOnly;
    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = Role::create(['name' => 'ADMIN', 'display_name' => 'Admin', 'description' => '']);
        RolePermission::create(['role_id' => $admin->id, 'permission' => '*']);
        $this->admin = User::create([
            'name' => 'A', 'email' => 'a-import@test.local', 'password' => bcrypt('p'),
            'role_id' => $admin->id, 'email_verified_at' => now(),
        ]);

        $createOnly = Role::create(['name' => 'CREATE_ONLY', 'display_name' => 'Create only', 'description' => '']);
        RolePermission::create(['role_id' => $createOnly->id, 'permission' => 'households.view']);
        RolePermission::create(['role_id' => $createOnly->id, 'permission' => 'households.create']);
        $this->createOnly = User::create([
            'name' => 'C', 'email' => 'c-import@test.local', 'password' => bcrypt('p'),
            'role_id' => $createOnly->id, 'email_verified_at' => now(),
        ]);

        $viewer = Role::create(['name' => 'VIEWER', 'display_name' => 'Viewer', 'description' => '']);
        RolePermission::create(['role_id' => $viewer->id, 'permission' => 'households.view']);
        $this->viewer = User::create([
            'name' => 'V', 'email' => 'v-import@test.local', 'password' => bcrypt('p'),
            'role_id' => $viewer->id, 'email_verified_at' => now(),
        ]);

        $this->service = app(HouseholdImportService::class);
    }

    /**
     * Build an UploadedFile (tmp CSV) from header + rows.
     */
    private function csv(array $headers, array $rows): UploadedFile
    {
        $lines = [implode(',', $headers)];
        foreach ($rows as $r) {
            $lines[] = implode(',', array_map(fn ($v) => $this->csvEscape($v), $r));
        }
        $content = implode("\r\n", $lines) . "\r\n";
        return UploadedFile::fake()->createWithContent('households.csv', $content);
    }

    private function csvEscape($value): string
    {
        if ($value === null) {
            return '';
        }
        $s = (string) $value;
        if (str_contains($s, ',') || str_contains($s, '"') || str_contains($s, "\n")) {
            return '"' . str_replace('"', '""', $s) . '"';
        }
        return $s;
    }

    private function defaultHeaders(): array
    {
        return [
            'first_name', 'last_name', 'email', 'phone',
            'city', 'state', 'zip',
            'children_count', 'adults_count', 'seniors_count',
            'vehicle_make', 'vehicle_color',
            'notes',
        ];
    }

    private function row(array $overrides = []): array
    {
        $defaults = [
            'first_name'     => 'Mary',
            'last_name'      => 'Smith',
            'email'          => 'mary@example.test',
            'phone'          => '555-111-2222',
            'city'           => 'Princeton',
            'state'          => 'NJ',
            'zip'            => '08540',
            'children_count' => 1,
            'adults_count'   => 2,
            'seniors_count'  => 0,
            'vehicle_make'   => 'Toyota',
            'vehicle_color'  => 'Silver',
            'notes'          => '',
        ];
        $merged = array_merge($defaults, $overrides);
        return array_values(array_intersect_key($merged + $defaults, array_flip($this->defaultHeaders())));
    }

    // ─── Service: parse + validate ──────────────────────────────────────────

    public function test_parses_csv_with_minimum_required_columns_only(): void
    {
        $file = $this->csv(
            ['first_name', 'last_name', 'children_count', 'adults_count', 'seniors_count'],
            [['Mary', 'Smith', 1, 2, 0]],
        );

        $rows = $this->service->parseAndValidate($file);

        $this->assertCount(1, $rows);
        $this->assertSame('Mary',  $rows[0]['data']['first_name']);
        $this->assertSame('Smith', $rows[0]['data']['last_name']);
        $this->assertSame(1, $rows[0]['data']['children_count']);
        $this->assertNull($rows[0]['data']['email']);
    }

    public function test_skips_blank_rows(): void
    {
        $file = $this->csv($this->defaultHeaders(), [
            $this->row(['first_name' => 'Alice']),
            ['', '', '', '', '', '', '', '', '', '', '', '', ''],
            $this->row(['first_name' => 'Bob', 'email' => 'bob@example.test', 'phone' => '5552223333']),
        ]);

        $rows = $this->service->parseAndValidate($file);

        $this->assertCount(2, $rows);
        $this->assertSame('Alice', $rows[0]['data']['first_name']);
        $this->assertSame('Bob',   $rows[1]['data']['first_name']);
    }

    public function test_skips_hash_comment_rows(): void
    {
        // Defends the user-reported case where leftover README lines
        // ("# README — Household Import Template") inside a hand-edited
        // template caused row-4 first_name/last_name validation errors.
        // Comment-row skipping is silent so the user can keep notes in
        // a file without breaking the import.
        $file = $this->csv($this->defaultHeaders(), [
            $this->row(['first_name' => 'Alice', 'email' => 'alice@example.test', 'phone' => '5550001000']),
            ['# README — Household Import Template', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['#', '', '', '', '', '', '', '', '', '', '', '', ''],
            $this->row(['first_name' => 'Bob', 'email' => 'bob@example.test', 'phone' => '5550002000']),
        ]);

        $rows = $this->service->parseAndValidate($file);

        $this->assertCount(2, $rows);
        $this->assertSame('Alice', $rows[0]['data']['first_name']);
        $this->assertSame('Bob',   $rows[1]['data']['first_name']);
    }

    public function test_strips_utf8_bom_from_first_header_cell(): void
    {
        // Some Windows tools (Notepad, older PowerShell exports) prepend
        // a BOM to the first cell on save. Without defensive stripping,
        // "first_name" becomes "\xEF\xBB\xBFfirst_name" and the parser
        // raises a "missing required column first_name" error.
        $headers = $this->defaultHeaders();
        $headers[0] = "\xEF\xBB\xBF" . $headers[0];

        $file = $this->csv($headers, [
            $this->row(['first_name' => 'WithBom', 'email' => 'bom@example.test', 'phone' => '5550003000']),
        ]);

        $rows = $this->service->parseAndValidate($file);

        $this->assertCount(1, $rows);
        $this->assertSame('WithBom', $rows[0]['data']['first_name']);
    }

    public function test_normalizes_email_to_lowercase_and_state_to_uppercase(): void
    {
        $file = $this->csv($this->defaultHeaders(), [
            $this->row(['email' => 'MARY@EXAMPLE.TEST', 'state' => 'nj']),
        ]);

        $rows = $this->service->parseAndValidate($file);

        $this->assertSame('mary@example.test', $rows[0]['data']['email']);
        $this->assertSame('NJ', $rows[0]['data']['state']);
    }

    public function test_preserves_leading_zero_zip_as_string(): void
    {
        $file = $this->csv($this->defaultHeaders(), [
            $this->row(['zip' => '08540']),
        ]);

        $rows = $this->service->parseAndValidate($file);
        $this->assertSame('08540', $rows[0]['data']['zip']);
    }

    public function test_rejects_missing_required_header_column(): void
    {
        $file = $this->csv(
            ['first_name', 'last_name', 'children_count', 'adults_count'], // seniors_count missing
            [['Mary', 'Smith', 1, 2]],
        );

        try {
            $this->service->parseAndValidate($file);
            $this->fail('Expected HouseholdImportValidationException');
        } catch (HouseholdImportValidationException $e) {
            $this->assertNotEmpty($e->errors);
            $this->assertSame('header', $e->errors[0]['row']);
            $this->assertStringContainsString('seniors_count', $e->errors[0]['message']);
        }
    }

    public function test_rejects_invalid_email_with_row_level_error(): void
    {
        $file = $this->csv($this->defaultHeaders(), [
            $this->row(['email' => 'not-an-email']),
        ]);

        try {
            $this->service->parseAndValidate($file);
            $this->fail('Expected HouseholdImportValidationException');
        } catch (HouseholdImportValidationException $e) {
            $this->assertSame(2, $e->errors[0]['row']);
            $this->assertSame('email', $e->errors[0]['column']);
        }
    }

    public function test_rejects_in_file_duplicate_email(): void
    {
        $file = $this->csv($this->defaultHeaders(), [
            $this->row(['first_name' => 'Mary', 'email' => 'shared@example.test', 'phone' => '111']),
            $this->row(['first_name' => 'Bob',  'email' => 'shared@example.test', 'phone' => '222']),
        ]);

        try {
            $this->service->parseAndValidate($file);
            $this->fail('Expected HouseholdImportValidationException');
        } catch (HouseholdImportValidationException $e) {
            $this->assertSame(3, $e->errors[0]['row']);
            $this->assertSame('email', $e->errors[0]['column']);
            $this->assertStringContainsString('row 2', $e->errors[0]['message']);
        }
    }

    public function test_rejects_in_file_duplicate_phone_via_digits_only_match(): void
    {
        $file = $this->csv($this->defaultHeaders(), [
            $this->row(['first_name' => 'Mary', 'email' => 'a@example.test', 'phone' => '(555) 111-2222']),
            $this->row(['first_name' => 'Bob',  'email' => 'b@example.test', 'phone' => '5551112222']),
        ]);

        try {
            $this->service->parseAndValidate($file);
            $this->fail('Expected HouseholdImportValidationException');
        } catch (HouseholdImportValidationException $e) {
            $this->assertSame('phone', $e->errors[0]['column']);
        }
    }

    public function test_rejects_negative_count(): void
    {
        $file = $this->csv($this->defaultHeaders(), [
            $this->row(['children_count' => -1]),
        ]);

        try {
            $this->service->parseAndValidate($file);
            $this->fail('Expected HouseholdImportValidationException');
        } catch (HouseholdImportValidationException $e) {
            $this->assertSame('children_count', $e->errors[0]['column']);
        }
    }

    // ─── Service: duplicate flagging ────────────────────────────────────────

    public function test_flag_duplicates_marks_new_when_no_candidates(): void
    {
        $file = $this->csv($this->defaultHeaders(), [$this->row(['email' => 'unique@example.test', 'phone' => '5559999999'])]);
        $rows = $this->service->parseAndValidate($file);

        $rows = $this->service->flagDuplicates($rows);

        $this->assertSame('new', $rows[0]['status']);
        $this->assertEmpty($rows[0]['matches']);
    }

    public function test_flag_duplicates_marks_exact_match_on_email(): void
    {
        Household::create([
            'household_number' => 'H001', 'first_name' => 'Existing', 'last_name' => 'Person',
            'email' => 'mary@example.test', 'household_size' => 1,
            'qr_token' => bin2hex(random_bytes(16)),
        ]);

        $file = $this->csv($this->defaultHeaders(), [$this->row(['email' => 'MARY@example.test'])]);
        $rows = $this->service->parseAndValidate($file);
        $rows = $this->service->flagDuplicates($rows);

        $this->assertSame('exact_match', $rows[0]['status']);
        $this->assertCount(1, $rows[0]['matches']);
    }

    public function test_flag_duplicates_marks_exact_match_on_phone_via_digits_only(): void
    {
        Household::create([
            'household_number' => 'H002', 'first_name' => 'Existing', 'last_name' => 'Person',
            'phone' => '5551112222', 'household_size' => 1,
            'qr_token' => bin2hex(random_bytes(16)),
        ]);

        // Different formatting on the import side — must still match.
        $file = $this->csv($this->defaultHeaders(), [$this->row([
            'first_name' => 'Different', 'last_name' => 'Lastname',
            'email' => 'novel@example.test', 'phone' => '(555) 111-2222',
        ])]);
        $rows = $this->service->parseAndValidate($file);
        $rows = $this->service->flagDuplicates($rows);

        $this->assertSame('exact_match', $rows[0]['status']);
    }

    public function test_flag_duplicates_marks_fuzzy_match_on_soundex_name(): void
    {
        Household::create([
            'household_number' => 'H003', 'first_name' => 'Mary', 'last_name' => 'Smith',
            'household_size' => 1, 'qr_token' => bin2hex(random_bytes(16)),
        ]);

        // Same soundex (Mari ≈ Mary, Smith ≈ Smyth), different email/phone.
        $file = $this->csv($this->defaultHeaders(), [$this->row([
            'first_name' => 'Mari', 'last_name' => 'Smyth',
            'email' => 'novel@example.test', 'phone' => '5559998888',
        ])]);
        $rows = $this->service->parseAndValidate($file);
        $rows = $this->service->flagDuplicates($rows);

        $this->assertSame('fuzzy_match', $rows[0]['status']);
    }

    // ─── Service: commit ────────────────────────────────────────────────────

    public function test_commit_creates_new_household_via_household_service(): void
    {
        $file = $this->csv($this->defaultHeaders(), [$this->row(['first_name' => 'Brand', 'last_name' => 'New', 'email' => 'new@example.test', 'phone' => '5551110000'])]);
        $rows = $this->service->flagDuplicates($this->service->parseAndValidate($file));
        $decisions = [2 => ['action' => 'create']];

        $result = $this->service->commit($rows, $decisions, $this->admin);

        $this->assertSame(1, $result['created']);
        $this->assertSame(0, $result['updated']);
        $hh = Household::where('first_name', 'Brand')->first();
        $this->assertNotNull($hh);
        $this->assertNotEmpty($hh->household_number);
        $this->assertNotEmpty($hh->qr_token);
        $this->assertSame(3, $hh->household_size); // 1+2+0
    }

    public function test_commit_updates_existing_household_with_whitelisted_fields_only(): void
    {
        $existing = Household::create([
            'household_number' => 'H100',
            'first_name' => 'Old', 'last_name' => 'Name',
            'email' => 'old@example.test', 'phone' => '5550000000',
            'household_size' => 1, 'qr_token' => 'orig-token-1234567890123456',
        ]);

        $file = $this->csv($this->defaultHeaders(), [$this->row([
            'first_name' => 'New', 'last_name' => 'Name',
            'email' => 'old@example.test', 'phone' => '5559998888',
            'children_count' => 2, 'adults_count' => 3, 'seniors_count' => 1,
        ])]);
        $rows      = $this->service->flagDuplicates($this->service->parseAndValidate($file));
        $decisions = [2 => ['action' => 'update', 'update_target_id' => $existing->id]];

        $this->service->commit($rows, $decisions, $this->admin);

        $existing->refresh();
        $this->assertSame('New', $existing->first_name);
        $this->assertSame('5559998888', $existing->phone);
        $this->assertSame(6, $existing->household_size); // recomputed
        // Preserved fields:
        $this->assertSame('H100',                          $existing->household_number);
        $this->assertSame('orig-token-1234567890123456',   $existing->qr_token);
    }

    public function test_commit_skip_decision_is_no_op(): void
    {
        $file = $this->csv($this->defaultHeaders(), [$this->row(['first_name' => 'Skipped'])]);
        $rows = $this->service->flagDuplicates($this->service->parseAndValidate($file));

        $result = $this->service->commit($rows, [2 => ['action' => 'skip']], $this->admin);

        $this->assertSame(1, $result['skipped']);
        $this->assertSame(0, $result['created']);
        $this->assertSame(0, Household::where('first_name', 'Skipped')->count());
    }

    public function test_commit_rolls_back_on_mid_batch_failure(): void
    {
        $file = $this->csv($this->defaultHeaders(), [
            $this->row(['first_name' => 'First',  'email' => 'first@example.test', 'phone' => '111']),
            $this->row(['first_name' => 'Second', 'email' => 'second@example.test', 'phone' => '222']),
        ]);
        $rows = $this->service->flagDuplicates($this->service->parseAndValidate($file));

        // Force a mid-batch error: row 3 targets a non-existent household.
        $decisions = [
            2 => ['action' => 'create'],
            3 => ['action' => 'update', 'update_target_id' => 99999],
        ];

        try {
            $this->service->commit($rows, $decisions, $this->admin);
            $this->fail('Expected RuntimeException on missing target');
        } catch (\RuntimeException) {
            // expected
        }

        // Row 1 ("First") must NOT be in the DB — full rollback.
        $this->assertSame(0, Household::where('first_name', 'First')->count());
    }

    public function test_commit_writes_single_rollup_audit_row_not_per_household(): void
    {
        $file = $this->csv($this->defaultHeaders(), [
            $this->row(['first_name' => 'A', 'email' => 'a@example.test', 'phone' => '111']),
            $this->row(['first_name' => 'B', 'email' => 'b@example.test', 'phone' => '222']),
            $this->row(['first_name' => 'C', 'email' => 'c@example.test', 'phone' => '333']),
        ]);
        $rows = $this->service->flagDuplicates($this->service->parseAndValidate($file));

        $beforeRows = AuditLog::count();

        $this->service->commit($rows, [
            2 => ['action' => 'create'],
            3 => ['action' => 'create'],
            4 => ['action' => 'create'],
        ], $this->admin);

        $newRows = AuditLog::count() - $beforeRows;
        $this->assertSame(1, $newRows, 'Bulk import must write exactly ONE rollup audit row, not per-household');

        $rollup = AuditLog::orderByDesc('id')->first();
        $this->assertSame('households_imported', $rollup->action);
        // Action name must fit audit_logs.action varchar(20). MySQL strict
        // mode rejects with SQLSTATE[22001] but SQLite silently truncates,
        // so this assertion pins the constraint at the test layer.
        $this->assertLessThanOrEqual(20, strlen($rollup->action));
        $this->assertSame(3, $rollup->after_json['created']);
        $this->assertSame(3, count($rollup->after_json['created_household_ids']));
    }

    // ─── HTTP layer ─────────────────────────────────────────────────────────

    public function test_unauthenticated_upload_form_redirects_to_login(): void
    {
        $this->get(route('households.import.create'))->assertRedirect('/login');
    }

    public function test_viewer_cannot_open_upload_form(): void
    {
        $this->actingAs($this->viewer)
             ->get(route('households.import.create'))
             ->assertForbidden();
    }

    public function test_create_only_role_can_open_upload_form(): void
    {
        $this->actingAs($this->createOnly)
             ->get(route('households.import.create'))
             ->assertOk();
    }

    public function test_template_xlsx_downloads(): void
    {
        $resp = $this->actingAs($this->admin)
                     ->get(route('households.import.template', 'xlsx'))
                     ->assertOk();
        $this->assertStringContainsString('spreadsheetml', $resp->headers->get('Content-Type'));
    }

    public function test_template_csv_downloads_with_bom(): void
    {
        $resp = $this->actingAs($this->admin)
                     ->get(route('households.import.template', 'csv'))
                     ->assertOk();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $resp->streamedContent());
    }

    public function test_upload_with_validation_errors_renders_error_table(): void
    {
        $file = $this->csv(['first_name', 'last_name', 'children_count', 'adults_count'], [['Mary', 'Smith', 1, 1]]); // missing seniors_count

        $this->actingAs($this->admin)
             ->from(route('households.import.create'))
             ->post(route('households.import.store'), ['file' => $file])
             ->assertRedirect(route('households.import.create'))
             ->assertSessionHas('import_errors');

        $this->assertSame(0, Household::count());
    }

    public function test_happy_path_upload_preview_commit_round_trip(): void
    {
        $existing = Household::create([
            'household_number' => 'H200',
            'first_name' => 'Existing', 'last_name' => 'User',
            'email' => 'existing@example.test', 'household_size' => 1,
            'qr_token' => bin2hex(random_bytes(16)),
        ]);

        $file = $this->csv($this->defaultHeaders(), [
            $this->row(['first_name' => 'Newcomer', 'email' => 'new1@example.test', 'phone' => '5550001111']),
            $this->row(['first_name' => 'Existing', 'last_name' => 'User', 'email' => 'EXISTING@example.test', 'phone' => '5550002222', 'children_count' => 3]),
            $this->row(['first_name' => 'AnotherNew', 'email' => 'new2@example.test', 'phone' => '5550003333']),
        ]);

        // Step 1 — upload, expect redirect to preview.
        $resp = $this->actingAs($this->admin)
                     ->post(route('households.import.store'), ['file' => $file]);
        $resp->assertStatus(302);
        $location = $resp->headers->get('Location');
        $this->assertStringContainsString('/households/import/preview/', $location);

        // Extract the token from the redirect URL.
        $token = basename(parse_url($location, PHP_URL_PATH));

        // Step 2 — preview page renders.
        $this->actingAs($this->admin)
             ->get($location)
             ->assertOk()
             ->assertSee('Newcomer')
             ->assertSee('AnotherNew');

        // Step 3 — commit with mixed decisions.
        $this->actingAs($this->admin)
             ->post(route('households.import.commit'), [
                 'token' => $token,
                 'decisions' => [
                     2 => ['action' => 'create'],
                     3 => ['action' => 'update', 'update_target_id' => $existing->id],
                     4 => ['action' => 'skip'],
                 ],
             ])
             ->assertRedirect(route('households.index'))
             ->assertSessionHas('success');

        $this->assertNotNull(Household::where('first_name', 'Newcomer')->first());
        $this->assertNull(Household::where('first_name', 'AnotherNew')->first());
        $existing->refresh();
        $this->assertSame(3, $existing->children_count);
    }

    public function test_expired_token_redirects_to_upload_with_friendly_error(): void
    {
        $bogusToken = (string) Str::uuid();

        $this->actingAs($this->admin)
             ->get(route('households.import.preview', $bogusToken))
             ->assertRedirect(route('households.import.create'))
             ->assertSessionHas('error');
    }

    public function test_commit_rejects_update_decision_for_create_only_role(): void
    {
        $existing = Household::create([
            'household_number' => 'H300', 'first_name' => 'X', 'last_name' => 'Y',
            'household_size' => 1, 'qr_token' => bin2hex(random_bytes(16)),
        ]);

        // Stage a fake preview payload directly in cache so we can exercise
        // the commit endpoint without re-running the upload as admin.
        $token = (string) Str::uuid();
        Cache::put("household_import:{$this->createOnly->id}:{$token}", [
            'rows' => [[
                'row_number' => 2,
                'data' => [
                    'first_name' => 'Test', 'last_name' => 'User',
                    'email' => null, 'phone' => null,
                    'city' => null, 'state' => null, 'zip' => null,
                    'children_count' => 1, 'adults_count' => 1, 'seniors_count' => 0,
                    'vehicle_make' => null, 'vehicle_color' => null, 'notes' => null,
                ],
                'matches' => [], 'status' => 'new',
            ]],
            'filename' => 'fake.csv',
        ], now()->addMinutes(30));

        $this->actingAs($this->createOnly)
             ->post(route('households.import.commit'), [
                 'token' => $token,
                 'decisions' => [
                     2 => ['action' => 'update', 'update_target_id' => $existing->id],
                 ],
             ])
             ->assertForbidden();

        // Existing row untouched.
        $existing->refresh();
        $this->assertSame('X', $existing->first_name);
    }
}
