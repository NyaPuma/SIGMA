# app/Domain/Ticket

The Ticket domain contains all business logic specific to **maintenance tickets** -- the core work-order cards that move through your system from "someone reported a problem" to "the problem is resolved."

> **New to programming?** See [NON_TECHNICAL_PROJECT_GUIDE.md](../../../NON_TECHNICAL_PROJECT_GUIDE.md) -- the Ticket domain is "The Maintenance Department" where every rule about how repair requests are handled lives.

## What is a "Ticket"?

A **ticket** is like a **work-order card** you might find clipped to a broken air conditioner in an office building. It records:

- **What's broken** -- the title, description, and which room or equipment is affected
- **Who reported it** -- the user who created the ticket
- **How urgent it is** -- a priority level (Low, Medium, High, or Critical)
- **Where it stands** -- its current status (see the lifecycle below)
- **Who's fixing it** -- the assigned technician
- **What it cost** -- actual repair cost, time spent, and a technical report when closed

## The Ticket Lifecycle (Status Stations)

Think of a ticket moving through a series of **stations on a factory line**:

```
  OPEN                  IN PROGRESS               CLOSED
 [New]  ──start──>  [Being Worked On]  ──close──>  [Resolved]
   ^                      |    ^                      |
   |                      |    |                      |
   +---reopen-------------+    +---reopen-------------+
   |                                              |
   +---reopen from CANCELLED----------------------+

  Any Status ──cancel──>  CANCELLED  [No Longer Active]
```

In plain English:

1. **Open** -- A problem was just reported. The ticket is waiting in the queue.
2. **In Progress** -- A technician has picked it up and is working on it.
3. **Closed** -- The work is done. A report, cost, and time spent may be recorded.
4. **Cancelled** -- The ticket was dismissed (e.g., a duplicate, no longer needed).
5. **Reopened** -- A closed or cancelled ticket can be sent back to Open if the problem persists.

At any point, the system can also check if there are **higher-priority tickets** waiting -- useful for deciding which job to tackle next.

## How the pieces fit together

The Ticket domain is organized into five subdirectories, each with a specific responsibility:

```
Ticket/
├── Actions/         "The Workers"     -- perform each step of the lifecycle
├── Concerns/        "The Habits"      -- behaviour shared by more than one Worker
├── Queries/         "The Accountants" -- pull numbers and data for dashboards
├── Services/        "The Inspectors"  -- verify conditions before actions happen
└── ValueObjects/    "The Precision Tools" -- encapsulate specific calculations
```

| Folder | Analogy | What it does |
|--------|---------|-------------|
| `Actions/` | Workers on the factory line | Each Action is a single, focused job: start a ticket, close it, cancel it, reopen it, or check if there are more urgent jobs waiting. They **change** the ticket's state. |
| `Concerns/` | The habits every worker shares | A trait mixed into several classes instead of duplicated code. `LocksTicketRow` re-reads the ticket under a write lock before a transition, so two people acting on the same ticket at the same time cannot overwrite each other. |
| `Queries/` | Accountants pulling reports | Each Query fetches specific data for dashboards: monthly trends, KPIs, priority breakdowns, top entities, and calendar events. They **never modify** data -- they only read it. |
| `Services/` | Quality inspectors | Services check conditions before other operations proceed. For example, "Is this ticket actually in the right status for what we're about to do?" They are **reusable checks** used by Actions and controllers. |
| `ValueObjects/` | Precision measurement tools | Value Objects wrap a specific calculation into a self-contained, immutable package. For example, `BudgetPauseMinutes` calculates exactly how long a ticket was paused waiting for budget approval. |

## A real-world scenario

Here's how these pieces work together when a technician closes a maintenance ticket:

1. The technician clicks "Mark as Complete" in the admin dashboard.
2. The **controller** (`TicketCloseController` at `app/Http/Controllers/Ticket/TicketCloseController.php`) receives the HTTP request.
3. The controller calls `TicketWorkflowService::close()` from `app/Services/`.
4. `TicketWorkflowService` delegates to **`CloseTicketAction.execute()`** from `Actions/`.
5. That Action updates the ticket's status to Closed, records the cost, technical report, and time spent, and sets the `closed_at` timestamp.
6. The controller returns a success response to the frontend.
7. Later, when the dashboard loads, **`TicketKpiQuery`** and **`MonthlyTicketsQuery`** (from `Queries/`) fetch the updated counts so the charts reflect the new closed ticket.

## Complete caller map

| Domain class | Called by (production) | File:line |
|---|---|---|
| `StartTicketAction` | `TicketWorkflowService::startRepair()` | `app/Services/TicketWorkflowService.php:33` |
| `CloseTicketAction` | `TicketWorkflowService::close()` | `app/Services/TicketWorkflowService.php:57` |
| `ReopenTicketAction` | `TicketWorkflowService::reopen()` | `app/Services/TicketWorkflowService.php:41` |
| `CancelTicketAction` | `TicketWorkflowService::cancel()` | `app/Services/TicketWorkflowService.php:49` |
| `CheckHigherPriorityAction` | `TicketWorkflowService::findHigherPriorityTickets()` | `app/Services/TicketWorkflowService.php:85` |
| `TicketStatusChecker` | No production caller found | Used in tests only |
| `TicketKpiQuery` | `AnalyticsDashboardService::buildPayload()` | `app/Services/AnalyticsDashboardService.php:61` |
| `MonthlyTicketsQuery` | `AnalyticsDashboardService::buildPayload()` | `app/Services/AnalyticsDashboardService.php:63` |
| `TicketPriorityQuery` | `AnalyticsDashboardService::buildPayload()` | `app/Services/AnalyticsDashboardService.php:62` |
| `TopEntitiesQuery` | `AnalyticsDashboardService::buildPayload()` | `app/Services/AnalyticsDashboardService.php:64` |
| `ScheduledEventsQuery` | `CalendarService::getScheduledEvents()` | `app/Services/CalendarService.php:83` |
| `BudgetPauseMinutes` | `Ticket::budgetPauseMinutes()` accessor (`budget_pause_minutes` attribute) | `app/Models/Ticket.php:218` |
| `LocksTicketRow` | `StartTicketAction`, `CloseTicketAction`, `ReopenTicketAction`, `CancelTicketAction` | `app/Domain/Ticket/Actions/*.php` |
| `LocksTicketRow` | `SubmitBudgetAction`, `ApproveBudgetAction` | `app/Actions/SubmitBudgetAction.php`, `app/Actions/ApproveBudgetAction.php` |

## Notes for developers / AI

- All Action classes are `final readonly` with a single `execute()` method -- they are immutable and do one thing.
- Query classes accept their dependencies (status IDs, base queries) via constructor -- they don't resolve status names themselves.
- This is the only domain module currently. As the system grows, new domains (e.g., `User/`, `Equipment/`) would follow the same structure.
- Business rules should live here, not in controllers. Controllers are thin adapters between HTTP and domain logic.
- `TicketWorkflowService` (in `app/Services/`) orchestrates all lifecycle actions and is the main consumer of these Domain classes.
