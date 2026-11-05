# app/Listeners

Event listeners that handle side effects when ticket events are dispatched. All listeners are queued.

> **New to programming?** See [NON_TECHNICAL_PROJECT_GUIDE.md](../../NON_TECHNICAL_PROJECT_GUIDE.md) -- this folder is explained as "The Follow-Up Team" that automatically reacts when something important happens.

## Overview

This folder contains **4 listener classes** that respond to the 3 ticket events (`TicketCreated`, `TicketStatusChanged`, `TicketStatusUpdated`). All listeners implement `ShouldQueue` and use `InteractsWithQueue`, meaning they run asynchronously in the queue worker after the event is dispatched.

**Event → Listener mapping** (defined in `app/Providers/EventServiceProvider.php`):

```
TicketCreated
  └── SendTicketCreatedNotification

TicketStatusChanged
  └── LogTicketWorkflowChange

TicketStatusUpdated
  ├── SendTicketStatusNotification
  └── NotifyAssignedTechnician
```

Event discovery is **disabled** — all mappings come from the explicit `$listen` array. It has to be switched off in `bootstrap/app.php` with `->withEvents(discover: false)`: `Application::configure()` also registers the framework provider, whose own `shouldDiscoverEvents()` is untouched by the override in `App\Providers\EventServiceProvider`, and it re-registered every listener a second time as `Class@handle`.

---

## Files


### `LogTicketWorkflowChange.php`

**What it is:** The only writer of `TicketWorkflowHistory` for status transitions. It listens to `TicketStatusChanged`, which `TicketObserver` fires whenever `status_id` changes, so it covers model-level writes and controller actions alike.

**Class:** `App\Listeners\LogTicketWorkflowChange`

**Triggers on:** `App\Events\TicketStatusChanged`

**Registered in:** `EventServiceProvider.php:28-30`:
```php
TicketStatusChanged::class => [
    LogTicketWorkflowChange::class,
],
```

**Dependencies:**
- `App\Models\TicketStatus` — queried to resolve status name strings to database IDs.
- `App\Models\TicketWorkflowHistory` — the model where the log record is created.

**`handle(TicketStatusChanged $event)` logic:**
1. Resolves both status IDs in one query: `TicketStatus::whereIn('name', [old, new])->get(['id', 'name'])->keyBy('name')`.
2. Creates a `TicketWorkflowHistory` record with:
   - `ticket_id` — from `$event->ticket->id`
   - `origin_status_id` / `destination_status_id` — from the map above (null when a name is unknown)
   - `technician_id` — `$event->changedBy?->id ?? auth()->id() ?? $event->ticket->assigned_to`. The observer captures the acting user while the request still has one, because the queued listener has no authenticated user of its own.
   - `comment` — `'Status changed from "aberto" to "em_curso".'`

`App\Listeners\LogTicketStatusChange` used to write the same transition from
`TicketStatusUpdated` as well; it was removed, since one transition must
produce exactly one history row.

**`failed()` logic:**
- Logs an error with the ticket ID and error message via `Log::error()`.

**Queue configuration:**
- `$tries = 3`

---

### `NotifyAssignedTechnician.php`

**What it is:** Sends a `NewTicketNotification` (mail + database) to the technician assigned to a ticket when the ticket's status changes.

**Class:** `App\Listeners\NotifyAssignedTechnician`

**Triggers on:** `App\Events\TicketStatusUpdated`

**Registered in:** `EventServiceProvider.php:32-35`:
```php
TicketStatusUpdated::class => [
    SendTicketStatusNotification::class,
    NotifyAssignedTechnician::class,
],
```

**Dependencies:**
- `App\Models\User` — used to check if the technician exists and has an email.
- `App\Notifications\NewTicketNotification` — the notification class sent to the technician.

**`handle(TicketStatusUpdated $event)` logic:**
1. Gets the ticket from the event.
2. Checks if `$ticket->assigned_to` is set. If not, returns early (no technician assigned).
3. Loads the `$ticket->technician` relationship (the `User` model for the assigned technician).
4. If the technician is a valid `User` instance with a non-null `email`, sends `new NewTicketNotification($ticket)` to them via `$technician->notify(...)`.
5. The `NewTicketNotification` sends an email + database notification to the technician about the ticket.

