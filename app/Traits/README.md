# app/Traits

Shared reusable PHP traits attached to Eloquent models and application services.

> **New to programming?** See [NON_TECHNICAL_PROJECT_GUIDE.md](../../NON_TECHNICAL_PROJECT_GUIDE.md) -- this folder is explained as "Shared Behaviors" -- reusable capabilities that can be plugged into any model.

## Files

| File | Purpose |
|---|---|
| [`Auditable.php`](./Auditable.php) | Automatically records model lifecycle events (`created`, `updated`, `deleted`) to the `audits` table, capturing old/new attribute diffs, acting user ID, IP address, and user agent. |

---

## `Auditable.php`

**File:** `app/Traits/Auditable.php`

### What reusable behavior it provides

Adds **drop-in audit logging** to any Eloquent model. Once a model `use`s this trait, Laravel automatically installs `created`, `updated`, and `deleted` listeners (via the trait-booting convention `bootAuditable()`). Every such event writes a row to the `audits` table documenting **who** changed **what**, **when**, and **from where**.

### Class members

| Member | Purpose |
|---|---|
| `private static ?int $resolvedUserId = null` | Static per-process cache of the acting user's ID. Cached once per request so repeated audit writes (e.g. a `create` that also touches related rows) resolve the user a single time. |
| `public static function resetResolvedUserId(): void` | Clears the `$resolvedUserId` static cache. **Must be called in long-running workers** (queue workers, Laravel Octane) between jobs so each job re-resolves the correct actor. Sets the cache back to `null`. |
| `public static function bootAuditable(): void` | The Eloquent trait-boot hook. Loops over `['created', 'updated', 'deleted']` and registers a closure on each event. Each closure wraps `self::createAudit($model, $event)` in a `try/catch (Throwable)`, logging a `Log::warning('Audit trail failed', [...])` and swallowing the error so an auditing failure **never aborts the primary business transaction**. |
| `private static function createAudit(Model $model, string $event): void` | Builds and persists the `App\Models\Audit` row. Determines `$request` via the global `request()` helper (when available), resolves the user ID via `resolveUserId()`, then computes the `old`/`new` value diff depending on the event (see below), and finally calls `Audit::create([...])` with `user_id`, `auditable_type`, `auditable_id`, `event`, `old_values`, `new_values`, `url`, `ip_address`, `user_agent`. |
| `private static function resolveUserId(?Request $request): ?int` | Resolves the acting user ID, caching the result in `$resolvedUserId`. Resolution order: (1) `auth()->id()` if the auth helper exists and exposes `id()`; (2) fallback `auth()->user()->id` / `->getKey()`; (3) if still `null` and a request exists, looks for an `X-Auth-Token` header or Bearer token, hashes it via `User::hashToken()`, and looks up the matching `api_token` hash on the `users` table. Returns `(int)` or `null`. |

### Event → diff logic in `createAudit()`

- **`created`** → `$new = $model->getAttributes()`; `$old = null`.
- **`deleted`** → `$old = $model->getOriginal()`; `$new = null`.
- **`updated`** → uses `$model->getChanges()` (falls back to `$model->getDirty()` if empty); if non-empty, builds `$oldVals`/`$newVals` arrays per changed key via `$model->getOriginal($k)` and the new value `$v`; else leaves `old`/`new` as `null`.

### Which classes use it

Grep of `use App\Traits\Auditable` / the `Auditable` trait across `app/`:

| Class | File |
|---|---|
| `App\Models\Equipment` | `app/Models/Equipment.php:7` |
| `App\Models\Room` | `app/Models/Room.php:7` |
| `App\Models\Part` | `app/Models/Part.php:8` |
| `App\Models\Ticket` | `app/Models/Ticket.php:9` |

When any of these models is created/updated/deleted, an `audits` row is produced (unless the resolver finds no actor, in which case `user_id` is `null` — an anonymous/system-driven change).

**Related:** the audit rows written here are viewed through `app/Http/Controllers/AuditController.php`, whose `viewAny` ability is gated by `app/Policies/AuditPolicy.php` (admin-only).

## Notes for Developers & AI

- **Automatic Event Booting:** Uses Laravel's `bootAuditable()` trait booting convention. Any model including this trait will automatically intercept `created`, `updated`, and `deleted` lifecycle hooks.
- **Process Memory Cache:** `$resolvedUserId` is statically cached per HTTP request. In long-running workers (queue workers, Laravel Octane), call `Auditable::resetResolvedUserId()` to clear cached state between jobs.
- **Exception Safety:** All audit insertion calls are wrapped in `try/catch` blocks to prevent auditing logging failures from aborting primary business transactions.
- **Silent system changes** (e.g. an artisan command creating a `Ticket`) resolve no authenticated user, so `user_id` is stored as `null`. Only HTTP-level and token-authenticated actors are captured.
- **Storage footprint:** because every write to `Equipment`/`Room`/`Part`/`Ticket` produces an `audits` row, the `audits` table can grow quickly — this is why `app/Console/Commands/PartitionAudits.php` (`audit:partition`) RANGE-partitions it monthly and prunes old partitions (see the Console/Commands README).