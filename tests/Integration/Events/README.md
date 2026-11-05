# Events -- Automated Integration Tests

> **New to programming?** See [NON_TECHNICAL_PROJECT_GUIDE.md](../../../NON_TECHNICAL_PROJECT_GUIDE.md) -- this is part of "The Quality Assurance Lab." Integration tests check how different parts of the system work together.

## What is this folder?

Integration tests for the **ticket event pipeline**: they dispatch a real event and check
that the listeners registered in `EventServiceProvider` actually run and produce the right
side effect (notifications sent, rows written).

Formerly `tests/Integration/Broadcasting`, which asserted WebSocket channel payloads that no
client ever consumed.

## What Gets Tested

| Test | What it proves |
|---|---|
| `TicketStatusUpdatedEventTest` | `TicketStatusUpdated` reaches `SendTicketStatusNotification` (owner) and `NotifyAssignedTechnician` (technician) |

## How to run these tests

```bash
# All tests in this folder
php artisan test tests/Integration/Events

# A specific test
php artisan test tests/Integration/Events --filter=TicketStatusUpdatedEventTest
```