**`failed()` logic:**
- Logs a warning with the ticket ID, assigned technician ID, and error message.

**Queue configuration:**
- `$tries = 3`
- `$backoff = [5, 15, 30]`

---

### `SendTicketCreatedNotification.php`

**What it is:** Delegates new-ticket notification logic to `NotificationService::notifyTicketCreated()` when a `TicketCreated` event fires. This is the only listener for the `TicketCreated` event.

**Class:** `App\Listeners\SendTicketCreatedNotification`

**Triggers on:** `App\Events\TicketCreated`

**Registered in:** `EventServiceProvider.php:25-27`:
```php
TicketCreated::class => [
    SendTicketCreatedNotification::class,
],
```

**Dependencies:**
- `App\Services\NotificationService` — injected via constructor. This is a singleton service registered in `AppServiceProvider`.

**Constructor:**
```php
public function __construct(
    private readonly NotificationService $notificationService,
) {}
```

**`handle(TicketCreated $event)` logic:**
1. Calls `$this->notificationService->notifyTicketCreated($event->ticket)`.
2. This delegates to `BudgetNotificationService::notifyTicketCreated()` which calls `NotificationCreatorService::createForAdmins()` to create an in-app notification for every admin user with:
   - `title`: `"New Ticket - #{$ticket->id}"`
   - `message`: `"New ticket created: {$ticket->title}"`
   - `type`: `NotificationTypeEnum::TicketCreated->value`
   - `link`: `"/ui/tickets/{$ticket->id}"`

**`failed()` logic:**
- Logs an error with the ticket ID and error message.

**Queue configuration:**
- `$tries = 3`
- `$backoff = [5, 15, 30]`

---

### `SendTicketStatusNotification.php`

**What it is:** Sends a `TicketStatusChanged` notification (mail + database) to the ticket owner when the ticket's status changes.

**Class:** `App\Listeners\SendTicketStatusNotification`

**Triggers on:** `App\Events\TicketStatusUpdated`

**Registered in:** `EventServiceProvider.php:32-35`:
```php
TicketStatusUpdated::class => [
    SendTicketStatusNotification::class,
    NotifyAssignedTechnician::class,
],
```

**Dependencies:**
- `App\Models\User` — used to check if the ticket owner exists and has an email.
- `App\Notifications\TicketStatusChanged` — the notification class sent to the ticket owner.

**`handle(TicketStatusUpdated $event)` logic:**
1. Gets the ticket from the event.
2. Loads the `$ticket->user` relationship (the `User` model for the ticket creator/owner).
3. If the user is a valid `User` instance with a non-null `email`, sends `new TicketStatusChanged($ticket, $event->oldStatus->value, $event->newStatus->value)` to them via `$user->notify(...)`.
4. The `TicketStatusChanged` notification sends an email + database notification with the old and new status labels.

**`failed()` logic:**
- Logs a warning with the ticket ID and error message.

**Queue configuration:**
- `$tries = 3`
- `$backoff = [5, 15, 30]`

---

## Notes for developers / AI

- All listeners implement `ShouldQueue` and use `InteractsWithQueue`.
- Common `$tries = 3` and `$backoff = [5, 15, 30]` across notification listeners (`SendTicketStatusNotification`, `NotifyAssignedTechnician`, `SendTicketCreatedNotification`).
- `LogTicketWorkflowChange` looks both statuses up in a single `whereIn` query to avoid N+1.
- `LogTicketWorkflowChange` does two separate queries (one per status) — a simpler but less optimized approach.
- `NotifyAssignedTechnician` and `SendTicketStatusNotification` both guard against missing relationships (`assigned_to` being null, `user` not having an email) to avoid exceptions on edge-case tickets.
- `SendTicketCreatedNotification` is the only listener that receives its dependency via constructor injection (`NotificationService`); the other 4 listeners resolve their dependencies inline from the event or use facade calls.
