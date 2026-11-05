# Domain -- Automated Domain Logic Tests

> **New to programming?** See [NON_TECHNICAL_PROJECT_GUIDE.md](../../../NON_TECHNICAL_PROJECT_GUIDE.md) -- this folder is part of "The Quality Assurance Lab" covering the core business rules of ticket management.

## What is this folder?

Tests for the **core business rules** of the ticket system -- the rules that govern how tickets move through their lifecycle.

## What Gets Tested

| Test | What It Verifies |
|------|------------------|
| `TicketLifecycleActionsTest` | The full ticket journey works: open → in-progress → budget review → closed |
| `TicketQueriesTest` | Dashboard queries return correct data (counts, KPIs, top entities) |
| `CheckHigherPriorityActionTest` | Logic that checks whether higher-priority tickets exist works correctly |

## How to run these tests

```bash
# All domain tests
php artisan test tests/Feature/Domain

# A single test
php artisan test tests/Feature/Domain --filter=TicketLifecycleActionsTest
```
