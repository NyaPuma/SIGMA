# `app/Http/Controllers`

HTTP controllers for the SGM application. Each controller handles a specific resource or feature, translating incoming HTTP requests into calls to services, actions, or other collaborators.

> **New to programming?** See [NON_TECHNICAL_PROJECT_GUIDE.md](../../../NON_TECHNICAL_PROJECT_GUIDE.md) -- this folder is explained as "The Front Desk Clerks" where each clerk handles a specific type of request and routes it to the right department.

## Overview

Controllers are **final** classes extending a base `Controller` (which provides `authorize()` via `AuthorizesRequests` and a helper `authenticatedUser()` that resolves the user from the `api` guard first, then `$request->user()`). They are responsible for:

- **Input retrieval** – extracting data from `Request` objects (query params, route params, body).
- **Authorization** – delegating policy checks via `$this->authorize()`.
- **Response formatting** – returning JSON (`JsonResponse`), views (`View`), file downloads (`Response` / `StreamedResponse`), or redirects (`RedirectResponse`).

Controllers do **not** contain business logic; they delegate to:
- **Action classes** (single-purpose command handlers) in `app/Actions/` and `app/Domain/*/Actions/`.
- **Service classes** (orchestration / query logic) in `app/Services/`.
- **Policies** (authorization rules) in `app/Policies/`.

## Directory Structure

| Subdirectory | Purpose |
|---|---|
| Root (`Controllers/`) | General-purpose controllers (tickets, equipment, rooms, users, stock, etc.) |
| `Ticket/` | Specialized ticket lifecycle controllers (assignment, scheduling, start, close, reopen/cancel) |

## Key Patterns

### Controller Structure

```php
final class SomeController extends Controller
{
    public function __construct(
        private readonly SomeService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SomeModel::class);

        $items = $this->service->list(...);

        return response()->json([
            'items' => SomeResource::collection($items),
        ]);
    }
}
```

### Resource Controllers

Most controllers follow the standard RESTful pattern:

| Method | Purpose | Typical Response |
|---|---|---|
| `index()` | Paginated listing with filters | JSON with `data` + pagination metadata |
| `show()` / `showDetail()` | Single resource detail | JSON with resource |
| `store()` | Create a resource | JSON 201 with resource + success message |
| `update()` | Modify a resource | JSON with resource + success message |
| `destroy()` / `delete()` | Soft-delete a resource | JSON with success message |

### Authorization

Controllers use policy-based authorization:

```php
$this->authorize('viewAny', SomeModel::class);  // Collection access
$this->authorize('update', $model);              // Single resource access
```

Policies are defined in `app/Policies/` and auto-discovered by Laravel. Each method below lists the exact policy method it calls (e.g. `authorize('viewAny', Ticket::class)`).

### Response Format

All JSON responses follow a consistent shape:

```json
{
    "items": [...],
    "meta": {
        "current_page": 1,
        "per_page": 15,
        "last_page": 5,
        "total": 72
    }
}
```

Success responses include a `message` key:

```json
{
    "message": "Record created successfully.",
    "item": { ... }
}
```

### OpenAPI Documentation

API controllers use `#[OA\...]` attributes (from `@nicegoodthings/openapi-laravel`) for Swagger documentation. These attributes define the endpoint's summary, tags, parameters, request body, and responses.

## Routing & Middleware Model

Routes live in `routes/web.php` and `routes/api.php`. Both register equivalent endpoints (the `web` routes are used by the browser UI, the `api` routes by token-based API clients). They are grouped by **middleware**:

| Middleware group | Scope | What it guards |
|---|---|---|
| Public (no group) | `/`, `/ui/login`, `/theme/custom.css`, `/locale`, ticket public pages | Open to everyone |
| `custom.auth` | Wraps most of the app | Requires a valid auth token (see `CustomAuthMiddleware`) — the outermost gate for every protected route |
| `role:admin,user` | `ui.tickets.create` | Admin **or** regular user |
| `role:technician` | `technician/tickets/*` | Technician only |
| `role:admin,technician` | stock read + movements | Admin or technician |
| `role:admin` | admin area | Admin only |

**Middleware aliases** (registered in `bootstrap/app.php`):

- `custom.auth` → `App\Http\Middleware\CustomAuthMiddleware` — authenticates the request (validates `api_token`, bearer token, or session against a hashed token on the `User` row).
- `role` → `App\Http\Middleware\RoleMiddleware` — checks the user's `profile.name` against a comma-separated list passed as the middleware argument (e.g. `role:admin,technician`).
- `rate.limit:MAX,MINUTES` → `App\Http\Middleware\RateLimitMiddleware` — per-endpoint rate limiting (e.g. `rate.limit:5,1` = max 5 requests per minute). In addition, many controllers apply their own per-email cache-based brute-force protection (see `AuthController::login`).

**CSRF:** Routes that receive form/JSON writes from non-browser clients use `withoutMiddleware([ValidateCsrfToken::class])`; the `api.php` file has no CSRF layer at all.

> **Note:** `routes/api.php` and `routes/web.php` frequently map the **same controller method** to two URIs (one `web`, one `api`, with different route names). Each method below lists both mappings where they exist. API routes are mounted under the `/api` prefix automatically; the URIs below for `api.php` are shown **without** that prefix for brevity (so `POST /tickets` in `api.php` is actually `POST /api/tickets`).

---

## Controller Reference

Below is every controller grouped by the area it powers, with a plain-English description, its exact routes, its middleware, and a method-by-method breakdown.

---

# 1. Authentication & User Management

---

### `AuthController`

- **File:** `app/Http/Controllers/AuthController.php`
- **What it is:** Handles email/password login (with a per-email brute-force lockout) and logout for both the web UI and the API.

**Its routes:**

| Method | URI | Route name | Middleware | File |
|---|---|---|---|---|
| POST | `/login` | `login` | `rate.limit:5,1` (web) | `web.php:56` |
| POST | `/api/login` | `api.login` | `rate.limit:5,1` | `api.php:45` |
| POST | `/logout` | `auth.logout` | `custom.auth` | `web.php:105` |

(Note: logout has no equivalent in `api.php`.)

**Dependencies:** `UserService`, `LoginRequest`.

- **Method:** `login(LoginRequest $request): JsonResponse`
  - **What it does:** Authenticates a user by email + password and issues an API token + session.
  - **When it runs / how it works:**
    1. Lowercases the submitted email and checks a cache key `login_attempts:sha1(ip|email)` — keyed by address **and** email on purpose, exactly like the `rate.limit` middleware on the same route (`RateLimitMiddleware::resolveRequestSignature()`), because a counter stored under the email alone would let anyone lock a real user out of their own account by failing five logins. If the attempt count already reached `max_attempts` (config `services.custom.auth.max_attempts`, default 5), it returns **429** with `Retry-After` (lockout minutes * 60) and the "account temporarily locked" message.
    2. Looks up `User` where `email` matches **and** `active = true`.
    3. Verifies the password with `Hash::check`. On failure it atomically increments the cache counter (`Cache::add` seeds with TTL on first failure, `Cache::increment` for concurrenCy) and returns **401** "invalid credentials".
    4. On success it forgets the rate-limit key, re-hashes the password if `Hash::needsRehash`, ensures a default user profile exists (`UserService::ensureDefaultProfile`), creates the token (`UserService::createToken`), and returns `UserService::buildAuthResponse` (the opaque API token + user, cookie set via the service).
  - **Authorization:** none (public — guest endpoint).

- **Method:** `logout(Request $request): JsonResponse`
  - **What it does:** Terminates the authenticated user's session — nullifies the token and remember-me cookie.
  - **When it runs / how it works:** Resolves the user via `authenticatedUser()`, sets `api_token = null` and clears the remember token, saves, and returns `UserService::buildLogoutResponse`.
  - **Authorization:** `custom.auth` middleware.

---

### `RegisterController`

- **File:** `app/Http/Controllers/RegisterController.php`
- **What it is:** Lets an administrator create a new user account on behalf of someone else, issuing an API token that is **not** linked to the registrant's own session.

**Its routes:**

| Method | URI | Route name | Middleware | File |
|---|---|---|---|---|
| POST | `/admin/users/register` | `admin.users.register` | `custom.auth` + `role:admin` + `rate.limit:5,1` | `web.php:322` |

(There is no equivalent in `api.php`.)

**Dependencies:** `UserService`, `RegisterRequest`.

- **Method:** `__invoke(RegisterRequest $request): JsonResponse`
  - **What it does:** Creates a new `User` and returns it with a fresh API token.
  - **When it runs / how it works:**
    1. Ensures the default "User" profile exists (`UserProfile::firstOrCreate`).
    2. Creates the `User` with validated name, lowercased email, hashed password, `profile_id`, and `active = true`.
    3. Calls `UserService::createToken($user, $request, false)` — the `false` prevents the token from being linked to the current request's session/cookies.
    4. Returns **201** with `{ user, token }`.
  - **Authorization:** `$this->authorize` is **not** called; access is enforced entirely by the route's `role:admin` middleware.

---

### `PasswordResetController`

- **File:** `app/Http/Controllers/PasswordResetController.php`
- **What it is:** Handles the password recovery flow — sending a reset token by email and applying the new password.

**Its routes:**

| Method | URI | Route name | Middleware | File |
|---|---|---|---|---|
| POST | `/api/password/email` | `api.password.email` | `rate.limit:3,1` | `api.php:49` |
| POST | `/api/password/reset` | `api.password.reset` | `rate.limit:5,1` | `api.php:56` |

(Only exposed via the API file; the reset form page itself lives on `PageController::passwordResetForm`.)

**Dependencies:** `PasswordResetService`, `SendResetLinkRequest`, `ResetPasswordRequest`, `PasswordResetMail`.

- **Method:** `sendResetLink(SendResetLinkRequest $request): JsonResponse`
  - **What it does:** Emails a password-reset token/link to the requested address.
  - **When it runs / how it works:**
    1. Calls `PasswordResetService::createResetToken($email)`.
    2. Looks up the user by the same trimmed, lowercased email (so case differences still resolve).
    3. If a user exists, `Mail::to($user)->send(new PasswordResetMail($token))`.
    4. Returns JSON `{ message, token }` — the token is `null` in production (debug only).
  - **Authorization:** none (public guest endpoint).

- **Method:** `resetPassword(ResetPasswordRequest $request): JsonResponse`
  - **What it does:** Validates the token+email pair and saves the new password.
  - **When it runs / how it works:**
    1. Calls `PasswordResetService::validateToken($email, $token)`; if it returns null, responds **422** "invalid or expired token".
    2. Otherwise `PasswordResetService::resetPassword($user, $password)` and returns a success message.
  - **Authorization:** none (public guest endpoint).

---

### `ProfileController`

- **File:** `app/Http/Controllers/ProfileController.php`
- **What it is:** Lets the logged-in user change their password and update their profile (name / password).

**Its routes:**

| Method | URI | Route name | Middleware | File |
|---|---|---|---|---|
| POST | `/password/change` | `auth.password.change` | `custom.auth` + `rate.limit:10,1` | `web.php:108` |
| POST | `/api/password/change` | `api.password.change` | `custom.auth` + `rate.limit:10,1` | `api.php:71` |
| POST | `/profile/update` | `auth.profile.update` | `custom.auth` + `rate.limit:10,1` | `web.php:111` |

(`profile/update` only in `web.php`.)

**Dependencies:** `ChangePasswordRequest`, `UpdateProfileRequest`, `UserResource`.

- **Method:** `changePassword(ChangePasswordRequest $request, UserService $userService): JsonResponse`
  - **What it does:** Verifies the current password, saves a new one and rotates the API token.
  - **When it runs / how it works:** Uses `$request->user()`; if `Hash::check(current_password, user.password)` fails, returns **403** "incorrect current password"; otherwise hashes and saves `new_password`, then issues a fresh token through `UserService::createToken(..., withSession: false)` and returns a success message with `re_authentication_required: true`.
  - **Why the token rotates:** a leaked token stayed usable after the owner changed their password, which made the change useless as a remediation. Every previously issued token, including the caller's own, now has to authenticate again.
  - **Authorization:** `custom.auth`.

- **Method:** `updateProfile(UpdateProfileRequest $request, UserService $userService): JsonResponse`
  - **What it does:** Updates the user's `name` and/or `password`.
  - **When it runs / how it works:** Reads `password` (optional) and `name` (optional); hashes the password if provided, sets the name if provided, saves, `loadMissing('profile')`, and returns `{ message, user: UserResource }`.
  - **Authorization:** `custom.auth`.

---

### `PreferencesController`

- **File:** `app/Http/Controllers/PreferencesController.php`
- **What it is:** Manages per-user display preferences — language, currency, date format, time format, and number format. Preferences are saved to the DB for authenticated users and to the session for guests.

**Its routes:** All are public (not under `custom.auth`) but resolve the user from tokens if present.

