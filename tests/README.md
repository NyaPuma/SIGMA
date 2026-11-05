# tests/ -- Automated Test Suite

> **New to programming?** See [NON_TECHNICAL_PROJECT_GUIDE.md](../NON_TECHNICAL_PROJECT_GUIDE.md) for a plain-English explanation of what tests are and why they matter.

## What are automated tests?

Think of automated tests as **proofreaders for your code**. Just like a proofreader re-reads a book to catch typos before it goes to print, automated tests re-check every part of the application every time someone changes code -- making sure nothing is broken, nothing was forgotten, and nothing works in a way it shouldn't.

This project has **1,410+ tests** because the SGM maintenance-management system handles real-world data: work orders, budgets, equipment, user accounts, and security-sensitive operations. Each time a developer adds a feature or fixes a bug, they (or the team) run these tests to confirm that the change didn't accidentally break something elsewhere. The tests act as a safety net -- they run in seconds, check everything, and report exactly what passed and what failed.

---

## Beginner glossary: types of tests in plain English

| Test type | What it is (plain English) | Analogy |
|-----------|---------------------------|---------|
| **Unit test** | Checks one small piece of the code *in isolation* -- a single function, a single action class, a single enum. It doesn't talk to a real database or send real HTTP requests. | Testing that a single gear in a clock turns correctly, without assembling the whole clock. |
| **Feature test** | Checks a complete user workflow *from start to finish* -- for example, "a user logs in, creates a maintenance ticket, and adds a comment." It simulates real HTTP requests and uses a real (but temporary) database. | Walking through an entire recipe from ingredients to finished dish, verifying every step. |
| **Security test** | Tries to *break in* on purpose -- SQL injection, XSS attacks, privilege escalation, brute-force login attempts. It verifies the app defends itself correctly. | A hotel hiring someone to try picking every lock, then confirming the locks hold. |
| **Performance test** | Measures *how fast and how efficiently* the app responds -- login speed, dashboard load time, database query counts, memory usage. It catches slowdowns before users notice them. | Timing how quickly a barista makes coffee during the morning rush. |
| **Database test** | Verifies that data is stored *correctly and safely* -- foreign keys work, constraints prevent bad data, relationships stay consistent after updates and deletes. | A bank auditor checking that every ledger entry balances and no record can be silently altered. |
| **Integration test** | Verifies that *multiple components work together* -- database and models, queue and broadcast, mail and notifications. | Testing that the entire postal system (sorting, transport, delivery) works as one. |

---

## Test folder tree -- what each folder covers

Below is a plain-English walkthrough of every top-level folder inside `tests/`. Each folder also has its own detailed `README.md` if you want to dig deeper.

### `Unit/` -- Individual building blocks

**What it covers:** Tests for single classes used in isolation -- Action classes (approve a budget, assign a technician), DTOs (data validation objects), Enums (ticket priorities, roles), Models (relationships, accessors), Services (analytics, notifications, search), Policies (who can do what), Events, Jobs, Listeners, Middleware, Observers, Providers, Repositories, Traits, and Value Objects. These tests run fast because they don't hit a real database or send HTTP requests.

**How to run it:**

```bash
php artisan test tests/Unit/
```

> See [`tests/Unit/README.md`](Unit/README.md) for a detailed breakdown.

---

### `Feature/` -- End-to-end user workflows

**What it covers:** Tests that simulate real user interactions through the API and web pages. Sub-folders include:
- **`API/Controllers/`** -- Every API endpoint (tickets, budgets, admin CRUD, analytics, notifications, attachments, QR codes, stock, etc.)
- **`Web/Controllers/`** -- Web page rendering (dashboard, profile, registration, rooms, UI)
- **`Web/Views/`** -- View templates, asset pipeline, design system components
- **`Middleware/`** -- Security middleware (CSRF, auth, rate limiting, role checks)
- **`Domain/`** -- Ticket lifecycle, status workflows, higher-priority checks
- **`Actions/`** -- Action classes tested with a full database behind them
- **`Console/`** -- Artisan command tests
- **`Repositories/`** -- Repository layer queries
- **`Validation/`** -- Edge-case input validation

