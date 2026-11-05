# app/Http/Middleware

HTTP middleware classes for request processing in the SGM maintenance management platform.

> **New to programming?** See [NON_TECHNICAL_PROJECT_GUIDE.md](../../../NON_TECHNICAL_PROJECT_GUIDE.md) -- this folder is explained as "The Security Guards" that check credentials, validate requests, and enforce rules before anything gets through.

## What is middleware? (Plain English)

Think of the middleware as **the security guards and checkpoints** at the entrance of a very busy building. When a request arrives on the server (for example, "please show me my list of tickets" or "please save this new equipment"), it does not immediately reach its final destination — the controller that actually does the work. Instead, it passes **through a chain of guards, one after another**, and each guard asks a question about that request:

- *"Do you have proof of who you are?"* (authentication)
- *"May I see a ticket that proves you really filled out this form?"* (CSRF protection)
- *"Haven't you been here a little too often lately?"* (rate limiting)
- *"What language do you prefer to see the screen in?"* (locale)
- *"Do you have permission to visit this part of the building?"* (roles)

Each guard either **lets the request continue** to the next guard, **changes/attaches something** to the request for later use, or **turns the request away** with a clear error message (for example, "you are not logged in" or "you don't have permission"). Only a request that passes **every** guard reaches the controller.

Below is what each guard in this folder does and how it actually works.

---

## Files

### RequestContextMiddleware

**File:** `app/Http/Middleware/RequestContextMiddleware.php`
**What it does:** The **Envelope Stamper** — gives every request a unique tracking ID (UUID), measures how long the request takes, and watches for requests that are too slow or use too much memory.
**When it runs:** This is the **very first guard in the whole application** — added globally via `$middleware->append(RequestContextMiddleware::class)` in `bootstrap/app.php:34`. It runs on **every single request** regardless of whether the route is `web`, `api`, public, or protected. No other middleware runs before it.
**Constructor dependencies:** None.

#### `handle(Request $request, Closure $next): Response`

The step-by-step logic:

1. **Records start time:** `$startedAt = microtime(true)` — captures the current time in seconds with microsecond precision.
2. **Resolves request ID:** Reads the `X-Request-ID` header from the client. If present, uses it; otherwise generates a new UUID via `Str::uuid()`.
3. **Stores tracking data:** Sets two request attributes via `$request->attributes->set(...)`:
   - `request_id` — the UUID for this request.
   - `request_started_at` — the start timestamp.
4. **Binds request ID to logger:** `Log::withContext(['request_id' => $requestId])` — so every log entry written during this request automatically includes the tracking ID.
5. **Calls `$next($request)`** — passes the request to the next middleware/controller in the chain. If the chain throws, the `finally` block still executes.
6. **Stamps response header:** After the chain completes, sets `X-Request-ID` on the response header so the client can correlate responses with server logs.
7. **In the `finally` block (always runs):**
   - Calculates `durationMs` = (end time - start time) × 1000, rounded to 2 decimals.
   - Stores `execution_time_ms` on the request attributes.
   - **Slow request warning:** If `durationMs >= config('observability.slow_request_threshold_ms', 100)` (default 100 ms), logs a `warning` level message with the metric `http.request.duration_ms`.
   - Calculates peak memory usage in MB via `memory_get_peak_usage(true)`.
   - **High memory warning:** If `peakMemoryMb >= config('observability.high_memory_threshold_mb', 128)` (default 128 MB), logs a `warning` with the metric `http.request.peak_memory_mb`.
   - Calls `Log::withoutContext()` to clean up the per-request context.

**Order:** Runs before everything else — it is the outermost wrapper around every request.

---

### SecurityHeaders

**File:** `app/Http/Middleware/SecurityHeaders.php`
**What it does:** The **Safety Label Paster** — adds a set of protective HTTP security headers to the response that tell the browser how to behave safely.
**When it runs:** Added to **both** the `web` and `api` middleware groups in `bootstrap/app.php`:
- `$middleware->web(append: [..., SecurityHeaders::class])` (line 39)
- `$middleware->api(append: [..., SecurityHeaders::class])` (line 44)

