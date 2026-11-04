# app/Events

Broadcast events for real-time ticket status updates via WebSockets (Laravel Echo).

> **New to programming?** See [NON_TECHNICAL_PROJECT_GUIDE.md](../../NON_TECHNICAL_PROJECT_GUIDE.md) -- this folder is explained as "The PA System" that broadcasts important announcements to everyone who needs to know.

## Overview

This folder contains **3 event classes** that handle real-time broadcasting of ticket lifecycle moments to the frontend via WebSockets (Laravel Echo / Reverb / Pusher). All events implement either `ShouldBroadcast` (queued broadcast — processed by the queue worker) or `ShouldBroadcastNow` (synchronous broadcast — dispatched immediately in the request lifecycle).

Each event defines:
- **`broadcastOn()`** — which private WebSocket channels receive the event.
- **`broadcastAs()`** — the event name string emitted to Laravel Echo (e.g. `ticket.created`).
- **`broadcastWith()`** — the JSON payload sent over the wire, including pre-computed `label()` and `color()` values from PHP enums so the frontend can render status/priority badges without any mapping logic.

---

## Files

### `TicketCreated.php`

**What it is:** An event that announces a new ticket has been created. It is broadcast over WebSockets so the admin panel and the creator's own dashboard can show the new ticket in real time without a page refresh.

**Class:** `App\Events\TicketCreated`

**Implements:** `ShouldBroadcast` (queued — the broadcast is dispatched via the queue worker, not synchronously).

**Traits used:** `Dispatchable`, `InteractsWithSockets`, `SerializesModels`

**Constructor parameters:**
| Parameter | Type | Description |
|---|---|---|
| `$ticket` | `App\Models\Ticket` | The newly created ticket (serialized via `SerializesModels`). |
| `$creator` | `App\Models\User` | The user who created the ticket. |
| `$createdAt` | `?CarbonImmutable` | Optional timestamp; defaults to `CarbonImmutable::now()`. |

**Public properties (broadcast payload):**
- `$ticket` — the `Ticket` model.
- `$creator` — the `User` model.
- `$createdAt` — `CarbonImmutable` timestamp.

**`broadcastOn()` — channels:**
1. `private("users.{$this->creator->id}")` — the creator's personal channel.
2. `private('tickets.admin')` — the shared admin channel.

**`broadcastAs()` — event name:** `ticket.created`

**`broadcastWith()` — payload structure:**
```json
{
  "ticket_id": 42,
  "code": "TKT-00042",
  "title": "Broken projector in Room B",
  "status": {
    "value": "aberto",
    "label": "Aberto",
    "color": "#..."
  },
  "priority": {
    "value": "alta",
    "label": "Alta",
    "color": "#..."
  },
  "creator": {
    "id": 1,
    "name": "João Silva",
    "email": "joao@example.com"
  },
  "created_at": "2026-09-02T14:30:00+00:00"
}
```
The `status` and `priority` objects include `value`, `label()`, and `color()` pre-computed from `TicketStatusEnum` and `TicketPriorityEnum` respectively, so the frontend can render colored badges directly.

**WHO fires it and WHEN:**
- `app/Observers/TicketObserver.php:22` — in the `created()` hook:
  ```php
  event(new TicketCreated($ticket, $ticket->who));
  ```
  This fires immediately after a `Ticket` model is created and saved to the database. The observer checks that `$ticket->user` exists before dispatching.

**Who listens:**
- `App\Listeners\SendTicketCreatedNotification` — sends an in-app notification to all admins via `NotificationService::notifyTicketCreated()`.

---

### `TicketStatusChanged.php`

**What it is:** An event that announces a ticket's status has changed (e.g. from "Open" to "In Progress"). It broadcasts over WebSockets and carries the old/new status with labels, colors, and the identity of the user who made the change.

**Class:** `App\Events\TicketStatusChanged`

**Implements:** `ShouldBroadcast` (queued broadcast).

**Traits used:** `Dispatchable`, `InteractsWithSockets`, `SerializesModels`