| Method | URI | Route name | Middleware | File |
|---|---|---|---|---|
| GET | `/preferences` | `preferences.edit` | public | `web.php:76` |
| POST | `/preferences/language` | `preferences.update_language` | public, no CSRF | `web.php:78` |
| POST | `/preferences/currency` | `preferences.update_currency` | public, no CSRF | `web.php:81` |
| POST | `/preferences/date-format` | `preferences.update_date_format` | public, no CSRF | `web.php:84` |
| POST | `/preferences/time-format` | `preferences.update_time_format` | public, no CSRF | `web.php:87` |
| POST | `/preferences/number-format` | `preferences.update_number_format` | public, no CSRF | `web.php:90` |
| POST | `/preferences` | `preferences.update_all` | public, no CSRF | `web.php:93` |

**Dependencies:** `PreferencesService`, `LocaleService`, `AuthUserResolver`.

- **Method:** `edit(Request $request): View`
  - **What it does:** Renders the preferences form.
  - **When it runs / how it works:** Loads current preferences via `PreferencesService::current($request)` and passes the current values plus the supported options (locales, currencies, date/time/number formats) to the `preferences.edit` Blade view.
  - **Authorization:** none (public; works for guests too).

- **Method:** `updateLanguage(Request $request): JsonResponse|RedirectResponse`
  - **What it does:** Saves the user's `language` preference and syncs the session locale.
  - **When it runs / how it works:** Validates `language`, sanitises via `LocaleService::sanitize`, merges into the current preferences preserving the other fields, persists to DB (authenticated) or session (guest), sets the session `locale`, and returns JSON `{ success, language, message }` (if `wantsJson`) or a redirect back with a flash message.

- **Method:** `updateCurrency(Request $request): JsonResponse|RedirectResponse`
  - **What it does:** Saves the `currency` preference (must be 3 uppercase chars).
  - **When it runs / how it works:** Same merge/persist logic as `updateLanguage`, using the current language as the locale key.

- **Method:** `updateDateFormat(Request $request): JsonResponse|RedirectResponse`
  - **What it does:** Saves the `date_format` preference. Identical pattern to `updateLanguage` with the relevant field.

- **Method:** `updateTimeFormat(Request $request): JsonResponse|RedirectResponse`
  - **What it does:** Saves the `time_format` preference. Same pattern.

- **Method:** `updateNumberFormat(Request $request): JsonResponse|RedirectResponse`
  - **What it does:** Saves the `number_format` preference (optional field). Same pattern.

- **Method:** `updateAll(Request $request): JsonResponse|RedirectResponse`
  - **What it does:** Saves all five preferences at once.
  - **When it runs / how it works:** Validates all fields (number_format nullable), persists the validated array, returns JSON `{ success, preferences, message }` or redirect back.

---

### `AdminUserController`

- **File:** `app/Http/Controllers/AdminUserController.php`
- **What it is:** Gives administrators full control over user accounts — listing, creating, updating, deactivating, deleting, and listing profiles/roles.

**Its routes:** All require `custom.auth` + `role:admin`.

| Method | URI | Route name | File |
|---|---|---|---|
| GET | `/admin/users` | `admin.users.index` / `api.admin.users.index` | `web.php:336`, `api.php:136` |
| POST | `/admin/users` | `admin.users.store` / `api.admin.users.store` | `web.php:337`, `api.php:137` |
| PATCH | `/admin/users/{targetUser}` | `admin.users.update` / `api.admin.users.update` | `web.php:338`, `api.php:138` |
| PATCH | `/admin/users/{targetUser}/inactive` | `admin.users.inactivate` / `api.admin.users.inactivate` | `web.php:339`, `api.php:139` |
| DELETE | `/admin/users/{targetUser}` | `admin.users.destroy` / `api.admin.users.destroy` | `web.php:340`, `api.php:140` |
| GET | `/admin/profiles` | `admin.profiles.index` / `api.admin.profiles.index` | `web.php:341`, `api.php:141` |

**Dependencies:** `UserRepositoryInterface`, `CreateUserAction`, `UpdateUserAction`, `StoreUserRequest`, `UpdateUserRequest`, `UserResource`, `UserProfileResource`.

- **Method:** `index(Request $request): JsonResponse`
  - **What it does:** Lists all users with optional filters.
  - **When it runs / how it works:** `authorize('viewAny', User::class)`; reads `q` (search), `role`, `status` query params; calls `userRepository->getAll(['profile'], $search, $role, $status)`; returns `{ users: UserResource::collection(...) }`.

- **Method:** `store(StoreUserRequest $request): JsonResponse`
  - **What it does:** Creates a new user.
  - **When it runs / how it works:** `authorize('create', User::class)`; builds `StoreUserData::fromRequest(...)` and executes `CreateUserAction::execute`; loads `profile`; returns **201** `{ message, user }`.

- **Method:** `update(UpdateUserRequest $request, User $targetUser): JsonResponse`
  - **What it does:** Updates an existing user.
  - **When it runs / how it works:** `authorize('update', $targetUser)`; builds `UpdateUserData` and executes `UpdateUserAction::execute`; returns `{ message, user }`.

- **Method:** `inactivate(Request $request, User $targetUser): JsonResponse`
  - **What it does:** Deactivates a user's account.
  - **When it runs / how it works:** `authorize('inactivate', $targetUser)`; **guards against self-deactivation** — if `request->user()->id === targetUser->id` returns **422**; otherwise `userRepository->inactivate($targetUser)` and returns `{ message, user }`.

- **Method:** `destroy(Request $request, User $targetUser): JsonResponse`
  - **What it does:** Soft-deletes a user.
  - **When it runs / how it works:** `authorize('delete', $targetUser)`; guards against self-deletion (**422**); `userRepository->delete($targetUser)`; returns success message.

- **Method:** `profiles(Request $request): JsonResponse`
  - **What it does:** Lists all available profiles/roles with user counts.
  - **When it runs / how it works:** `authorize('viewAny', UserProfile::class)`; `UserProfile::withCount('users')->get()`; returns `{ profiles: UserProfileResource::collection(...) }`.

---

### `AdminController`

- **File:** `app/Http/Controllers/AdminController.php`
- **What it is:** Powers two admin-only operations — approving/rejecting a ticket's budget and creating preventive maintenance tickets.

**Its routes:** Both require `custom.auth` + `role:admin`.

| Method | URI | Route name | File |
|---|---|---|---|
| PATCH | `/admin/tickets/{ticket}/approve-budget` | `admin.tickets.approve-budget` / `api.admin.tickets.approve-budget` | `web.php:367`, `api.php:160` |
| POST | `/admin/preventive` | `admin.preventive.store` / `api.admin.preventive.store` | `web.php:366`, `api.php:159` |

**Dependencies:** `ApproveBudgetAction`, `CreatePreventiveTicketAction`, `BudgetDecisionRequest`, `StorePreventiveRequest`, `BudgetDecisionData`, `TicketResource`.

- **Method:** `approveBudget(BudgetDecisionRequest $request, Ticket $ticket): JsonResponse`
  - **What it does:** Approves or rejects a ticket's pending budget.
  - **When it runs / how it works:** `authorize('approveBudget', $ticket)`; executes `ApproveBudgetAction::execute($ticket, $admin, BudgetDecisionData::fromRequest(...))`; reads the resulting `budget_status` — if Approved, message "budget approved, ticket unlocked", else "budget refused, repair aborted"; loads relations and returns `{ message, ticket }`.

- **Method:** `storePreventive(StorePreventiveRequest $request): JsonResponse`
  - **What it does:** Creates a preventive maintenance ticket.
  - **When it runs / how it works:** `authorize('createPreventive', Ticket::class)`; executes `CreatePreventiveTicketAction::execute` with the admin, title, description, `technician_id`, and `scheduled_at` (parsed as a date); loads relations and returns **201** `{ message, ticket }`.

---

# 2. Tickets

---

### `TicketController`

- **File:** `app/Http/Controllers/TicketController.php`
- **What it is:** The central controller for maintenance tickets — listing, creating, searching, and viewing individual tickets, plus open-ticket and most-urgent lookups.

**Its routes:**

| Method | URI | Route name | Middleware | File |
|---|---|---|---|---|
| GET | `/tickets` | `tickets.index` / `api.tickets.index` | `custom.auth` | `web.php:210`, `api.php:76` |
| POST | `/tickets` | `tickets.store` / `api.tickets.store` | `custom.auth` + `rate.limit:30,1` | `web.php:212`, `api.php:78` |
| GET | `/tickets/search` | `tickets.search` / `api.tickets.search` | `custom.auth` | `web.php:208`, `api.php:77` |
| GET | `/tickets/{ticket}` | `tickets.show` / `api.tickets.show` | `custom.auth` | `web.php:211`, `api.php:81` |
| GET | `/admin/tickets/{ticket}` | `admin.tickets.show` | `custom.auth` + `role:admin` | `web.php:327` |
| GET | `/technician/tickets/open` | `technician.tickets.open` | `custom.auth` + `role:admin` | `web.php:309` |
| GET | `/tickets/most-urgent` | `tickets.most-urgent` | `custom.auth` | `web.php:209` |

> Note the URL pattern order: `/tickets/search` and `/tickets/most-urgent` are declared **before** `/tickets/{ticket}` so they are not captured by the `{ticket}` wildcard.

**Dependencies:** `TicketRepositoryInterface`, `CreateTicketAction`, `TechnicianAssignmentService`, `TicketSearchService`, `StoreTicketRequest`, `TicketResource`, `GenerateAiRecommendationJob`.

- **Method:** `index(Request $request): JsonResponse`
  - **What it does:** Lists tickets scoped by the user's role.
  - **When it runs / how it works:** `authorize('viewAny', Ticket::class)`; if the user is a technician → `getTicketsByTechnician(user_id)`; if admin → `getAll(['equipment','room','user','technician','status'])`; else (regular user) → `getTicketsByUser(user_id)`. Returns `{ tickets }`.

- **Method:** `store(StoreTicketRequest $request): JsonResponse`
  - **What it does:** Creates a new support ticket.
  - **When it runs / how it works:** `authorize('create', Ticket::class)`; builds `CreateTicketData::fromRequest`; executes `CreateTicketAction::execute($user, $data)`; dispatches `GenerateAiRecommendationJob::dispatch($ticket)->afterCommit()` (AI technician suggestion in the background); loads relations; returns **201** `{ message, ticket }`.

- **Method:** `search(Request $request): JsonResponse`
  - **What it does:** Searches and filters tickets by various criteria.
  - **When it runs / how it works:** `authorize('viewAny', Ticket::class)`; validates the `priority` param against `TicketPriorityEnum::acceptedValues()` (returns **422** if invalid); builds `TicketFilters::fromRequest`. For technicians the filters are re-scoped with `technicianId = user.id`; for regular users `userId = user.id`; admins keep full scope. Passes to `TicketSearchService::search`, returns `{ tickets, meta }` with pagination.

- **Method:** `show(Request $request, Ticket $ticket): JsonResponse|View`
  - **What it does:** Shows a single ticket's detail, loading `equipment.category`, `room`, `user`, `technician`, `status`.
  - **When it runs / how it works:** `authorize('view', $ticket)`; loads the relations; if the request wants JSON or AJAX returns `{ ticket }`, otherwise renders the `ui.ticket-detail` Blade view with the ticket.

- **Method:** `openTickets(Request $request): JsonResponse`
  - **What it does:** Lists all open tickets (restricted to technicians and admins).
  - **When it runs / how it works:** `authorize('viewAny', Ticket::class)`; then a second check — if the profile name is neither `Technician` nor `Admin`, returns **403**; otherwise `ticketRepository->getOpenTickets()` and returns `{ tickets }`.

- **Method:** `getMostUrgentOpenTicket(Request $request): JsonResponse`
  - **What it does:** Returns the most urgent open ticket for priority assignment.
  - **When it runs / how it works:** `authorize('viewAny', Ticket::class)`; second profile check (technician/admin) → **403** otherwise; reads optional `exclude` id; calls `TechnicianAssignmentService::findMostUrgentOpenTicket($excludeId)`; if none found returns **404** `{ ticket_id: null, message }`; otherwise returns `{ ticket_id, title, priority }`.

---

### `TicketAssignmentController` (`Ticket/`)

- **File:** `app/Http/Controllers/Ticket/TicketAssignmentController.php`
- **What it is:** Assigns a technician to a ticket (auto or manual) and transitions it to "In Progress".

**Its routes:**

| Method | URI | Route name | Middleware | File |
|---|---|---|---|---|
| POST | `/tickets/{ticket}/assign-technician` | `tickets.assign-technician` | `custom.auth` + `role:admin` + `rate.limit:20,1` | `web.php:310` |
| PATCH | `/admin/tickets/{ticket}/assign` | `admin.tickets.assign` | `custom.auth` + `role:admin`, no CSRF | `web.php:328` |
| PATCH | `/api/admin/tickets/{ticket}/assign` | `api.admin.tickets.assign` | `custom.auth` + `role:admin` + `rate.limit:20,1` | `api.php:161` |

**Dependencies:** `TechnicianAssignmentService`, `TicketWorkflowService`, `AssignTechnicianToTicketRequest`, `NotifiesTicketStatusChange` trait.