**How to run it:**

```bash
php artisan test tests/Feature/
```

> See [`tests/Feature/README.md`](Feature/README.md) for a detailed breakdown.

---

### `Security/` -- Trying to break in

**What it covers:** Attack-scenario tests that deliberately send malicious input to verify the app defends itself. Covers SQL injection, XSS (cross-site scripting), CSRF (cross-site request forgery), IDOR (accessing someone else's data by guessing IDs), brute-force login attempts, path traversal, privilege escalation, mass assignment, file upload abuse, session hijacking, token security, security headers, and user enumeration.

**How to run it:**

```bash
php artisan test tests/Security/
```

> See [`tests/Security/README.md`](Security/README.md) for a detailed breakdown.

---

### `Performance/` -- Is it fast enough?

**What it covers:** Timing and resource-usage tests that measure whether API endpoints, database queries, authentication flows, dashboards, caches, searches, file uploads, reports, and memory usage meet acceptable speed thresholds. These tests often define a maximum number of milliseconds or database queries allowed.

**How to run it:**

```bash
php artisan test tests/Performance/
```

> See [`tests/Performance/README.md`](Performance/README.md) for a detailed breakdown.

---

### `Database/` -- Is the data correct?

**What it covers:** Tests for database integrity, constraints, relationships, indexes, audit trails, budget calculations, concurrency, model lifecycles, token integrity, migrations (schema validation), and seeders (compliance data).

**How to run it:**

```bash
php artisan test tests/Database/
```

> See [`tests/Database/README.md`](Database/README.md) for a detailed breakdown.

---

### `Integration/` -- Multiple pieces working together

**What it covers:** Tests that verify multiple components cooperate correctly -- foreign key integrity across related models, mass assignment protection, soft deletes, broadcasting and queue integration, and mail delivery.

**How to run it:**

```bash
php artisan test tests/Integration/
```

> See [`tests/Integration/README.md`](Integration/README.md) for a detailed breakdown.

---

### `Authentication/` -- Login, registration, password reset

**What it covers:** The full authentication lifecycle -- login flows, registration, password reset, edge cases like expired tokens or locked accounts.

**How to run it:**

```bash
php artisan test tests/Authentication/
```

> See [`tests/Authentication/README.md`](Authentication/README.md) for a detailed breakdown.

---

### `Authorization/` -- Who can do what?

**What it covers:** Role-based access control -- verifying that regular users, technicians, and admins each see and can do only what their role allows, both in the UI and via the API.

**How to run it:**

```bash
php artisan test tests/Authorization/
```

> See [`tests/Authorization/README.md`](Authorization/README.md) for a detailed breakdown.

---

### `Base/` -- Test base classes (the foundation)

**What it covers:** Three abstract base classes that every test in the project extends:
- **`UnitTestCase`** -- Sets up a clean Laravel application without a database (for isolated unit tests).
- **`FeatureTestCase`** -- Sets up a clean in-memory SQLite database, refreshes it before each test, and seeds lookup data (roles, statuses). Used for feature and security tests.
- **`DatabaseTestCase`** -- Like FeatureTestCase, plus extra helpers for asserting database structure (columns, indexes, foreign keys).

> See [`tests/Base/README.md`](Base/README.md) for a detailed breakdown.

---

### `Concerns/` -- Reusable test helpers (traits)

**What it covers:** PHP traits that tests can `use` to avoid repeating setup code. Examples:
- `CreatesUsers` -- quick methods like `createAdmin()`, `createTechnician()`, `createRegularUser()`
- `InteractsWithApi` -- common API call helpers
- `InteractsWithEvents`, `InteractsWithMail`, `InteractsWithNotifications`, `InteractsWithQueue`, `InteractsWithStorage` -- faking external services
- `SeedsLookupData` -- populates reference tables (roles, statuses)

> See [`tests/Concerns/README.md`](Concerns/README.md) for a detailed breakdown.

---

### `Fixtures/` -- Test data builders and fake services

**What it covers:** Pre-built test data objects, dataset arrays, and fake/mock service implementations used across multiple tests.

> See [`tests/Fixtures/README.md`](Fixtures/README.md) for a detailed breakdown.

---

## How tests work in this project

### Clean database for every test

Most tests extend `FeatureTestCase` or `DatabaseTestCase`, which use Laravel's `RefreshDatabase` trait. This means **every single test gets a brand-new, empty database** -- created in memory (SQLite), populated with the tables the app needs, and then **completely erased** when the test finishes. No test can ever be contaminated by data left over from a previous test.

### Factories: instant fake data

Instead of manually creating users, tickets, or rooms in every test, the project uses **model factories** -- small blueprints that know how to generate realistic fake records. For example, `User::factory()->create()` instantly creates a complete user with a name, email, password, and role. You can override any field you need:

```php
$admin = User::factory()->create([
    'profile_id' => UserProfile::where('name', 'Admin')->first()->id,
]);
```

### The Arrange / Act / Assert pattern

Every test follows three steps, even if they're not written as comments:

1. **Arrange** -- Set up the conditions. Create a user, a ticket, seed statuses, fake external services.
2. **Act** -- Do the thing being tested. Call the action, send the HTTP request, invoke the service method.
3. **Assert** -- Check the result. Did the ticket status change? Was the right HTTP status returned? Is the data in the database correct?

Example from a real test (`ApproveBudgetActionTest`):

```php
// Arrange: create an admin and a ticket with a pending budget
$admin = User::factory()->create([...]);
$ticket = Ticket::factory()->create(['budget_status' => 'pending', ...]);

// Act: approve the budget
$result = $this->action->execute($ticket, $admin, $data);

// Assert: budget status is now Approved
$this->assertEquals('approved', $result->budget_status);
```

---

## How to run tests

### Run everything

```bash
php artisan test
```

This runs all 1,410+ tests. It takes a few minutes on most machines.

### Run a specific folder

```bash
php artisan test tests/Unit/              # Only unit tests
php artisan test tests/Feature/           # Only feature tests
php artisan test tests/Security/          # Only security tests
php artisan test tests/Performance/       # Only performance tests
php artisan test tests/Database/          # Only database tests
php artisan test tests/Integration/       # Only integration tests
php artisan test tests/Authentication/    # Only authentication tests
php artisan test tests/Authorization/     # Only authorization tests
```

### Run a single test file

```bash
php artisan test tests/Unit/Actions/ApproveBudgetActionTest.php
```

### Filter by name

The `--filter` flag lets you run only tests whose names match a string:

```bash
php artisan test --filter=AuthFlow         # All tests containing "AuthFlow"
php artisan test --filter=Security         # All tests containing "Security"
php artisan test --filter=it_approves      # Tests containing "it_approves"
```

### Run with code coverage

```bash
php artisan test --coverage               # Summary coverage report
php artisan test --coverage --min=80      # Fail if coverage is below 80%
```

Coverage shows which lines of the application code are actually exercised by tests -- useful for finding untested code.

### Using a testsuite name

```bash
php artisan test --testsuite=Feature      # Run the "Feature" testsuite
php artisan test --testsuite=Unit         # Run the "Unit" testsuite
```

---

## Comandos de Execucao

Para executar isoladamente todos os testes desta pasta:

```bash
php artisan test --testsuite=Feature
```

Para filtrar por um teste ou metodo especifico:

```bash
php artisan test tests --filter=NomeDoTeste
```

Para executar com cobertura de codigo (se suportado pelo ambiente):

```bash
php artisan test tests --coverage
```
