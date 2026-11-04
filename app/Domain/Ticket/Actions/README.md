# app/Domain/Ticket/Actions

Domain-level action classes for ticket lifecycle operations. These actions handle state transitions (start, close, cancel, reopen) and priority analysis for tickets.

> **New to programming?** See [NON_TECHNICAL_PROJECT_GUIDE.md](../../../../NON_TECHNICAL_PROJECT_GUIDE.md) -- these are "The Ticket Lifecycle Specialists" who manage the step-by-step journey a repair report takes from "reported" to "resolved."

## The Big Picture

Think of Actions as **workers on a factory assembly line**. Each worker has exactly one job: pick up the ticket, do their specific task, and pass it along. They don't make decisions about the overall process -- they just execute their step correctly and efficiently.

Every Action follows the same pattern:
- It is a small, focused class with a single `execute()` method.
- It receives its dependencies (like the `TicketStatusService`) through its constructor.
- It opens a **transaction** first, so that if anything goes wrong the entire change is rolled back and nothing is left in a half-updated state.
- Inside that transaction it calls `lockTicketRow()` (from `App\Domain\Ticket\Concerns\LocksTicketRow`) to re-read the row under a write lock, then applies its **guard clauses** to the committed values: if the ticket is already in the target state, it returns `true` immediately without changing anything (this is called being **idempotent** -- doing the same thing twice gives the same result as doing it once).

The order matters. A controller hands the Action a ticket it loaded earlier, and two people can act on the same ticket at the same time (an admin closing while a technician starts, or two technicians claiming one ticket). Checking the guard against that stale copy would approve a transition the database no longer supports, and the save would then drop whatever the other request committed.

## Invocation chain

All five actions are consumed by **`TicketWorkflowService`** (`app/Services/TicketWorkflowService.php`), which is in turn consumed by four controllers:

```
TicketStartController::__invoke()
  └─> TicketWorkflowService::startRepair()
        └─> StartTicketAction::execute()

TicketCloseController::simpleClose()
  └─> TicketWorkflowService::close()
        └─> CloseTicketAction::execute()

TicketCloseController::closeFinal()
  ├─> TicketWorkflowService::findHigherPriorityTickets()
  │     └─> CheckHigherPriorityAction::execute()
  └─> TicketWorkflowService::close()
        └─> CloseTicketAction::execute()

TicketLifecycleController::reopen()
  └─> TicketWorkflowService::reopen()
        └─> ReopenTicketAction::execute()

TicketLifecycleController::cancel()
  └─> TicketWorkflowService::cancel()
        └─> CancelTicketAction::execute()

TicketStartController::__invoke()
  └─> TicketWorkflowService::findHigherPriorityTickets()
        └─> CheckHigherPriorityAction::execute()
```

---

## `CancelTicketAction.php`

**File:** `app/Domain/Ticket/Actions/CancelTicketAction.php`
**What it is:** Cancels a ticket by setting its status to "Cancelled" and recording the time it was closed.

**Dependencies (constructor):**
- `TicketStatusService $statusService` -- resolves `TicketStatusEnum::Cancelled` to the database status ID

**Traits:** `LocksTicketRow` (`app/Domain/Ticket/Concerns/LocksTicketRow.php`)

**Public methods:**

### `execute(Ticket $ticket): bool`

1. Resolves `$cancelledStatusId = $this->statusService->getByName(TicketStatusEnum::Cancelled)`.
2. Opens `DB::transaction`:
   - Calls `lockTicketRow($ticket)` so the guard reads the committed row, not the copy the controller loaded.
   - **Guard clause:** If `$ticket->status_id === $cancelledStatusId`, returns `true` immediately (idempotent -- already cancelled, and the `closed_at` another request wrote is kept).
   - Sets `$ticket->status_id = $cancelledStatusId`.
   - Sets `$ticket->closed_at = now()`.
   - Calls `$ticket->save()`.
3. Returns the save result (`bool`).

**Who calls it and when:**
- `TicketWorkflowService::cancel()` at `app/Services/TicketWorkflowService.php:49`.
- Ultimately triggered by `TicketLifecycleController::cancel()` at `app/Http/Controllers/Ticket/TicketLifecycleController.php:71` -- when a user cancels an Open ticket.
- Tests: `tests/Feature/Domain/TicketLifecycleActionsTest.php:31`, `tests/Feature/Domain/TicketLifecycleActionsTest.php:44`, `tests/Feature/Domain/TicketLifecycleActionsTest.php:189`.

---

## `CheckHigherPriorityAction.php`

**File:** `app/Domain/Ticket/Actions/CheckHigherPriorityAction.php`
**What it is:** Counts how many open tickets have a strictly higher priority than the given ticket, including how many of those are assigned to the same technician.

**Dependencies (constructor):**
- `TicketStatusService $statusService` -- resolves `TicketStatusEnum::Open` to the database status ID

**Public methods:**

### `execute(Ticket $ticket): array`

