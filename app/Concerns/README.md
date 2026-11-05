# app/Concerns

Reusable traits for cross-cutting application behavior — right now, one trait that turns a
committed ticket status transition into the notifications people expect.

> **New to programming?** See [NON_TECHNICAL_PROJECT_GUIDE.md](../../NON_TECHNICAL_PROJECT_GUIDE.md) -- this folder is explained as "The Shared Toolbelt" -- reusable behaviors that any worker can pick up and use.

## Files

| File | Purpose |
|---|---|
| [`NotifiesTicketStatusChange.php`](./NotifiesTicketStatusChange.php) | Provides `notifyStatusChange()`, the single way a ticket controller announces "this ticket changed state": it dispatches the `App\Events\TicketStatusUpdated` event, whose listeners mail the owner and the assigned technician. |

---

## `NotifiesTicketStatusChange.php`

**File:** `app/Concerns/NotifiesTicketStatusChange.php`

### What reusable behavior it provides

A single, consistent way for any ticket controller to **report a ticket status transition**.
When a controller calls `notifyStatusChange()`, the concern:

1. normalizes both statuses to raw string values (`TicketStatusEnum`, `TicketStatus` model,
   plain string, or `null`), and
2. dispatches the `App\Events\TicketStatusUpdated` event through Laravel's `event()` helper.

The actual messaging happens in the listeners registered for that event
(`SendTicketStatusNotification`, `NotifyAssignedTechnician`) — see `app/Providers/EventServiceProvider.php`.

### Method

| Member | Signature | Purpose |
|---|---|---|
| `notifyStatusChange()` | `protected function notifyStatusChange(Ticket $ticket, TicketStatusEnum\|TicketStatus\|string\|null $oldStatus, TicketStatusEnum\|TicketStatus\|string\|null $newStatus): void` | Maps each status through the private `normalizeStatusValue()` (`TicketStatusEnum->value`, `TicketStatus->name`, non-empty string as-is, anything else → `TicketStatusEnum::Open->value`), then dispatches `new TicketStatusUpdated($ticket, $oldStatusValue, $newStatusValue)`. The dispatch is wrapped in `try/catch (Throwable)` → `Log::warning('Failed to dispatch ticket status change.', ['ticket_id', 'old_status', 'new_status', 'error'])`. |

### Which classes use it

Grep of `use App\Concerns\NotifiesTicketStatusChange` across `app/Http/Controllers`:

| Class | Import | Calls to `notifyStatusChange()` |
|---|---|---|
| `App\Http\Controllers\Ticket\TicketLifecycleController` | `app/Http/Controllers/Ticket/TicketLifecycleController.php:5` | `:43` (reopen), `:77` (cancel) |
| `App\Http\Controllers\Ticket\TicketStartController` | `app/Http/Controllers/Ticket/TicketStartController.php:5` | `:82` (start → `InProgress`) |
| `App\Http\Controllers\Ticket\TicketCloseController` | `app/Http/Controllers/Ticket/TicketCloseController.php:5` | `:55` and `:115` (close → `Closed`) |
| `App\Http\Controllers\Ticket\TicketAssignmentController` | `app/Http/Controllers/Ticket/TicketAssignmentController.php:5` | `:63` (assign → `InProgress`) |

Each of these controllers declares `use NotifiesTicketStatusChange;` (e.g.
`app/Http/Controllers/Ticket/TicketLifecycleController.php:16`), which makes the protected
method available. The values passed are the `oldStatus` the controller captured *before* the
workflow mutation and the new status (a `TicketStatusEnum` constant such as `InProgress`,
`Cancelled`, `Closed`, or the refreshed `$ticket->status`).

## Notes for Developers & AI

- **Non-blocking error handling:** the dispatch sits in `try/catch (Throwable)` with a
  `Log::warning`, so a listener that cannot serialize or a mailer that is down cannot undo a
  transition that is already committed.
- **Type flexibility:** each status argument accepts a `TicketStatusEnum`, a `TicketStatus`
  model, a string, or `null` — the caller can pass exactly what it captured.
- **Ownership of the mail:** this trait only dispatches. `SendTicketStatusNotification` is the
  only place the owner receives the "status changed" notification; the trait used to call
  `$user->notify()` here as well, which delivered the same message twice per transition.
- **Tests:** `tests/Unit/Concerns/NotifiesTicketStatusChangeTest.php` covers the four accepted
  status shapes, the owner-less ticket, and the "nothing is sent directly from the trait" rule.