- **Method:** `__invoke(AssignTechnicianToTicketRequest $request, Ticket $ticket): JsonResponse`
  - **What it does:** Assigns a technician and sets the ticket to In Progress.
  - **When it runs / how it works:**
    1. `authorize('assign', $ticket)`.
    2. Guards: if the ticket is Closed or Cancelled, returns **422** "cannot assign to a closed ticket".
    3. Captures `oldStatus`.
    4. Calls `TechnicianAssignmentService::assignToTicket($ticket, $technicianId)` where `technician_id` may be null (auto-assignment).
    5. If null returned, returns **422** with the appropriate message (invalid selected technician vs. no technicians available).
    6. On success, `TicketWorkflowService::startRepair($ticket)` to move to In Progress, unsets the stale `status` relation.
    7. Dispatches `notifyStatusChange()` so the owner and the technician are told.
    8. Loads relations and returns `{ message, ticket }`.

---

### `TicketStartController` (`Ticket/`)

- **File:** `app/Http/Controllers/Ticket/TicketStartController.php`
- **What it is:** Lets a technician start working on an open ticket, transitioning it to "In Progress" — with a higher-priority override check.

**Its routes:**

| Method | URI | Route name | Middleware | File |
|---|---|---|---|---|
| PUT | `/technician/tickets/{ticket}/start` | `technician.tickets.start` | `custom.auth` + `role:technician` + `rate.limit:20,1`, no CSRF | `web.php:260` |
| PUT | `/api/technician/tickets/{ticket}/start` | `api.technician.tickets.start` | `custom.auth` + `role:technician` + `rate.limit:20,1` | `api.php:103` |

**Dependencies:** `TicketWorkflowService`, `NotificationService`, `StartTicketRequest`, `NotifiesTicketStatusChange` trait.

- **Method:** `__invoke(StartTicketRequest $request, Ticket $ticket): JsonResponse`
  - **What it does:** Starts the intervention on an open ticket.
  - **When it runs / how it works:**
    1. `authorize('start', $ticket)`.
    2. If the ticket is not "Open", returns **422**.
    3. Captures `oldStatus`, reads the `force` boolean, and calls `TicketWorkflowService::findHigherPriorityTickets($ticket)`.
    4. If higher-priority tickets exist **and** `force` is false, returns **409** with a warning payload `{ warning, message, urgent_tickets_count, my_urgent_tickets_count, current_priority, can_force }`.
    5. Otherwise `TicketWorkflowService::startRepair($ticket, $user)`.
    6. If forced over a priority conflict, `NotificationService::notifyPriorityOverride(...)` is sent to admins.
    7. Dispatches `notifyStatusChange()` (owner + technician are told); loads relations; returns `{ message, overridden, ticket }`.

---

### `TicketScheduleController` (`Ticket/`)

- **File:** `app/Http/Controllers/Ticket/TicketScheduleController.php`
- **What it is:** Sets the intervention time window for a ticket (when the technician will be on-site).

**Its routes:**

| Method | URI | Route name | Middleware | File |
|---|---|---|---|---|
| POST | `/tickets/{ticket}/schedule` | `tickets.schedule` | `custom.auth`, no CSRF | `web.php:241` |
| POST | `/api/tickets/{ticket}/schedule` | `api.tickets.schedule` | `custom.auth` | `api.php:99` |

**Dependencies:** `ScheduleTicketAction`, `ScheduleTicketRequest`, `ScheduleTicketData`, `TicketResource`.

- **Method:** `__invoke(ScheduleTicketRequest $request, Ticket $ticket): JsonResponse`
  - **What it does:** Schedules the intervention window.
  - **When it runs / how it works:** `authorize('schedule', $ticket)`; executes `ScheduleTicketAction::execute($ticket, ScheduleTicketData::fromRequest($request))` in a try/catch — an `InvalidArgumentException` (e.g. closed ticket, end before start) is caught and returned as **422**; loads `equipment`, `room`; returns `{ message, ticket }`.

---

### `TicketCloseController` (`Ticket/`)

- **File:** `app/Http/Controllers/Ticket/TicketCloseController.php`
- **What it is:** Closes a ticket — a quick simple close, or a full final close with priority verification.

**Its routes:**

| Method | URI | Route name | Middleware | File |
|---|---|---|---|---|
| PUT | `/technician/tickets/{ticket}/close` | `technician.tickets.close` | `custom.auth` + `role:technician` + `rate.limit:20,1`, no CSRF | `web.php:264` |
| PUT | `/api/technician/tickets/{ticket}/close` | `api.technician.tickets.close` | `custom.auth` + `role:technician` + `rate.limit:20,1` | `api.php:106` |
| POST | `/tickets/{ticket}/close` | `tickets.close` | `custom.auth`, no CSRF | `web.php:249` |
| PUT | `/api/technician/tickets/{ticket}/close-final` | `api.technician.tickets.close-final` | `custom.auth` + `role:technician` + `rate.limit:20,1` | `api.php:109` |

**Dependencies:** `TicketWorkflowService`, `NotificationService`, `LocalizationService`, `CloseTicketRequest`, `CloseTicketSimpleRequest`, `NotifiesTicketStatusChange` trait.

- **Method:** `simpleClose(CloseTicketSimpleRequest $request, Ticket $ticket): JsonResponse`
  - **What it does:** Quick close of an in-progress ticket.
  - **When it runs / how it works:** `authorize('close', $ticket)`; if the ticket is not "In Progress", returns **422**; captures `oldStatus`; `TicketWorkflowService::close($ticket, cost: float, report, minutesSpent: int)`; dispatches `notifyStatusChange()` for the Closed transition; loads relations; returns `{ message, ticket }`.

