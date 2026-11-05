# app/Events

Domain events for the ticket lifecycle. Each one is dispatched by the code that performed
a change and consumed by listeners that log it or notify people.

> **New to programming?** See [NON_TECHNICAL_PROJECT_GUIDE.md](../../NON_TECHNICAL_PROJECT_GUIDE.md) -- this folder is explained as "The PA System" that announces important moments to everyone who needs to know.

## Overview

This folder contains **3 event classes**. They are plain Laravel events (`Dispatchable` +
`SerializesModels`) — they do **not** broadcast over WebSockets:

- `bootstrap/app.php:26` registers events with `withEvents(discover: false)`, so only the
  explicit `$listen` map in `app/Providers/EventServiceProvider.php` runs.
- There is no `routes/channels.php`, no broadcast driver is configured (`config/broadcasting.php`
  was removed) and no frontend code subscribes with Laravel Echo. These events used to
  implement `ShouldBroadcast`/`ShouldBroadcastNow` and publish to private channels that
  nothing ever listened to; that plumbing has been removed.

If real-time delivery is wanted again, these events are the right hook: add a broadcast
driver and `routes/channels.php`, then mark the event `ShouldBroadcast` once more.

---

## Files

### `TicketCreated.php`

**What it is:** Announces that a ticket has just been inserted.

**Class:** `App\Events\TicketCreated`

**Traits used:** `Dispatchable`, `SerializesModels`

**Constructor parameters:**

| Parameter | Type | Notes |
|-----------|------|-------|
| `$ticket` | `Ticket` | The newly created ticket (public property). |
| `$creator` | `User` | The requester, taken from `$ticket->user`. |
| `$createdAt` | `?CarbonImmutable` | Optional timestamp; defaults to `CarbonImmutable::now()`. |

**Public properties:** `$ticket`, `$creator`, `$createdAt`.

**Dispatched by:** `app/Observers/TicketObserver.php:38` — `created()` fires it only when the
ticket has an owner, so there is always a person behind the record.

**Listeners** (`app/Providers/EventServiceProvider.php:24`):
- `App\Listeners\SendTicketCreatedNotification` — notifies the admins through
  `NotificationService::notifyTicketCreated($event->ticket)`.

---

### `TicketStatusChanged.php`

**What it is:** Announces that a ticket moved from one status to another, detected at the
database level. Because it comes from the observer it also catches statuses written by
actions, seeds or queued jobs — anything that saves the row.

**Class:** `App\Events\TicketStatusChanged`

**Traits used:** `Dispatchable`, `SerializesModels`

**Constructor parameters:**

| Parameter | Type | Notes |
|-----------|------|-------|
| `$ticket` | `Ticket` | The ticket whose status changed. |
| `$oldStatus` | `TicketStatusEnum\|string` | Normalized in the constructor. |
| `$newStatus` | `TicketStatusEnum\|string` | Normalized in the constructor. |
| `$changedBy` | `?User` | Optional actor. The observer passes `auth()->user()` because its listener is queued and the session is gone by the time it runs. |
| `$changedAt` | `?CarbonImmutable` | Optional timestamp; defaults to `CarbonImmutable::now()`. |

**Public properties:** `$ticket`, `$oldStatus`, `$newStatus` (both always `TicketStatusEnum`
after construction), `$changedBy`, `$changedAt`.

**Constructor normalization:** `TicketStatusEnum::normalize()` coerces accents, case and
aliases; an unrecognized string falls back to `TicketStatusEnum::Open` instead of throwing.

**Dispatched by:** `app/Observers/TicketObserver.php:62` — `updated()`, guarded by
`$ticket->wasChanged('status_id')` and only when the previous `status_id` was not null, so a
status written on insert is not reported as a change.

**Listeners** (`app/Providers/EventServiceProvider.php:27`):
- `App\Listeners\LogTicketWorkflowChange` — writes one `TicketWorkflowHistory` row per
  transition.

---

### `TicketStatusUpdated.php`

**What it is:** Announces a status transition that a ticket controller has just committed.
Its job is to trigger the notification pipeline for the requester and the technician.

**Class:** `App\Events\TicketStatusUpdated`

**Traits used:** `Dispatchable`, `SerializesModels`

**Constructor parameters:**

| Parameter | Type | Notes |
|-----------|------|-------|
| `$ticket` | `Ticket` | The ticket whose status changed. |
| `$oldStatus` | `TicketStatusEnum\|string` | Normalized in the constructor. |
| `$newStatus` | `TicketStatusEnum\|string` | Normalized in the constructor. |
| `$changedAt` | `?CarbonImmutable` | Optional timestamp; defaults to `CarbonImmutable::now()`. |

**Public properties:** `$ticket`, `$oldStatus`, `$newStatus`, `$changedAt`.

**Constructor normalization:** Same as `TicketStatusChanged`.

**Dispatched by:** `app/Concerns/NotifiesTicketStatusChange.php:32` — the
`notifyStatusChange()` trait method, called from:

1. `app/Http/Controllers/Ticket/TicketLifecycleController.php:43` and `:77`
2. `app/Http/Controllers/Ticket/TicketAssignmentController.php:63`
3. `app/Http/Controllers/Ticket/TicketCloseController.php:55` and `:115`
4. `app/Http/Controllers/Ticket/TicketStartController.php:82`

The trait wraps the dispatch in `try/catch (Throwable)` and logs a warning, so a
notification failure cannot roll back a transition that is already persisted.

**Listeners** (`app/Providers/EventServiceProvider.php:30`):
- `App\Listeners\SendTicketStatusNotification` — sends `App\Notifications\TicketStatusChanged`
  to the ticket owner.
- `App\Listeners\NotifyAssignedTechnician` — sends `App\Notifications\NewTicketNotification`
  to the assigned technician, and returns without notifying when `assigned_to` is null.

**History:** this class was called `TicketStatusUpdatedBroadcast` and implemented
`ShouldBroadcastNow`. The name now matches what the event actually does.

---

## Comparison: TicketStatusChanged vs TicketStatusUpdated

| Aspect | `TicketStatusChanged` | `TicketStatusUpdated` |
|--------|-----------------------|------------------------|
| **Fired by** | `TicketObserver::updated()` | `NotifiesTicketStatusChange` trait (4 controllers) |
| **Catches** | Every write to `status_id`, including seeds and jobs | Only transitions made through those controllers |
| **Knows the actor** | Yes (`$changedBy`) | No |
| **Listeners** | `LogTicketWorkflowChange` | `SendTicketStatusNotification`, `NotifyAssignedTechnician` |

They overlap on purpose: one records *what* happened for the workflow history, the other
decides *who has to be told*. A transition made through a controller therefore emits both
events — `TicketStatusUpdated` explicitly, `TicketStatusChanged` because the same save also
triggers the observer.

## Notes

- Every listener implements `ShouldQueue`, so with a non-`sync` `QUEUE_CONNECTION` the work
  leaves the request; that is why `$changedBy` is captured eagerly in the observer.
- Delivery of the "status changed" message belongs to `SendTicketStatusNotification` alone.
  The trait used to call `$user->notify()` in addition to firing the event, which made every
  transition reach the owner twice.
- The events carry models and enums, not pre-rendered payloads: listeners read `$event->ticket`
  and `$event->newStatus->label()` themselves, so there is no duplicated presentation layer.