Returns `array{total: int, assigned_to_user: int, has_higher: bool}`.

1. Calls `TicketPriorityEnum::normalize($ticket->priority)` to get the normalized enum.
2. **Guard clause:** If `$normalized === null` (can't understand the priority), returns `[total: 0, assigned_to_user: 0, has_higher: false]`.
3. Gets `$currentWeight = $normalized->weight()`.
4. Filters `TicketPriorityEnum::cases()` to find all priorities with `$p->weight() > $currentWeight`.
5. **Guard clause:** If no higher priorities exist, returns `[total: 0, assigned_to_user: 0, has_higher: false]`.
6. Resolves `$openStatusId` via `$this->statusService->getByName(TicketStatusEnum::Open)`.
7. Queries the database with a single raw SQL query:
   - `COUNT(*) as total` -- total open tickets with higher priority (excluding the current ticket).
   - `SUM(CASE WHEN assigned_to = ? THEN 1 ELSE 0 END) as assigned_to_user` -- how many are assigned to this ticket's technician.
   - Filters: `status_id = $openStatusId`, `id != $ticket->id`, `priority IN $priorityValues`.
8. Casts results to `int`; returns the structured array.

**Who calls it and when:**
- `TicketWorkflowService::findHigherPriorityTickets()` at `app/Services/TicketWorkflowService.php:85`.
- Triggered by:
  - `TicketStartController::__invoke()` at `app/Http/Controllers/Ticket/TicketStartController.php:43` -- checks before starting an intervention.
  - `TicketCloseController::closeFinal()` at `app/Http/Controllers/Ticket/TicketCloseController.php:77` -- checks before final close.
- Tests: `tests/Feature/Domain/CheckHigherPriorityActionTest.php` (5 test cases).

---

## `CloseTicketAction.php`

**File:** `app/Domain/Ticket/Actions/CloseTicketAction.php`
**What it is:** Closes a ticket, optionally recording the actual cost, a technical report, and the time spent on the repair.

**Dependencies (constructor):**
- `TicketStatusService $statusService` -- resolves `TicketStatusEnum::Closed` to the database status ID

**Traits:** `LocksTicketRow` (`app/Domain/Ticket/Concerns/LocksTicketRow.php`)

**Public methods:**

### `execute(Ticket $ticket, ?float $cost = null, ?string $report = null, ?int $minutesSpent = null): bool`

1. Resolves `$closedStatusId = $this->statusService->getByName(TicketStatusEnum::Closed)`.
2. Opens `DB::transaction`:
   - Calls `lockTicketRow($ticket)`. `closed_at` is only kept when the **committed** row really has one -- the caller's copy may predate a concurrent reopen that just cleared it.
   - **Guard clause:** If `$ticket->status_id === $closedStatusId`, returns `true` immediately (idempotent -- already closed).
   - Builds `$attributes` array with: `status_id`, `closed_at` (preserves existing value or `now()`), `resolved_at` (preserves existing value or `now()`). `CalendarService` and `ScheduledEventsQuery` range finished interventions on `resolved_at`, so a close that left it null disappeared from the calendar.
   - Conditionally adds: `actual_cost` (if `$cost !== null`), `technical_report` (if `$report !== null`), `minutes_spent` (if `$minutesSpent !== null`).
   - Calls `$ticket->update($attributes)`.
3. Returns the update result (`bool`).

**Who calls it and when:**
- `TicketWorkflowService::close()` at `app/Services/TicketWorkflowService.php:57` -- wrapped in an outer `DB::transaction`.
- Triggered by:
  - `TicketCloseController::simpleClose()` at `app/Http/Controllers/Ticket/TicketCloseController.php:45` -- quick close of an in-progress ticket.
  - `TicketCloseController::closeFinal()` at `app/Http/Controllers/Ticket/TicketCloseController.php:96` -- final close with cost/report and priority verification.
- Tests: `tests/Feature/Domain/TicketLifecycleActionsTest.php:57`, `:74`, `:86`, `:224`, `:261`, `:273`.

---

## `ReopenTicketAction.php`

**File:** `app/Domain/Ticket/Actions/ReopenTicketAction.php`
**What it is:** Reopens a closed or cancelled ticket back to "Open" status, clearing the close timestamp and recording when it was reopened.

**Dependencies (constructor):**
- `TicketStatusService $statusService` -- resolves `TicketStatusEnum::Open` to the database status ID

**Traits:** `LocksTicketRow` (`app/Domain/Ticket/Concerns/LocksTicketRow.php`)

**Public methods:**

### `execute(Ticket $ticket): bool`

1. Resolves `$openStatusId = $this->statusService->getByName(TicketStatusEnum::Open)`.
2. Opens `DB::transaction`:
   - Calls `lockTicketRow($ticket)`. Reopen is the transition whose guard decides whether the request is valid at all, so it has to read the row as it stands now.
   - **Guard clause:** If the ticket is NOT Closed AND NOT Cancelled (checked via `$ticket->hasStatus()`), returns `false` -- you can't reopen a ticket that is already open or in progress, including one another request just started.
   - Sets `$ticket->status_id = $openStatusId`.
   - Sets `$ticket->reopened_at = now()`.
   - Clears `$ticket->closed_at = null` and `$ticket->resolved_at = null` -- the work is unfinished again, so it must stop counting as a resolved event in the calendar.
   - Calls `$ticket->save()`.
3. Returns the save result (`bool`).

**Who calls it and when:**
- `TicketWorkflowService::reopen()` at `app/Services/TicketWorkflowService.php:41`.
- Ultimately triggered by `TicketLifecycleController::reopen()` at `app/Http/Controllers/Ticket/TicketLifecycleController.php:33` -- when a user reopens a Closed or Cancelled ticket.
- Tests: `tests/Feature/Domain/TicketLifecycleActionsTest.php:98`, `:109`, `:208`, `:284`.

---

## `StartTicketAction.php`

**File:** `app/Domain/Ticket/Actions/StartTicketAction.php`
**What it is:** Transitions a ticket to "In Progress" status, optionally assigning a technician if one hasn't been assigned yet.

**Dependencies (constructor):**
- `TicketStatusService $statusService` -- resolves `TicketStatusEnum::InProgress` to the database status ID

**Traits:** `LocksTicketRow` (`app/Domain/Ticket/Concerns/LocksTicketRow.php`)

**Public methods:**

### `execute(Ticket $ticket, ?User $user = null): bool`

1. Resolves `$inProgressStatusId = $this->statusService->getByName(TicketStatusEnum::InProgress)`.
2. Opens `DB::transaction`:
   - Calls `lockTicketRow($ticket)`, so `$ticket->assigned_to` is the value another request last committed.
   - **Guard clause:** If `$ticket->status_id === $inProgressStatusId`, returns `true` immediately (idempotent -- already in progress).
   - Sets `$ticket->status_id = $inProgressStatusId`.
   - Sets `$ticket->assigned_to = $ticket->assigned_to ?? $user?->id` -- only assigns if not already assigned (won't overwrite).
   - Sets `$ticket->in_progress_at = $ticket->in_progress_at ?? now()` -- preserves original start time if re-entered.
   - Calls `$ticket->save()`.
3. Returns the save result (`bool`).

**Who calls it and when:**
- `TicketWorkflowService::startRepair()` at `app/Services/TicketWorkflowService.php:33`.
- Triggered by:
  - `TicketStartController::__invoke()` at `app/Http/Controllers/Ticket/TicketStartController.php:70` -- when a technician starts working on an Open ticket.
  - `TicketAssignmentController::__invoke()` at `app/Http/Controllers/Ticket/TicketAssignmentController.php:56` -- after assigning a technician, starts the repair.
- Tests: `tests/Feature/Domain/TicketLifecycleActionsTest.php:124`, `:140`, `:153`, `:170`, `:241`.

---

## Concurrency: `LocksTicketRow`

**File:** `app/Domain/Ticket/Concerns/LocksTicketRow.php` (shared with `App\Actions\ApproveBudgetAction` and `App\Actions\SubmitBudgetAction`)

`lockTicketRow(Ticket $ticket)` re-reads the row with `lockForUpdate()->firstOrFail()` inside the caller's transaction and copies the committed column values onto the model the controller passed in. Two consequences worth knowing:

- **Lost updates disappear.** The guards above evaluate the current row, and a write never silently reverts a column another transaction changed.
- **`status` and `technician` caches are dropped.** Both are belongs-to relations keyed on columns a transition moves, and the resources serialize the cached model, so leaving them would make the response describe the pre-transition status.

`firstOrFail()` respects the `SoftDeletes` trait, so deleting the ticket while a transition is in flight raises `ModelNotFoundException` (a 404) and rolls the transaction back.

`lockForUpdate()` is a no-op on SQLite (the dev and test driver compiles `SELECT ... FOR UPDATE` to nothing) and a real `FOR UPDATE` on MySQL and PostgreSQL. The tests prove the re-read by committing a change between loading the copy and calling the action: those cases fail without `lockTicketRow()` and pass with it.

---

## Notes for developers / AI

- All actions are `final readonly` with a single `execute()` method.
- Each action depends on `TicketStatusService::getByName()` to resolve enum values to database IDs.
- The four lifecycle actions use the `LocksTicketRow` concern; guard clauses run **after** the locked re-read, inside the transaction, so they judge the committed row (see "Concurrency: `LocksTicketRow`" above).
- `CheckHigherPriorityAction` only reads, so it takes no lock.
- State transitions assume the status enum values exist in the database -- `RuntimeException` is thrown if they don't.
- These actions are **not** called directly from controllers. They are called from `TicketWorkflowService` (`app/Services/TicketWorkflowService.php`), which is the intermediary that controllers use.
- The `execute()` return type is `bool` for lifecycle actions (start, close, reopen, cancel) and `array` for `CheckHigherPriorityAction`.
