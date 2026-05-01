<?php

use App\Services\SettingService;

if (! function_exists('settings')) {
    /**
     * Get a setting value by full key (e.g. 'general.app_name').
     * Falls back to the definition default, then to $default.
     *
     * Usage:
     *   settings('event_queue.queue_poll_interval', 10)
     *   settings('finance.currency_symbol', '$')
     */
    function settings(string $key, mixed $default = null): mixed
    {
        return SettingService::get($key, $default);
    }
}

if (! function_exists('fmt_currency')) {
    /**
     * Format a monetary amount using the configured currency symbol and precision.
     *
     * Usage:
     *   fmt_currency(1250.50)   → "$1,250.50"
     *   fmt_currency(1250.50, 0) → "$1,251"
     */
    function fmt_currency(float $amount, ?int $decimals = null): string
    {
        return SettingService::formatCurrency($amount, $decimals);
    }
}
