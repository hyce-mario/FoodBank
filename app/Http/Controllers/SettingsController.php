<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    // ─── Index — redirect to first group ─────────────────────────────────────

    public function index(): RedirectResponse
    {
        return redirect()->route('settings.show', 'general');
    }

    // ─── Show a settings group ────────────────────────────────────────────────

    public function show(string $group): View
    {
        $groups = SettingService::groups();

        abort_if(! array_key_exists($group, $groups), 404);

        $settings    = SettingService::group($group);
        $definitions = SettingService::groupDefinitions($group);

        // Dynamically populate the default_new_user_role options
        if ($group === 'security') {
            $roles = Role::orderBy('display_name')->pluck('display_name', 'id')->toArray();
            $definitions['default_new_user_role']['options'] = ['' => '— None —'] + $roles;
        }

        return view('settings.show', [
            'group'       => $group,
            'groups'      => $groups,
            'settings'    => $settings,
            'definitions' => $definitions,
            'groupLabel'  => $groups[$group],
        ]);
    }

    // ─── Update a settings group ──────────────────────────────────────────────

    public function update(Request $request, string $group): RedirectResponse
    {
        $groups = SettingService::groups();

        abort_if(! array_key_exists($group, $groups), 404);

        $definitions = SettingService::groupDefinitions($group);

        // Build validation rules from definitions
        $rules = [];
        foreach ($definitions as $key => $def) {
            $type = $def['type'];
            if ($type === 'boolean' || $type === 'file') {
                // Booleans: checkboxes handled specially in updateGroup
                // Files: handled by dedicated upload/delete routes
                continue;
            }
            if ($type === 'integer') {
                $rules[$key] = ['nullable', 'integer'];
            } elseif ($type === 'float') {
                $rules[$key] = ['nullable', 'numeric'];
            } elseif ($type === 'color') {
                $rules[$key] = ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'];
            } elseif ($type === 'select') {
                $options = array_keys($def['options'] ?? []);
                if (! empty($options)) {
                    // Rule::in takes the option list as an array, so values
                    // that contain commas (e.g. date_format 'M j, Y') don't
                    // get mis-split by the string-form 'in:a,b,c' rule.
                    $rules[$key] = ['nullable', Rule::in($options)];
                }
            } elseif ($type === 'multi_select') {
                $options = array_keys($def['options'] ?? []);
                $rules[$key] = ['nullable', 'array'];
                if (! empty($options)) {
                    $rules["{$key}.*"] = [Rule::in($options)];
                }
            } else {
                $rules[$key] = ['nullable', 'string', 'max:10000'];
            }
        }

        // Strip empty-string entries from multi_select arrays before
        // validating. Some browsers / older form submissions can leak
        // an empty entry through (and old() carries them forward across
        // a failed-validation roundtrip), which would fail the per-element
        // `in:` rule with "The selected …0 is invalid." even when the real
        // selections are valid.
        $cleaned = $request->all();
        foreach ($definitions as $shortKey => $def) {
            if (($def['type'] ?? null) !== 'multi_select') continue;
            if (! isset($cleaned[$shortKey]) || ! is_array($cleaned[$shortKey])) continue;
            $cleaned[$shortKey] = array_values(array_filter(
                $cleaned[$shortKey],
                fn ($v) => $v !== '' && $v !== null
            ));
        }
        $request->merge($cleaned);

        $request->validate($rules);

        SettingService::updateGroup($group, $request->all());

        return redirect()
            ->route('settings.show', $group)
            ->with('success', $groups[$group] . ' settings have been saved.');
    }

    // ─── Upload branding asset (logo or favicon) ──────────────────────────────

    public function uploadBrandingAsset(Request $request, string $asset): RedirectResponse
    {
        abort_if(! in_array($asset, ['logo', 'favicon']), 404);

        $rules = match ($asset) {
            'logo'    => ['required', 'file', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048'],
            'favicon' => ['required', 'file', 'mimes:ico,png', 'max:512'],
        };

        $request->validate(['file' => $rules]);

        $settingKey = "branding.{$asset}_path";

        // Delete the previously stored file if one exists
        $oldPath = SettingService::get($settingKey, '');
        if ($oldPath && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        $ext  = $request->file('file')->getClientOriginalExtension();
        $path = $request->file('file')->storeAs('branding', "{$asset}.{$ext}", 'public');

        SettingService::set($settingKey, $path);

        return redirect()
            ->route('settings.show', 'branding')
            ->with('success', ucfirst($asset) . ' has been updated.');
    }

    // ─── Delete branding asset (logo or favicon) ──────────────────────────────

    public function deleteBrandingAsset(string $asset): RedirectResponse
    {
        abort_if(! in_array($asset, ['logo', 'favicon']), 404);

        $settingKey = "branding.{$asset}_path";
        $path       = SettingService::get($settingKey, '');

        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }

        SettingService::set($settingKey, '');

        return redirect()
            ->route('settings.show', 'branding')
            ->with('success', ucfirst($asset) . ' has been removed.');
    }
}
