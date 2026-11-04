# app/Observers

Eloquent model observers that react to model lifecycle events and trigger side effects.

> **New to programming?** See [NON_TECHNICAL_PROJECT_GUIDE.md](../../NON_TECHNICAL_PROJECT_GUIDE.md) -- this folder is explained as "The Watchdogs" that automatically react when data is created, changed, or deleted.

## Overview

This folder contains **3 observer classes** that hook into Eloquent model lifecycle events (`created`, `updated`, `deleted`, `creating`, `updating`, `restoring`, `updating`, `deleting`, `forceDeleting`). Observers are registered in `AppServiceProvider::registerObservers()`:

```php
Ticket::observe(TicketObserver::class);
User::observe(UserObserver::class);
Audit::observe(AuditObserver::class);
```

All observers are `final readonly` classes.

---

## Files

### `AuditObserver.php`

**What it is:** An immutability enforcer for `Audit` records. It prevents any modification, deletion, or force-deletion of audit trail entries by throwing a `LogicException` on every mutation attempt.

**Class:** `App\Observers\AuditObserver`

**Observed model:** `App\Models\Audit`

**Registered in:** `AppServiceProvider.php:147`:
```php
Audit::observe(AuditObserver::class);
```

**Hooks:**

#### `updating(Audit $audit): void`
- **Fires when:** Any `Audit` record is about to be updated (before the `UPDATE` query executes).
- **Side effect:** Throws `LogicException('Audit records are immutable and cannot be modified.')`.
- **Result:** The update is completely blocked. No database write occurs.

#### `deleting(Audit $audit): void`
- **Fires when:** Any `Audit` record is about to be soft-deleted or hard-deleted (before the `DELETE` or `UPDATE ... SET deleted_at` query executes).
- **Side effect:** Throws `LogicException('Audit records are immutable and cannot be deleted.')`.
- **Result:** The deletion is completely blocked. No database write occurs.

#### `forceDeleting(Audit $audit): void`
- **Fires when:** Any `Audit` record is about to be force-deleted from the database (bypassing soft deletes, before the `DELETE` query executes).
- **Side effect:** Throws `LogicException('Audit records are immutable and cannot be permanently deleted from the database.')`.
- **Result:** The force-deletion is completely blocked. No database write occurs.

**Important design note:** This observer intentionally throws exceptions on **every** mutation hook. The `Audit` model is append-only — once a record is created, it can never be changed or removed. This enforces data integrity for the audit trail.

---

### `TicketObserver.php`

**What it is:** The primary observer for `Ticket` model lifecycle events. It fires broadcast events when tickets are created or have their status changed, and invalidates analytics cache on every mutation.

**Class:** `App\Observers\TicketObserver`

**Observed model:** `App\Models\Ticket`

**Registered in:** `AppServiceProvider.php:145`:
```php
Ticket::observe(TicketObserver::class);
```

**Dependencies:**
- `App\Events\TicketCreated` — fired on creation.
- `App\Events\TicketStatusChanged` — fired on status change.
- `App\Models\TicketStatus` — queried to resolve status IDs to names.
- `App\Enums\TicketStatusEnum` — used as fallback when status name cannot be resolved.
- `Illuminate\Support\Facades\Cache` — used to invalidate analytics cache.

**Hooks:**

#### `created(Ticket $ticket): void`
- **Fires when:** A new `Ticket` has been created and saved to the database.
- **Side effects:**
  1. If `$ticket->user` exists (the creator relationship is loaded), fires `event(new TicketCreated($ticket, $ticket->user))`. This triggers the WebSocket broadcast and the `SendTicketCreatedNotification` listener.
  2. Calls `$this->invalidateAnalyticsCache()` to clear the cached dashboard analytics data.

#### `updated(Ticket $ticket): void`
- **Fires when:** An existing `Ticket` has been updated.
- **Side effects:**
  1. Checks if the `status_id` field changed via `$ticket->wasChanged('status_id')`.
  2. If `status_id` changed:
     - Looks up the old status name: `TicketStatus::where('id', $originalStatusId)->value('name')`.
     - Looks up the new status name: `TicketStatus::where('id', $newStatusId)->value('name')`.
     - If the original status ID was not null, fires `event(new TicketStatusChanged($ticket, $oldStatusName, $newStatusName))`. This triggers the WebSocket broadcast and the `LogTicketWorkflowChange` listener.
  3. Calls `$this->invalidateAnalyticsCache()` regardless of whether `status_id` changed.

