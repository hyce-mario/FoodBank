<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase C — pin the three household-directory exports (Print HTML, PDF, XLSX).
 *
 * The exports must (1) authorize like the index does, (2) return the right
 * MIME / content-type, (3) include EVERY row matching the current filters
 * (not just the first page), and (4) reflect search/zip/size/attendance
 * filters when present so the export matches what the user sees on screen.
 */
class HouseholdExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['name' => 'ADMIN', 'display_name' => 'Administrator', 'description' => '']);
        RolePermission::create(['role_id' => $adminRole->id, 'permission' => '*']);
        $this->admin = User::create([
            'name'              => 'Admin',
            'email'             => 'admin@test.local',
            'password'          => bcrypt('p'),
            'role_id'           => $adminRole->id,
            'email_verified_at' => now(),
        ]);
    }

    private function makeHousehold(string $first, array $overrides = []): Household
    {
        return Household::create(array_merge([
            'household_number' => substr(md5($first . microtime(true)), 0, 6),
            'first_name'       => $first,
            'last_name'        => 'Family',
            'household_size'   => 2,
            'children_count'   => 0,
            'adults_count'     => 2,
            'seniors_count'    => 0,
            'qr_token'         => substr(md5($first . random_int(0, 99999)), 0, 32),
        ], $overrides));
    }

    public function test_print_export_returns_html_with_all_rows(): void
    {
        // 30 households, paginator default is 25 — print export must show all 30.
        for ($i = 1; $i <= 30; $i++) {
            $this->makeHousehold("Person{$i}");
        }

        $response = $this->actingAs($this->admin)->get(route('households.export.print'));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/html; charset=utf-8');

        // Spot check the first and last names so we know the page actually
        // rendered all 30 rows, not just page one.
        $response->assertSee('Person1');
        $response->assertSee('Person30');
        $response->assertSee('30 households');
    }

    public function test_pdf_export_returns_pdf_mime(): void
    {
        $this->makeHousehold('Alice');
        $this->makeHousehold('Bob');

        $response = $this->actingAs($this->admin)->get(route('households.export.pdf'));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');

        // PDF byte stream — PDFs always start with "%PDF-".
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_xlsx_export_returns_xlsx_mime(): void
    {
        $this->makeHousehold('Alice');
        $this->makeHousehold('Bob');

        $response = $this->actingAs($this->admin)->get(route('households.export.xlsx'));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        // XLSX is a ZIP archive; magic bytes are PK\x03\x04.
        $content = $response->streamedContent();
        $this->assertStringStartsWith("PK\x03\x04", $content);
    }

    public function test_search_filter_applies_to_export(): void
    {
        // The directory's only filter inputs are the search box (which now
        // also matches zip) and the attendance dropdown. Confirm an export
        // requested with ?search= returns only matching rows AND surfaces
        // the filter on the export header.
        $this->makeHousehold('AliceZipA', ['zip' => '10001']);
        $this->makeHousehold('BobZipB',   ['zip' => '20002']);
        $this->makeHousehold('CarolZipA', ['zip' => '10001']);

        $response = $this->actingAs($this->admin)
            ->get(route('households.export.print', ['search' => '10001']));

        $response->assertOk();
        $response->assertSee('AliceZipA');
        $response->assertSee('CarolZipA');
        $response->assertDontSee('BobZipB');
        $response->assertSee('Filters applied:');
        $response->assertSee('Search: &quot;10001&quot;', false);
    }

    public function test_search_filter_applies_to_xlsx_export(): void
    {
        $this->makeHousehold('UniqueAlice');
        $this->makeHousehold('OtherBob');

        $response = $this->actingAs($this->admin)
            ->get(route('households.export.xlsx', ['search' => 'UniqueAlice']));

        $response->assertOk();
        // Confirm the XLSX only contains Alice. We open the workbook from the
        // streamed bytes via PhpSpreadsheet and look at sheet contents.
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx_test_');
        file_put_contents($tmp, $response->streamedContent());
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp);
        $cells       = $spreadsheet->getActiveSheet()->toArray();
        unlink($tmp);

        $flat = collect($cells)->flatten()->filter()->implode(' | ');
        $this->assertStringContainsString('UniqueAlice', $flat);
        $this->assertStringNotContainsString('OtherBob', $flat);
    }

    public function test_unauthorized_user_cannot_export(): void
    {
        // Role with only households.view (which the policy treats as viewAny).
        // Actually, ResourcePoliciesTest shows households.view DOES grant
        // viewAny — so we instead create a role with NO household perms.
        $noPermRole = Role::create(['name' => 'NONE', 'display_name' => 'Nothing', 'description' => '']);
        $user       = User::create([
            'name'              => 'Nobody',
            'email'             => 'nobody@test.local',
            'password'          => bcrypt('p'),
            'role_id'           => $noPermRole->id,
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('households.export.pdf'));
        $response->assertForbidden();
    }
}
