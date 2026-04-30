# Settings Module

---

## Architecture

Settings are stored in the `app_settings` database table (not config files), enabling admin-panel changes without code deploys.

```
app_settings
  group   string(64)    e.g. 'general'
  key     string(128)   e.g. 'general.app_name'
  value   text          stored as string
  type    string(32)    string | boolean | integer | float | json | text
```

The `SettingService` is the sole interface for reading and writing settings. Never query `app_settings` directly.

---

## SettingService Usage

Inject `SettingService` or resolve from the container:

```php
// In a controller or view
app(SettingService::class)->get('general.app_name', 'FoodBank')

// Get all settings for a group
app(SettingService::class)->group('branding')
// Returns: ['primary_color' => '#f97316', 'logo_path' => null, ...]

// Update a group
app(SettingService::class)->updateGroup('general', $request->validated())

// Set a single value
app(SettingService::class)->set('general.app_name', 'My Food Bank')
```

---

## Settings Groups (12 groups, ~98 keys)

### `general`
| Key | Type | Default | Description |
|-----|------|---------|-------------|
| app_name | string | FoodBank | Application display name |
| timezone | string | America/Chicago | App timezone |
| date_format | string | M j, Y | PHP date format for display |
| time_format | string | g:i A | PHP time format for display |
| currency | string | USD | Currency code |
| records_per_page | integer | 25 | Default pagination |
| dashboard_default_event | integer | null | Default event shown on dashboard |

### `organization`
| Key | Type | Default |
|-----|------|---------|
| name | string | — |
| email | string | — |
| phone | string | — |
| website | string | — |
| address | text | — |
| about | text | — |

### `branding`
| Key | Type | Default |
|-----|------|---------|
| primary_color | string | #f97316 |
| secondary_color | string | #1e3a5f |
| accent_color | string | #10b981 |
| sidebar_bg | string | #1e3a5f |
| nav_text_color | string | #ffffff |
| logo_display | string | text | text or image |
| appearance | string | light | light or dark |
| logo_path | string | null | Storage path |
| favicon_path | string | null | Storage path |

### `event_queue`
| Key | Type | Default |
|-----|------|---------|
| default_lane_count | integer | 1 |
| allow_lane_drag | boolean | true |
| allow_queue_reorder | boolean | true |
| queue_poll_interval | integer | 5 | seconds |
| show_household_names_scanner | boolean | true |
| show_vehicle_info_queue | boolean | true |
| show_family_breakdown | boolean | true |
| bag_calculation_strategy | string | ruleset | ruleset or flat |
| default_bags_per_person | integer | 1 | used for flat strategy |

### `public_access`

> Note: `auth_code_length` was previously here as a configurable integer.
> It was removed because the schema fixes auth-code columns at char(4)
> and there was no upper bound on the setting — bumping it past 4
> silently broke event creation. Length is now hard-coded as
> `Event::AUTH_CODE_LENGTH = 4`.

| Key | Type | Default |
|-----|------|---------|
| allow_code_regeneration | boolean | true |
| require_event_date_validation | boolean | false |
| invalidate_on_completion | boolean | false |
| session_timeout_minutes | integer | 480 |
| auto_generate_codes | boolean | true |
| one_code_per_role | boolean | true |

### `households`
| Key | Type | Default |
|-----|------|---------|
| household_number_length | integer | 5 |
| auto_generate_household_number | boolean | true |
| require_phone | boolean | false |
| require_address | boolean | true |
| require_vehicle_info | boolean | false |
| enable_represented_families | boolean | true |
| max_represented_families | integer | 10 |
| warn_duplicate_email | boolean | true |
| warn_duplicate_phone | boolean | true |

### `reviews`
| Key | Type | Default |
|-----|------|---------|
| enable_reviews | boolean | true |
| allow_anonymous | boolean | true |
| email_optional | boolean | true |
| require_moderation | boolean | false |
| default_visibility | boolean | true |
| restrict_to_recent_events | boolean | false |
| thankyou_message | text | Thank you for your feedback! |
| min_review_length | integer | 10 |
| max_review_length | integer | 1000 |
| show_average_rating | boolean | true |

### `inventory`
| Key | Type | Default |
|-----|------|---------|
| low_stock_threshold | integer | 10 |
| allow_negative_stock | boolean | false |
| require_movement_notes | boolean | false |
| show_inactive_items | boolean | false |
| enable_event_allocations | boolean | true |
| dashboard_low_stock_alert | boolean | true |
| out_of_stock_behavior | string | warn | warn or block |

### `finance`
| Key | Type | Default |
|-----|------|---------|
| currency_symbol | string | $ |
| decimal_precision | integer | 2 |
| allow_attachments | boolean | true |
| allowed_attachment_types | string | pdf,jpg,jpeg,png |
| require_category | boolean | true |
| enable_event_metrics | boolean | true |
| default_date_range | string | this_month |
| allow_draft_expenses | boolean | true |

### `notifications`
| Key | Type | Default |
|-----|------|---------|
| sender_email | string | — |
| sender_name | string | — |
| reply_to_email | string | — |
| support_email | string | — |
| public_contact_email | string | — |
| notify_admin_on_review | boolean | false |
| notify_low_stock | boolean | false |
| enable_event_day_alerts | boolean | false |

### `security`
| Key | Type | Default |
|-----|------|---------|
| session_timeout_minutes | integer | 120 |
| password_min_length | integer | 8 |
| require_strong_password | boolean | false |
| allow_self_delete | boolean | false |
| allow_user_deactivation | boolean | true |
| default_new_user_role | string | null |
| audit_logging_enabled | boolean | false |
| protect_system_roles | boolean | true |
| role_deletion_protection | boolean | true |

### `system`
| Key | Type | Default |
|-----|------|---------|
| maintenance_mode | boolean | false |
| default_pagination_limit | integer | 25 |
| chart_default_period | string | 6months |
| report_export_format | string | csv |
| soft_delete_enabled | boolean | false |
| archive_completed_events_after_days | integer | 90 |
| show_debug_to_admin | boolean | false |

---

## Settings UI

Route: `/settings/{group}`

Each group has a dedicated form rendered by `settings/show.blade.php`. The view reads the group definitions and renders the appropriate input type for each setting (text, toggle, color picker, number, select, file upload).

Branding asset uploads (logo/favicon) go through dedicated endpoints: `POST /settings/branding/logo` and `POST /settings/branding/favicon`. Files are stored on the `public` disk.
