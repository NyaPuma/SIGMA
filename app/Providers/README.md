# `app/Providers`

Laravel service providers for bootstrapping application services, registering bindings, and wiring events.

> **New to programming?** See [NON_TECHNICAL_PROJECT_GUIDE.md](../../NON_TECHNICAL_PROJECT_GUIDE.md) -- this folder is explained as "The Wiring Diagram" that connects all the pieces together at startup so everything can find each other.

## Files

| File | Purpose |
|---|---|
| `AppServiceProvider.php` | Main service provider. In `register()`: binds repository interfaces to concrete repositories and registers six domain services as singletons. In `boot()`: registers authorization policies, model observers, Blade formatting directives, the slow-query listener, queue observability hooks, Sentry PII sanitization, and applies saved system-settings overrides. |
| `EventServiceProvider.php` | Defines the event-to-listener mapping for ticket lifecycle events. Auto-discovery is explicitly disabled for performance and control. |

---

## `AppServiceProvider.php`

**File:** `app/Providers/AppServiceProvider.php`

### What it registers — `register()`

Runs during the **initialization** phase, before any request handling. Registers container bindings only.

**Repository interface → concrete bindings (per-request binding factories):**
| Interface | Concrete |
|---|---|
| `UserRepositoryInterface` | `UserRepository` |
| `TicketRepositoryInterface` | `TicketRepository` |
| `EquipmentRepositoryInterface` | `EquipmentRepository` |
| `RoomRepositoryInterface` | `RoomRepository` |

**Singleton domain services:**
- `TicketStatusService`
- `AnalyticsService`
- `NotificationService`
- `AIService`
- `SystemSettingsService`

### What it boots — `boot()`

Runs **after all providers have registered**, before the app serves requests. Calls, in order:

