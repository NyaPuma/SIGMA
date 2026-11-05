# app/Notifications

Notification classes for delivering ticket-related alerts by e-mail.

> **New to programming?** See [NON_TECHNICAL_PROJECT_GUIDE.md](../../NON_TECHNICAL_PROJECT_GUIDE.md) -- this folder is explained as "The Alert System" that pushes notifications to users via email, browser pop-ups, or the app.

## Overview

This folder contains **2 notification classes** that implement Laravel's `Notification` contract. Each one declares the channels it supports with `via()` — both return `['mail']` — and provides the e-mail content with `toMail()`. There is no broadcast channel: neither class defines `toBroadcast()` and the application has no WebSocket driver configured.

Neither class declares the `database` channel either, and that is deliberate. The framework's `DatabaseChannel` writes its own `id` (a UUID), a `type` holding the notification class name, and leaves `user_id`, `title` and `message` empty -- but the `notifications` table in this project is the internal panel's table: `id` is a `bigint` auto-increment, `user_id`/`title`/`message` are NOT NULL and `type` is a closed enum. Every `INSERT` failed with "2 problems: datatype mismatch / NOT NULL constraint failed", and because the caller wraps delivery in a `try`, nobody noticed. The panel is written by `NotificationCreatorService`, which knows that schema; notifications go out by e-mail.

All notifications in this project implement `ShouldQueue`, meaning they are dispatched asynchronously to the queue worker rather than being sent synchronously during the HTTP request.

**Channel summary:**

| Notification | Channels | Purpose |
|---|---|---|
| `NewTicketNotification` | `mail` | E-mail to the assigned technician about a new ticket |
| `TicketStatusChanged` | `mail` | E-mail to the ticket owner about a status change |

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

**`via(object $notifiable)` — channels:** `['mail']` (see the note above about the `database` channel).

**`toMail(object $notifiable)` — email content:**
- **Subject:** `__('notifications.subject_new_ticket', ['id' => $this->ticket->id])` — e.g. "Novo Ticket #42"
- **Greeting:** `__('notifications.greeting', ['name' => $notifiable->name])` — e.g. "Olá João,"
- **Lines:**
  1. `__('notifications.new_ticket_line', ['title' => $this->ticket->title])` — "Foi criado um novo ticket: <title>"
  2. `__('notifications.priority_line', ['priority' => ucfirst((string) $this->ticket->priority)])` — "Prioridade: Alta"
- **Action button:** "Ver Ticket" → `url("/ui/tickets/{$this->ticket->id}")`
- **Closing line:** `__('notifications.follow_up_line')`
- **Salutation:** `__('notifications.salutation')`

**WHO sends it and WHEN:**
- `app/Listeners/NotifyAssignedTechnician.php:43`:
  ```php
  $technician->notify(new NewTicketNotification($ticket));
  ```
  Sent to the **assigned technician** when the `TicketStatusUpdated` event fires and the ticket has an assigned technician with a valid email.

Note: This notification is also used by the `NotifiesTicketStatusChange` trait indirectly — the trait fires `TicketStatusUpdated` which triggers `NotifyAssignedTechnician`, which sends this notification.

---

### `TicketStatusChanged.php`

**What it is:** A notification sent to the ticket owner when their ticket's status changes, informing them of the old and new status by e-mail.

**Class:** `App\Notifications\TicketStatusChanged`

**Implements:** `ShouldQueue` (queued delivery).

**Uses:** `Queueable`

**Constructor parameters:**
| Parameter | Type | Description |
|---|---|---|
| `$ticket` | `App\Models\Ticket` | The ticket whose status changed. |
| `$oldStatus` | `string` | The old status string (e.g. `'aberto'`). |
| `$newStatus` | `string` | The new status string (e.g. `'em_curso'`). |

**`via(object $notifiable)` — channels:** `['mail']` — sends an e-mail to the ticket owner (see the note above about the `database` channel).

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
- `NewTicketNotification` and `TicketStatusChanged` are mail-only. The in-app panel has its own writer (`NotificationCreatorService`) because its table does not match the framework's notification schema.
- `TicketStatusChanged::resolveStatusLabel()` maps status strings to human-friendly labels via `TicketStatusEnum`.
