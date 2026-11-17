# Integration -- Automated Integration Tests

> **New to programming?** See [NON_TECHNICAL_PROJECT_GUIDE.md](../../NON_TECHNICAL_PROJECT_GUIDE.md) -- this folder is explained as part of "The Quality Assurance Lab." Integration tests verify different parts of the system work together correctly -- like testing that the kitchen, cash register, and delivery service all coordinate properly.

## What is this folder?

These tests verify that **different subsystems cooperate correctly**. While unit tests check parts in isolation, integration tests connect the parts and confirm they work as a whole.

## What Gets Tested

| Area | What It Verifies |
|------|------------------|
| **Events** | Ticket events reach the listeners that send notifications (a status change really notifies the owner and the technician) |
| **Queues** | Background jobs are queued and processed correctly (exports, emails, notifications) |
| **Database** | Foreign key integrity (you can't delete a room that still has equipment in it), soft deletes, model lifecycles, mass assignment protection, relationship correctness |
| **Mail** | Email delivery works correctly (test email through the configured mailer) |

## How to run these tests

```bash
# All integration tests
php artisan test tests/Integration

# A specific area
php artisan test tests/Integration/Database
php artisan test tests/Integration/Events
php artisan test tests/Integration/Mail

# A single test
php artisan test tests/Integration --filter=SoftDeleteTest
```