| Step | Method | What it does |
|---|---|---|
| Policies | `registerPolicies()` | Defines the `viewPulse` Gate ability (`fn ($user = null) => $user?->isAdmin() === true` — restricts the Laravel Pulse dashboard to admins in non-local environments). Then binds 11 policies: `Ticket→TicketPolicy`, `User→UserPolicy`, `Equipment→EquipmentPolicy`, `Room→RoomPolicy`, `UserProfile→UserProfilePolicy`, `Part→PartPolicy`, `Supplier→SupplierPolicy`, `StockMovement→StockMovementPolicy`, `TaxRate→TaxRatePolicy`, `PartCategory→PartCategoryPolicy`, `MaintenancePlan→MaintenancePlanPolicy`. (`AuditPolicy` is *not* bound here — it is auto-discovered by convention.) |
| Observers | `registerObservers()` | `Ticket::observe(TicketObserver::class)` → `app/Observers/TicketObserver.php`; `User::observe(UserObserver::class)` → `app/Observers/UserObserver.php`; `Audit::observe(AuditObserver::class)` → `app/Observers/AuditObserver.php`. |
| Slow queries | `registerSlowQueryListener()` | Gated by `config('database.connections.mysql.slow_query_log')`. If enabled, attaches a `DB::listen()` → if `$query->time >= slow_query_threshold_ms` (default 100ms), logs `Log::warning('Slow database query detected', [...])` with `metric`, `sql`, `bindings`, `duration_ms`. |
| Queue observability | `registerQueueObservability()` | Registers `Queue::before` (records `microtime(true)` keyed by job ID), `Queue::after` (logs `Log::warning('Slow queue job detected', [...])` if duration ≥ `observability.queue_slow_job_threshold_ms`, default 1000ms), and `Queue::failing` (logs `Log::error('Queue job failed', [...])` with metric `queue.job.failure`, then attempts `Sentry\captureException` — with its own guard). |
| Blade directives | `registerFormattingDirectives()` | Registers locale-aware directives that delegate to `LocalizationService`: `@money` → `formatCurrency`, `@number` → `formatNumber`, `@percent` → `formatPercent`, `@date` → `formatDate`, `@datetime` → `formatDateTime`, `@localizedDate`, `@localizedDateTime`, `@localizedNumber`, `@localizedCurrency`, and `@localizedUnit` → `convertUnit($expr)['formatted']`. |
| Sentry sanitization | `registerSentrySanitization()` | `afterResolving(HubInterface::class)` installs a `beforeSendCallback` on the Sentry client that recursively redacts any key matching `/password|token|secret|api[_-]?key|authorization|card|cvv/` (replaced with `[REDACTED]`) across the event's request, contexts, and extra. Moved out of `config/sentry.php` because closures break `php artisan config:cache`. |
| System settings | `$this->app->make(SystemSettingsService::class)->applyOverrides()` | Applies any overrides saved in the Settings → Configuration page immediately at startup. |
| Worker locale baseline | `resetLocaleForQueuedJobs()` | Called after `applyOverrides()` because that can itself set `app.locale`. Captures the configured locale once and registers a `Queue::before` listener that re-applies it before every job: a worker keeps one application instance, and `App::setLocale()` (used by the export jobs to honour the requester's language) also rewrites `config('app.locale')`, so without this the first export would leave every later job — and its notifications — in that user's language. |

### WHEN it runs

On **every application boot** (both HTTP requests via `bootstrap/app.php` and CLI/artisan commands). It is the central wiring point for repositories, services, policies, observers, queue telemetry, and rendering directives.

---

## `EventServiceProvider.php`

**File:** `app/Providers/EventServiceProvider.php`

### What it registers — `$listen` mapping

| Event | Listeners |
|---|---|
| `App\Events\TicketCreated` | `App\Listeners\SendTicketCreatedNotification` |
| `App\Events\TicketStatusChanged` | `App\Listeners\LogTicketWorkflowChange` |
| `App\Events\TicketStatusUpdatedBroadcast` | `App\Listeners\SendTicketStatusNotification`<br>`App\Listeners\NotifyAssignedTechnician` |

### What it boots — `boot()`

Calls `parent::boot()` which registers the `$listen` map (without auto-discovery). `shouldDiscoverEvents()` returns `false` — you must **explicitly** add any new event/listener pair to `$listen`.

### WHEN it runs

On application boot, alongside `AppServiceProvider`. These listeners fire at runtime when the corresponding events are dispatched — e.g. `TicketStatusUpdatedBroadcast` is emitted by `app/Concerns/BroadcastsTicketStatus.php::broadcastStatusChange()` (see the Concerns README) and then fans out to its three listeners.

---

## Notes for developers / AI

- `AppServiceProvider` is the central wiring point — new repository bindings, services, policies, or observers should be added here.
- `EventServiceProvider` uses explicit mapping (not auto-discovery) — add new event/listener pairs to the `$listen` array.
- Blade directives (`@money`, `@number`, `@percent`, `@date`, `@datetime`, `@localized*`) delegate to `LocalizationService` for locale-aware formatting.
- The slow query listener is gated by the `database.connections.mysql.slow_query_log` config flag; the queue slow-job threshold is gated by `observability.queue_slow_job_threshold_ms`.
- `AuditPolicy` is intentionally **not** in `registerPolicies()` — it resolves via Laravel's convention-based policy auto-discovery.

## Related Folders

| Path | Relationship |
|---|---|
| `app/Repositories/` | Concrete repositories bound by AppServiceProvider |
| `app/Repositories/Contracts/` | Interface contracts bound by AppServiceProvider |
| `app/Services/` | Singleton services registered by AppServiceProvider |
| `app/Models/` | Models with policies and observers registered here |
| `app/Policies/` | Authorization policies registered here |
| `app/Observers/` | Model observers registered here |
| `app/Events/` | Events mapped in EventServiceProvider |
| `app/Listeners/` | Listeners mapped in EventServiceProvider |