**Constructor parameters:**
| Parameter | Type | Description |
|---|---|---|
| `$ticket` | `App\Models\Ticket` | The ticket whose status changed. |
| `$oldStatus` | `TicketStatusEnum\|string` | The previous status (enum or string — normalized to enum in constructor). |
| `$newStatus` | `TicketStatusEnum\|string` | The new status (enum or string — normalized to enum in constructor). |
| `$changedBy` | `?User` | The user who performed the change (nullable). |
| `$changedAt` | `?CarbonImmutable` | Optional timestamp; defaults to `CarbonImmutable::now()`. |

**Public properties (broadcast payload):**
- `$ticket` — the `Ticket` model.
- `$oldStatus` — `TicketStatusEnum` (always an enum after normalization).
- `$newStatus` — `TicketStatusEnum` (always an enum after normalization).
- `$changedBy` — nullable `User` model.
- `$changedAt` — `CarbonImmutable` timestamp.

**Constructor normalization:** If `$oldStatus` or `$newStatus` are strings, they are passed through `TicketStatusEnum::normalize()`. If normalization fails, they default to `TicketStatusEnum::Open`.

**`broadcastOn()` — channels:**
1. `private("tickets.{$this->ticket->id}")` — channel specific to this ticket.
2. `private("users.{$this->ticket->user_id}")` — the ticket owner's personal channel.
3. `private('tickets.admin')` — the shared admin channel.

**`broadcastAs()` — event name:** `ticket.status_changed`

**`broadcastWith()` — payload structure:**
```json
{
  "ticket_id": 42,
  "code": "TKT-00042",
  "old_status": {
    "value": "aberto",
    "label": "Aberto",
    "color": "#..."
  },
  "new_status": {
    "value": "em_curso",
    "label": "Em Curso",
    "color": "#..."
  },
  "changed_by": {
    "id": 5,
    "name": "Maria Santos"
  },
  "is_final": false,
  "changed_at": "2026-09-02T15:00:00+00:00"
}
```
- `changed_by` is `null` if `$changedBy` was not provided.
- `is_final` is computed from `$this->newStatus->isFinal()` — `true` if the new status is a terminal state (e.g. "Closed").

**WHO fires it and WHEN:**
- `app/Observers/TicketObserver.php:41` — in the `updated()` hook:
  ```php
  event(new TicketStatusChanged(
      $ticket,
      $oldStatusName ?? TicketStatusEnum::Open->value,
      $newStatusName ?? TicketStatusEnum::Open->value,
  ));
  ```
  This fires when a `Ticket` model is updated **and** the `status_id` field has changed. The observer looks up the old and new `TicketStatus` names from the database (via `TicketStatus::where('id', ...)->value('name')`) and dispatches the event with string values. Note: `$changedBy` is **not passed** in this path — it is `null`.

**Who listens:**
- `App\Listeners\LogTicketWorkflowChange` — logs the status transition to `TicketWorkflowHistory`.

---

### `TicketStatusUpdatedBroadcast.php`

**What it is:** An event that broadcasts a ticket status change **immediately** (synchronously, not queued) for real-time WebSocket delivery. It also triggers three queued listeners for workflow logging, email notifications, and technician notifications.

**Class:** `App\Events\TicketStatusUpdatedBroadcast`

**Implements:** `ShouldBroadcastNow` (synchronous — dispatched in the current request, not via the queue).

**Traits used:** `Dispatchable`, `InteractsWithSockets`, `SerializesModels`

**Constructor parameters:**
| Parameter | Type | Description |
|---|---|---|
| `$ticket` | `App\Models\Ticket` | The ticket whose status changed. |
| `$oldStatus` | `TicketStatusEnum\|string` | The previous status (normalized to enum). |
| `$newStatus` | `TicketStatusEnum\|string` | The new status (normalized to enum). |
| `$broadcastedAt` | `?CarbonImmutable` | Optional timestamp; defaults to `CarbonImmutable::now()`. |

**Public properties (broadcast payload):**
- `$ticket` — the `Ticket` model.
- `$oldStatus` — `TicketStatusEnum`.
- `$newStatus` — `TicketStatusEnum`.
- `$broadcastedAt` — `CarbonImmutable` timestamp.

**Constructor normalization:** Same as `TicketStatusChanged` — strings are normalized via `TicketStatusEnum::normalize()`, defaulting to `TicketStatusEnum::Open`.