- **Method:** `closeFinal(CloseTicketRequest $request, Ticket $ticket): JsonResponse`
  - **What it does:** Final close with pending-priority verification and notification dispatch.
  - **When it runs / how it works:**
    1. `authorize('close', $ticket)`; reads `force`.
    2. If not forced, checks `findHigherPriorityTickets($ticket)`; if higher-priority tickets exist, returns **409** with a warning payload.
    3. Reads `actual_cost` (float) and `report` (kept null when absent so it doesn't overwrite the existing technical report), plus `minutes_spent`.
    4. `TicketWorkflowService::close(...)`.
    5. If `force` was used and higher-priority tickets exist, `NotificationService::notifyPriorityOverride(...)`.
    6. Dispatches `notifyStatusChange()` for the Closed transition; formats the cost via `LocalizationService`; dispatches `NotificationService::notifyTicketClosed(...)` to everyone.
    7. Returns `{ message, ticket }`.

---

### `TicketLifecycleController` (`Ticket/`)

- **File:** `app/Http/Controllers/Ticket/TicketLifecycleController.php`
- **What it is:** Handles reopening a closed/cancelled ticket and cancelling an open ticket.

**Its routes:**

| Method | URI | Route name | Middleware | File |
|---|---|---|---|---|
| POST | `/tickets/{ticket}/reopen` | `tickets.reopen` | `custom.auth`, no CSRF | `web.php:235` |
| POST | `/api/tickets/{ticket}/reopen` | `api.tickets.reopen` | `custom.auth` | `api.php:97` |
| POST | `/tickets/{ticket}/cancel` | `tickets.cancel` | `custom.auth`, no CSRF | `web.php:238` |
| POST | `/api/tickets/{ticket}/cancel` | `api.tickets.cancel` | `custom.auth` | `api.php:98` |

**Dependencies:** `TicketWorkflowService`, `NotifiesTicketStatusChange` trait.

- **Method:** `reopen(Request $request, Ticket $ticket): JsonResponse`
  - **What it does:** Re-opens a previously closed or cancelled ticket.
  - **When it runs / how it works:** `authorize('reopen', $ticket)`; if `TicketWorkflowService::reopen($ticket)` returns false (not closed/cancelled), returns **422**; otherwise unsets the stale `status` relation, dispatches `notifyStatusChange()`, loads relations, returns `{ message, ticket }`.

- **Method:** `cancel(Request $request, Ticket $ticket): JsonResponse`
  - **What it does:** Cancels an open ticket.
  - **When it runs / how it works:** `authorize('cancel', $ticket)`; if not "Open", returns **422**; `TicketWorkflowService::cancel($ticket)`; unsets `status`, dispatches `notifyStatusChange()`, loads relations, returns `{ message, ticket }`.

---

### `TicketBudgetController`

- **File:** `app/Http/Controllers/TicketBudgetController.php`
- **What it is:** Manages the budget estimation and approval flow — a technician submits a cost, which is either auto-approved (below threshold → In Progress) or sent to admins for approval (above threshold → Pending Budget).

**Its routes:**

| Method | URI | Route name | Middleware | File |
|---|---|---|---|---|
| POST | `/tickets/{ticket}/budget` | `tickets.budget` | `custom.auth`, no CSRF | `web.php:254` |
| PUT | `/technician/tickets/{ticket}/request-budget` | `technician.tickets.request-budget` | `custom.auth` + `role:technician` + `rate.limit:20,1`, no CSRF | `web.php:276` |
| PUT | `/api/technician/tickets/{ticket}/request-budget` | `api.technician.tickets.request-budget` | `custom.auth` + `role:technician` + `rate.limit:20,1` | `api.php:112` |

**Dependencies:** `TicketStatusService`, `NotificationService`, `SubmitBudgetRequest`, `RequestBudgetRequest`, `BudgetSubmissionData`, `TicketResource`. Uses the `LocksTicketRow` concern (`app/Domain/Ticket/Concerns/LocksTicketRow.php`).

- **Method:** `submitEstimate(SubmitBudgetRequest $request, Ticket $ticket): JsonResponse`
  - **What it does:** Submits a quick budget estimate for a ticket.
  - **When it runs / how it works:** `authorize('submitBudget', $ticket)`; calls `ensureSubmitAllowed($ticket)` (rejects **422** if the ticket is Closed/Cancelled/Rejected or a budget request is already pending); builds `BudgetSubmissionData` and hands everything to `processSubmission`.

- **Method:** `requestAuthorization(RequestBudgetRequest $request, Ticket $ticket): JsonResponse`
  - **What it does:** Requests detailed budget authorization for a ticket (technician flow).
  - **When it runs / how it works:** `authorize('requestBudget', $ticket)` (policy restricts to the ticket's assigned technician); same `ensureSubmitAllowed` guard, then the same `processSubmission`.

- **Private helpers (referenced for clarity):**
  - `processSubmission(Ticket, BudgetSubmissionData, User): JsonResponse` — opens `DB::transaction`, calls `lockTicketRow($ticket)` and re-checks `ensureSubmitAllowed` against the committed row, applies the changes and routes on the threshold. The route-bound model was read before authorization and validation ran, so another request may have claimed the ticket or ruled on the previous budget in the meantime; without the re-read the last writer wins and the guard can approve a request the database no longer allows.
  - `ensureSubmitAllowed(Ticket $ticket): ?JsonResponse` — returns a 422 response or null; rejects closed/cancelled/rejected tickets and existing pending requests.
  - `applyBudgetChanges(Ticket, BudgetSubmissionData, User)` — sets optional `budget_details` (as an array: the column is array-cast, so encoding it first would store JSON inside JSON), assigns the requesting user as technician if needed, sets `budget_requested = true`, `budget_amount`, and clears `budget_approved_by`/`budget_decided_at` from the replaced request.
  - `handleAboveThreshold(...)` — sets `budget_status = Pending`, `budget_requested_at = now()`, moves the ticket to `PendingBudget` status, sends `notifyBudgetSubmitted`, returns `{ message, ticket }`.
  - `handleBelowThreshold(...)` — clears `budget_status`, moves to `InProgress`, sends `notifyBudgetAutoApproved`, returns `{ message, ticket }`.

---

### `TicketCommentController`

- **File:** `app/Http/Controllers/TicketCommentController.php`
- **What it is:** Lets users add and view comments on a ticket.

**Its routes:**

| Method | URI | Route name | Middleware | File |
|---|---|---|---|---|
| POST | `/tickets/{ticket}/comments` | `tickets.comments.store` | `custom.auth` + `rate.limit:30,1`, no CSRF | `web.php:218` |
| POST | `/api/tickets/{ticket}/comments` | `api.tickets.comments.store` | `custom.auth` + `rate.limit:30,1` | `api.php:84` |
| GET | `/tickets/{ticket}/comments` | `tickets.comments.index` | `custom.auth` | `web.php:222` |
| GET | `/api/tickets/{ticket}/comments` | `api.tickets.comments.index` | `custom.auth` | `api.php:87` |

**Dependencies:** `StoreCommentRequest`, `TicketCommentResource`.

- **Method:** `store(StoreCommentRequest $request, int $id): JsonResponse`
  - **What it does:** Adds a new comment to a ticket. (Takes the ticket id as a raw `int`, unlike the route-model-bound variants.)
  - **When it runs / how it works:** `Ticket::findOrFail($id)`; `authorize('view', $ticket)` (only users who can view the ticket may comment); creates a `TicketComment` with the ticket id, the current user id, and the validated comment; loads `user`; returns **201** `{ message, comment }`.

- **Method:** `index(Request $request, Ticket $ticket): JsonResponse`
  - **What it does:** Lists all comments on a ticket in chronological order.
  - **When it runs / how it works:** `authorize('view', $ticket)`; queries `TicketComment::where('ticket_id', ...)->with('user')->chronological()->get()`; returns `{ comments }`.

---

### `TicketAttachmentController`

- **File:** `app/Http/Controllers/TicketAttachmentController.php`
- **What it is:** Lets users upload photos to a ticket and manage (list/delete) existing attachments.

**Its routes:**

| Method | URI | Route name | Middleware | File |
|---|---|---|---|---|
| POST | `/tickets/{ticket}/photos` | `tickets.photos.store` | `custom.auth` + `rate.limit:30,1`, no CSRF | `web.php:225` |
| POST | `/api/tickets/{ticket}/photos` | `api.tickets.photos.store` | `custom.auth` + `rate.limit:30,1` | `api.php:90` |
| GET | `/tickets/{ticket}/photos` | `tickets.photos.index` | `custom.auth` | `web.php:229` |
| GET | `/api/tickets/{ticket}/photos` | `api.tickets.photos.index` | `custom.auth` | `api.php:93` |
| DELETE | `/tickets/{ticket}/photos/{attachment}` | `tickets.photos.destroy` | `custom.auth`, no CSRF | `web.php:230` |
| DELETE | `/api/tickets/{ticket}/photos/{attachment}` | `api.tickets.photos.destroy` | `custom.auth` | `api.php:94` |

**Dependencies:** `UploadPhotoRequest`, `TicketAttachmentResource`. Allowed MIME types: JPEG, PNG, GIF, WebP.

- **Method:** `store(UploadPhotoRequest $request, Ticket $ticket): JsonResponse`
  - **What it does:** Uploads and stores a new photo, then registers it as a `TicketAttachment`.
  - **When it runs / how it works:** `authorize('attachPhoto', $ticket)`; reads the `photo` file; validates the **real** MIME type (via `getMimeType()`) against the allowed set — returns **422** if not allowed; derives the extension from the real MIME (never the client name); stores as a UUID filename in `ticket_photos/` on the `public` disk; creates the `TicketAttachment` record (ticket, user, original name, safe filename, path, disk, extension, mime, size); returns **201** `{ message, attachment }`.

- **Method:** `index(Request $request, Ticket $ticket): JsonResponse`
  - **What it does:** Lists all attachments on a ticket.
  - **When it runs / how it works:** `authorize('view', $ticket)`; `$ticket->loadMissing('attachments')`; returns `{ attachments }`.

- **Method:** `destroy(Request $request, Ticket $ticket, TicketAttachment $attachment): JsonResponse`
  - **What it does:** Removes an attachment from a ticket (physical file + DB record).
  - **When it runs / how it works:** `authorize('deletePhoto', $ticket)`; verifies `attachment->ticket_id === $ticket->id` else **404**; deletes the physical file from the `public` disk if it exists; `$attachment->delete()`; returns success message.

---

# 3. Rooms & Equipment

---

### `RoomController`

- **File:** `app/Http/Controllers/RoomController.php`
- **What it is:** Manages rooms (spaces/locations) where equipment is installed — list, create, update, and deactivate.

**Its routes:**

| Method | URI | Route name | Middleware | File |
|---|---|---|---|---|
| GET | `/api/rooms` | `rooms.index` | `custom.auth` | `web.php:196` |
| GET | `/admin/rooms` | `admin.rooms.index` / `api.admin.rooms.index` | `custom.auth` + `role:admin` | `web.php:360`, `api.php:153` |
| POST | `/api/rooms` | `rooms.store` | `custom.auth`, no CSRF | `web.php:197` |
| POST | `/admin/rooms` | `admin.rooms.store` / `api.admin.rooms.store` | `custom.auth` + `role:admin` | `web.php:361`, `api.php:154` |
| PUT | `/api/rooms/{room}` | `rooms.update` | `custom.auth`, no CSRF | `web.php:200` |
| PATCH | `/api/rooms/{room}` | `rooms.update-patch` | `custom.auth`, no CSRF | `web.php:203` |
| PATCH | `/admin/rooms/{room}` | `admin.rooms.update` / `api.admin.rooms.update` | `custom.auth` + `role:admin` | `web.php:362`, `api.php:155` |
| PATCH | `/admin/rooms/{room}/inactive` | `admin.rooms.inactivate` / `api.admin.rooms.inactivate` | `custom.auth` + `role:admin` | `web.php:363`, `api.php:156` |

> Note: `GET /api/rooms` and `POST/PUT/PATCH /api/rooms[/{id}]` (the `RoomController` lines in the `web.php` "Rooms API" block, `web.php:196-205`) are declared **outside** the `role:admin` group, so they are gated only by `custom.auth`. The `/admin/rooms` equivalents additionally require `role:admin`.

**Dependencies:** `RoomRepositoryInterface`, `CreateRoomAction`, `UpdateRoomAction`, `StoreRoomRequest`, `UpdateRoomRequest`, `RoomResource`.

- **Method:** `indexRoom(Request $request): JsonResponse`
  - **What it does:** Lists all rooms with an equipment count per room.
  - **When it runs / how it works:** `authorize('viewAny', Room::class)`; `roomRepository->getAll(withCounts: ['equipments'])`; returns `{ rooms, meta }` with pagination.
  - **Authorization:** routed behind `custom.auth` (for `/api/rooms`) or `role:admin` (for `/admin/rooms`).

- **Method:** `storeRoom(StoreRoomRequest $request): JsonResponse`
  - **What it does:** Creates a new room.
  - **When it runs / how it works:** `authorize('create', Room::class)`; `CreateRoomAction::execute(StoreRoomData::fromRequest(...))`; returns **201** `{ message, room }`.

- **Method:** `updateRoom(UpdateRoomRequest $request, Room $room): JsonResponse`
  - **What it does:** Updates an existing room (mapped to both PUT and PATCH).
  - **When it runs / how it works:** `authorize('update', $room)`; `UpdateRoomAction::execute($room, UpdateRoomData::fromRequest(...))`; returns `{ message, room }`.

- **Method:** `inactivateRoom(Request $request, Room $room): JsonResponse`
  - **What it does:** Deactivates a room (soft "delete").
  - **When it runs / how it works:** `authorize('update', $room)`; `roomRepository->inactivate($room)`; returns success message.

---

### `AdminEquipmentController`

- **File:** `app/Http/Controllers/AdminEquipmentController.php`
- **What it is:** Lets administrators manage the equipment catalogue — listing, creating, updating, and deleting equipment records.

**Its routes:** All require `custom.auth` + `role:admin`.

| Method | URI | Route name | File |
|---|---|---|---|
| GET | `/admin/equipment` | `admin.equipment.index` / `api.admin.equipment.index` | `web.php:344`, `api.php:147` |
| POST | `/admin/equipment` | `admin.equipment.store` / `api.admin.equipment.store` | `web.php:345`, `api.php:148` |
| PATCH | `/admin/equipment/{equipment}` | `admin.equipment.update` / `api.admin.equipment.update` | `web.php:346`, `api.php:149` |
| DELETE | `/admin/equipment/{equipment}` | `admin.equipment.destroy` / `api.admin.equipment.destroy` | `web.php:347`, `api.php:150` |

**Dependencies:** `EquipmentRepositoryInterface`, `CreateEquipmentAction`, `UpdateEquipmentAction`, `StoreEquipmentRequest`, `UpdateEquipmentRequest`, `EquipmentResource`.

- **Method:** `index(Request $request): JsonResponse`
  - **What it does:** Lists equipment with optional filters.
  - **When it runs / how it works:** `authorize('viewAny', Equipment::class)`; reads `q` (search), `status`, `category`; `equipmentRepository->getAll(['room','category'], $search, $status, $category)`; returns `{ equipments }`.

- **Method:** `store(StoreEquipmentRequest $request): JsonResponse`
  - **What it does:** Creates a new equipment record.
  - **When it runs / how it works:** `authorize('create', Equipment::class)`; `CreateEquipmentAction::execute(StoreEquipmentData::fromRequest(...))`; loads `room`; returns **201** `{ message, equipment }`.

- **Method:** `update(UpdateEquipmentRequest $request, Equipment $equipment): JsonResponse`
  - **What it does:** Updates an existing equipment record.
  - **When it runs / how it works:** `authorize('update', $equipment)`; `UpdateEquipmentAction::execute(...)`; loads `room`; returns `{ message, equipment }`.

- **Method:** `destroy(Request $request, Equipment $equipment): JsonResponse`
  - **What it does:** Deletes an equipment record.
  - **When it runs / how it works:** `authorize('delete', $equipment)`; `equipmentRepository->delete($equipment)`; returns success message.

---

# 4. Parts & Stock Management

---

### `PartController`

- **File:** `app/Http/Controllers/PartController.php`
- **What it is:** Manages the spare parts catalogue — listing, viewing, creating (with initial stock movement), updating, and soft-deleting parts. Documented with OpenAPI.

**Its routes:**

| Method | URI | Route name | Middleware | File |
|---|---|---|---|---|
| GET | `/stock/parts` | `stock.parts.index` / `api.stock.parts.index` | `custom.auth` + `role:admin,technician` | `web.php:276`, `api.php:119` |
| GET | `/stock/parts/{part}` | `stock.parts.show` / `api.stock.parts.show` | `custom.auth` + `role:admin,technician` | `web.php:277`, `api.php:120` |
| POST | `/admin/parts` | `admin.stock.parts.store` / `api.admin.stock.parts.store` | `custom.auth` + `role:admin` | `web.php:372`, `api.php:166` |
| PATCH | `/admin/parts/{part}` | `admin.stock.parts.update` / `api.admin.stock.parts.update` | `custom.auth` + `role:admin` | `web.php:373`, `api.php:167` |
| DELETE | `/admin/parts/{part}` | `admin.stock.parts.destroy` / `api.admin.stock.parts.destroy` | `custom.auth` + `role:admin` | `web.php:374`, `api.php:168` |

**Dependencies:** `PartService`, `CreatePartAction`, `UpdatePartAction`, `StorePartRequest`, `UpdatePartRequest`, `PartResource`.

- **Method:** `index(Request $request): JsonResponse`
  - **What it does:** Paginated listing of parts, filterable by search (`q`), `status` (low_stock / out_of_stock / healthy), and `category_id`, with a `per_page` option (default 15).
  - **When it runs / how it works:** `authorize('viewAny', Part::class)`; `PartService::listPaginated(...)`; returns `{ parts, pagination }`.

- **Method:** `show(Part $part): JsonResponse`
  - **What it does:** Shows a single part with category, VAT rate, and suppliers loaded.
  - **When it runs / how it works:** `authorize('view', $part)`; returns `{ part: PartResource($part->load(['category','taxRate','suppliers'])) }`.

- **Method:** `store(StorePartRequest $request): JsonResponse`
  - **What it does:** Creates a new part; the action also records the initial stock as an inward movement.
  - **When it runs / how it works:** `authorize('create', Part::class)`; `CreatePartAction::execute(StorePartData::fromRequest(...))`; `Cache::forget('stock_dashboard_summary')` (invalidates the dashboard cache); returns **201** `{ message, part }`.

- **Method:** `update(UpdatePartRequest $request, Part $part): JsonResponse`
  - **What it does:** Updates a part.
  - **When it runs / how it works:** `authorize('update', $part)`; `UpdatePartAction::execute($part, UpdatePartData::fromRequest(...))`; forgets the dashboard cache; returns `{ message, part }`.

- **Method:** `destroy(Part $part): JsonResponse`
  - **What it does:** Soft-deletes a part.
  - **When it runs / how it works:** `authorize('delete', $part)`; `$part->delete()`; forgets the dashboard cache; returns success message.

---

### `PartCategoryController`

- **File:** `app/Http/Controllers/PartCategoryController.php`
- **What it is:** Manages the categories used to group spare parts (e.g., "Electrical", "Mechanical"). "Deletion" deactivates (`active: false`) so existing parts aren't orphaned.

**Its routes:** All require `custom.auth` + `role:admin`.

| Method | URI | Route name | File |
|---|---|---|---|
| GET | `/admin/part-categories` | `api.admin.stock.categories.index` | `api.php:179` (web: no index route mapped) |
| POST | `/admin/part-categories` | `admin.stock.categories.store` / `api.admin.stock.categories.store` | `web.php:384`, `api.php:180` |
| PATCH | `/admin/part-categories/{category}` | `admin.stock.categories.update` / `api.admin.stock.categories.update` | `web.php:385`, `api.php:181` |
| DELETE | `/admin/part-categories/{category}` | `admin.stock.categories.destroy` / `api.admin.stock.categories.destroy` | `web.php:386`, `api.php:182` |

> **Verify:** The `index()` method is only wired in `api.php:179` (`/admin/part-categories` GET). There is **no** `index` route in `web.php` for the part-category list (the web UI list is served by `StockUiController::categories` instead).

**Dependencies:** `PartCategoryActions`, `StorePartCategoryRequest`, `UpdatePartCategoryRequest`, `PartCategoryResource`.

- **Method:** `index(): JsonResponse`
  - **What it does:** Lists all part categories sorted by name.
  - **When it runs / how it works:** `authorize('viewAny', PartCategory::class)`; `PartCategory::orderBy('name')->get()`; returns `{ categories }`.

- **Method:** `store(StorePartCategoryRequest $request): JsonResponse`
  - **What it does:** Creates a new category.
  - **When it runs / how it works:** `authorize('create', PartCategory::class)`; `partCategoryActions->create(name, active)`; returns **201** `{ message, category }`.

- **Method:** `update(UpdatePartCategoryRequest $request, PartCategory $category): JsonResponse`
  - **What it does:** Updates a category (name and/or active).
  - **When it runs / how it works:** `authorize('update', $category)`; `partCategoryActions->update(...)`; returns `{ message, category }`.

- **Method:** `destroy(PartCategory $category): JsonResponse`
  - **What it does:** Deactivates a category.
  - **When it runs / how it works:** `authorize('delete', $category)`; `$category->update(['active' => false])`; returns success message.

---

### `StockMovementController`

- **File:** `app/Http/Controllers/StockMovementController.php`
- **What it is:** Records every movement of parts in or out of stock — purchases, consumption, adjustments, and returns — and atomically updates each part's stock quantity.

**Its routes:** Both require `custom.auth` + `role:admin,technician`.

| Method | URI | Route name | File |
|---|---|---|---|
| GET | `/stock/movements` | `stock.movements.index` / `api.stock.movements.index` | `web.php:280`, `api.php:123` |
| POST | `/stock/movements` | `stock.movements.store` / `api.stock.movements.store` | `web.php:281`, `api.php:124` |

**Dependencies:** `StockMovementService`, `StoreStockMovementRequest`, `StockMovementResource`, `StockMovementTypeEnum`.

- **Method:** `index(Request $request): JsonResponse`
  - **What it does:** Paginated listing of movements, filterable by `part_id`, `movement_type`, and `ticket_id`.
  - **When it runs / how it works:** `authorize('viewAny', StockMovement::class)`; builds a `StockMovement` query eager-loading `part.taxRate` and `user`, applies the filters if present, orders `latest()`, paginates 20; returns `{ movements, pagination }`.

- **Method:** `store(StoreStockMovementRequest $request): JsonResponse`
  - **What it does:** Registers a stock movement and atomically updates the part's stock.
  - **When it runs / how it works:** `authorize('create', StockMovement::class)`; `Part::findOrFail($part_id)`; normalises `movement_type` via `StockMovementTypeEnum::normalize` (defaulting to `Adjust`); calls `StockMovementService::record(...)` which atomically updates the stock quantity. If stock would go negative, an `InvalidArgumentException` is caught and returned as **422** `{ message, errors }`; any other `Throwable` is logged and returned as **500**. On success forgets the dashboard cache and returns **201** `{ message, movement }`.

---

### `StockDashboardController`

- **File:** `app/Http/Controllers/StockDashboardController.php`
- **What it is:** Provides the data that powers the stock dashboard — summary stats, top consumed parts, slow-moving parts, cost breakdowns, and runout forecasts. All behind `viewAny` on `Part`.

**Its routes:** All require `custom.auth` + `role:admin,technician`.

| Method | URI | Route name | File |
|---|---|---|---|
| GET | `/stock/dashboard/summary` | `stock.dashboard.summary` / `api.stock.dashboard.summary` | `web.php:284`, `api.php:125` |
| GET | `/stock/dashboard/top-consumed` | `stock.dashboard.top-consumed` / `api.stock.dashboard.top-consumed` | `web.php:285`, `api.php:126` |
| GET | `/stock/dashboard/slow-moving` | `stock.dashboard.slow-moving` / `api.stock.dashboard.slow-moving` | `web.php:286`, `api.php:127` |
| GET | `/stock/dashboard/runout-forecast` | `stock.dashboard.runout-forecast` / `api.stock.dashboard.runout-forecast` | `web.php:287`, `api.php:128` |
| GET | `/stock/dashboard/cost-by-equipment` | `stock.dashboard.cost-by-equipment` / `api.stock.dashboard.cost-by-equipment` | `web.php:288`, `api.php:129` |
| GET | `/stock/dashboard/cost-by-ticket` | `stock.dashboard.cost-by-ticket` / `api.stock.dashboard.cost-by-ticket` | `web.php:289`, `api.php:130` |

**Dependencies:** `StockDashboardService`, `LowStockAlertService`.

- **Method:** `summary(): JsonResponse`
  - **What it does:** Returns total stock value, total parts, low-stock count, and the list of parts needing attention.
  - **When it runs / how it works:** `authorize('viewAny', Part::class)`; returns a `Cache::remember('stock_dashboard_summary', 60)` array with the four metrics.

- **Method:** `topConsumed(Request $request): JsonResponse`
  - **What it does:** Returns the most consumed parts within an optional date range.
  - **When it runs / how it works:** `authorize('viewAny', Part::class)`; `dashboardService->topConsumed(from, to, limit ?? 10)`; returns `{ items }`.

- **Method:** `slowMoving(Request $request): JsonResponse`
  - **What it does:** Returns active parts with no movements for `inactive_days` (default 90), sorted by current stock.
  - **When it runs / how it works:** `authorize('viewAny', Part::class)`; `dashboardService->slowMovingParts(inactiveDays ?? 90, limit ?? 20)`; returns `{ items }`.

- **Method:** `costByEquipment(Request $request): JsonResponse`
  - **What it does:** Returns part costs grouped by equipment for an optional date range.
  - **When it runs / how it works:** `authorize('viewAny', Part::class)`; `dashboardService->costByEquipment(from, to)`; returns `{ items }`.

- **Method:** `costByTicket(Request $request): JsonResponse`
  - **What it does:** Returns part costs grouped by ticket/intervention for an optional date range.
  - **When it runs / how it works:** `authorize('viewAny', Part::class)`; `dashboardService->costByTicket(from, to)`; returns `{ items }`.

- **Method:** `runoutForecast(Request $request): JsonResponse`
  - **What it does:** Estimates how many months of stock remain based on average monthly consumption.
  - **When it runs / how it works:** `authorize('viewAny', Part::class)`; reads `months` (default 3); returns a `Cache::remember("stock_dashboard_runout:{months}", 60)` payload `{ items }`.

---

### `StockReportController`

- **File:** `app/Http/Controllers/StockReportController.php`
- **What it is:** Exports stock data as CSV files (streamed) and dispatches PDF report generation jobs. Requires `viewAny` on `Part`.

**Its routes:** All require `custom.auth` + `role:admin`.

| Method | URI | Route name | File |
|---|---|---|---|
| GET | `/stock/reports/low-stock.csv` | `stock.reports.low-stock.csv` / `api.stock.reports.low-stock.csv` | `web.php:395`, `api.php:190` |
| GET | `/stock/reports/inventory.csv` | `stock.reports.inventory.csv` / `api.stock.reports.inventory.csv` | `web.php:396`, `api.php:191` |
| GET | `/stock/reports/costs-by-equipment.pdf` | `stock.reports.costs-by-equipment.pdf` / `api.stock.reports.costs-by-equipment.pdf` | `web.php:397`, `api.php:192` |

**Dependencies:** `LocalizationService`, `ExportStockCostsPdfJob`.

- **Method:** `lowStockCsv(): StreamedResponse`
  - **What it does:** Streams a CSV of all parts below minimum stock levels.
  - **When it runs / how it works:** `authorize('viewAny', Part::class)`; `response()->streamDownload` writes a UTF-8 BOM and a header row (SKU, name, brand, category, current/min stock, location) with `;` as the separator, then lazily iterates `Part::lowStock()->orderBy('name')` streaming each row to `php://output`. Memory-efficient.

- **Method:** `inventoryCsv(): StreamedResponse`
  - **What it does:** Streams a full inventory export including cost price, VAT percentage, price with VAT, stock value, and location.
  - **When it runs / how it works:** `authorize('viewAny', Part::class)`; streams a BOM + header (SKU, name, brand, category, stock, cost, tax, price with VAT, stock value, location), iterating all parts lazily and formatting decimals via `LocalizationService` and model helpers `priceWithVat()` / `stockValue()`.

- **Method:** `costsByEquipmentPdf(Request $request): JsonResponse`
  - **What it does:** Dispatches asynchronous generation of the cost-by-equipment PDF report.
  - **When it runs / how it works:** `authorize('viewAny', Part::class)`; `ExportStockCostsPdfJob::dispatch(userId, from, to)`; returns `{ message }` ("PDF export processing — you'll be notified when ready").

---

### `StockUiController`

- **File:** `app/Http/Controllers/StockUiController.php`
- **What it is:** Serves all the HTML (Blade) pages for the stock management section of the web UI. Purely view-rendering — no business logic.

**Its routes:**

| Method | URI | Route name | Middleware | File |
|---|---|---|---|---|
| GET | `/ui/stock` | `ui.stock.dashboard` | `custom.auth` | `web.php:164` |
| GET | `/ui/stock/parts` | `ui.stock.parts` | `custom.auth` | `web.php:165` |
| GET | `/ui/stock/parts/create` | `ui.stock.parts.create` | `custom.auth` + `role:admin` | `web.php:166` |
| GET | `/ui/stock/parts/{part}` | `ui.stock.parts.show` | `custom.auth` | `web.php:169` |
| GET | `/ui/stock/parts/{part}/edit` | `ui.stock.parts.edit` | `custom.auth` + `role:admin` | `web.php:172` |
| GET | `/ui/stock/suppliers` | `ui.stock.suppliers` | `custom.auth` | `web.php:176` |
| GET | `/ui/stock/suppliers/create` | `ui.stock.suppliers.create` | `custom.auth` + `role:admin` | `web.php:177` |
| GET | `/ui/stock/suppliers/{supplier}/edit` | `ui.stock.suppliers.edit` | `custom.auth` + `role:admin` | `web.php:180` |
| GET | `/ui/stock/movements` | `ui.stock.movements` | `custom.auth` | `web.php:184` |
| GET | `/ui/stock/tax-rates` | `ui.stock.tax-rates` | `custom.auth` + `role:admin` | `web.php:185` |
| GET | `/ui/stock/categories` | `ui.stock.categories` | `custom.auth` + `role:admin` | `web.php:188` |
| GET | `/ui/stock/plans` | `ui.stock.plans` | `custom.auth` + `role:admin` | `web.php:191` |

- **Method:** `dashboard(Request $request): View` — renders `ui.stock.dashboard` with the authenticated user.
- **Method:** `parts(Request $request): View` — renders `ui.stock.parts` with the user and active part categories sorted by name.
- **Method:** `partCreate(Request $request): View` — renders `ui.stock.parts.create` with user, active categories, and active tax rates (admin-only route).
- **Method:** `partShow(Request $request, Part $part): View` — renders `ui.stock.parts.show` with the part and its category, tax rate, and suppliers loaded.
- **Method:** `partEdit(Request $request, Part $part): View` — renders `ui.stock.parts.edit` with the loaded part, active categories, and tax rates (admin-only route).
- **Method:** `suppliers(Request $request): View` — renders `ui.stock.suppliers` with the user.
- **Method:** `supplierCreate(Request $request): View` — renders `ui.stock.suppliers.create` with the user (admin-only route).
- **Method:** `supplierEdit(Request $request, Supplier $supplier): View` — renders `ui.stock.suppliers.edit` with the supplier (admin-only route).
- **Method:** `movements(Request $request): View` — renders `ui.stock.movements` with the user and active parts sorted by name.
- **Method:** `taxRates(Request $request): View` — renders `ui.stock.tax-rates` with the user and all tax rates sorted by percentage (admin-only route).
- **Method:** `categories(Request $request): View` — renders `ui.stock.categories` with the user and all categories sorted by name (admin-only route).
- **Method:** `plans(Request $request): View` — renders `ui.stock.plans` with the user, active equipment, and active parts (admin-only route).

---

### `SupplierController`

- **File:** `app/Http/Controllers/SupplierController.php`
- **What it is:** Manages the supplier directory — companies that provide spare parts — with search, CRUD, and soft-delete.

**Its routes:**

| Method | URI | Route name | Middleware | File |
|---|---|---|---|---|
| GET | `/stock/suppliers` | `stock.suppliers.index` / `api.stock.suppliers.index` | `custom.auth` + `role:admin,technician` | `web.php:278`, `api.php:121` |
| GET | `/stock/suppliers/{supplier}` | `stock.suppliers.show` / `api.stock.suppliers.show` | `custom.auth` + `role:admin,technician` | `web.php:279`, `api.php:122` |
| POST | `/admin/suppliers` | `admin.stock.suppliers.store` / `api.admin.stock.suppliers.store` | `custom.auth` + `role:admin` | `web.php:376`, `api.php:170` |
| PATCH | `/admin/suppliers/{supplier}` | `admin.stock.suppliers.update` / `api.admin.stock.suppliers.update` | `custom.auth` + `role:admin` | `web.php:377`, `api.php:171` |
| DELETE | `/admin/suppliers/{supplier}` | `admin.stock.suppliers.destroy` / `api.admin.stock.suppliers.destroy` | `custom.auth` + `role:admin` | `web.php:378`, `api.php:172` |

**Dependencies:** `CreateSupplierAction`, `UpdateSupplierAction`, `StoreSupplierRequest`, `UpdateSupplierRequest`, `SupplierResource`.

- **Method:** `index(Request $request): JsonResponse`
  - **What it does:** Paginated listing of suppliers, searchable by name or NIF.
  - **When it runs / how it works:** `authorize('viewAny', Supplier::class)`; if `q` is present, filters through `App\Support\SqlPattern::whereContains()` (`LIKE ? ESCAPE '!'` on `name` and `nif`, value bound as a parameter); orders by name, paginates 15; returns `{ suppliers, pagination }`.

- **Method:** `show(Supplier $supplier): JsonResponse`
  - **What it does:** Shows a single supplier with its associated parts (and their tax rates).
  - **When it runs / how it works:** `authorize('view', $supplier)`; returns `{ supplier: SupplierResource($supplier->load('parts.taxRate')) }`.

- **Method:** `store(StoreSupplierRequest $request): JsonResponse`
  - **What it does:** Creates a new supplier.
  - **When it runs / how it works:** `authorize('create', Supplier::class)`; `CreateSupplierAction::execute(StoreSupplierData::fromRequest(...))`; returns **201** `{ message, supplier }`.

- **Method:** `update(UpdateSupplierRequest $request, Supplier $supplier): JsonResponse`
  - **What it does:** Updates a supplier.
  - **When it runs / how it works:** `authorize('update', $supplier)`; `UpdateSupplierAction::execute(...)`; returns `{ message, supplier }`.

- **Method:** `destroy(Supplier $supplier): JsonResponse`
  - **What it does:** Soft-deletes a supplier.
  - **When it runs / how it works:** `authorize('delete', $supplier)`; `$supplier->delete()`; returns success message.

---

### `TaxRateController`

- **File:** `app/Http/Controllers/TaxRateController.php`
- **What it is:** Manages the VAT (tax) rates applied to spare parts. "Deletion" deactivates (`active: false`).

**Its routes:** All require `custom.auth` + `role:admin`.

| Method | URI | Route name | File |
|---|---|---|---|
| GET | `/admin/tax-rates` | `api.admin.stock.tax-rates.index` | `api.php:174` (web: no index route mapped) |
| POST | `/admin/tax-rates` | `admin.stock.tax-rates.store` / `api.admin.stock.tax-rates.store` | `web.php:380`, `api.php:175` |
| PATCH | `/admin/tax-rates/{taxRate}` | `admin.stock.tax-rates.update` / `api.admin.stock.tax-rates.update` | `web.php:381`, `api.php:176` |
| DELETE | `/admin/tax-rates/{taxRate}` | `admin.stock.tax-rates.destroy` / `api.admin.stock.tax-rates.destroy` | `web.php:382`, `api.php:177` |

> **Verify:** The `index()` method is only wired in `api.php:174` (`/admin/tax-rates` GET). There is **no** `index` route in `web.php` for the tax-rate list (the web UI list is served by `StockUiController::taxRates`).

**Dependencies:** `TaxRateActions`, `StoreTaxRateRequest`, `UpdateTaxRateRequest`, `TaxRateResource`.

- **Method:** `index(): JsonResponse`
  - **What it does:** Lists all VAT rates sorted by percentage.
  - **When it runs / how it works:** `authorize('viewAny', TaxRate::class)`; `TaxRate::orderBy('percent')->get()`; returns `{ tax_rates }`.

- **Method:** `store(StoreTaxRateRequest $request): JsonResponse`
  - **What it does:** Creates a new VAT rate.
  - **When it runs / how it works:** `authorize('create', TaxRate::class)`; `taxRateActions->create(name, percent, isDefault, active)`; returns **201** `{ message, tax_rate }`.

- **Method:** `update(UpdateTaxRateRequest $request, TaxRate $taxRate): JsonResponse`
  - **What it does:** Updates a VAT rate.
  - **When it runs / how it works:** `authorize('update', $taxRate)`; `taxRateActions->update(...)`; returns `{ message, tax_rate }`.

- **Method:** `destroy(TaxRate $taxRate): JsonResponse`
  - **What it does:** Deactivates a VAT rate.
  - **When it runs / how it works:** `authorize('delete', $taxRate)`; `$taxRate->update(['active' => false])`; returns success message.

---

# 5. Maintenance & Calendar

---

### `MaintenancePlanController`

- **File:** `app/Http/Controllers/MaintenancePlanController.php`
- **What it is:** Manages preventive maintenance plans — recurring schedules specifying which parts and intervals apply to each piece of equipment.

**Its routes:** All require `custom.auth` + `role:admin`.

| Method | URI | Route name | File |
|---|---|---|---|
| GET | `/admin/maintenance-plans` | `admin.stock.plans.index` / `api.admin.stock.plans.index` | `web.php:388`, `api.php:184` |
| GET | `/admin/maintenance-plans/{plan}` | `admin.stock.plans.show` | `web.php:392` |
| POST | `/admin/maintenance-plans` | `admin.stock.plans.store` / `api.admin.stock.plans.store` | `web.php:389`, `api.php:185` |
| PATCH | `/admin/maintenance-plans/{plan}` | `admin.stock.plans.update` / `api.admin.stock.plans.update` | `web.php:390`, `api.php:186` |
| DELETE | `/admin/maintenance-plans/{plan}` | `admin.stock.plans.destroy` / `api.admin.stock.plans.destroy` | `web.php:391`, `api.php:187` |

> **Note:** `/admin/maintenance-plans/{plan}` (GET `show`) is declared **after** the other `/admin/maintenance-plans` routes (`web.php:392`) so the earlier fixed routes match first.

**Dependencies:** `MaintenancePlanActions`, `StoreMaintenancePlanRequest`, `UpdateMaintenancePlanRequest`, `MaintenancePlanResource`, `MaintenancePlanIntervalTypeEnum`.

- **Method:** `index(Request $request): JsonResponse`
  - **What it does:** Paginated listing of preventive maintenance plans, filterable by `equipment_id`.
  - **When it runs / how it works:** `authorize('viewAny', MaintenancePlan::class)`; queries with `equipment` eager-loaded, applies the equipment filter, orders by name, paginates 15; returns `{ plans, pagination }`.

- **Method:** `show(MaintenancePlan $plan): JsonResponse`
  - **What it does:** Shows a plan with its equipment and associated parts.
  - **When it runs / how it works:** `authorize('view', $plan)`; returns `{ plan: MaintenancePlanResource($plan->load(['equipment','parts'])) }`.

- **Method:** `store(StoreMaintenancePlanRequest $request): JsonResponse`
  - **What it does:** Creates a new preventive maintenance plan.
  - **When it runs / how it works:** `authorize('create', MaintenancePlan::class)`; `Equipment::findOrFail($equipment_id)`; normalises `interval_type` via `MaintenancePlanIntervalTypeEnum` (default `Days`); executes `maintenancePlanActions->create(...)` with the parts map from `partsPayload($request)`; returns **201** `{ message, plan }`.

- **Method:** `update(UpdateMaintenancePlanRequest $request, MaintenancePlan $plan): JsonResponse`
  - **What it does:** Updates an existing plan.
  - **When it runs / how it works:** `authorize('update', $plan)`; same normalisation; `maintenancePlanActions->update(...)`; returns `{ message, plan }`.

- **Method:** `destroy(MaintenancePlan $plan): JsonResponse`
  - **What it does:** Hard-deletes a plan.
  - **When it runs / how it works:** `authorize('delete', $plan)`; `$plan->delete()`; returns success message.

- **Private helper:** `partsPayload(Request $request): array` — converts the request `parts` array `[{part_id, expected_quantity}]` (also accepts `id`) into a `{part_id => quantity}` map, discarding invalid ids and clamping quantities to >= 1.

---

### `CalendarController`

- **File:** `app/Http/Controllers/CalendarController.php`
- **What it is:** Provides the maintenance calendar view and handles scheduling/rescheduling of events.

**Its routes:**

| Method | URI | Route name | Middleware | File |
|---|---|---|---|---|
| GET | `/calendar` | `calendar.view` | `custom.auth` | `web.php:255` |
| GET | `/calendar/events` | `calendar.events` | `custom.auth` | `web.php:254` |
| POST | `/calendar/maintenance` | `calendar.maintenance` | `custom.auth` + `role:admin` | `web.php:306` |
| PATCH | `/calendar/events/{ticket}` | `calendar.events.reschedule` | `custom.auth` | `web.php:256` |

**Dependencies:** `CalendarService`, `ScheduleMaintenanceAction`, `ScheduleMaintenanceRequest`, `RescheduleEventRequest`, `ScheduleMaintenanceData`, `TicketResource`.

- **Method:** `index(Request $request): View`
  - **What it does:** Renders the calendar view.
  - **When it runs / how it works:** loads the user's scheduled events via `CalendarService::getScheduledEventsForUser($user)`; returns the `calendar` Blade view with `events` and `user`.

- **Method:** `events(Request $request): JsonResponse`
  - **What it does:** Returns the user's scheduled events as JSON for AJAX-driven calendars.
  - **When it runs / how it works:** same `getScheduledEventsForUser` call; returns `{ events }`.

- **Method:** `scheduleMaintenance(ScheduleMaintenanceRequest $request): JsonResponse`
  - **What it does:** Schedules a preventive maintenance, creating a scheduled ticket (admin).
  - **When it runs / how it works:** `authorize('create', Ticket::class)`; executes `ScheduleMaintenanceAction::execute($user, ScheduleMaintenanceData::fromRequest($request))` in a try/catch — `InvalidArgumentException` returns **422**; otherwise returns **201** `{ message, ticket }`.

- **Method:** `reschedule(Ticket $ticket, RescheduleEventRequest $request): JsonResponse`
  - **What it does:** Reschedules a dragged calendar event by updating `scheduled_at` and `scheduled_end`.
  - **When it runs / how it works:** **Manual authorization** — if the user is not an admin and not the ticket's assigned technician, `abort(403)`. If `scheduled_at` is null, returns **422**. Parses the `start` (required) and `end` (optional, defaults to start + 2 hours) values, saves them, and returns `{ message, event: { id, start, end } }` in ISO-8601.

---

# 6. Communication & Activity Tracking

---

### `NotificationController`

- **File:** `app/Http/Controllers/NotificationController.php`
- **What it is:** Lets users view their notifications, mark them as read, and send test emails.

**Its routes:** All require `custom.auth`.

| Method | URI | Route name | Middleware | File |
|---|---|---|---|---|
| GET | `/notifications` | `notifications.index` / `api.notifications.index` | `custom.auth` | `web.php:116`, `api.php:204` |
| PATCH | `/notifications/{id}` | `notifications.mark-read` / `api.notifications.mark-read` | `custom.auth`, no CSRF (web) | `web.php:117`, `api.php:205` |
| POST | `/notifications/test-email` | `notifications.test-email` / `api.notifications.test-email` | `custom.auth` + `rate.limit:5,1`, no CSRF (web) | `web.php:120`, `api.php:206` |

**Dependencies:** `NotificationResource`, `SendTestEmailJob`.

- **Method:** `index(Request $request): JsonResponse`
  - **What it does:** Paginated listing of the authenticated user's notifications.
  - **When it runs / how it works:** reads `per_page` (default 50, capped at 200); `Notification::where('user_id', user->id)->latest()->paginate($perPage)`; returns the `NotificationResource` collection merged with its pagination metadata.

- **Method:** `markAsRead(Request $request, int $id): JsonResponse`
  - **What it does:** Marks one of the user's notifications as read.
  - **When it runs / how it works:** looks up `Notification::where('user_id', user->id)->find($id)` — this ensures the notification **belongs to the current user** (prevents modifying others' notifications); if not found returns **404**; otherwise sets `is_read = true`, saves, returns `{ message, notification }`.

- **Method:** `sendTestEmail(Request $request): JsonResponse`
  - **What it does:** Dispatches a test email in the background via the queue.
  - **When it runs / how it works:** `SendTestEmailJob::dispatch($user->email, $user->name)`; logs the queue action; returns `{ message }` ("test email processing via queue").

---

### `ActivityFeedController`

- **File:** `app/Http/Controllers/ActivityFeedController.php`
- **What it is:** Powers the recent-activity timeline on the dashboard, transforming recent audit entries into display-ready objects.

**Its routes:**

| Method | URI | Route name | Middleware | File |
|---|---|---|---|---|
| GET | `/api/activities` | `api.activities` | `custom.auth` | `api.php:68` |

(Only exposed via the API file.)

- **Method:** `index(): JsonResponse`
  - **What it does:** Returns the 15 most recent audit entries as a display-ready activity feed.
  - **When it runs / how it works:** queries `Audit::with('user')->latest()->take(15)->get()`; maps each entry to `{ id, title, description, time_ago, icon_bg, dot_color }` using the private localisation/colour helpers. `titleFor`/`descriptionFor` produce localized strings depending on the auditable type (Ticket/Part/Equipment/Room/User) and event (created/updated/deleted). `iconBgFor`/`dotColorFor` return Tailwind colour class strings keyed by model type (blue=tickets, emerald=parts/equipment, amber=rooms, purple=users). This endpoint deliberately exposes internal entity names and staff users, so it is gated behind `custom.auth`.
  - **Authorization:** `custom.auth` middleware only (no policy check in the controller).

---

### `AuditController`

- **File:** `app/Http/Controllers/AuditController.php`
- **What it is:** Provides a paginated, admin-only view of the system audit log.

**Its routes:**

| Method | URI | Route name | Middleware | File |
|---|---|---|---|---|
| GET | `/admin/audits` | `admin.audits.index` | `custom.auth` + `role:admin` | `web.php:333` |
| GET | `/api/admin/audits` | `api.admin.audits.index` | `custom.auth` + `role:admin` | `api.php:144` |

**Dependencies:** `AuditResource`.

- **Method:** `index(Request $request): JsonResponse`
  - **What it does:** Lists the system's audit records, paginated.
  - **When it runs / how it works:** `authorize('viewAny', Audit::class)`; reads the page size from `config('services.custom.pagination.admin_per_page', 15)`; `Audit::with('user')->latest()->paginate($perPage)`; returns `{ audits }` (collection merged with pagination metadata).

---

# 7. Content, UI & System

---

### `PageController`

- **File:** `app/Http/Controllers/PageController.php`
- **What it is:** Serves the static and utility pages — home page, login, password reset form, language switcher, and an email test route. (Consolidates former route closures so `route:cache` works.)

**Its routes:**

| Method | URI | Route name | Middleware | File |
|---|---|---|---|---|
| GET | `/` | `home` | public | `web.php:49` |
| GET | `/lang/{locale}` | `lang.switch` | public | `web.php:50` |
| GET | `/ui/login` | `ui.login` | public | `web.php:52` |
| GET | `/test-email` | `test.email` | public | `web.php:54` |
| GET | `/api/password/reset/{token}` | `api.password.reset.form` | public | `api.php:53` |

- **Method:** `home(): View` — renders the `main` Blade view (landing page). Public.
- **Method:** `switchLang(Request $request, string $locale): RedirectResponse`
  - **What it does:** Legacy language-switching route that stores the locale in the session and a permanent cookie, then redirects.
  - **When it runs / how it works:** sanitises the locale via `LocaleService::sanitize`; stores `locale` in session and a `forever` `locale` cookie; checks for an auth token cookie (`api_token` or `auth_token`) — if present, redirects to `ui.index`; otherwise redirects to `ui.login`. Public.
- **Method:** `login(): View` — renders the `ui.auth` authentication form. Public.
- **Method:** `testEmail(): string`
  - **What it does:** Sends a raw test email via the default mail transport.
  - **When it runs / how it works:** if the environment is `production` it `abort(404)` (never available in production); otherwise `Mail::raw(...)` sends a fixed test message; returns a plain string success message. Public.
- **Method:** `passwordResetForm(string $token): View` — renders the `ui.auth-reset` view with the token pre-filled. Public (API route).

---

### `UiController`

- **File:** `app/Http/Controllers/UiController.php`
- **What it is:** The main web UI controller — renders every HTML (Blade) page for the application (dashboard, tickets, equipment, rooms, users, profile, settings, analytics, audits), plus a couple of JSON helper endpoints.

**Its routes (all under `custom.auth` unless noted):**

| Method | URI | Route name | Extra middleware | File |
|---|---|---|---|---|
| GET | `/ui` | `ui.index` | — | `web.php:126` |
| GET | `/ui/profile` | `ui.profile` | — | `web.php:127` |
| GET | `/ui/tickets` | `ui.tickets` | — | `web.php:128` |
| GET | `/ui/tickets/create` | `ui.tickets.create` | `role:admin,user` | `web.php:129` |
| GET | `/ui/tickets/{id}` | `ui.tickets.show` | — | `web.php:132` |
| GET | `/ui/equipments` | `ui.equipments` | — | `web.php:133` |
| GET | `/equipments` | `equipments.list` | — | `web.php:134` |
| GET | `/dashboard/picket` | `dashboard.picket` | — | `web.php:135` |
| GET | `/ui/settings/appearance` | `ui.settings.appearance` | — | `web.php:138` |
| POST | `/ui/settings/appearance` | `ui.settings.appearance.update` | no CSRF | `web.php:139` |
| GET | `/ui/equipments/create` | `ui.equipments.create` | `role:admin` | `web.php:147` |
| GET | `/ui/equipments/{equipment}` | `ui.equipments.show` | — | `web.php:150` |
| GET | `/ui/equipments/{equipment}/edit` | `ui.equipments.edit` | `role:admin` | `web.php:153` |
| GET | `/ui/rooms` | `ui.rooms` | — | `web.php:158` |
| GET | `/ui/rooms/{room}` | `ui.rooms.show` | — | `web.php:159` |
| GET | `/ui/users` | `ui.users` | `role:admin` | `web.php:296` |
| GET | `/ui/audits` | `ui.audits` | `role:admin` | `web.php:297` |
| GET | `/ui/users/create` | `ui.users.create` | `role:admin` | `web.php:298` |
| GET | `/ui/users/{targetUser}/edit` | `ui.users.edit` | `role:admin` | `web.php:299` |
| GET | `/ui/users/{targetUser}` | `ui.users.show` | `role:admin` | `web.php:300` |
| GET | `/ui/rooms/create` | `ui.rooms.create` | `role:admin` | `web.php:301` |
| GET | `/ui/rooms/{room}/edit` | `ui.rooms.edit` | `role:admin` | `web.php:302` |
| GET | `/ui/analytics` | `ui.analytics` | `role:admin` | `web.php:303` |

**Dependencies:** `EquipmentService`, `ThemePresetService`, `TicketStatusService`, `TicketStatusEnum`, `UserRoleEnum`.

- **Method:** `index(Request $request): View` — renders `ui.index` (dashboard) with the user.
- **Method:** `tickets(Request $request): View` — renders `ui.tickets`.
- **Method:** `ticketCreate(Request $request): View` — renders `ui.ticket-create`.
- **Method:** `equipments(Request $request): View` — renders `ui.equipments`.
- **Method:** `equipmentCreate(Request $request): View` — renders `ui.equipments.create` with the user, all rooms ordered by name, and all equipment categories. (admin route)
- **Method:** `equipmentEdit(Request $request, Equipment $equipment): View` — renders `ui.equipments.edit` with the user, equipment, rooms, and categories. (admin route)
- **Method:** `equipmentDetail(Request $request, Equipment $equipment): View`
  - **What it does:** Renders the equipment detail page with its tickets and audit trail.
  - **When it runs / how it works:** eager-loads `room`, `category`, `tickets.status/user/technician`; resolves the Open and In Progress status ids via `TicketStatusService`; sorts tickets by `opened_at` desc; fetches the last 12 audits for this equipment; passes `openTicketsCount` and `inProgressTicketsCount` counts to the `ui.equipments.show` view.
- **Method:** `users(Request $request): View` — renders `ui.users`. (admin route)
- **Method:** `userCreate(Request $request): View` — renders `ui.users-create`. (admin route)
- **Method:** `userEdit(Request $request, User $targetUser): View` — renders `ui.users-edit` with the target user's profile loaded. (admin route)
- **Method:** `userDetail(Request $request, User $targetUser): View` — renders `ui.users.show` with the target user's profile loaded. (admin route)
- **Method:** `rooms(Request $request): View` — renders `ui.rooms`.
- **Method:** `roomCreate(Request $request): View` — renders `ui.rooms.create`. (admin route)
- **Method:** `roomDetail(Request $request, Room $room): View`
  - **What it does:** Renders the room detail page with its equipment, tickets, and audit trail.
  - **When it runs / how it works:** eager-loads `equipments.category`, `tickets.status/user/technician`; resolves Open/InProgress status ids; sorts tickets by `opened_at` desc, equipment by active/created_at; fetches the last 12 audits for the room; passes open/in-progress counts to `ui.rooms.show`.
- **Method:** `roomEdit(Request $request, Room $room): View` — renders `ui.rooms.edit`. (admin route)
- **Method:** `audits(Request $request): View` — renders `ui.audits`. (admin route)
- **Method:** `ticketDetail(Request $request, int $id): View` — renders `ui.ticket-detail`, passing only the ticket `id` and the user.
- **Method:** `getEquipments(Request $request): JsonResponse`
  - **What it does:** JSON helper for paginated equipment listing within the UI.
  - **When it runs / how it works:** `EquipmentService::listPaginated($request->query('q'), $request->query('status'))`; returns `{ equipments }`.
- **Method:** `getTechnicalPicket(Request $request): JsonResponse`
  - **What it does:** Returns the active "technical picket" — active technicians with their number of in-progress tickets.
  - **When it runs / how it works:** queries active users whose profile name is `Technician`, `withCount` of assigned tickets currently in `InProgress` status; maps to `[{ id, name, in_progress_tickets }]`; returns `{ picket }`.
- **Method:** `analytics(Request $request): View` — renders `ui.analytics`. (admin route)
- **Method:** `profile(Request $request): View` — renders `ui.profile`.
- **Method:** `themeAppearance(Request $request): View` — renders `ui.settings.appearance` with the user, all theme presets, and the active preset for the user's saved theme.
- **Method:** `themeAppearanceUpdate(Request $request): JsonResponse|RedirectResponse`
  - **What it does:** Saves the user's chosen theme preset (per-user).
  - **When it runs / how it works:** validates `theme` must be one of the known preset keys; `ThemePresetService::applyForUser($user, $theme)`; if the request expects JSON returns `{ ok, theme, mode }`, otherwise redirects back to `ui.settings.appearance` with a flash message.

---

### `LocaleController`

- **File:** `app/Http/Controllers/LocaleController.php`
- **What it is:** Handles language switching for the application, persisting the choice across sessions (the modern replacement for `PageController::switchLang`).

**Its routes:**

| Method | URI | Route name | Middleware | File |
|---|---|---|---|---|
| POST | `/locale` | `locale.switch` | public, no CSRF | `web.php:51` |

**Dependencies:** `LocaleService`, `PreferencesService`, `AuthUserResolver`.

- **Method:** `switch(Request $request): RedirectResponse`
  - **What it does:** Saves the user's language preference.
  - **When it runs / how it works:** validates the requested `locale` is supported via `LocaleService::isSupported` (returns back with errors if not); sanitises it; stores `locale` in the session and a `forever` cookie; resolves the user (`$request->user()` → `api` guard → `AuthUserResolver::fromRequest`); if authenticated, saves `locale` to the `users.locale` column and persists the full preference set (language + current currency/date/time/number formats) to the `user_preferences` table so `SetLocaleMiddleware` doesn't revert it on subsequent requests. Redirects back with the cookie.

---

### `ThemeController`

- **File:** `app/Http/Controllers/ThemeController.php`
- **What it is:** Serves the dynamic CSS stylesheet for the current user's theme and handles theme switching.

**Its routes:**

| Method | URI | Route name | Middleware | File |
|---|---|---|---|---|
| GET | `/theme/custom.css` | `theme.custom` | public | `web.php:53` |
| POST | `/theme/switch` | `theme.switch` | `custom.auth`, no CSRF | `web.php:142` |

**Dependencies:** `ThemePresetService`.

- **Method:** `customCss(Request $request): Response`
  - **What it does:** Generates the current user's theme CSS with custom properties.
  - **When it runs / how it works:** determines the effective theme id via `effectiveThemeId(resolveCssUser($request))`; gets the preset values from `ThemePresetService::valuesFor`; builds CSS via `buildCss` (which derives `--color-primary-light`, `--color-primary-hover`, and `--color-on-primary` from the primary colour using WCAG contrast logic); computes an ETag from the CSS. If the `If-None-Match` header matches, returns **304**; otherwise returns **200** with `Content-Type: text/css; charset=utf-8` and the ETag.
  - **Authorization:** none (public route — the guest/auth pages link it), but the user is resolved from tokens/cookies/session without redirect via `resolveCssUser`.
- **Method:** `switchTheme(Request $request): JsonResponse`
  - **What it does:** Applies a theme preset (light/dark of the same family) and saves it as the user's preference.
  - **When it runs / how it works:** validates `theme` is a known preset key; `ThemePresetService::applyForUser($request->user(), $theme)`; returns `{ ok, theme, mode, values }`.
- **Static method:** `cacheBuster(?string $themeId = null): string`
  - **What it does:** Computes a cache-buster hash for the dynamic CSS link based on the effective theme id and its serialized values. Called from Blade templates; not a route.
- **Private helpers:** `buildCss`, `readableOnColor`, `luminance`, `hexToRgb`, `darkenHex` — generate WCAG-compliant, readable theme CSS from the preset values.

---

### `SystemSettingsController`

- **File:** `app/Http/Controllers/SystemSettingsController.php`
- **What it is:** Lets administrators view and modify system-level configuration settings (e.g., budget thresholds, pagination defaults).

**Its routes:** Both require `custom.auth` + `role:admin`.

| Method | URI | Route name | File |
|---|---|---|---|
| GET | `/ui/settings/system` | `ui.settings.system` | `web.php:304` |
| POST | `/ui/settings/system` | `ui.settings.system.update` | `web.php:305` |

(No `api.php` equivalent.)

**Dependencies:** `SystemSettingsService`.

- **Method:** `index(Request $request): View`
  - **What it does:** Renders the system settings page.
  - **When it runs / how it works:** returns the `ui.settings.system` view with the user and the setting `groups()` and current `values()` from the service.

- **Method:** `update(Request $request): JsonResponse`
  - **What it does:** Saves setting overrides or resets an entire group.
  - **When it runs / how it works:** validates either `updates` (map) or `reset` (group id); if `reset` is present, calls `SystemSettingsService::reset($group)` and returns `{ ok, reset, values }`; otherwise flattens the `updates` array with `Arr::dot`, calls `SystemSettingsService::update($updates)`, and returns `{ ok, values }`.

---

### `QrCodeController`

- **File:** `app/Http/Controllers/QrCodeController.php`
- **What it is:** Generates QR codes for equipment that link to the public damage-report form.

**Its routes:** All require `custom.auth` + `role:admin`.

| Method | URI | Route name | File |
|---|---|---|---|
| GET | `/ui/equipments/{equipment}/qr` | `ui.equipments.qr` | `web.php:350` |
| GET | `/ui/equipments/{equipment}/qr/download` | `ui.equipments.qr.download` | `web.php:353` |
| GET | `/ui/equipments/qr/export` | `ui.equipments.qr.export` | `web.php:356` |

(No `api.php` equivalent.)

**Dependencies:** `QrCodeService`, `ExportEquipmentQrPdfJob`.

- **Method:** `show(Request $request, Equipment $equipment): View`
  - **What it does:** Renders a print-ready page with the equipment's QR code.
  - **When it runs / how it works:** returns the `ui.equipments.qr` view with the user, equipment, a PNG data URI (`QrCodeService::pngDataUri`), and the ticket URL (`QrCodeService::urlFor`).

- **Method:** `download(Equipment $equipment): Response`
  - **What it does:** Downloads the equipment's QR code as a PNG file.
  - **When it runs / how it works:** builds a filename `qr-{asset_tag or id}.png` and returns the PNG bytes with `Content-Type: image/png` and a `Content-Disposition: attachment` header.

- **Method:** `exportPdf(Request $request): JsonResponse`
  - **What it does:** Dispatches background generation of a PDF with QR codes for all active equipment.
  - **When it runs / how it works:** `ExportEquipmentQrPdfJob::dispatch($user->id)`; returns `{ message }` ("PDF export processing — notify when ready").

---

### `AnalyticsController`

- **File:** `app/Http/Controllers/AnalyticsController.php`
- **What it is:** Provides analytics data for the reporting dashboard and handles export to CSV, PDF, and Excel (via background jobs).

**Its routes:** All require `custom.auth` + `role:admin`.

| Method | URI | Route name | File |
|---|---|---|---|
| GET | `/analytics` | `analytics.stats` | `web.php:316` |
| GET | `/api/analytics/stats` | `api.analytics.stats` | `api.php:197` |
| GET | `/analytics/export/csv` | `analytics.export.csv` / `api.analytics.export.csv` | `web.php:317`, `api.php:198` |
| GET | `/analytics/export/pdf` | `analytics.export.pdf` / `api.analytics.export.pdf` | `web.php:318`, `api.php:199` |
| GET | `/analytics/export/excel` | `analytics.export.excel` / `api.analytics.export.excel` | `web.php:319`, `api.php:200` |

**Dependencies:** `AnalyticsService`, `ExportCsvJob`, `ExportPdfJob`, `ExportExcelJob`.

- **Method:** `stats(Request $request): JsonResponse`
  - **What it does:** Returns the aggregated analytical payload for the dashboard.
  - **When it runs / how it works:** `authorize('viewAnalytics', Ticket::class)`; returns `AnalyticsService::getDashboardPayload()`.

- **Method:** `exportCsv(Request $request): JsonResponse`
  - **What it does:** Dispatches asynchronous CSV export.
  - **When it runs / how it works:** `authorize('exportAnalytics', Ticket::class)`; `ExportCsvJob::dispatch($user->id)`; returns `{ message }`.
- **Method:** `exportPdf(Request $request): JsonResponse` — same pattern, dispatches `ExportPdfJob`, returns `{ message }`.
- **Method:** `exportExcel(Request $request): JsonResponse` — same pattern, dispatches `ExportExcelJob`, returns `{ message }`.

---

### `PublicTicketController`

- **File:** `app/Http/Controllers/PublicTicketController.php`
- **What it is:** Allows anyone (even unauthenticated visitors) to report equipment damage by scanning a QR code — no account required.

**Its routes:**

| Method | URI | Route name | Middleware | File |
|---|---|---|---|---|
| GET | `/ticket/new` | `ticket.public.create` | public + `rate.limit:20,1` | `web.php:68` |
| POST | `/ticket/store` | `ticket.public.store` | public + `rate.limit:5,1` | `web.php:71` |
| GET | `/ticket/success/{ticket}` | `ticket.public.success` | `signed` + `rate.limit:20,1`, `{ticket}` must be numeric | `web.php:77` |

(Only in `web.php`.)

**Dependencies:** `CreatePublicTicketAction`, `NotificationCreatorService`, `PublicStoreTicketRequest`, `GenerateAiRecommendationJob`, allowed photo MIMEs (JPEG/PNG/GIF/WebP).

- **Method:** `create(Request $request): View`
  - **What it does:** Renders the public damage-report form, pre-filled with the equipment found via `machine_id`.
  - **When it runs / how it works:** reads `machine_id` query param; `Equipment::with(['room','category'])->findOrFail($machineId)` (**404** if not found); returns the `ui.tickets.public.create` view with the equipment and the list of `PublicTicketProblemTypeEnum` cases.

- **Method:** `store(PublicStoreTicketRequest $request): RedirectResponse`
  - **What it does:** Registers the public ticket and notifies administrators.
  - **When it runs / how it works:** finds the equipment by validated `equipment_id`; normalises `problem_type` via `PublicTicketProblemTypeEnum::normalize` (default `Other`); executes `CreatePublicTicketAction::execute(...)` with equipment, problem type, description, and optional reporter name/contact; if a `photo` was uploaded, `storePhoto` saves it (silently skipping disallowed MIMEs) as a UUID filename under `ticket_photos/` with `user_id = null`; dispatches `GenerateAiRecommendationJob` after commit; notifies all admins via `NotificationCreatorService::createForAdmins` (title `__('tickets.Novo Ticket Reportado')`, link to the UI ticket page); redirects to a **temporary signed** `ticket.public.success` URL (1 day validity).

- **Method:** `success(Ticket $ticket): View`
  - **What it does:** Renders the confirmation page with the created ticket reference.
  - **When it runs / how it works:** returns `ui.tickets.public.success` with the ticket and its `equipment` and `status` relations loaded.
  - **Authorization:** unauthenticated visitors may open it, but only with a valid signature (`signed` middleware). Ticket ids are sequential and the page prints the reference, the equipment and the priority, so the previous public-by-id behaviour let anyone enumerate other people's reports.

---

# Specialized Controllers

#### `TicketBudgetController`
See the [Tickets](#2-tickets) section above.

#### `StockDashboardController`
See the [Parts & Stock Management](#4-parts--stock-management) section above.

---

## Controller Inventory

| Controller | Purpose | Public methods |
|---|---|---|
| `ActivityFeedController` | Recent activity feed for the dashboard timeline | `index` |
| `AdminController` | Budget approval/rejection + preventive ticket creation | `approveBudget`, `storePreventive` |
| `AdminEquipmentController` | Admin CRUD for equipment | `index`, `store`, `update`, `destroy` |
| `AdminUserController` | Admin user management (CRUD, deactivation, profiles) | `index`, `store`, `update`, `inactivate`, `destroy`, `profiles` |
| `AnalyticsController` | Dashboard analytics + CSV/PDF/Excel export | `stats`, `exportCsv`, `exportPdf`, `exportExcel` |
| `AuditController` | Paginated audit log listing | `index` |
| `AuthController` | Login (with rate limiting) + logout | `login`, `logout` |
| `CalendarController` | Calendar view + schedule/reschedule maintenance | `index`, `events`, `scheduleMaintenance`, `reschedule` |
| `Controller` | Abstract base (authorization + authenticatedUser helper) | — |
| `ExportDownloadController` | Streams a generated report (CSV/XLSX/PDF) from the private disk to its owner only | `__invoke` |
| `LocaleController` | Language preference switching and persistence | `switch` |
| `MaintenancePlanController` | Preventive maintenance plan CRUD | `index`, `show`, `store`, `update`, `destroy` |
| `NotificationController` | User notifications (list, mark read, test email) | `index`, `markAsRead`, `sendTestEmail` |
| `PageController` | Static pages: home, login, password reset form, language switch | `home`, `switchLang`, `login`, `testEmail`, `passwordResetForm` |
| `PartCategoryController` | Part category CRUD | `index`, `store`, `update`, `destroy` |
| `PartController` | Spare parts catalogue CRUD | `index`, `show`, `store`, `update`, `destroy` |
| `PasswordResetController` | Password recovery email + token-based reset | `sendResetLink`, `resetPassword` |
| `PreferencesController` | User display preferences (language, currency, formats) | `edit`, `updateLanguage`, `updateCurrency`, `updateDateFormat`, `updateTimeFormat`, `updateNumberFormat`, `updateAll` |
| `ProfileController` | Password change + profile update | `changePassword`, `updateProfile` |
| `PublicTicketController` | Guest damage-report form (via QR code) | `create`, `store`, `success` |
| `QrCodeController` | QR code generation, download, and bulk PDF export | `show`, `download`, `exportPdf` |
| `RegisterController` | Admin-initiated user registration | `__invoke` |
| `RoomController` | Room CRUD + deactivation | `indexRoom`, `storeRoom`, `updateRoom`, `inactivateRoom` |
| `StockDashboardController` | Stock summary stats, top consumed, slow-moving, forecasts | `summary`, `topConsumed`, `slowMoving`, `costByEquipment`, `costByTicket`, `runoutForecast` |
| `StockMovementController` | Stock movement CRUD (in/out/adjust/return) | `index`, `store` |
| `StockReportController` | CSV exports + PDF report generation for stock | `lowStockCsv`, `inventoryCsv`, `costsByEquipmentPdf` |
| `StockUiController` | Blade views for all stock management pages | `dashboard`, `parts`, `partCreate`, `partShow`, `partEdit`, `suppliers`, `supplierCreate`, `supplierEdit`, `movements`, `taxRates`, `categories`, `plans` |
| `SupplierController` | Supplier directory CRUD | `index`, `show`, `store`, `update`, `destroy` |
| `SystemSettingsController` | Admin system settings (view + update/reset) | `index`, `update` |
| `TaxRateController` | VAT rate CRUD | `index`, `store`, `update`, `destroy` |
| `ThemeController` | Dynamic CSS theme generation + theme switching | `customCss`, `switchTheme` (+ static `cacheBuster`) |
| `TicketAttachmentController` | Photo upload, listing, and deletion for tickets | `store`, `index`, `destroy` |
| `TicketBudgetController` | Budget estimation and approval workflow | `submitEstimate`, `requestAuthorization` |
| `TicketCommentController` | Add and list comments on tickets | `store`, `index` |
| `TicketController` | Ticket CRUD, search, open tickets, most-urgent lookup | `index`, `store`, `search`, `show`, `openTickets`, `getMostUrgentOpenTicket` |
| `Ticket/TicketAssignmentController` | Assign technician to ticket + auto-transition to In Progress | `__invoke` |
| `Ticket/TicketCloseController` | Simple close + final close with priority verification | `simpleClose`, `closeFinal` |
| `Ticket/TicketLifecycleController` | Reopen closed tickets + cancel open tickets | `reopen`, `cancel` |
| `Ticket/TicketScheduleController` | Set intervention time window for a ticket | `__invoke` |
| `Ticket/TicketStartController` | Start repair on a ticket (with priority override check) | `__invoke` |
| `UiController` | All Blade views for the main web UI + helper JSON endpoints | `index`, `tickets`, `ticketCreate`, `equipments`, `equipmentCreate`, `equipmentEdit`, `equipmentDetail`, `users`, `userCreate`, `userEdit`, `userDetail`, `rooms`, `roomCreate`, `roomDetail`, `roomEdit`, `audits`, `ticketDetail`, `getEquipments`, `getTechnicalPicket`, `analytics`, `profile`, `themeAppearance`, `themeAppearanceUpdate` |

---

## Dependencies

| Dependency | Used For |
|---|---|
| `App\Services\*` | Business logic delegation |
| `App\Actions\*` | Single-purpose command execution |
| `App\Policies\*` | Authorization checks |
| `App\Http\Resources\*` | Response serialization |
| `App\Http\Requests\*` | Form validation (Request classes) |
| `App\DTOs\*` | Data transfer objects for structured input |
| `App\Jobs\*` | Background processing (exports, AI recommendations) |
| `Illuminate\Http\*` | Response types (JsonResponse, View, etc.) |
| `Illuminate\View\View` | Blade view rendering |
| `OpenApi\Attributes as OA` | API documentation |

## Related Folders

| Path | Relationship |
|---|---|
| `app/Http/Requests/` | Form validation requests used by controllers |
| `app/Http/Resources/` | API resource transformers for responses |
| `app/Http/Middleware/` | Request preprocessing (`CustomAuthMiddleware`, `RoleMiddleware`, `RateLimitMiddleware`, `SetLocaleMiddleware`, ...) |
| `app/Services/` | Business logic layer |
| `app/Actions/` | Command/action classes |
| `app/Domain/` | Domain-specific actions and services |
| `app/Policies/` | Authorization policies |
| `app/DTOs/` | Data transfer objects |
| `app/Jobs/` | Background queue jobs |
| `resources/views/` | Blade templates rendered by UI controllers |