This means it runs on every `web` and `api` route, but **after** the session, locale, and preferences middleware in the `web` group. Since it attaches headers to the response *after* calling `$next()`, it is a "post-processing" guard — it lets the request through first, then decorates the response on the way back.

**Constructor dependencies:** None.

#### `handle(Request $request, Closure $next): Response`

1. **Calls `$next($request)` first** — lets the entire middleware chain and controller execute, producing a `Response`.
2. **Calls `addSecurityHeaders($response, $request)`** — decorates the response with headers (see below).
3. **Returns the decorated response.**

#### `addSecurityHeaders(Response $response, Request $request): void` (private)

Sets the following headers on the response:

| Header | Value | Purpose |
|---|---|---|
| `X-Frame-Options` | `DENY` | Prevents the page from being shown inside another site's `<iframe>` (clickjacking protection). |
| `X-Content-Type-Options` | `nosniff` | Stops the browser from guessing file types (MIME-sniffing protection). |
| `X-XSS-Protection` | `0` | Disables the legacy XSS auditor (modern browsers use CSP instead; the old auditor had vulnerabilities). |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | Limits how much URL information is sent in the `Referer` header on cross-origin requests. |
| `Permissions-Policy` | `camera=(), microphone=(), geolocation=()` | Forbids the page from using the camera, microphone, or geolocation APIs. |
| `Cross-Origin-Opener-Policy` | `same-origin` | Isolates the browsing context to prevent cross-origin window interactions. |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains` | **Only on HTTPS** (`$request->secure()`). Tells the browser to always use HTTPS for the next year. |
| `Content-Security-Policy` | *(varies by environment)* | Only set if the response doesn't already have a CSP header. Built by `buildCsp()`. |

#### `buildCsp(Request $request): string` (private)

Returns a Content Security Policy string appropriate for the environment:

- **Development** (`app()->environment('local')` or `config('app.debug')`): A relaxed CSP that allows `localhost:5173` and `127.0.0.1:5173` (Vite dev server), `unsafe-inline` and `unsafe-eval` scripts, WebSocket connections for hot-reload (`ws://localhost:5173`), and CDN resources from `cdn.jsdelivr.net` and `fonts.bunny.net`.
- **Production**: A strict CSP. `script-src` allows `'self'`, two specific `sha256` hashes for inline scripts and `cdn.jsdelivr.net` for external scripts — no `unsafe-inline` or `unsafe-eval` there. `style-src` still allows `'unsafe-inline'` alongside `'self'` and `cdn.jsdelivr.net`.

Both environments set `frame-ancestors 'none'` as an additional clickjacking defense.

**Order:** In the `web` group, runs after `SetLocaleMiddleware` and `SetUserPreferencesMiddleware` (it is the third item appended). In the `api` group, runs after `SetLocaleMiddleware`. Being a post-processing middleware, its actual work happens *after* the controller returns.

---

### SetLocaleMiddleware

**File:** `app/Http/Middleware/SetLocaleMiddleware.php`
**What it does:** The **Language Picker** — decides which language the site should display in for this request (default is `pt-PT`).
**When it runs:** Added to **both** the `web` and `api` middleware groups in `bootstrap/app.php`:
- `$middleware->web(append: [SetLocaleMiddleware::class, ...])` (line 37)
- `$middleware->api(append: [SetLocaleMiddleware::class, ...])` (line 43)

