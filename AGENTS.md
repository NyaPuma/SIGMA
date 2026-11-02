# AGENTS.md — SGM (Integrated Maintenance Management System)

## Project Overview

SGM is an enterprise-grade Laravel 12 web platform for digitalizing the complete
maintenance/fault management lifecycle within an organization's Maintenance
Department. Three roles: Operator (standard user), Technician, Administrator
(Operations Director).

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Runtime | PHP 8.2+ (8.3 in dev) |
| Framework | Laravel 12 |
| Database | SQLite (dev/tests), PostgreSQL/MySQL (prod) |
| Auth | Laravel Sanctum + session, RBAC via UserProfile |
| Frontend | Blade + Tailwind CSS + Alpine.js + Vite |
| Queue | sync (dev), database/redis (prod) |
| Testing | PHPUnit 11, 8 suites (~1552 tests) |
| Static | PHPStan level 5 (Larastan), Laravel Pint |
| AI | OpenAI PHP (ticket categorization/recommendations) |
| Exports | maatwebsite/excel, barryvdh/laravel-dompdf |
| Realtime | Pusher (optional) |

## Project Structure

```
app/
├── Actions/          # Single-purpose action classes (readonly)
├── Console/          # Artisan commands
├── Domain/           # Domain queries/actions (Ticket)
├── DTOs/             # Data transfer objects
├── Enums/            # Backed enums
├── Events/ Listeners/ # Domain events
├── Exports/          # Excel/PDF exports
├── Http/
│   ├── Controllers/  # HTTP controllers
│   ├── Middleware/   # Custom middleware
│   ├── Requests/     # Form requests (validation)
│   └── Resources/    # API resources
├── Jobs/             # Queued jobs
├── Mail/ Notifications/
├── Models/           # Eloquent models
├── Observers/        # Model observers
├── Policies/         # Authorization policies
├── Providers/
├── Repositories/     # Repository layer
├── Services/         # Business services
├── Traits/
└── ValueObjects/
routes/
├── web.php           # Web routes
├── api.php           # API routes
resources/views/       # Blade templates (124)
tests/                # PHPUnit suites
docs/                 # Documentation
```

## Key Commands

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --force
npm install && npm run build

# Dev
composer dev   # serves + queue + pail + vite

# Tests
php artisan test            # or vendor/bin/phpunit
php artisan test --filter=X

# Static analysis
vendor/bin/phpstan analyse --level=5
vendor/bin/pint
vendor/bin/pint --test
```

## Environment

- `DB_CONNECTION=sqlite`, `DB_DATABASE=database/database.sqlite` (dev)
- `APP_KEY` required (AES-256-CBC)
- `APP_ENV=testing` in phpunit.xml

## Conventions

- PHP 8.2+ strict types, readonly actions, constructor promotion.
- Named exports not applicable (PHP). Classes for controllers/services.
- Zod not used (PHP); Form Requests + DTOs for validation.
- Eloquent models with `$fillable`, `$casts()`, scopes.
- Policies for authorization; `role:admin` middleware for admin routes.
- Responses: JSON for API, Blade views for web.
- i18n: `__('messages....')`, `__('common....')` (pt-PT).
- Tests: PHPUnit 11, 8 suites, SQLite in-memory.

## Testing

- Framework: PHPUnit 11
- Location: `tests/{Unit,Feature,Performance,Security,Integration,Authentication,Authorization,Database}`
- Run: `php artisan test` or `vendor/bin/phpunit`
- Coverage: not enforced globally.

## Notes

- AGENTS.md formerly described an unrelated Node/Express project; corrected 2026-10-30.
- See `docs/audit-report-2026.md` and `docs/audit-checklist.md`.
