# Food Bank Management System — Project Overview

## What Is This?

A full-featured web application for managing food bank operations. Built with Laravel 11, it covers every aspect of a food distribution organisation: household registration, event-day check-in, volunteer scheduling, inventory tracking, finance management, and public-facing registration and review pages.

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend framework | Laravel 11 |
| PHP version | ^8.2 |
| Database | MySQL (configurable; SQLite for dev) |
| Frontend templating | Blade (Laravel built-in) |
| CSS framework | Tailwind CSS v3 |
| Build tool | Vite |
| Auth | Laravel built-in session auth |
| Queue | Database driver |
| Storage | Local / public disk |

---

## Core Functional Modules

| Module | Purpose |
|--------|---------|
| **Households** | Register and manage beneficiary households including represented families |
| **Events** | Plan and run food distribution events with multi-lane queues |
| **Check-In** | Real-time household check-in with QR scanning |
| **Event-Day Roles** | Public-facing pages for intake, scanner, loader, and exit staff |
| **Volunteers** | Manage volunteer profiles and group assignments |
| **Inventory** | Track stock levels, movements, and event allocations |
| **Finance** | Income/expense ledger with category and event linking |
| **Reports & Analytics** | Cross-module analytics, charts, and CSV exports |
| **Settings** | 12-group configurable settings with branding uploads |
| **RBAC** | Role-based access control with dot-notation permissions |
| **Public Pages** | Event pre-registration and review submission (no login required) |

---

## Application Architecture

```
app/
  Console/            Artisan commands (events:sync-statuses)
  Http/
    Controllers/      28 controllers (one per domain, thin)
    Middleware/       CheckPermission, MaintenanceMode
    Requests/         30+ FormRequest classes (all validation here)
  Models/             20 Eloquent models
  Providers/          AppServiceProvider (middleware alias, gate bindings)
  Services/           10 service classes (business logic lives here)

database/
  migrations/         37 migration files
  seeders/            9 seeders (roles, users, demo data, settings)
  factories/          Eloquent model factories

resources/
  views/              Blade templates organised by module
  css/app.css         Tailwind entry point
  js/app.js           Vite entry point

routes/
  web.php             All routes (guest, auth, event-day, public)
  console.php         Scheduled commands
```

---

## Development Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run dev          # Vite dev server
php artisan serve    # PHP dev server (or use XAMPP)
```

Default admin credentials are seeded by `AdminUserSeeder`.

---

## Key Design Decisions

1. **Thin controllers, fat services** — all business logic lives in `app/Services/`. Controllers only resolve input, call a service, and return a response.
2. **FormRequest validation** — every form input is validated in a dedicated `FormRequest` class, never inside controllers.
3. **Immutable inventory movements** — `inventory_movements` records are never updated; stock is computed from the ledger of movements.
4. **Auth-code event-day pages** — public-facing role pages authenticate via short numeric codes rather than full login, enabling staff tablets without accounts.
5. **Settings in database** — all app configuration is stored in `app_settings` (key-value with type casting), allowing admin UI changes without code deploys.
6. **Representative households** — a household can represent multiple family units, sharing a single visit/queue entry.
