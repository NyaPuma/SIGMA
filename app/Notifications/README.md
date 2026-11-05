# app/Notifications

Notification classes for delivering ticket-related alerts by mail and in the database.

> **New to programming?** See [NON_TECHNICAL_PROJECT_GUIDE.md](../../NON_TECHNICAL_PROJECT_GUIDE.md) -- this folder is explained as "The Alert System" that pushes notifications to users via email, browser pop-ups, or the app.

## Overview

This folder contains **2 notification classes** that implement Laravel's `Notification` contract. Each one declares the channels it supports with `via()` — both return `['mail', 'database']` — and provides the content for each channel with `toMail()` and `toArray()`. There is no broadcast channel: neither class defines `toBroadcast()` and the application has no WebSocket driver configured.

All notifications in this project implement `ShouldQueue`, meaning they are dispatched asynchronously to the queue worker rather than being sent synchronously during the HTTP request.

**Channel summary:**

| Notification | Channels | Purpose |
|---|---|---|
| `NewTicketNotification` | `mail`, `database` | Email + in-app alert for new tickets |
| `TicketStatusChanged` | `mail`, `database` | Email + in-app alert for status changes |

---

## Files

### `NewTicketNotification.php`

**What it is:** A notification sent to users (technicians, admins) when a new ticket is created, informing them about the ticket's title and priority with a direct link to view it.

**Class:** `App\Notifications\NewTicketNotification`

**Implements:** `ShouldQueue` (queued delivery).

**Uses:** `Queueable`

**Constructor parameters:**
| Parameter | Type | Description |
|---|---|---|
| `$ticket` | `App\Models\Ticket` | The newly created ticket (public readonly). |

**`via(object $notifiable)` — channels:**
- `mail` — sends an email to the notifiable user.
- `database` — stores a record in the `notifications` table for the in-app notification panel.

**`toMail(object $notifiable)` — email content:**
- **Subject:** `__('notifications.subject_new_ticket', ['id' => $this->ticket->id])` — e.g. "Novo Ticket #42"
- **Greeting:** `__('notifications.greeting', ['name' => $notifiable->name])` — e.g. "Olá João,"
- **Lines:**
  1. `__('notifications.new_ticket_line', ['title' => $this->ticket->title])` — "Foi criado um novo ticket: <title>"
  2. `__('notifications.priority_line', ['priority' => ucfirst((string) $this->ticket->priority)])` — "Prioridade: Alta"
- **Action button:** "Ver Ticket" → `url("/ui/tickets/{$this->ticket->id}")`
- **Closing line:** `__('notifications.follow_up_line')`
- **Salutation:** `__('notifications.salutation')`

**`toArray(object $notifiable)` — database payload:**
```json
{
  "ticket_id": 42,
  "title": "Novo Ticket",
  "message": "Novo ticket #42: Broken projector in Room B",
  "type": "info",
  "link": "/ui/tickets/42"
}
```

**WHO sends it and WHEN:**
- `app/Listeners/NotifyAssignedTechnician.php:43`:
  ```php
  $technician->notify(new NewTicketNotification($ticket));
  ```
  Sent to the **assigned technician** when the `TicketStatusUpdated` event fires and the ticket has an assigned technician with a valid email.

Note: This notification is also used by the `NotifiesTicketStatusChange` trait indirectly — the trait fires `TicketStatusUpdated` which triggers `NotifyAssignedTechnician`, which sends this notification.

---

### `TicketStatusChanged.php`

**What it is:** A notification sent to the ticket owner when their ticket's status changes, informing them of the old and new status via email and database storage.

**Class:** `App\Notifications\TicketStatusChanged`

**Implements:** `ShouldQueue` (queued delivery).

**Uses:** `Queueable`

**Constructor parameters:**
| Parameter | Type | Description |
|---|---|---|
| `$ticket` | `App\Models\Ticket` | The ticket whose status changed. |
| `$oldStatus` | `string` | The old status string (e.g. `'aberto'`). |
| `$newStatus` | `string` | The new status string (e.g. `'em_curso'`). |

**`via(object $notifiable)` — channels:**
- `mail` — sends an email to the ticket owner.
- `database` — stores a record in the `notifications` table.

**`toMail(object $notifiable)` — email content:**
- **Subject:** `__('notifications.subject_status_changed', ['id' => $this->ticket->id, 'status' => $newLabel])` — e.g. "Estado do Ticket #42 atualizado para Em Curso"
- **Greeting:** `__('notifications.greeting', ['name' => $notifiable->name])` — e.g. "Olá João,"
- **Lines:**
  1. `__('notifications.status_updated_line')` — "O estado do seu ticket foi atualizado."
  2. `__('notifications.ticket_line', ['id' => $this->ticket->id, 'title' => $this->ticket->title])` — "Ticket #42: Broken projector in Room B"
  3. `__('notifications.old_status_line', ['status' => $oldLabel])` — "Estado anterior: Aberto"
  4. `__('notifications.new_status_line', ['status' => $newLabel])` — "Novo estado: Em Curso"
- **Action button:** "Ver Ticket" → `url("/ui/tickets/{$this->ticket->id}")`
- **Closing line:** `__('notifications.thanks_line')`

**`toArray(object $notifiable)` — database payload:**
```json
{
  "ticket_id": 42,
  "title": "Estado Ticket #42",
  "message": "Ticket Broken projector in Room B atualizado para Em Curso",
  "type": "info",
  "link": "/ui/tickets/42",
  "old_status": "aberto",
  "new_status": "em_curso"
}
```
Note: The database payload includes `old_status` and `new_status` raw values (not just labels), which can be used by the frontend to render status badges.

**Private method `resolveStatusLabel(string $status)`:**
- Tries `TicketStatusEnum::tryFrom($status)` first (matches by enum value).
- If that fails, iterates all `TicketStatusEnum::cases()` and matches by `label()` (handles cases where the status string is already a label).
- Falls back to returning the raw status string if no match is found.

**WHO sends it and WHEN:**
1. `app/Listeners/SendTicketStatusNotification.php:39`:
   ```php
   $user->notify(new TicketStatusChanged(
       $ticket,
       $event->oldStatus->value,
       $event->newStatus->value
   ));
   ```
   Sent to the **ticket owner** when the `TicketStatusUpdated` event fires and the owner has a valid email.

`SendTicketStatusNotification` is the only sender. The `NotifiesTicketStatusChange` trait used
to call `$user->notify(new TicketStatusChanged(...))` right after dispatching the event, so
every transition reached the owner twice; that direct call is gone and the listener owns the
delivery.

---

## Notes for developers / AI

- All notifications implement `ShouldQueue` for async delivery.
- Notification content strings (greetings, lines, subjects) are in Portuguese — managed by the i18n project, not this refactor.
- `NewTicketNotification` and `TicketStatusChanged` use both `mail` and `database` channels.
- `TicketStatusChanged::resolveStatusLabel()` maps status strings to human-friendly labels via `TicketStatusEnum`.
- The `TicketStatusChanged` notification can be sent to the ticket owner from two independent code paths (listener + trait), which may result in duplicate notifications.
