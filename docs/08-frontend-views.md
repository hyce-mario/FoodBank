# Frontend & Views

---

## Stack

- **Blade** — server-side templating (Laravel built-in)
- **Tailwind CSS v3** — utility-first CSS, compiled via Vite
- **Vanilla JS** — no Vue/React/Alpine; all interactivity is plain JS with `fetch()`
- **Vite** — asset bundling

---

## Layout

### `resources/views/layouts/app.blade.php`

Main admin layout shared by all authenticated pages.

**Sections:**
- `@yield('title')` — page title
- `@yield('content')` — main page body
- `@stack('scripts')` — deferred JS pushed from child views

**Structure:**
```
<html>
  <head>  <!-- meta, Vite CSS/JS, dynamic branding from settings -->
  <body class="flex h-screen overflow-hidden">
    <!-- Sidebar nav (responsive, collapsible) -->
    <!-- Main content area with top bar + @yield('content') -->
  </body>
</html>
```

**Sidebar nav items:** Dashboard, Households, Events, Check-In, Monitor, Volunteers, Inventory, Finance, Reports, Users, Roles, Settings

**Dynamic theming:** Sidebar background color, nav text color, and primary brand color are injected as CSS custom properties from `SettingService::group('branding')`.

---

## Reusable Blade Components

Located in `resources/views/components/`.

### `stat-card`
Renders a KPI tile on the dashboard and finance pages.

**Props:** `title`, `value`, `icon`, `color`, `change` (optional trend indicator)

### `flash-message`
Renders `session('success')` / `session('error')` alert banners.

### `form-field`
Wraps a label + input + error message into a consistent layout block.

### `badge`
Renders a colored pill badge. Used for statuses, types, and roles.

### `pagination`
Custom Tailwind-styled pagination links wrapping Laravel's `$paginator->links()`.

---

## View Directory Structure

```
resources/views/
├── auth/
│   └── login.blade.php
├── components/
│   ├── stat-card.blade.php
│   ├── flash-message.blade.php
│   ├── form-field.blade.php
│   ├── badge.blade.php
│   └── pagination.blade.php
├── dashboard/
│   └── index.blade.php
├── households/
│   ├── index.blade.php
│   ├── create.blade.php
│   ├── show.blade.php
│   ├── edit.blade.php
│   └── _form.blade.php          (shared create/edit form partial)
├── events/
│   ├── index.blade.php
│   ├── create.blade.php
│   ├── show.blade.php           (rich: tabs for allocations, media, reviews, pre-regs)
│   ├── edit.blade.php
│   └── _form.blade.php
├── checkin/
│   └── index.blade.php          (JS-heavy single-page check-in UI)
├── event-day/
│   └── index.blade.php          (role-specific queue page, minimal layout)
├── monitor/
│   └── index.blade.php          (lane grid, auto-refreshing)
├── visit-log/
│   └── index.blade.php
├── volunteers/
│   ├── index.blade.php
│   ├── create.blade.php
│   ├── show.blade.php
│   ├── edit.blade.php
│   └── _form.blade.php
├── volunteer-groups/
│   ├── index.blade.php
│   ├── create.blade.php
│   ├── show.blade.php
│   ├── edit.blade.php
│   ├── members.blade.php
│   └── _form.blade.php
├── inventory/
│   ├── categories/
│   │   └── index.blade.php      (inline AJAX modals)
│   └── items/
│       ├── index.blade.php
│       ├── create.blade.php
│       ├── show.blade.php       (movement history, allocation history)
│       └── edit.blade.php
├── allocation-rulesets/
│   ├── index.blade.php
│   ├── create.blade.php
│   ├── edit.blade.php
│   └── preview.blade.php
├── finance/
│   ├── dashboard.blade.php      (charts, KPI cards)
│   ├── reports.blade.php
│   ├── categories/
│   │   └── index.blade.php
│   └── transactions/
│       ├── index.blade.php
│       ├── create.blade.php
│       ├── show.blade.php
│       └── edit.blade.php
├── reports/
│   ├── overview.blade.php
│   ├── events.blade.php
│   ├── trends.blade.php
│   ├── demographics.blade.php
│   ├── lanes.blade.php
│   ├── queue-flow.blade.php
│   ├── volunteers.blade.php
│   ├── reviews.blade.php
│   ├── inventory.blade.php
│   └── export.blade.php
├── roles/
│   ├── index.blade.php
│   ├── create.blade.php
│   ├── show.blade.php
│   └── edit.blade.php
├── users/
│   ├── index.blade.php
│   ├── create.blade.php
│   ├── show.blade.php
│   └── edit.blade.php
├── settings/
│   ├── index.blade.php
│   └── show.blade.php           (dynamic form per group)
├── reviews/
│   └── index.blade.php          (admin moderation)
├── profile/
│   └── show.blade.php
└── public/
    ├── events/
    │   ├── index.blade.php
    │   ├── register.blade.php
    │   └── success.blade.php
    └── reviews/
        └── create.blade.php
```

---

## Check-In UI (`checkin/index.blade.php`)

The most JS-intensive page. Uses `fetch()` polling for real-time updates.

**Features:**
- Household search by name/number/phone (debounced, 300ms)
- QR scan input field (auto-submit on scan)
- Inline household create form (shown when no result found)
- Represented-family management (add/attach/detach without page reload)
- Lane selector and queue position preview
- Active queue display (polls `/checkin/queue` every N seconds per setting)
- Recent log panel (polls `/checkin/log`)

---

## Event-Day Pages (`event-day/index.blade.php`)

A simplified layout (no sidebar, minimal chrome) for tablets used by operational staff.

**Roles:**
- **intake** — check-in form (search + QR input), active queue for intake's lane
- **scanner** — shows queue, allows marking visits as `queued`
- **loader** — shows queued visits, marks as `loaded`
- **exit** — shows loaded visits, marks as `exited`

Auth flow: on first visit, a code-entry form is shown. On submit, the code is validated server-side. Success sets a session flag. Subsequent page loads check the flag.

---

## Tailwind Configuration (`tailwind.config.js`)

**Custom colors:**
```js
brand: {
    DEFAULT: '#f97316',   // orange
    dark:    '#ea580c',
},
navy: {
    DEFAULT: '#1e3a5f',
    light:   '#2d5282',
}
```

**Safelist:** Dynamic color classes for role badges (blue, purple, orange, green, etc.) and finance type badges are safelisted to prevent purging.

**Custom layout:**
```js
gridTemplateColumns: {
    'settings': '220px 1fr',   // settings sidebar + content
}
```

**Content paths:** `./resources/views/**/*.blade.php`, `./resources/js/**/*.js`

---

## CSS (`resources/css/app.css`)

Entry point for Tailwind:
```css
@tailwind base;
@tailwind components;
@tailwind utilities;
```

May include custom component classes (e.g., `.btn-primary`, `.card`, `.form-input`) using `@layer components`.

---

## JavaScript (`resources/js/app.js`)

Minimal — imports only what is needed:
- No framework
- Uses native `fetch()` for AJAX
- Uses `document.addEventListener('DOMContentLoaded', ...)` for page init
- Charts powered by Chart.js (imported per-page via `@push('scripts')`)
