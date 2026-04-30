<?php

namespace App\Providers;

use App\Models\Event;
use App\Models\EventReview;
use App\Models\Household;
use App\Models\Volunteer;
use App\Policies\EventPolicy;
use App\Policies\EventReviewPolicy;
use App\Policies\HouseholdPolicy;
use App\Policies\VolunteerPolicy;
use App\Services\SettingService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Phase 4.1: resource policies — bridge Laravel Gate to the project's
        // custom dot-notation permission system.
        Gate::policy(Household::class,   HouseholdPolicy::class);
        Gate::policy(Event::class,       EventPolicy::class);
        Gate::policy(Volunteer::class,   VolunteerPolicy::class);
        Gate::policy(EventReview::class, EventReviewPolicy::class);

        // Phase 3.1: rate limiter for event-day auth-code endpoints.
        // Keyed by IP + role + event_id so a targeted brute-force attempt against
        // one event's codes doesn't consume quota for other events on the same IP.
        RateLimiter::for('auth-code', function (Request $request) {
            $role  = $request->segment(1) ?? 'unknown';
            // Throttle middleware fires before route model binding resolves,
            // so route('event') may be a raw string ID or an already-bound model.
            $event   = $request->route('event');
            $eventId = is_object($event) ? $event->getKey() : ($event ?? '0');
            return Limit::perMinute(5)->by("{$request->ip()}:{$role}:{$eventId}");
        });

        // Guard: skip before app_settings table exists (e.g. during fresh migrate)
        if (! Schema::hasTable('app_settings')) {
            return;
        }

        // App name — used in page titles, emails, etc.
        config(['app.name' => SettingService::get('general.app_name', config('app.name'))]);

        // Timezone — applied globally for the request
        $timezone = SettingService::get('general.timezone');
        if ($timezone) {
            config(['app.timezone' => $timezone]);
            date_default_timezone_set($timezone);
        }

        // ── Shared view data ─────────────────────────────────────────────────
        // These are loaded once per request and shared with every view.
        // Controllers can override individual keys by passing them to compact().

        View::share('brandingSettings', [
            'primary_color'   => SettingService::get('branding.primary_color',   '#f97316'),
            'secondary_color' => SettingService::get('branding.secondary_color', '#1b2b4b'),
            'accent_color'    => SettingService::get('branding.accent_color',    '#ea6b0a'),
            'sidebar_bg'      => SettingService::get('branding.sidebar_bg',      '#ffffff'),
            'nav_text_color'  => SettingService::get('branding.nav_text_color',  '#374151'),
        ]);

        View::share('orgSettings', [
            'name'    => SettingService::get('organization.name',    'Our Food Bank'),
            'email'   => SettingService::get('organization.email',   ''),
            'phone'   => SettingService::get('organization.phone',   ''),
            'website' => SettingService::get('organization.website', ''),
        ]);

        // General display settings useful in many views
        View::share('generalSettings', [
            'date_format'      => SettingService::get('general.date_format',      'M j, Y'),
            'time_format'      => SettingService::get('general.time_format',      'g:i A'),
            'records_per_page' => (int) SettingService::get('general.records_per_page', 25),
            'currency'         => SettingService::get('general.currency',         'USD'),
        ]);

        // Finance display settings needed in every finance view
        View::share('financeSettings', [
            'currency_symbol'   => SettingService::get('finance.currency_symbol',   '$'),
            'decimal_precision' => (int) SettingService::get('finance.decimal_precision', 2),
            'allow_attachments' => (bool) SettingService::get('finance.allow_attachments', true),
            'require_category'  => (bool) SettingService::get('finance.require_category', false),
            'allow_draft_expenses'     => (bool) SettingService::get('finance.allow_draft_expenses', false),
            'enable_event_metrics'     => (bool) SettingService::get('finance.enable_event_metrics', true),
            'allowed_attachment_types' => SettingService::get('finance.allowed_attachment_types', 'pdf,jpg,jpeg,png'),
        ]);

        // Queue/event-day display settings needed in check-in and monitor views
        View::share('queueSettings', [
            'allow_lane_drag'              => (bool) SettingService::get('event_queue.allow_lane_drag',              true),
            'allow_queue_reorder'          => (bool) SettingService::get('event_queue.allow_queue_reorder',          true),
            'show_household_names_scanner' => (bool) SettingService::get('event_queue.show_household_names_scanner', true),
            'show_vehicle_info_queue'      => (bool) SettingService::get('event_queue.show_vehicle_info_queue',      true),
            'show_family_breakdown'        => (bool) SettingService::get('event_queue.show_family_breakdown',        true),
            'queue_poll_interval'          => max(5, (int) SettingService::get('event_queue.queue_poll_interval',   10)),
        ]);

        // Inventory display settings
        View::share('inventorySettings', [
            'enable_event_allocations' => (bool) SettingService::get('inventory.enable_event_allocations', true),
            'dashboard_low_stock_alert'=> (bool) SettingService::get('inventory.dashboard_low_stock_alert', true),
            'low_stock_threshold'      => (int) SettingService::get('inventory.low_stock_threshold', 10),
        ]);

        // Review settings needed in public review pages
        View::share('reviewSettings', [
            'enable_reviews'     => (bool) SettingService::get('reviews.enable_reviews', true),
            'allow_anonymous'    => (bool) SettingService::get('reviews.allow_anonymous', true),
            'show_average_rating'=> (bool) SettingService::get('reviews.show_average_rating', true),
        ]);

        // Security settings needed in user/role management
        View::share('securitySettings', [
            'allow_self_delete'       => (bool) SettingService::get('security.allow_self_delete', false),
            'allow_user_deactivation' => (bool) SettingService::get('security.allow_user_deactivation', true),
            'protect_system_roles'    => (bool) SettingService::get('security.protect_system_roles', true),
            'role_deletion_protection'=> (bool) SettingService::get('security.role_deletion_protection', true),
        ]);
    }
}
