# app/Domain/Ticket/Concerns

Traits shared by the classes that change a ticket. A concern lives here when two or more
classes need the same behaviour and copying it would let the copies drift apart.

> **New to programming?** See [NON_TECHNICAL_PROJECT_GUIDE.md](../../../../NON_TECHNICAL_PROJECT_GUIDE.md) -- these are the habits every ticket worker follows, written once instead of pasted into each class.

## `LocksTicketRow.php`

**File:** `app/Domain/Ticket/Concerns/LocksTicketRow.php`

**What it is:** `lockTicketRow(Ticket $ticket): void` re-reads the ticket under a database write
lock and puts the committed column values onto the model the caller already holds.

**Why it exists:** Controllers load a ticket, then hand that same object to an action. Two people
can act on one ticket at the same time -- an admin closing while a technician starts, two
technicians claiming the same job, two admins ruling on one budget request. Each of them judges a
status that is no longer true and the save overwrites what the other request committed (a *lost
update*). Locking the row inside the transaction makes the guard read current values and makes the
other transaction wait.

**How it works:**

1. `Ticket::whereKey($ticket->getKey())->lockForUpdate()->firstOrFail()` -- the lock is held until
   the surrounding transaction ends, so every caller must already be inside `DB::transaction`.
2. `$ticket->setRawAttributes($locked->getAttributes(), true)` -- column values are replaced, the
   model identity (`exists`, `key`) is not, so the object the controller serializes afterwards
   describes the row that was written.
3. `unsetRelation('status')` and `unsetRelation('technician')` -- both belongs-to relations are
   keyed on columns a transition moves, and the API resources serialize the cached model, so a
   stale cache would report a closed ticket as still in progress.

`firstOrFail()` respects `SoftDeletes`: if the ticket is deleted while a transition is in flight,
a `ModelNotFoundException` (rendered as 404) rolls the transaction back.

**Driver note:** SQLite (dev and tests) compiles `lockForUpdate()` to nothing, so it is a safe
no-op there; MySQL and PostgreSQL issue a real `SELECT ... FOR UPDATE`. The tests in
`tests/Feature/Domain/TicketLifecycleActionsTest.php` commit a change between loading the copy and
calling the action -- they fail without the re-read and pass with it, on every driver.

**Who uses it:**

| Class | File |
|---|---|
| `StartTicketAction` | `app/Domain/Ticket/Actions/StartTicketAction.php` |
| `CloseTicketAction` | `app/Domain/Ticket/Actions/CloseTicketAction.php` |
| `ReopenTicketAction` | `app/Domain/Ticket/Actions/ReopenTicketAction.php` |
| `CancelTicketAction` | `app/Domain/Ticket/Actions/CancelTicketAction.php` |
| `ApproveBudgetAction` | `app/Actions/ApproveBudgetAction.php` |
| `SubmitBudgetAction` | `app/Actions/SubmitBudgetAction.php` |
| `TicketBudgetController` | `app/Http/Controllers/TicketBudgetController.php` |

## Notes for developers / AI

- Call `lockTicketRow()` as the first statement inside `DB::transaction`, before any guard.
- A guard placed outside the transaction is decoration: it still reads the pre-lock snapshot.
- Queries (`app/Domain/Ticket/Queries/`) never lock -- they only read.
- `CheckHigherPriorityAction` aggregates other rows and does not lock the ticket it inspects.