This means it runs early for nearly every request — it is the **first** custom middleware in both groups (right after Laravel's built-in session and cookie middleware).
**Constructor dependencies:** None (injects `AuthUserResolver` and `LocaleService` via static calls).

#### `handle(Request $request, Closure $next): Response`

1. **Resolves the locale** via `resolveLocale($request)` (private method, see below).
2. **Sets it globally:** `App::setLocale(...)` — switches the entire application to that language for this request. All `__()` calls, Blade translations, and Carbon date formatting will use this locale.
3. **Calls `$next($request)`** — passes to the next middleware.

#### `resolveLocale(Request $request): string` (private)

Resolves the locale by checking sources in this **priority order** (first match wins):

1. **Session:** `$request->session()->get('locale')` — if the user previously chose a language, it's stored here. Checked via `sessionLocale()` (private static).
2. **Authenticated user's DB preference:** `Auth::user()` or `Auth::guard('api')->user()`, or if both are null, `AuthUserResolver::fromRequest($request)`. Reads the `locale` column on the `User` model. Validates via `LocaleService::isSupported()` — if the stored locale is not in the supported list, it's ignored. If valid, **also saves it to the session** (`$request->session()->put('locale', $fromUser)`) so it persists for future requests.
3. **Cookie:** `$request->cookie('locale')` — checked via `cookieLocale()` (private static).
4. **Browser header:** `LocaleService::resolveFromRequest($request)` — reads `Accept-Language` from the browser.
5. **Default:** Falls back to whatever `LocaleService::resolveFromRequest()` returns (ultimately `pt-PT`).

#### `resolveFromRequest(Request $request): string` (static, public)

A **static helper** used outside the middleware chain — specifically in `bootstrap/app.php:77` to set the locale on **error pages** where the `web` group middleware no longer runs. It checks session → cookie → browser header (skipping the user DB lookup since the user may not be authenticated during error handling).

**Constants:** `DEFAULT_LOCALE = 'pt-PT'` — the fallback locale, matching the database-level default for the application.

**Order:** First custom middleware in both `web` and `api` groups. Runs after Laravel's built-in `StartSession` middleware (so the session is available) but before `SetUserPreferencesMiddleware` and `SecurityHeaders`.

---

### SetUserPreferencesMiddleware

**File:** `app/Http/Middleware/SetUserPreferencesMiddleware.php`
**What it does:** The **Preference Card Slipper** — loads the user's full preferences (currency, date format, number format) and makes them available to controllers and views for the duration of the request.
**When it runs:** Added to the **`web`** middleware group only in `bootstrap/app.php:38`:
```php
$middleware->web(append: [
    SetLocaleMiddleware::class,
    SetUserPreferencesMiddleware::class,
    SecurityHeaders::class,
]);
```

It does **not** run on `api` routes. It runs **after** `SetLocaleMiddleware` (which handles language separately and earlier) and **before** `SecurityHeaders`.

**Constructor dependencies:** None (uses `PreferencesService::current()` statically).

#### `handle(Request $request, Closure $next): Response`

1. **Loads preferences:** `$prefs = PreferencesService::current($request)` — the `PreferencesService` determines the user's current preferences (resolving from session, user profile, or defaults). Returns an object/array containing `currency`, `date_format`, `number_format`, etc.
2. **Attaches to request:** `$request->merge(['_preferences' => $prefs])` — makes the preferences available as `$request->_preferences` for any downstream controller, view, or Blade template.
3. **Calls `$next($request)`** — passes to `SecurityHeaders`.

**Order:** Second middleware in the `web` group. Runs after `SetLocaleMiddleware` (which it depends on for the locale to already be set) and before `SecurityHeaders`. Not in the `api` group.

---

### CustomAuthMiddleware

**File:** `app/Http/Middleware/CustomAuthMiddleware.php`
**What it does:** The **Bouncer** — blocks requests that come without a valid login token, and identifies who the logged-in user is. The token is an opaque string (accepted via `X-Auth-Token`, `Authorization: Bearer`, the `api_token`/`auth_token` cookies or the session), hashed with `User::hashToken` (HMAC-SHA256) and looked up in `users.api_token` — it is **not** a JWT.
**When it runs:** Registered as the alias `custom.auth` in `bootstrap/app.php:49`:
```php
$middleware->alias([
    'custom.auth' => CustomAuthMiddleware::class,
    ...
]);
```

Applied at the **route level** (not global). It is used in:

- **`routes/web.php:109`** — wraps the entire protected web routes group:
  ```php
  Route::middleware(['custom.auth'])->group(function () { ... });
  ```
  This covers all authenticated web routes: logout, profile, notifications, UI pages, tickets, rooms, equipment, stock, admin, analytics, calendar, technician area, and more.

- **`routes/api.php:43`** — applied to the `GET /api/user` route individually.

- **`routes/api.php:65`** — wraps the protected API routes group:
  ```php
  Route::middleware(['custom.auth'])->group(function () { ... });
  ```
  This covers all authenticated API routes: activity feed, password change, tickets, comments, photos, workflow, technician actions, stock, admin, analytics, notifications.

**Constructor dependencies:** None.

#### `handle(Request $request, Closure $next): Response`

1. **Collects token candidates** via `collectTokenCandidates($request)`:
   - Checks in order: `X-Auth-Token` header, `Authorization: Bearer ...` token, `api_token` cookie, `auth_token` cookie, `session('api_token')`.
   - Filters out empty/null values. If no candidates remain, returns unauthenticated response.

2. **Resolves which tokens to try** via `resolveTokensToTry($request, $candidates)`:
   - If an **explicit** token was provided (header or Bearer), only tries that one token (takes precedence).
   - If only cookies/session tokens are available, tries all candidates in order.

3. **Finds user by token** via `findUserByTokens($tokens)`:
   - For each candidate token, hashes it via `User::hashToken($candidate)` and looks for a matching user:
     ```php
     User::with('profile')
         ->where('api_token', $tokenHash)
         ->where('active', true)
         ->whereNull('deleted_at')
         ->first();
     ```
   - **Testing bypass:** In the `testing` environment, also tries the raw (unhashed) token as a fallback.
   - Returns `[$user, $token]` on first match, or `[null, null]` if no candidate works.

4. **If no user found:** Returns `invalidTokenResponse()`:
   - **JSON requests:** Returns 401 with `{"message": "Token inválido ou utilizador inativo."}`. If the request had cookies, also forgets `api_token` and `auth_token` cookies.
   - **Browser requests:** Redirects to `/ui/login` (clearing cookies if present).

5. **If user has invalid profile** (no `profile_id` or no `profile->name`): Returns `invalidProfileResponse()`:
   - **JSON:** Returns 403 with `{"message": "Perfil inválido."}`.
   - **Browser:** Redirects to `/ui/login`.

6. **If token is expired** via `isTokenExpired($user)`:
   - Checks `$user->token_created_at` against `config('services.custom.auth.token_expiry_days', 30)`.
   - If expired, nullifies the user's `api_token` (saving to DB) and returns `expiredTokenResponse()`:
     - **JSON:** Returns 401 with `{"message": "Token expirado. Faça login novamente."}`.
     - **Browser:** Redirects to `/ui/login`.
   - Tokens without a `token_created_at` date never expire (deterministic behavior in tests).

7. **If everything is valid:**
   - `Auth::guard('api')->setUser($user)` — manually sets the authenticated user on the `api` guard.
   - `Auth::shouldUse('api')` — tells Laravel to use the `api` guard for this request.
   - Calls `$next($request)` — the request continues with `Auth::user()` returning the authenticated user.

**Order:** Route-level middleware. Runs after all global and group middleware (`RequestContextMiddleware`, session, `SetLocaleMiddleware`, `SetUserPreferencesMiddleware`, `SecurityHeaders`). Applied to every protected route in both `web.php` and `api.php`.

---

### RoleMiddleware

**File:** `app/Http/Middleware/RoleMiddleware.php`
**What it does:** The **Badge Checker** — checks that the logged-in user has the right role (profile) to access a particular area.
**When it runs:** Registered as the alias `role` in `bootstrap/app.php:50`:
```php
$middleware->alias([
    ...
    'role' => RoleMiddleware::class,
    ...
]);
```

Applied at the **route level** wherever `role:...` appears. It is used in many places across both `routes/web.php` and `routes/api.php`:

**In `routes/web.php`:**
- `role:admin,user` — `GET /ui/tickets/create` (line 143)
- `role:admin` — `GET /ui/equipments/create` (line 162), `GET /ui/equipments/{equipment}/edit` (line 168), `GET /ui/stock/parts/create` (line 181), `GET /ui/stock/parts/{part}/edit` (line 187), `GET /ui/stock/suppliers/create` (line 192), `GET /ui/stock/suppliers/{supplier}/edit` (line 195), `GET /ui/stock/tax-rates` (line 200), `GET /ui/stock/categories` (line 203), `GET /ui/stock/plans` (line 206)
- `role:technician` — entire technician area group (lines 272-285): `PUT /technician/tickets/{ticket}/start`, `PUT /technician/tickets/{ticket}/close`, `PUT /technician/tickets/{ticket}/request-budget`
- `role:admin,technician` — stock read/movement group (lines 288-303): `GET /stock/parts`, `GET /stock/suppliers`, `GET /stock/movements`, `POST /stock/movements`, stock dashboard routes
- `role:admin` — entire admin group (lines 306-414): users, rooms, analytics, tickets, stock management, audit, equipment, budgets, reports, maintenance plans, QR codes

**In `routes/api.php`:**
- `role:technician` — technician area group (lines 102-115)
- `role:admin,technician` — stock group (lines 118-131)
- `role:admin` — admin group (lines 134-201): users, audit, equipment, rooms, budgets, stock management, analytics

**Constructor dependencies:** None.

#### `handle(Request $request, Closure $next, string ...$roles): Response`

The `$roles` parameter is a **variadic string** — it receives all comma-separated role names from the route definition. For example, `role:admin,technician` passes `$roles = ['admin', 'technician']`.

**Step-by-step logic:**

1. **Gets authenticated user:** `$user = Auth::user()` — retrieves the user that was set by `CustomAuthMiddleware` (which runs earlier on the same route).

2. **Checks authentication and active status:**
   - If `$user` is null or `$user->active` is false, calls `handleUnauthenticated()`:
     - **JSON:** Returns 401 with `{"message": "Autenticação necessária."}`.
     - **Browser with `api_token` cookie:** Redirects to `/ui/login` and forgets the `api_token` cookie.
     - **Browser without cookie:** Redirects to `/ui/login`.

3. **Checks profile validity:**
   - If `$user->profile_id` is null or `$user->profile?->name` is null/empty, calls `handleInvalidProfile()`:
     - **JSON:** Returns 403 with `{"message": "Perfil inválido."}`.
     - **Browser:** Redirects to `/ui/login`.

4. **Checks role authorization:**
   - `in_array($user->profile->name, $roles, true)` — checks if the user's profile name is one of the allowed roles. The comparison is **strict** (case-sensitive).
   - If not in the list, calls `handleForbidden()`:
     - **JSON:** Returns 403 with `{"message": "Acesso proibido para o seu perfil."}`.
     - **Browser:** Redirects to `/ui` with a flash error message: `Não tem permissões para aceder a esta página.`

5. **If all checks pass:** Calls `$next($request)` — the request continues.

**Order:** Route-level middleware. Always runs **after** `CustomAuthMiddleware` on the same route (which sets `Auth::user()`). The typical middleware chain for a protected admin route is: `RequestContextMiddleware` → session → `SetLocaleMiddleware` → `SetUserPreferencesMiddleware` → `SecurityHeaders` → `custom.auth` (CustomAuthMiddleware) → `role:admin` (RoleMiddleware) → controller.

---

### RateLimitMiddleware

**File:** `app/Http/Middleware/RateLimitMiddleware.php`
**What it does:** The **Traffic Cop** — limits how many requests a single user or IP address can make in a short time, to stop abuse and brute-force attacks.
**When it runs:** Registered as the alias `rate.limit` in `bootstrap/app.php:51`:
```php
$middleware->alias([
    ...
    'rate.limit' => RateLimitMiddleware::class,
]);
```

Applied at the **route level** with custom parameters. Notable usages across `routes/web.php` and `routes/api.php`:

| Route | Limit | Where |
|---|---|---|
| `POST /login` | `rate.limit:5,1` (5/min) | `web.php:60` |
| `POST /ticket/store` | `rate.limit:5,1` | `web.php:73` |
| `GET /ticket/success/{ticket}` | `signed` + `rate.limit:20,1` | `web.php:80` |
| `POST /password/change` | `rate.limit:10,1` | `web.php:117` |
| `POST /profile/update` | `rate.limit:10,1` | `web.php:120` |
| `POST /tickets` | `rate.limit:30,1` | `web.php:228` |
| `POST /tickets/{ticket}/comments` | `rate.limit:30,1` | `web.php:234` |
| `POST /tickets/{ticket}/photos` | `rate.limit:30,1` | `web.php:241` |
| Technician actions | `rate.limit:20,1` | `web.php:276,280,284` |
| `POST /tickets/{ticket}/assign-technician` | `rate.limit:20,1` | `web.php:326` |
| `POST /admin/users/register` | `rate.limit:5,1` | `web.php:339` |
| `POST /api/login` | `rate.limit:5,1` | `api.php:47` |
| `POST /api/password/email` | `rate.limit:3,1` | `api.php:51` |
| `POST /api/password/reset` | `rate.limit:5,1` | `api.php:58` |
| `POST /api/tickets` | `rate.limit:30,1` | `api.php:80` |
| Technician API actions | `rate.limit:20,1` | `api.php:105,108,111,114` |
| `POST /api/notifications/test-email` | `rate.limit:5,1` | `api.php:208` |

**Constructor dependencies:** `Illuminate\Cache\RateLimiter $limiter` — injected by Laravel's service container.

#### `handle(Request $request, Closure $next, string $maxAttempts = '60', int $decayMinutes = 1): Response`

The `$maxAttempts` and `$decayMinutes` parameters come from the route definition (e.g. `rate.limit:5,1` means `$maxAttempts = '5'`, `$decayMinutes = 1`).

**Step-by-step logic:**

1. **Resolves request signature** via `resolveRequestSignature($request)`:
   - **Auth endpoints** (login, register, password recovery): Uses `sha1(IP + '|' + email)` — so rate limiting is per IP+email, allowing different users from the same IP.
   - **All other endpoints:** Uses `sha1(IP + '|' + userId + '|' + path)` — rate limiting is per IP+user+path. For unauthenticated users, userId is `'guest'`.

2. **Checks if limit exceeded:** `$this->limiter->tooManyAttempts($key, $maxAttemptsInt)` — if the requester has exceeded the allowed attempts, returns the rate-limit response.

3. **Records the attempt:** `$this->limiter->hit($key, $decayMinutes * 60)` — increments the counter, with a TTL of `$decayMinutes` minutes.

4. **Calls `$next($request)`** — lets the request proceed.

5. **Adds response headers** via `addHeaders()`:
   - `X-RateLimit-Limit` — the maximum allowed attempts.
   - `X-RateLimit-Remaining` — calculated via `calculateRemainingAttempts()`: `max(0, $maxAttempts - $this->limiter->attempts($key))`.

#### `buildResponse(string $key, int $maxAttempts, int $decayMinutes): Response` (protected)

Builds the response returned when the rate limit is exceeded:

- **Status:** 429 Too Many Requests.
- **Body:** `{"message": "Demasiadas tentativas. Tente novamente mais tarde.", "retry_after": <seconds>}`.
- **Headers:** `Retry-After` (seconds until the limit resets), `X-RateLimit-Limit`, `X-RateLimit-Remaining: 0`.

#### `isAuthEndpoint(Request $request): bool` (protected)

Determines if the request targets login, registration, or password recovery — where the rate limit key should include the email rather than the user ID. Checks:
- `$request->is('login', 'register')` — URL path matching.
- Route name is `api.login` or starts with `api.password.`.

**Order:** Route-level middleware. Typically applied as the last or near-last middleware before the controller on specific routes. Runs after `CustomAuthMiddleware` and `RoleMiddleware` where those are also present.

---

### LocalizeSwaggerDocument

**File:** `app/Http/Middleware/LocalizeSwaggerDocument.php`
**What it does:** The **Tour Guide Translator** — translates the human-readable headings of the API documentation (Swagger/OpenAPI) into the user's language.
**When it runs:** Applied **only** to the Swagger docs route, in `config/l5-swagger.php:81`:
```php
'docs' => ['web', 'custom.auth', 'role:admin', LocalizeSwaggerDocument::class],
```

This means it runs on the route that serves the OpenAPI JSON document (typically `docs/openapi-json`), after authentication and admin role checks. It is **not** global — it only acts on this one specific route.

**Constructor dependencies:** None.

#### `handle(Request $request, Closure $next): Response`

1. **Calls `$next($request)` first** — lets the entire chain (including the Swagger document generator) produce the response.

2. **Checks if this is the OpenAPI route:** `str_ends_with($request->path(), 'docs/openapi-json')`. If not, returns the response unchanged — **no-op** for all other routes.

3. **Parses the JSON document:** `json_decode($response->getContent(), true)` — converts the OpenAPI JSON to a PHP array.

4. **Translates fields** via `translateFields(array &$node)` (private, recursive):
   - Walks every key-value pair in the document array.
   - For nested arrays, recurses.
   - For string values where the **key** is one of `title`, `summary`, or `description` (the `TRANSLATABLE_FIELDS` constant):
     - Looks up the value in `SOURCE_DOMAINS` — a mapping of source strings to Laravel translation domains:
       ```php
       'Gestão de Avarias API' => 'common',
       'Documentação OpenAPI da aplicação...' => 'tickets',
       'Servidor Principal da API' => 'common',
       'Token de autenticação personalizado...' => 'auth',
       'Autenticação baseada em Token JWT' => 'auth',
       'Catálogo de peças, fornecedores...' => 'stock',
       'Gestão administrativa de peças...' => 'stock',
       ```
     - If the source string is found, calls `__($domain.'.'.$value)` to get the translated version.
     - If not found, leaves the value unchanged.
   - Technical values (paths, operationIds, schemas, properties) are **never touched** — only human-readable labels are translated.

5. **Re-encodes and sets the response:** `json_encode($document, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)` — preserves Unicode characters (important for Portuguese accents) and slashes. Sets `Content-Type: application/json`.

**Order:** Post-processing middleware applied to a single route. Runs after `custom.auth` and `role:admin` on the docs route. Its work happens after the entire chain completes.

---

## Typical request journey (illustrative)

Middleware order is defined in two places: `bootstrap/app.php` (global and per-group middleware) and the routes themselves (route-level aliases like `custom.auth`, `role:`, and `rate.limit:`). Global and group middleware run first, then route-level middleware, so the order for a typical **logged-in admin** request looks like this:

1. **`RequestContextMiddleware`** — stamps a tracking ID and start time (global, runs on every request).
2. **Laravel's own session & auth scaffolding** — restores the visitor's session (`web` group, built into Laravel).
3. **`SetLocaleMiddleware`** — picks the language from session → user DB → cookie → browser → default (`web` group).
4. **`SetUserPreferencesMiddleware`** — loads currency/date/number preferences into `$request->_preferences` (`web` group).
5. **`SecurityHeaders`** — will attach the safety headers to the response (`web` group, post-processing).
6. **`custom.auth` (`CustomAuthMiddleware`)** — verifies the login token and identifies the admin (route-level).
7. **`role:admin` (`RoleMiddleware`)** — verifies the user's profile is allowed here (route-level, on the specific route).
8. **`rate.limit` (`RateLimitMiddleware`)** — checks the visitor isn't making too many requests (route-level, where configured).
9. **The controller** — finally does the actual work and returns the response.
10. **Post-processing:** `SecurityHeaders` attaches headers, `RequestContextMiddleware` logs timing/memory, `LocalizeSwaggerDocument` translates (on the docs route only).

> **Note:** The exact order of the route-level guards (`custom.auth`, `role`, `rate.limit`) is defined per route in `routes/web.php` and `routes/api.php`, so it can vary slightly from route to route. The journey above reflects how the global/group middleware in `bootstrap/app.php` are combined with the common route-level guards — treat it as a representative example of a typical admin request rather than the exact order for every single route.

## Notes

- `__()` translation keys are part of the i18n domain — not normalized in this refactor
- `LocalizeSwaggerDocument::SOURCE_DOMAINS` maps source strings to translation domains — i18n domain
- `SetLocaleMiddleware::DEFAULT_LOCALE` is `'pt-PT'` — DB-level default, reported separately
- `CustomAuthMiddleware` sets the user on the `api` guard only — this is why `SetLocaleMiddleware` also checks `Auth::guard('api')->user()` and falls back to `AuthUserResolver::fromRequest()` for the user's locale
- All middleware classes are `final` — they cannot be extended or overridden
