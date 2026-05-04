<?php

namespace App\Services;

use App\Models\AppSetting;

class SettingService
{
    /** In-request cache: key → casted value */
    private static ?array $cache = null;

    // ─────────────────────────────────────────────────────────────────────────
    // Public API
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Get one setting value by full key (e.g. 'general.app_name').
     * Falls back to the definition default, then to $default.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $loaded = static::loadAll();

        if (array_key_exists($key, $loaded)) {
            return $loaded[$key];
        }

        // Try definition default
        $def = static::definition($key);
        return $def ? ($def['default'] ?? $default) : $default;
    }

    /**
     * Return all current values for a group, merged with definition defaults.
     * Keys are short (without the group prefix).
     */
    public static function group(string $group): array
    {
        $defs    = static::groupDefinitions($group);
        $loaded  = static::loadAll();
        $result  = [];

        foreach ($defs as $shortKey => $def) {
            $fullKey = "{$group}.{$shortKey}";
            $result[$shortKey] = array_key_exists($fullKey, $loaded)
                ? $loaded[$fullKey]
                : ($def['default'] ?? null);
        }

        return $result;
    }

    /**
     * Persist updated values for a group.
     * Only keys that exist in the group's definitions are saved.
     * Boolean fields absent from $data are stored as false.
     */
    public static function updateGroup(string $group, array $data): void
    {
        $defs = static::groupDefinitions($group);

        foreach ($defs as $shortKey => $def) {
            $fullKey = "{$group}.{$shortKey}";
            $type    = $def['type'];

            if ($type === 'file') {
                continue; // managed by dedicated upload/delete routes
            } elseif ($type === 'boolean') {
                // The hidden input always sends '0'; checkbox sends '1' when checked.
                // Check the actual value, not just key existence.
                $value = filter_var($data[$shortKey] ?? false, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
            } elseif ($type === 'multi_select') {
                // Checkbox-grid input — comes in as an array. Whitelist
                // against the definition's `options` so a tampered request
                // can't inject arbitrary values, then JSON-encode for storage.
                $arr  = (array) ($data[$shortKey] ?? []);
                $opts = array_keys($def['options'] ?? []);
                if (! empty($opts)) {
                    $arr = array_values(array_intersect($arr, $opts));
                } else {
                    $arr = array_values($arr);
                }
                $value = json_encode($arr);
            } else {
                $value = isset($data[$shortKey]) ? (string) $data[$shortKey] : '';
            }

            AppSetting::updateOrCreate(
                ['key' => $fullKey],
                ['group' => $group, 'value' => $value, 'type' => $type]
            );
        }

        static::flush();
    }

    /**
     * Set a single setting by full key.
     */
    public static function set(string $key, mixed $value): void
    {
        [$group] = explode('.', $key, 2);
        $def     = static::definition($key);
        $type    = $def['type'] ?? 'string';

        AppSetting::updateOrCreate(
            ['key' => $key],
            ['group' => $group, 'value' => (string) $value, 'type' => $type]
        );

        static::flush();
    }

    /** Clear the in-request cache so the next read hits the DB. */
    public static function flush(): void
    {
        static::$cache = null;
    }

    /**
     * Return a branding asset (logo or favicon) as a self-contained data URI,
     * or null if not configured / the file is missing. Reading the file off
     * the public disk and inlining as base64 sidesteps every URL-routing
     * concern (APP_URL mismatch, missing public/storage symlink, vhost
     * rewrites) so the same code path works in dev XAMPP, staging, and prod.
     */
    public static function brandingLogoDataUri(): ?string
    {
        return static::brandingAssetDataUri('branding.logo_path');
    }

    public static function brandingFaviconDataUri(): ?string
    {
        return static::brandingAssetDataUri('branding.favicon_path');
    }

    private static function brandingAssetDataUri(string $settingKey): ?string
    {
        $path = (string) static::get($settingKey, '');
        if ($path === '') {
            return null;
        }

        $disk = \Illuminate\Support\Facades\Storage::disk('public');
        if (! $disk->exists($path)) {
            return null;
        }

        $mime = $disk->mimeType($path) ?: 'image/png';
        return 'data:' . $mime . ';base64,' . base64_encode($disk->get($path));
    }

    /** Return the ordered list of settings groups as slug => label. */
    public static function groups(): array
    {
        return [
            'general'       => 'General',
            'organization'  => 'Organization Profile',
            'branding'      => 'Branding & Theme',
            'event_queue'   => 'Event & Queue',
            'public_access' => 'Public Access',
            'households'    => 'Households & Intake',
            'reviews'       => 'Reviews & Feedback',
            'inventory'     => 'Inventory',
            'finance'       => 'Finance',
            'notifications' => 'Notifications & Contact',
            'security'      => 'Users & Security',
            'system'        => 'System Preferences',
        ];
    }

    /** Return definitions for a single group. */
    public static function groupDefinitions(string $group): array
    {
        return static::definitions()[$group] ?? [];
    }

    /** Return the definition for a single full key. */
    public static function definition(string $fullKey): ?array
    {
        [$group, $shortKey] = array_pad(explode('.', $fullKey, 2), 2, null);
        if (! $shortKey) {
            return null;
        }
        return static::definitions()[$group][$shortKey] ?? null;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // All Definitions
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Master list of every setting in the app, organized by group.
     *
     * Each entry:
     *   label       — Human-readable field label
     *   type        — string | boolean | integer | float | text | select | color
     *   default     — Value used when the setting is absent from the DB
     *   description — Optional helper text shown beneath the field
     *   options     — Array of value => label pairs for 'select' type
     */
    public static function definitions(): array
    {
        return [

            // ── General ──────────────────────────────────────────────────────
            'general' => [
                'app_name' => [
                    'label'       => 'Application Name',
                    'type'        => 'string',
                    'default'     => 'FoodBank',
                    'description' => 'Displayed in the browser title bar and throughout the admin.',
                ],
                'timezone' => [
                    'label'       => 'Default Timezone',
                    'type'        => 'select',
                    'default'     => 'America/Chicago',
                    'description' => 'Used for event dates, timestamps, and reports.',
                    'options'     => [
                        'America/New_York'    => 'Eastern Time (US)',
                        'America/Chicago'     => 'Central Time (US)',
                        'America/Denver'      => 'Mountain Time (US)',
                        'America/Los_Angeles' => 'Pacific Time (US)',
                        'America/Phoenix'     => 'Arizona (no DST)',
                        'America/Anchorage'   => 'Alaska',
                        'Pacific/Honolulu'    => 'Hawaii',
                        'UTC'                 => 'UTC',
                    ],
                ],
                'date_format' => [
                    'label'       => 'Date Format',
                    'type'        => 'select',
                    'default'     => 'M j, Y',
                    'description' => 'Controls how dates are displayed throughout the app.',
                    'options'     => [
                        'M j, Y'   => 'Apr 15, 2026',
                        'm/d/Y'    => '04/15/2026',
                        'Y-m-d'    => '2026-04-15',
                        'd/m/Y'    => '15/04/2026',
                        'F j, Y'   => 'April 15, 2026',
                    ],
                ],
                'time_format' => [
                    'label'       => 'Time Format',
                    'type'        => 'select',
                    'default'     => 'g:i A',
                    'description' => 'Controls how times are displayed.',
                    'options'     => [
                        'g:i A' => '12-hour (2:30 PM)',
                        'H:i'   => '24-hour (14:30)',
                    ],
                ],
                'currency' => [
                    'label'       => 'Default Currency',
                    'type'        => 'select',
                    'default'     => 'USD',
                    'description' => 'Used across finance and reporting modules.',
                    'options'     => [
                        'USD' => 'USD — US Dollar',
                        'CAD' => 'CAD — Canadian Dollar',
                        'GBP' => 'GBP — British Pound',
                        'EUR' => 'EUR — Euro',
                        'AUD' => 'AUD — Australian Dollar',
                    ],
                ],
                'records_per_page' => [
                    'label'       => 'Default Records Per Page',
                    'type'        => 'select',
                    'default'     => '25',
                    'description' => 'Default pagination limit for index pages.',
                    'options'     => [
                        '10'  => '10',
                        '25'  => '25',
                        '50'  => '50',
                        '100' => '100',
                    ],
                ],
                'dashboard_default_event' => [
                    'label'       => 'Dashboard Default Event',
                    'type'        => 'select',
                    'default'     => 'current',
                    'description' => 'Which event the dashboard highlights when loaded.',
                    'options'     => [
                        'current' => 'Active/current event',
                        'recent'  => 'Most recent completed event',
                        'none'    => 'No event pre-selected',
                    ],
                ],
                'max_upload_size_mb' => [
                    'label'       => 'Max Upload Size (MB)',
                    'type'        => 'integer',
                    'default'     => 50,
                    'description' => 'Cap on media upload size.',
                    'placeholder' => '50',
                ],
                'allowed_upload_formats' => [
                    'label'       => 'Allowed Upload Formats',
                    'type'        => 'multi_select',
                    'default'     => [
                        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
                        'video/mp4',  'video/quicktime', 'video/x-msvideo', 'video/webm',
                        'application/pdf',
                    ],
                    'description' => 'File types accepted by the uploader.',
                    'options'     => [
                        'image/jpeg'       => 'JPEG / JPG',
                        'image/png'        => 'PNG',
                        'image/gif'        => 'GIF',
                        'image/webp'       => 'WebP',
                        'video/mp4'        => 'MP4',
                        'video/quicktime'  => 'QuickTime (.mov)',
                        'video/x-msvideo'  => 'AVI',
                        'video/webm'       => 'WebM',
                        'application/pdf'  => 'PDF',
                    ],
                ],
            ],

            // ── Organization Profile ─────────────────────────────────────────
            'organization' => [
                'name' => [
                    'label'       => 'Organization Name',
                    'type'        => 'string',
                    'default'     => 'Our Food Bank',
                    'description' => 'Used in public pages, reports, and print headers.',
                ],
                'email' => [
                    'label'       => 'Contact Email',
                    'type'        => 'string',
                    'default'     => '',
                    'description' => 'Primary contact email shown on public pages.',
                ],
                'phone' => [
                    'label'       => 'Phone Number',
                    'type'        => 'string',
                    'default'     => '',
                    'description' => '',
                ],
                'website' => [
                    'label'       => 'Website URL',
                    'type'        => 'string',
                    'default'     => '',
                    'description' => '',
                ],
                'address_line1' => [
                    'label'   => 'Address Line 1',
                    'type'    => 'string',
                    'default' => '',
                ],
                'address_line2' => [
                    'label'   => 'Address Line 2',
                    'type'    => 'string',
                    'default' => '',
                ],
                'city' => [
                    'label'   => 'City',
                    'type'    => 'string',
                    'default' => '',
                ],
                'state' => [
                    'label'   => 'State',
                    'type'    => 'string',
                    'default' => '',
                ],
                'zip' => [
                    'label'   => 'ZIP / Postal Code',
                    'type'    => 'string',
                    'default' => '',
                ],
                'about' => [
                    'label'       => 'About / Public Description',
                    'type'        => 'text',
                    'default'     => '',
                    'description' => 'Shown on the public event registration page.',
                ],
            ],

            // ── Branding & Theme ─────────────────────────────────────────────
            'branding' => [
                'primary_color' => [
                    'label'       => 'Primary Color',
                    'type'        => 'color',
                    'default'     => '#f97316',
                    'description' => 'Main brand/action color (buttons, highlights).',
                ],
                'secondary_color' => [
                    'label'       => 'Secondary Color',
                    'type'        => 'color',
                    'default'     => '#1b2b4b',
                    'description' => 'Used for the sidebar, headers, and accents.',
                ],
                'accent_color' => [
                    'label'       => 'Accent Color',
                    'type'        => 'color',
                    'default'     => '#ea6b0a',
                    'description' => 'Hover states and secondary actions.',
                ],
                'sidebar_bg' => [
                    'label'   => 'Sidebar Background',
                    'type'    => 'color',
                    'default' => '#ffffff',
                ],
                'nav_text_color' => [
                    'label'   => 'Navigation Text Color',
                    'type'    => 'color',
                    'default' => '#374151',
                ],
                'logo_display' => [
                    'label'       => 'Logo Display Style',
                    'type'        => 'select',
                    'default'     => 'icon_text',
                    'description' => 'How the logo appears in the sidebar.',
                    'options'     => [
                        'icon_text' => 'Icon + Text',
                        'icon_only' => 'Icon Only',
                        'text_only' => 'Text Only',
                    ],
                ],
                'appearance' => [
                    'label'   => 'Appearance Mode',
                    'type'    => 'select',
                    'default' => 'light',
                    'options' => [
                        'light'  => 'Light',
                        'dark'   => 'Dark (coming soon)',
                        'system' => 'Follow System',
                    ],
                ],
                'logo_path' => [
                    'label'       => 'Application Logo',
                    'type'        => 'file',
                    'default'     => '',
                    'description' => 'Displayed in the sidebar. PNG, SVG, or WebP — max 2 MB.',
                ],
                'favicon_path' => [
                    'label'       => 'Favicon',
                    'type'        => 'file',
                    'default'     => '',
                    'description' => 'Browser tab icon. ICO or PNG — ideally 32×32 px.',
                ],
            ],

            // ── Event & Queue ─────────────────────────────────────────────────
            'event_queue' => [
                'default_lane_count' => [
                    'label'       => 'Default Lane Count for New Events',
                    'type'        => 'integer',
                    'default'     => 1,
                    'description' => 'Number of queue lanes created automatically on new events.',
                ],
                'allow_lane_drag' => [
                    'label'       => 'Allow Lane Drag (across columns)',
                    'type'        => 'boolean',
                    'default'     => true,
                    'description' => 'Enables drag-and-drop of queue cards between lanes.',
                ],
                'allow_queue_reorder' => [
                    'label'       => 'Allow Queue Reorder',
                    'type'        => 'boolean',
                    'default'     => true,
                    'description' => 'Lets staff reorder households within a lane.',
                ],
                're_checkin_policy' => [
                    'label'       => 'Re-Check-In Policy',
                    'type'        => 'select',
                    'default'     => 'override',
                    'description' => '',
                    'options'     => [
                        'allow'    => 'Allow',
                        'override' => 'Require Supervisor Override',
                        'deny'     => 'Deny',
                    ],
                ],
                'queue_poll_interval' => [
                    'label'       => 'Queue Polling Interval (seconds)',
                    'type'        => 'integer',
                    'default'     => 10,
                    'description' => 'How often the queue/monitor auto-refreshes.',
                ],
                'show_household_names_scanner' => [
                    'label'       => 'Show Household Names on Scanner Page',
                    'type'        => 'boolean',
                    'default'     => true,
                    'description' => '',
                ],
                'show_vehicle_info_queue' => [
                    'label'       => 'Show Vehicle Info on Queue Cards',
                    'type'        => 'boolean',
                    'default'     => true,
                    'description' => '',
                ],
                'show_family_breakdown' => [
                    'label'       => 'Show Family Breakdown Summary',
                    'type'        => 'boolean',
                    'default'     => true,
                    'description' => 'Display family count breakdown on queue and monitor cards.',
                ],
                'bag_calculation_strategy' => [
                    'label'       => 'Bag Calculation Strategy',
                    'type'        => 'select',
                    'default'     => 'per_person',
                    'description' => 'How food bags are calculated per household visit.',
                    'options'     => [
                        'per_person'    => 'Per person',
                        'per_family'    => 'Per family unit',
                        'per_household' => 'Per household (flat)',
                        'custom'        => 'Custom ruleset',
                    ],
                ],
                'default_bags_per_person' => [
                    'label'       => 'Default Bags Per Person (fallback)',
                    'type'        => 'float',
                    'default'     => 1.0,
                    'description' => 'Used when no allocation ruleset is active.',
                ],

                // Phase 5.6.j — Multi-check-in safety rails for the public
                // volunteer check-in page. Both apply on the public path
                // (PublicVolunteerCheckInController → VolunteerCheckInService::checkIn);
                // admin manual check-ins via EventVolunteerCheckInController bypass
                // both knobs (admin can always override).
                'volunteer_stale_open_hours_cap' => [
                    'label'       => 'Volunteer Stale-Open Auto-Close (hours)',
                    'type'        => 'integer',
                    'default'     => 12,
                    'description' => 'When a volunteer re-checks-in and their previous open row is older than this many hours, the stale row is auto-closed at checked_in_at + cap and a fresh row is started. Prevents day-old "forgot to check out" rows from inflating hours_served.',
                ],
                'volunteer_min_session_gap_minutes' => [
                    'label'       => 'Volunteer Min Session Gap (minutes)',
                    'type'        => 'integer',
                    'default'     => 5,
                    'description' => 'A volunteer who just checked out can\'t immediately check back in for the same event. Prevents accidental rapid double-tap and trivial gaming. Set to 0 to disable.',
                ],
            ],

            // ── Public Access ─────────────────────────────────────────────────
            'public_access' => [
                // `auth_code_length` was previously a configurable integer here.
                // Removed because the schema fixes auth-code columns at char(4)
                // and there was no upper bound on the setting — bumping it past
                // 4 silently broke event creation with "Data too long for column"
                // errors. Length is now hard-coded as Event::AUTH_CODE_LENGTH.
                'allow_code_regeneration' => [
                    'label'       => 'Allow Auth Code Regeneration',
                    'type'        => 'boolean',
                    'default'     => true,
                    'description' => 'Admins can regenerate event auth codes on demand.',
                ],
                'require_event_date_validation' => [
                    'label'       => 'Require Event Date Validation',
                    'type'        => 'boolean',
                    'default'     => false,
                    'description' => 'Block auth code entry if today is not the event date.',
                ],
                'invalidate_on_completion' => [
                    'label'       => 'Invalidate Codes When Event Completes',
                    'type'        => 'boolean',
                    'default'     => true,
                    'description' => 'Public role access is blocked after event is marked complete.',
                ],
                'session_timeout_minutes' => [
                    'label'       => 'Public Session Timeout (minutes)',
                    'type'        => 'integer',
                    'default'     => 480,
                    'description' => 'How long public role sessions remain valid before expiry.',
                ],
                'auto_generate_codes' => [
                    'label'       => 'Auto-generate Codes on Event Creation',
                    'type'        => 'boolean',
                    'default'     => true,
                    'description' => 'Automatically creates auth codes when a new event is saved.',
                ],
                'one_code_per_role' => [
                    'label'       => 'One Code Per Role Per Event',
                    'type'        => 'boolean',
                    'default'     => true,
                    'description' => 'Each role (intake, scanner, loader, exit) gets one shared code.',
                ],
            ],

            // ── Households & Intake ───────────────────────────────────────────
            'households' => [
                'household_number_length' => [
                    'label'       => 'Household Number Length',
                    'type'        => 'integer',
                    'default'     => 6,
                    'description' => 'Character length for auto-generated household numbers.',
                ],
                'auto_generate_household_number' => [
                    'label'       => 'Auto-generate Household Number',
                    'type'        => 'boolean',
                    'default'     => true,
                    'description' => 'System assigns a unique number when a household is created.',
                ],
                'require_phone' => [
                    'label'       => 'Require Phone Number',
                    'type'        => 'boolean',
                    'default'     => false,
                    'description' => 'Make phone a required field on household create/edit.',
                ],
                'require_address' => [
                    'label'       => 'Require Address',
                    'type'        => 'boolean',
                    'default'     => false,
                    'description' => 'Make city/state/zip required fields.',
                ],
                'require_vehicle_info' => [
                    'label'       => 'Require Vehicle Info',
                    'type'        => 'boolean',
                    'default'     => false,
                    'description' => 'Make vehicle make/color required for drive-through events.',
                ],
                'enable_represented_families' => [
                    'label'       => 'Enable Represented Family Workflow',
                    'type'        => 'boolean',
                    'default'     => true,
                    'description' => 'Allow households to represent multiple family units.',
                ],
                'max_represented_families' => [
                    'label'       => 'Max Represented Families Allowed',
                    'type'        => 'integer',
                    'default'     => 5,
                    'description' => 'Maximum number of family units per household record.',
                ],
                'warn_duplicate_email' => [
                    'label'       => 'Warn on Duplicate Email',
                    'type'        => 'boolean',
                    'default'     => true,
                    'description' => 'Alert staff if an email already exists in the system.',
                ],
                'warn_duplicate_phone' => [
                    'label'       => 'Warn on Duplicate Phone',
                    'type'        => 'boolean',
                    'default'     => true,
                    'description' => 'Alert staff if a phone number already exists.',
                ],
            ],

            // ── Reviews & Feedback ────────────────────────────────────────────
            'reviews' => [
                'enable_reviews' => [
                    'label'       => 'Enable Public Reviews',
                    'type'        => 'boolean',
                    'default'     => true,
                    'description' => 'Allow the public review submission form to accept entries.',
                ],
                'allow_anonymous' => [
                    'label'       => 'Allow Anonymous Reviews',
                    'type'        => 'boolean',
                    'default'     => true,
                    'description' => 'Name field is optional on the public review form.',
                ],
                'email_optional' => [
                    'label'       => 'Email Optional on Reviews',
                    'type'        => 'boolean',
                    'default'     => true,
                    'description' => 'Email address is not required to submit a review.',
                ],
                'require_moderation' => [
                    'label'       => 'Require Admin Moderation',
                    'type'        => 'boolean',
                    'default'     => false,
                    'description' => 'New reviews are hidden until an admin approves them.',
                ],
                'default_visibility' => [
                    'label'       => 'Default Review Visibility',
                    'type'        => 'select',
                    'default'     => 'visible',
                    'options'     => [
                        'visible' => 'Visible immediately',
                        'hidden'  => 'Hidden until reviewed',
                    ],
                ],
                'restrict_to_recent_events' => [
                    'label'       => 'Only Allow Reviews for Past/Active Events',
                    'type'        => 'boolean',
                    'default'     => true,
                    'description' => 'Prevents reviews from being submitted for future events.',
                ],
                'thankyou_message' => [
                    'label'       => 'Thank-You Message',
                    'type'        => 'text',
                    'default'     => 'Thank you for your feedback! We appreciate your time.',
                    'description' => 'Shown after a review is successfully submitted.',
                ],
                'min_review_length' => [
                    'label'       => 'Minimum Review Length (characters)',
                    'type'        => 'integer',
                    'default'     => 10,
                    'description' => '0 = no minimum.',
                ],
                'max_review_length' => [
                    'label'       => 'Maximum Review Length (characters)',
                    'type'        => 'integer',
                    'default'     => 2000,
                    'description' => '',
                ],
                'show_average_rating' => [
                    'label'       => 'Show Average Rating on Event Pages',
                    'type'        => 'boolean',
                    'default'     => true,
                    'description' => 'Display a star rating summary on admin event detail pages.',
                ],
            ],

            // ── Inventory ─────────────────────────────────────────────────────
            'inventory' => [
                'low_stock_threshold' => [
                    'label'       => 'Low Stock Threshold (default)',
                    'type'        => 'integer',
                    'default'     => 10,
                    'description' => 'Items at or below this quantity are flagged as low stock.',
                ],
                'allow_negative_stock' => [
                    'label'       => 'Allow Negative Stock',
                    'type'        => 'boolean',
                    'default'     => false,
                    'description' => 'Allow stock quantity to go below zero on adjustments.',
                ],
                'require_movement_notes' => [
                    'label'       => 'Require Notes on Manual Adjustments',
                    'type'        => 'boolean',
                    'default'     => false,
                    'description' => 'Staff must enter a reason when manually adjusting stock.',
                ],
                'show_inactive_items' => [
                    'label'       => 'Show Inactive Items by Default',
                    'type'        => 'boolean',
                    'default'     => false,
                    'description' => 'Include inactive items in the default inventory list view.',
                ],
                'enable_event_allocations' => [
                    'label'       => 'Enable Event Inventory Allocations',
                    'type'        => 'boolean',
                    'default'     => true,
                    'description' => 'Allow inventory to be allocated and tracked per event.',
                ],
                'dashboard_low_stock_alert' => [
                    'label'       => 'Show Low Stock Alert on Dashboard',
                    'type'        => 'boolean',
                    'default'     => true,
                    'description' => 'Highlight low/out-of-stock items on the main dashboard.',
                ],
                'out_of_stock_behavior' => [
                    'label'       => 'Out-of-Stock Behavior',
                    'type'        => 'select',
                    'default'     => 'warn',
                    'description' => 'What happens when an item reaches zero stock.',
                    'options'     => [
                        'warn'  => 'Warn only — allow allocation',
                        'block' => 'Block allocation',
                        'allow' => 'Allow silently',
                    ],
                ],
            ],

            // ── Finance ───────────────────────────────────────────────────────
            'finance' => [
                'currency_symbol' => [
                    'label'       => 'Currency Symbol',
                    'type'        => 'string',
                    'default'     => '$',
                    'description' => 'Symbol displayed alongside monetary values.',
                ],
                'decimal_precision' => [
                    'label'       => 'Decimal Precision',
                    'type'        => 'select',
                    'default'     => '2',
                    'options'     => ['0' => '0', '2' => '2', '3' => '3'],
                ],
                'allow_attachments' => [
                    'label'       => 'Allow Attachment Uploads on Transactions',
                    'type'        => 'boolean',
                    'default'     => true,
                    'description' => 'Enable receipt/document uploads on finance transactions.',
                ],
                'allowed_attachment_types' => [
                    'label'       => 'Allowed Attachment File Types',
                    'type'        => 'string',
                    'default'     => 'pdf,jpg,jpeg,png',
                    'description' => 'Comma-separated list of allowed extensions.',
                ],
                'require_category' => [
                    'label'       => 'Require Category on Every Transaction',
                    'type'        => 'boolean',
                    'default'     => false,
                    'description' => '',
                ],
                'enable_event_metrics' => [
                    'label'       => 'Enable Event Finance Metrics',
                    'type'        => 'boolean',
                    'default'     => true,
                    'description' => 'Show cost-per-household/person calculations in event reports.',
                ],
                'default_date_range' => [
                    'label'       => 'Default Finance Date Range',
                    'type'        => 'select',
                    'default'     => 'current_month',
                    'options'     => [
                        'current_month'  => 'Current month',
                        'last_30_days'   => 'Last 30 days',
                        'current_year'   => 'Current year',
                        'all_time'       => 'All time',
                    ],
                ],
                'allow_draft_expenses' => [
                    'label'       => 'Allow Draft Expenses',
                    'type'        => 'boolean',
                    'default'     => false,
                    'description' => 'Transactions can be saved as drafts before finalizing.',
                ],
            ],

            // ── Notifications & Contact ────────────────────────────────────────
            'notifications' => [
                'sender_email' => [
                    'label'       => 'Default Sender Email',
                    'type'        => 'string',
                    'default'     => '',
                    'description' => 'The From address for all outgoing system emails.',
                ],
                'sender_name' => [
                    'label'       => 'Default Sender Name',
                    'type'        => 'string',
                    'default'     => 'FoodBank System',
                    'description' => 'The From name for outgoing emails.',
                ],
                'reply_to_email' => [
                    'label'       => 'Reply-To Email',
                    'type'        => 'string',
                    'default'     => '',
                    'description' => 'Where replies to system emails are directed.',
                ],
                'support_email' => [
                    'label'       => 'Organization Support Email',
                    'type'        => 'string',
                    'default'     => '',
                    'description' => 'Shown to users on error pages and public contact info.',
                ],
                'public_contact_email' => [
                    'label'       => 'Public Contact Email',
                    'type'        => 'string',
                    'default'     => '',
                    'description' => 'Displayed on public-facing event and registration pages.',
                ],
                'notify_admin_on_review' => [
                    'label'       => 'Notify Admin on New Review Submission',
                    'type'        => 'boolean',
                    'default'     => false,
                    'description' => 'Send an email to admin when a public review is submitted.',
                ],
                'notify_low_stock' => [
                    'label'       => 'Notify Admin on Low Stock',
                    'type'        => 'boolean',
                    'default'     => false,
                    'description' => 'Send an email alert when an item drops below threshold.',
                ],
                'enable_event_day_alerts' => [
                    'label'       => 'Enable Event-Day Alert Notifications',
                    'type'        => 'boolean',
                    'default'     => false,
                    'description' => 'System can send alerts during active event operations.',
                ],
            ],

            // ── Users & Security ──────────────────────────────────────────────
            'security' => [
                'session_timeout_minutes' => [
                    'label'       => 'Admin Session Timeout (minutes)',
                    'type'        => 'integer',
                    'default'     => 120,
                    'description' => 'Authenticated admin sessions expire after this duration.',
                ],
                'password_min_length' => [
                    'label'       => 'Password Minimum Length',
                    'type'        => 'integer',
                    'default'     => 8,
                    'description' => '',
                ],
                'require_strong_password' => [
                    'label'       => 'Require Strong Password',
                    'type'        => 'boolean',
                    'default'     => false,
                    'description' => 'Enforce uppercase, number, and special character requirements.',
                ],
                'allow_self_delete' => [
                    'label'       => 'Allow Users to Delete Their Own Account',
                    'type'        => 'boolean',
                    'default'     => false,
                    'description' => '',
                ],
                'allow_user_deactivation' => [
                    'label'       => 'Allow User Deactivation (instead of delete)',
                    'type'        => 'boolean',
                    'default'     => true,
                    'description' => 'Deactivated users cannot log in but their data is retained.',
                ],
                'default_new_user_role' => [
                    'label'       => 'Default Role for New Users',
                    'type'        => 'select',
                    'default'     => '',
                    'description' => 'Automatically assigned role when creating a new user.',
                    'options'     => [], // Populated dynamically in controller
                ],
                'audit_logging_enabled' => [
                    'label'       => 'Enable Audit Logging',
                    'type'        => 'boolean',
                    'default'     => true,
                    'description' => 'Track who changed what in the system.',
                ],
                'protect_system_roles' => [
                    'label'       => 'Protect System Role Editing',
                    'type'        => 'boolean',
                    'default'     => true,
                    'description' => 'Prevent modifications to core system roles (ADMIN, INTAKE, etc.).',
                ],
                'role_deletion_protection' => [
                    'label'       => 'Role Deletion Protection',
                    'type'        => 'boolean',
                    'default'     => true,
                    'description' => 'Prevent deletion of roles that have users assigned.',
                ],
            ],

            // ── System Preferences ────────────────────────────────────────────
            'system' => [
                'maintenance_mode' => [
                    'label'       => 'Maintenance Mode',
                    'type'        => 'boolean',
                    'default'     => false,
                    'description' => 'When enabled, non-admin users see a maintenance message.',
                ],
                'default_pagination_limit' => [
                    'label'       => 'Global Default Pagination Limit',
                    'type'        => 'select',
                    'default'     => '25',
                    'options'     => ['10' => '10', '25' => '25', '50' => '50', '100' => '100'],
                ],
                'chart_default_period' => [
                    'label'       => 'Dashboard Chart Default Period',
                    'type'        => 'select',
                    'default'     => '30',
                    'description' => 'Default number of days shown on dashboard charts.',
                    'options'     => [
                        '7'   => 'Last 7 days',
                        '30'  => 'Last 30 days',
                        '90'  => 'Last 90 days',
                        '365' => 'Last 12 months',
                    ],
                ],
                'report_export_format' => [
                    'label'       => 'Default Report Export Format',
                    'type'        => 'select',
                    'default'     => 'csv',
                    'options'     => ['csv' => 'CSV', 'xlsx' => 'Excel (XLSX)', 'pdf' => 'PDF'],
                ],
                'soft_delete_enabled' => [
                    'label'       => 'Enable Soft Deletes',
                    'type'        => 'boolean',
                    'default'     => true,
                    'description' => 'Deleted records are archived rather than permanently removed.',
                ],
                'archive_completed_events_after_days' => [
                    'label'       => 'Archive Completed Events After (days)',
                    'type'        => 'integer',
                    'default'     => 0,
                    'description' => 'Set 0 to disable auto-archiving.',
                ],
                'show_debug_to_admin' => [
                    'label'       => 'Show Debug Information to Admins',
                    'type'        => 'boolean',
                    'default'     => false,
                    'description' => 'Display technical debug info to admin users only.',
                ],
            ],

        ]; // end definitions
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Convenience helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Format a monetary amount using the configured currency symbol and decimal precision.
     *
     * Fallback defaults:
     *   symbol    → '$'
     *   precision → 2
     */
    public static function formatCurrency(float $amount, ?int $decimals = null): string
    {
        $symbol    = static::get('finance.currency_symbol', '$');
        $precision = $decimals ?? (int) static::get('finance.decimal_precision', 2);

        return $symbol . number_format($amount, $precision);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internal
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Load all settings from DB into the in-request cache (key → casted value).
     */
    private static function loadAll(): array
    {
        if (static::$cache === null) {
            static::$cache = AppSetting::all()
                ->mapWithKeys(fn ($s) => [$s->key => $s->casted_value])
                ->toArray();
        }
        return static::$cache;
    }
}
