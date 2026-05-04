<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use App\Services\SettingService;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Seed all settings with their definition defaults.
     * Uses updateOrCreate so re-running is safe and never overwrites custom values.
     */
    public function run(): void
    {
        $definitions = SettingService::definitions();

        foreach ($definitions as $group => $fields) {
            foreach ($fields as $shortKey => $def) {
                $fullKey = "{$group}.{$shortKey}";

                // Determine the stored value: booleans as '1'/'0', others as string
                $default = $def['default'] ?? null;
                $type    = $def['type'] ?? 'string';

                if ($type === 'boolean') {
                    $value = $default ? '1' : '0';
                } elseif (is_array($default)) {
                    $value = json_encode($default);
                } else {
                    $value = $default !== null ? (string) $default : null;
                }

                AppSetting::updateOrCreate(
                    ['key' => $fullKey],
                    [
                        'group' => $group,
                        'value' => $value,
                        'type'  => $type,
                    ]
                );
            }
        }

        // Flush the service cache so any subsequent reads are fresh
        SettingService::flush();
    }
}