**`broadcastOn()` — channels:**
1. `private("tickets.{$this->ticket->id}")` — channel specific to this ticket.
2. `private("users.{$this->ticket->user_id}")` — the ticket owner's personal channel.
3. `private('tickets.admin')` — the shared admin channel.

**`broadcastAs()` — event name:** `ticket.status.updated`

**`broadcastWith()` — payload structure:**
```json
{
  "id": 42,
  "code": "TKT-00042",
  "title": "Broken projector in Room B",
  "old_status": {
    "value": "aberto",
    "label": "Aberto",
    "color": "#..."
  },
  "new_status": {
    "value": "em_curso",
    "label": "Em Curso",
    "color": "#..."
  },
  "is_final": false,
  "updated_at": "2026-09-02T15:00:00+00:00"
}
```
Key differences from `TicketStatusChanged`:
- Uses `id` instead of `ticket_id`.
- Includes `title` (not present in `TicketStatusChanged`).
- Does **not** include `changed_by` — this event is broadcast-only, not user-attribution focused.
- Uses `updated_at` instead of `changed_at`.

**WHO fires it and WHEN:**
- `app/Concerns/BroadcastsTicketStatus.php:27` — `broadcastStatusChange()` trait method:
  ```php
  event(new TicketStatusUpdatedBroadcast($ticket, $oldStatusValue, $newStatusValue));
  ```
  This trait is used by **4 controllers** that change ticket status:
  1. `app/Http/Controllers/Ticket/TicketLifecycleController.php:16` — `use BroadcastsTicketStatus;`
  2. `app/Http/Controllers/Ticket/TicketAssignmentController.php:17` — `use BroadcastsTicketStatus;`
  3. `app/Http/Controllers/Ticket/TicketCloseController.php:19` — `use BroadcastsTicketStatus;`
  4. `app/Http/Controllers/Ticket/TicketStartController.php:17` — `use BroadcastsTicketStatus;`

  The `broadcastStatusChange()` method also directly sends a `TicketStatusChanged` notification to the ticket owner via `$user->notify(new TicketStatusChanged(...))`.

**Who listens (via EventServiceProvider `$listen` mapping):**
1. `App\Listeners\SendTicketStatusNotification` — sends an email + database `TicketStatusChanged` notification to the ticket owner.

3. `App\Listeners\NotifyAssignedTechnician` — sends a `NewTicketNotification` to the assigned technician (if one exists).

---

## Comparison: TicketStatusChanged vs TicketStatusUpdatedBroadcast

| Aspect | `TicketStatusChanged` | `TicketStatusUpdatedBroadcast` |
|---|---|---|
| **Broadcast interface** | `ShouldBroadcast` (queued) | `ShouldBroadcastNow` (sync) |
| **Event name** | `ticket.status_changed` | `ticket.status.updated` |
| **Fired by** | `TicketObserver::updated()` | `BroadcastsTicketStatus` trait (4 controllers) |
| **Includes `changed_by`?** | Yes | No |
| **Includes `title`?** | No | Yes |
| **Payload key for ticket ID** | `ticket_id` | `id` |
| **Listeners** | `LogTicketWorkflowChange` | `SendTicketStatusNotification`, `NotifyAssignedTechnician` |
| **Purpose** | Observer-level: fires on every Eloquent update | Controller-level: fires only on explicit user-initiated status changes |

Both events broadcast to the same 3 private channels, but `TicketStatusUpdatedBroadcast` is the "richer" event used for the full notification pipeline (listeners + email + in-app), while `TicketStatusChanged` is the observer-level event that only triggers workflow logging.

---

## Notes for developers / AI

- All events use `PrivateChannel` for authenticated broadcasting — the frontend must be authenticated via Laravel Echo's private channel auth.
- Payload arrays include pre-computed `label()` and `color()` from PHP enums for direct UI consumption — the frontend does not need to map enum values to labels.
- `TicketStatusUpdatedBroadcast` uses `ShouldBroadcastNow` because it needs to reach the WebSocket server immediately within the HTTP request lifecycle, while `TicketCreated` and `TicketStatusChanged` use `ShouldBroadcast` (queued) because they are fired from Eloquent observers where immediate delivery is less critical.
- The `BroadcastsTicketStatus` trait also sends a `TicketStatusChanged` notification directly via `$user->notify()` in addition to firing the event — this creates a dual notification path (event-based via listener + direct in the trait).