#### `deleted(Ticket $ticket): void`
- **Fires when:** A `Ticket` is soft-deleted.
- **Side effects:**
  1. Calls `$this->invalidateAnalyticsCache()`.

#### `restored(Ticket $ticket): void`
- **Fires when:** A soft-deleted `Ticket` is restored.
- **Side effects:**
  1. Calls `$this->invalidateAnalyticsCache()`.

**Private method `invalidateAnalyticsCache()`:**
```php
private function invalidateAnalyticsCache(): void
{
    // Only the acting locale is dropped: the payload has a 60 s TTL, so the other locales
    // self-heal, whereas forgetting all 52 keys on every ticket save would cost 52 queries
    // per write (CACHE_STORE=database) for a staleness window of at most one minute.
    Cache::forget('analytics_dashboard_payload:'.app()->getLocale());
}
```
- Clears **one key**: `analytics_dashboard_payload:<locale>` — the locale-suffixed key written by `AnalyticsDashboardService::getDashboardPayload()` (`app/Services/AnalyticsDashboardService.php:44`). There is no unsuffixed producer, so no unsuffixed key is written or read.
- Payloads cached under *other* locales are left to expire on their own 60 s TTL. Forgetting every key in `config('locales.languages')` (52 entries) was measured as 52 deletes per ticket write, which is worse than the one-minute staleness it removed.
- This ensures the dashboard analytics are recalculated on the next request after any ticket change.

**Important design note:** The observer does **not** fire `TicketStatusChanged` when the `status_id` changes if the original status ID was null. This handles edge cases where a ticket is created with a `status_id` that is later set for the first time.

---

### `UserObserver.php`

**What it is:** Ensures every `User` has a valid `UserProfile` association. If a user is created or updated without a valid `profile_id`, the observer assigns the default "User" profile.

**Class:** `App\Observers\UserObserver`

**Observed model:** `App\Models\User`

**Registered in:** `AppServiceProvider.php:146`:
```php
User::observe(UserObserver::class);
```

**Dependencies:**
- `App\Enums\UserRoleEnum` — used to resolve profile names to enum cases.
- `App\Models\UserProfile` — queried and created via `firstOrCreate`.

**Hooks:**

#### `creating(User $user): void`
- **Fires when:** A new `User` is about to be created (before the `INSERT` query executes).
- **Side effect:** Calls `$this->ensureValidProfile($user)`.

#### `updating(User $user): void`
- **Fires when:** An existing `User` is about to be updated (before the `UPDATE` query executes).
- **Side effect:** Calls `$this->ensureValidProfile($user)`.

**Private method `ensureValidProfile(User $user)`:**
1. If `$user->profile_id` is set:
   - Queries `UserProfile::where('id', $user->profile_id)->value('name')` to get the profile name.
   - If the profile name exists **and** maps to a valid `UserRoleEnum` case via `UserRoleEnum::tryFrom($profileName)`, returns early (profile is valid).
2. If the profile is missing or invalid:
   - Calls `UserProfile::firstOrCreate(['name' => UserRoleEnum::User->value])` to ensure the default "User" profile exists (creates it if not).
   - Assigns `$user->profile_id = $defaultProfile->id`.

**Important design note:** This is a safety net that prevents users from being created without a valid role/profile. It uses `firstOrCreate` to lazily bootstrap the default profile if it doesn't exist yet (e.g. after a fresh database migration without seeding).

---

## Notes for developers / AI

- All observers are `final readonly` classes — they cannot be extended or have their properties modified.
- `AuditObserver` throws `LogicException` on any mutation attempt — this is intentional to enforce immutability of the audit trail.
- `TicketObserver::invalidateAnalyticsCache()` forgets the `analytics_dashboard_payload:<locale>` key for the locale of the request that changed the ticket; payloads of other locales expire with the service's 60 s TTL.
- `UserObserver` uses `firstOrCreate` to lazily bootstrap the default profile if missing or invalid.
- Observers are registered in `AppServiceProvider::registerObservers()` — not in `EventServiceProvider`.
- The `creating` and `updating` hooks fire **before** the database query, while `created`, `updated`, `deleted`, and `restored` fire **after**.
