# `routes/`

Laravel route definitions for the SGM application. Routes are split by transport layer.

> **New to programming?** See [NON_TECHNICAL_PROJECT_GUIDE.md](../NON_TECHNICAL_PROJECT_GUIDE.md) -- this folder is explained as "The Switchboard" that maps every URL to the right handler.

## What are routes, really?

Think of the `routes/` folder as the **address book / switchboard** of the whole application. Every single thing a user (or another computer) can ask the app to do is tied to a URL — and this folder is where all those URLs are written down and connected to the code that handles them.

When you type `https://sgm.example.com/tickets` into a browser, the app looks at the route files below, finds a line that says "when someone visits `/tickets`, run this piece of code", and then runs it. No route file, no page. It's literally a map.

The app separates its routes into **three files**, one for each "kind" of traffic:

## Files

| File | Purpose |
|---|---|
| `web.php` | **Browser-facing routes.** These return whole HTML pages (or handle form submissions) that a human sees in their web browser. Includes the login page, the ticket dashboard, the stock screens, and more. |
| `api.php` | **Computer-to-computer routes.** These return data in JSON format (not pretty pages) so that other apps or the JavaScript running inside a page can fetch information and display it. |
| `console.php` | **Background / scheduled tasks.** This has no URL at all. Instead, it schedules automated jobs (like nightly database backups) that run on a timer, whether or not anyone is looking at the site. |

## Difference between "web" and "api" routes (an analogy)

Imagine a **restaurant with two service windows**:

- **Web routes** are the **dining room**. A human walks in, sits down, and gets served a full plate of food (a complete, nicely formatted web page). The waiter remembers you during your visit (the "session"), and to keep things safe, there's a token (CSRF protection) that makes sure the order really came from you. These routes deal in *pages and forms*.

