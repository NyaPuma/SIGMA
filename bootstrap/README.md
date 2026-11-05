# `bootstrap/`

> **New to programming?** See [NON_TECHNICAL_PROJECT_GUIDE.md](../NON_TECHNICAL_PROJECT_GUIDE.md) -- this folder is explained as "The Power Button" — the first code that runs when the system starts up.

## What Is This Folder? (plain English)

This folder is the **"Power Button / ignition"** of the whole application. It contains the very first code that runs when the system boots, and it is responsible for **wiring everything up** before any request is handled.

Just like turning the key in a car has to start the engine, attach the gears, and get all systems ready before you can drive, the `bootstrap/` folder tells the app:

- **which routes** exist (web pages, API endpoints, background commands),
- **which middleware** every request must pass through (like security guards at the door),
- **which service providers** to load (the plug-in modules the app needs),
- **what to do when something goes wrong** (how to handle exceptions/errors),
- and any **scheduled background jobs** (tasks that run automatically on a timer).

## The Files in This Folder

### `app.php` — The ignition switch (application bootstrap)

This file is the heart of the boot process. When the app starts it runs `Application::configure(...)`, then a chain of small "setup" steps:

**1. Routing — declares what the app can respond to:**
- `routes/web.php` — the normal web pages.
- `routes/api.php` — the machine-to-machine JSON/API endpoints.
- `routes/console.php` — the command-line (artisan) commands.
- `health: '/up'` — a simple health-check endpoint the app reports as "I'm alive" on.

**2. Middleware — the security & helpers that wrap every request:**
- `RequestContextMiddleware` is always on, and gives every request a common "context".
- For **web** requests it adds: `SetLocaleMiddleware` (picks the right language for the visitor), `SetUserPreferencesMiddleware` (applies each user's saved preferences), and `SecurityHeaders` (adds protective headers).
- For **API** requests it adds the locale and security-headers middleware too.
- It also registers three **named aliases** used in the route files to keep them tidy:
  - `custom.auth` → login/authentication check (opaque API token + session).
  - `role` → role-based access (admin/technician/user).
  - `rate.limit` → throttling (stops bots flooding the app).
- It also **excludes** `api_token` and `auth_token` cookies from encryption so they work as expected.

**3. Scheduling — automatic background jobs:**
- A **`CheckLowStockJob`** runs **every day at 06:00** (and never overlaps itself) — it checks the spare-parts inventory and flags anything that has dropped below its minimum stock level.
- The rest of the automatic tasks are declared in `routes/console.php`:
  - **`telemetry:simulate`** runs **every hour** — it looks for equipment anomalies and opens preventive maintenance tickets.
  - **`backup:run --clean`** runs **every day at 02:00** — it makes a database backup and removes ones older than the retention period.
  - **`audit:partition --months=12`** runs **once a month** — it creates the monthly partitions for the audit trail table.
  - **`exports:prune`** runs **every day at 03:00** — it deletes generated report files past their retention window.
  - **`currency:update-rates`** runs **twice a day** — it refreshes the stored exchange rates used for per-user currency preferences.

**4. Exception handling — what happens when things go wrong:**
- Invalid-argument errors are turned into a clean 422 JSON response.
- For error pages, the app re-applies the correct language (locale) — because on an error page the normal "web" middleware no longer runs, this step makes sure the error is still shown in the visitor's language.

### `providers.php` — The plug-in modules (service providers)

This file is just a short list of the **service providers** to load at startup:
- `AppServiceProvider` — the app's core setup/configuration.
- `EventServiceProvider` — registers the app's **events** (things that "happen", like "a ticket was created", and the code that reacts to them).

Service providers are the "plugins" of a Laravel app — they bundle up related features and register them so the rest of the application can use them.

### `cache/` — A small working-folder

The `cache/` folder inside `bootstrap/` is used to store generated/compiled files and cached config. It is a working area for the framework and is normally not something you edit by hand.

## What Happens at Startup (the whole picture, in order)

When the app boots, `app.php`:
1. Configures the application with its base path.
2. Tells the app which route files to load (web, API, console, health check).
3. Registers all the middleware — both the always-on helpers and the named guards — along with which cookies to leave unencrypted.
4. Schedules the daily low-stock background job.
5. Sets up how exceptions/errors are rendered.
6. Then returns a fully "wired" application, ready to accept requests.

## Notes for developers / AI

- `app.php` is the Laravel 11+ application bootstrap file — it configures routing, middleware, scheduling, and exception handling in a fluent API.
- Middleware aliases: `custom.auth` (opaque API token + session), `role` (role-based access), `rate.limit` (throttling). The API token is a random opaque string hashed with HMAC-SHA256 before being stored in `users.api_token` — it is not a JWT.
- The `CheckLowStockJob` runs daily at 06:00 to check for low stock levels.
- Error pages resolve locale from the request to ensure proper language display.

## Related Folders

| Path | Relationship |
|---|---|
| `routes/` | The route files referenced by bootstrap. |
| `app/Http/Middleware/` | The middleware classes registered here. |
| `app/Providers/` | The service providers registered in `providers.php`. |
| `app/Jobs/CheckLowStockJob.php` | The scheduled job for low-stock alerts. |