- **API routes** are the **take-out / delivery window**. A customer (another computer, or the page's JavaScript) sends a short request — "give me the list of open tickets, please" — over the counter, and gets back a compact, machine-readable answer (JSON) — no fancy plates, just the data. Instead of a waiter remembering you, you show a pass (an auth token) each time you order. These routes deal in *data*.

Both windows serve the *same* underlying kitchen (the controllers), but how they talk to the customer is completely different.

---

## `web.php` — the human-facing routes

`web.php` is the biggest file. It groups routes into:

1. **Open / public routes** — anyone can visit these without logging in.
2. **Public ticket reporting routes** — so visitors can report a fault using a QR code.
3. **Preferences routes** — letting a logged-in user pick language, currency, date/time and number formats.
4. **Protected routes** — require a valid login (via the `custom.auth` middleware).
5. **Role-protected areas** — technician-only and admin-only parts.

### Examples of real routes (what each does in plain English)

- `GET /` → Shows the home / landing page of the app.
- `GET /ui/login` → Shows the login screen.
- `POST /login` → Accepts the email + password you typed and logs you in (limited to 5 tries per minute).
- `GET /ticket/new` → Shows the public fault-reporting form (the one people reach by scanning a QR code on equipment).
- `POST /ticket/store` → Saves a new fault report submitted through that public form.
- `GET /ticket/success/{ticket}` → Shows the "thank you — your report was received" confirmation page.
- `POST /logout` → Logs the current user out.
- `POST /profile/update` → Saves changes to your profile details (name, contact info, etc.).
- `GET /preferences` → Shows the page where you can change your language / currency / date format.
- `GET /ui` → Shows the main in-app interface (dashboard).
- `GET /ui/tickets` → Shows the list of all maintenance tickets.
- `GET /ui/tickets/{id}` → Shows the details of one specific ticket (where `{id}` is the ticket number).
- `GET /ui/equipments` → Shows the list of equipment/machines being tracked.
- `GET /ui/rooms` → Shows the list of rooms/locations.
- `GET /ui/stock` → Shows the stock/parts dashboard.
- `POST /tickets` → Creates a new maintenance request (used by the in-app "create ticket" form; limited to 30 per minute).
- `POST /tickets/{ticket}/comments` → Adds a comment to a ticket.
- `POST /tickets/{ticket}/photos` → Uploads a photo attached to a ticket.
- `POST /tickets/{ticket}/close` → Closes a ticket after the work is finished.
- `GET /calendar` → Shows the maintenance calendar.
- `PUT /technician/tickets/{ticket}/start` → Marks a ticket as "work started" (technician only).
- `GET /admin/users` → Shows the admin's user-management page (admin only).
- `GET /admin/audits` → Shows the audit log of what's been changed in the system (admin only).
- `GET /analytics` → Shows the analytics/stats page (admin only).
- `GET /stock/reports/low-stock.csv` → Downloads a CSV file listing parts that are running low (admin only).

---

## `api.php` — the machine-facing (JSON) routes

`api.php` returns **data** rather than pages. These are used by the app's own JavaScript and by any external program that wants to read or write information. Several are the "data twin" of the web routes above — e.g. the browser page for tickets asks the `api.php` ticket route for the actual ticket data to display.

They support the same login and role protection, but the "visitor" proves who they are with a token (`X-Auth-Token`) instead of a browser session.

### Examples of real API routes

- `GET /api/user` → Returns the currently-authenticated user's own info.
- `POST /api/login` → Logs in and returns an auth token (limited to 5 tries per minute).
- `POST /api/password/email` → Sends a password-reset link by email.
- `POST /api/password/reset` → Resets a password using the emailed reset token.
- `GET /api/activities` → Returns the recent activity feed.
- `GET /api/tickets` → Returns the list of tickets as JSON.
- `GET /api/tickets/{ticket}` → Returns one ticket's data as JSON.
- `POST /api/tickets` → Creates a new ticket and returns it as JSON.
- `POST /api/tickets/{ticket}/comments` → Adds a comment (returns the new comment as JSON).
- `POST /api/tickets/{ticket}/photos` → Uploads a photo to a ticket.
- `PUT /api/technician/tickets/{ticket}/start` → Marks a ticket started (technician).
- `POST /api/technician/tickets/{ticket}/request-budget` → Requests budget approval on a ticket (technician).
- `GET /api/stock/parts` → Returns the list of parts (admins + technicians).
- `POST /api/stock/movements` → Records a stock movement (in/out).
- `GET /api/admin/users` → Returns the user list for admin management (admin).
- `GET /api/analytics/stats` → Returns analytics numbers as JSON (admin).
- `GET /api/notifications` → Returns the list of notifications for the current user.

---

## `console.php` — the background / scheduled tasks

`console.php` has **no URLs**. Instead, it tells Laravel which automated jobs to run and **when**. These are the "silent workers" that keep the app healthy behind the scenes. They rely on the system's cron scheduler (`php artisan schedule:run`).

### What runs automatically

- **Every hour** — `telemetry:simulate` simulates sensor/telemetry readings for 5 pieces of equipment and can automatically create maintenance tickets when it detects a problem.
- **Every day at 02:00** — `backup:run --clean` creates a full database backup (compressed), keeping 30 days of history and deleting anything older.
- **Every month** — `audit:partition --months=12` sets up monthly partitions for the audit table, so the audit history stays fast and organised as it grows.
- **Twice a day** — `currency:update-rates` refreshes currency exchange rates so per-user currency preferences show up-to-date conversions.

## Complete Route Reference (exhaustive)

### `web.php` — full route list

**Public routes (open access):**
| Method | URI | Name | Notes |
|---|---|---|---|
| GET | `/` | `home` | Landing page |
| GET | `/lang/{locale}` | `lang.switch` | Switch language |
| POST | `/locale` | `locale.switch` | Set locale |
| GET | `/ui/login` | `ui.login` | Login screen |
| GET | `/theme/custom.css` | `theme.custom` | Custom CSS |
| GET | `/test-email` | `test.email` | Test email form |
| POST | `/login` | `login` | Authenticate (rate 5/min, no CSRF) |

**Public ticket reporting (QR code):**
| Method | URI | Name | Notes |
|---|---|---|---|
| GET | `/ticket/new` | `ticket.public.create` | Public fault form (rate 20/min) |
| POST | `/ticket/store` | `ticket.public.store` | Save report (rate 5/min) |
| GET | `/ticket/success/{ticket}` | `ticket.public.success` | Confirmation (`{ticket}` numeric, rate 20/min) — **signed**: the page prints reference, equipment and priority, and ticket ids are sequential, so an unsigned URL would let any anonymous visitor walk the range. `url()->temporarySignedRoute()` is used when redirecting after the report is created. |

**Preferences (public):**
| Method | URI | Name | Notes |
|---|---|---|---|
| GET | `/preferences` | `preferences.edit` | Preferences page |
| POST | `/preferences/language` | `preferences.update_language` | No CSRF |
| POST | `/preferences/currency` | `preferences.update_currency` | No CSRF |
| POST | `/preferences/date-format` | `preferences.update_date_format` | No CSRF |
| POST | `/preferences/time-format` | `preferences.update_time_format` | No CSRF |
| POST | `/preferences/number-format` | `preferences.update_number_format` | No CSRF |
| POST | `/preferences` | `preferences.update_all` | No CSRF |

**Protected routes — account & profile (`custom.auth`):**
| Method | URI | Name | Notes |
|---|---|---|---|
| POST | `/logout` | `auth.logout` | No CSRF |
| POST | `/password/change` | `auth.password.change` | Rate 10/min |
| POST | `/profile/update` | `auth.profile.update` | Rate 10/min |

**Protected — notifications:**
| Method | URI | Name | Notes |
|---|---|---|---|
| GET | `/notifications` | `notifications.index` | |
| PATCH | `/notifications/{id}` | `notifications.mark-read` | No CSRF |
| POST | `/notifications/test-email` | `notifications.test-email` | No CSRF, rate 5/min |

**Protected — generated export artifacts (`custom.auth`):**
| Method | URI | Name | Notes |
|---|---|---|---|
| GET | `/exports/{userId}/{filename}` | `exports.download` | `{userId}` must be the caller; `{filename}` constrained to `[A-Za-z0-9_-]+\.(csv\|xlsx\|pdf)`. Streams from the private disk, `403` for another user's report |

**Protected — web interface (UI):**
| Method | URI | Name | Notes |
|---|---|---|---|
| GET | `/ui` | `ui.index` | Dashboard |
| GET | `/ui/profile` | `ui.profile` | |
| GET | `/ui/tickets` | `ui.tickets` | |
| GET | `/ui/tickets/create` | `ui.tickets.create` | role:admin,user |
| GET | `/ui/tickets/{id}` | `ui.tickets.show` | |
| GET | `/ui/equipments` | `ui.equipments` | |
| GET | `/equipments` | `equipments.list` | |
| GET | `/dashboard/picket` | `dashboard.picket` | |
| GET | `/ui/settings/appearance` | `ui.settings.appearance` | |
| POST | `/ui/settings/appearance` | `ui.settings.appearance.update` | No CSRF |
| POST | `/theme/switch` | `theme.switch` | No CSRF |
| GET | `/ui/equipments/create` | `ui.equipments.create` | role:admin |
| GET | `/ui/equipments/{equipment}` | `ui.equipments.show` | numeric |
| GET | `/ui/equipments/{equipment}/edit` | `ui.equipments.edit` | role:admin |
| GET | `/ui/rooms` | `ui.rooms` | |
| GET | `/ui/rooms/{room}` | `ui.rooms.show` | numeric |
| GET | `/ui/stock` | `ui.stock.dashboard` | |
| GET | `/ui/stock/parts` | `ui.stock.parts` | |
| GET | `/ui/stock/parts/create` | `ui.stock.parts.create` | role:admin |
| GET | `/ui/stock/parts/{part}` | `ui.stock.parts.show` | |
| GET | `/ui/stock/parts/{part}/edit` | `ui.stock.parts.edit` | role:admin |
| GET | `/ui/stock/suppliers` | `ui.stock.suppliers` | |
| GET | `/ui/stock/suppliers/create` | `ui.stock.suppliers.create` | role:admin |
| GET | `/ui/stock/suppliers/{supplier}/edit` | `ui.stock.suppliers.edit` | role:admin |
| GET | `/ui/stock/movements` | `ui.stock.movements` | |
| GET | `/ui/stock/tax-rates` | `ui.stock.tax-rates` | role:admin |
| GET | `/ui/stock/categories` | `ui.stock.categories` | role:admin |
| GET | `/ui/stock/plans` | `ui.stock.plans` | role:admin |

**Protected — rooms API (`custom.auth`):**
| Method | URI | Name | Notes |
|---|---|---|---|
| GET | `/api/rooms` | `rooms.index` | |
| POST | `/api/rooms` | `rooms.store` | No CSRF |
| PUT | `/api/rooms/{room}` | `rooms.update` | No CSRF |
| PATCH | `/api/rooms/{room}` | `rooms.update-patch` | No CSRF |

**Protected — tickets:**
| Method | URI | Name | Notes |
|---|---|---|---|
| GET | `/tickets/search` | `tickets.search` | |
| GET | `/tickets/most-urgent` | `tickets.most-urgent` | |
| GET | `/tickets` | `tickets.index` | |
| GET | `/tickets/{ticket}` | `tickets.show` | |
| POST | `/tickets` | `tickets.store` | No CSRF, rate 30/min |
| POST | `/tickets/{ticket}/comments` | `tickets.comments.store` | No CSRF, rate 30/min |
| GET | `/tickets/{ticket}/comments` | `tickets.comments.index` | |
| POST | `/tickets/{ticket}/photos` | `tickets.photos.store` | No CSRF, rate 30/min |
| GET | `/tickets/{ticket}/photos` | `tickets.photos.index` | |
| DELETE | `/tickets/{ticket}/photos/{attachment}` | `tickets.photos.destroy` | No CSRF |
| POST | `/tickets/{ticket}/reopen` | `tickets.reopen` | No CSRF |
| POST | `/tickets/{ticket}/cancel` | `tickets.cancel` | No CSRF |
| POST | `/tickets/{ticket}/schedule` | `tickets.schedule` | No CSRF |
| POST | `/tickets/{ticket}/budget` | `tickets.budget` | No CSRF |
| POST | `/tickets/{ticket}/close` | `tickets.close` | No CSRF |

**Protected — calendar:**
| Method | URI | Name | Notes |
|---|---|---|---|
| GET | `/calendar/events` | `calendar.events` | |
| GET | `/calendar` | `calendar.view` | |
| PATCH | `/calendar/events/{ticket}` | `calendar.events.reschedule` | |

**Protected — technician area (`role:technician`):**
| Method | URI | Name | Notes |
|---|---|---|---|
| PUT | `/technician/tickets/{ticket}/start` | `technician.tickets.start` | No CSRF, rate 20/min |
| PUT | `/technician/tickets/{ticket}/close` | `technician.tickets.close` | No CSRF, rate 20/min |
| PUT | `/technician/tickets/{ticket}/request-budget` | `technician.tickets.request-budget` | No CSRF, rate 20/min |

**Protected — stock read/register (`role:admin,technician`):**
| Method | URI | Name | Notes |
|---|---|---|---|
| GET | `/stock/parts` | `stock.parts.index` | |
| GET | `/stock/parts/{part}` | `stock.parts.show` | |
| GET | `/stock/suppliers` | `stock.suppliers.index` | |
| GET | `/stock/suppliers/{supplier}` | `stock.suppliers.show` | |
| GET | `/stock/movements` | `stock.movements.index` | |
| POST | `/stock/movements` | `stock.movements.store` | No CSRF |
| GET | `/stock/dashboard/summary` | `stock.dashboard.summary` | |
| GET | `/stock/dashboard/top-consumed` | `stock.dashboard.top-consumed` | |
| GET | `/stock/dashboard/slow-moving` | `stock.dashboard.slow-moving` | |
| GET | `/stock/dashboard/runout-forecast` | `stock.dashboard.runout-forecast` | |
| GET | `/stock/dashboard/cost-by-equipment` | `stock.dashboard.cost-by-equipment` | |
| GET | `/stock/dashboard/cost-by-ticket` | `stock.dashboard.cost-by-ticket` | |

**Protected — administration area (`role:admin`):** admin UI pages, user management CRUD, equipment management CRUD, QR codes, room management, budget/preventive, stock management (parts/suppliers/tax-rates/categories/maintenance-plans CRUD), and reports/exports. See the source file for the full list (~50 routes), including analytics pages/export (CSV/PDF/Excel), audit views, user registration, and the low-stock/inventory/cost reports.

### `api.php` — full route list

**Public API routes:**
| Method | URI | Name | Notes |
|---|---|---|---|
| GET | `/api/user` | `api.user` | `custom.auth` |
| POST | `/api/login` | `api.login` | rate 5/min |
| POST | `/api/password/email` | `api.password.email` | rate 3/min |
| GET | `/api/password/reset/{token}` | `api.password.reset.form` | |
| POST | `/api/password/reset` | `api.password.reset` | rate 5/min |

**Protected API routes (`custom.auth`):** activity feed (`/activities`), password change (`/password/change`, rate 10/min), tickets (index, search, store rate 30/min, show), comments (store rate 30/min, index), photos (store rate 30/min, index, destroy), workflow (reopen, cancel, schedule); technician routes (`role:technician`): start, close, close-final, request-budget (all rate 20/min); stock routes (`role:admin,technician`): parts/suppliers/movements/dashboard; admin routes (`role:admin`): users/profiles, audits, equipment, rooms, budget/preventive/assign, stock management CRUD, reports, analytics (stats + CSV/PDF/Excel exports), notifications (index, mark-read, test-email rate 5/min). See `api.php` for the full list (~70 routes).

### `console.php` — scheduled tasks
| Schedule | Command | Notes |
|---|---|---|
| Hourly | `telemetry:simulate --equipments=5 --probability=25` | Preventative telemetry; logs to `storage/logs/telemetry.log` |
| Daily 02:00 | `backup:run --clean` | DB backup with retention; logs to `storage/logs/backup.log` |
| Monthly | `audit:partition --months=12` | Creates audit table partitions; logs to `storage/logs/audit_partitions.log` |
| Twice daily | `currency:update-rates` | Refreshes `currency_rates`; logs to `storage/logs/currency_rates.log` |

## Notes for developers / AI

- **Middleware**: `custom.auth` handles both opaque API-token and session-based authentication. `role:admin`, `role:technician`, `role:admin,technician` are role-based access guards.
- **CSRF**: Many API-like web routes disable CSRF validation via `withoutMiddleware([PreventRequestForgery::class])` — these are consumed by JavaScript fetch calls.
- **Rate limiting**: Applied to login, password reset, and test email routes (limits attempts per minute to prevent brute-force attacks).
- **Route names**: Prefixed by transport layer (`api.*` for API, no prefix for web). Route names are English throughout.
- **Route parameters**: Use English names (`{ticket}`, `{equipment}`, `{room}`, `{part}`, `{supplier}`, `{plan}`, `{taxRate}`, `{category}`). The `{...}` curly braces mean "any number goes here".
- **Console schedule**: Requires Laravel cron to be configured (`php artisan schedule:run`).

## Related Folders

| Path | Relationship |
|---|---|
| `app/Http/Controllers/` | All route handlers |
| `app/Http/Middleware/` | Middleware referenced in routes |
| `app/Console/Commands/` | Artisan commands scheduled in console.php |
