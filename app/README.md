# `app/`

Application source code for the SGM (Integrated Maintenance Management System) platform. A Laravel application following clean architecture principles with domain-driven organization.

> **New to programming?** See [NON_TECHNICAL_PROJECT_GUIDE.md](../NON_TECHNICAL_PROJECT_GUIDE.md) for a plain-English explanation of every folder and file in this project with real-world analogies.

---

## A Plain-English Tour of the `app/` Folder

Think of `app/` as the **engine room** of the SGM platform — it is the place where *every* business rule, every piece of logic, and every decision lives. When a user clicks a button, opens a report, or submits a maintenance ticket, the request eventually lands inside `app/` and the folders described below work together to make it happen. If `routes/` is the building's front door and `resources/views/` is the front window the customer sees, then `app/` is everything happening behind the scenes.

### The `app/` "Office Building" — Who Does What?

Imagine SGM as a large, well-organised office building. Each folder is a different department or role:

| Folder | Real-World Analogy | What It Does (plain English) |
|---|---|---|
| [`Http/Controllers/`](Http/Controllers/README.md) | **Front-desk clerks** | Receive the visitor's request, check the paperwork, and hand it off to the right department. |
| [`Http/Requests/`](Http/Requests/README.md) | **Paperwork inspector** | Inspects every form and document for completeness and correctness *before* it goes further. |
| [`Http/Resources/`](Http/Resources/README.md) | **Translation desk for outgoing mail** | Takes raw internal data and reformats it into the clean JSON (or view) the customer is waiting for. |
| [`Services/`](Services/README.md) | **Department managers** | Coordinate complex work that spans multiple teams — they call the shots but don't do the filing themselves. |
| [`Actions/`](Actions/README.md) | **Worker bees** | Each one knows exactly one task and does it well (create a user, close a ticket, submit a budget…). |
| [`Models/`](Models/README.md) | **Filing cabinet** | Defines the structure of every record the company keeps — who owns which ticket, which room has which equipment, and so on. |
| [`Repositories/`](Repositories/README.md) | **Librarians** | Knows exactly how to look things up, file things away, and retrieve them — keeps all database access in one expert place. |
| [`Middleware/`](Http/Middleware/README.md) | **Security guards at every floor** | Check credentials, enforce speed limits, and redirect traffic *before* anyone reaches a department. |
| [`Policies/`](Policies/README.md) | **Access-control list on the door** | Decides whether a specific person is allowed to perform a specific action on a record (view, edit, delete…). |
| [`DTOs/`](DTOs/README.md) | **Sealed envelopes** | Carry a fixed set of typed data from one department to another, guaranteeing nothing is missing or extra. |
| [`Enums/`](Enums/README.md) | **The company rulebook** | Lists the only allowed values for things like ticket status, priority, or user role — no magic strings allowed. |
| [`Jobs/`](Jobs/Queues/README.md) | **Assembly-line stations** | Handle heavy or slow tasks (generating exports, sending emails) on a side conveyor belt so the front desk doesn't stall. |
| [`Events/`](Events/README.md) | **PA-system announcements** | Shout "something just happened!" so the whole building can react without the originator knowing who's listening. |
| [`Listeners/`](Listeners/README.md) | **Follow-up team** | Each one hears a specific announcement and carries out its own small task in response. |
| [`Observers/`](Observers/README.md) | **Automatic notifiers on the filing cabinet** | Whenever a record is created, changed, or deleted, they trigger side-effects (audit logs, cache clears) without anyone remembering to call them. |
| [`Mail/`](Mail/README.md) | **Internal post room** | Prepares and sends emails — password resets, ticket confirmations, reports — often on a background schedule. |
| [`Notifications/`](Notifications/README.md) | **Multi-channel alert desk** | Delivers messages via email, database notification, or real-time broadcast depending on user preference. |
| [`Exports/`](Exports/README.md) | **Report-printing room** | Turns live data into CSV, PDF, or Excel files for download. |
| [`ValueObjects/`](ValueObjects/README.md) | **Specialised measuring tools** | Encapsulates a single concept (like `Money` or `Email`) so invalid data simply cannot exist. |
| [`Providers/`](Providers/README.md) | **Wiring diagram / switchboard** | Tells Laravel how to connect all the pieces together (dependency injection, event bindings, boot-up tasks). |

> 💡 *Every folder above has its own `README.md` with deeper explanations and file lists — follow the links to learn more.*

### How a Single Request Flows Through `app/`

Here is what happens, end to end, when a technician clicks **"Submit Maintenance Ticket"**:

1. **Security guards (Middleware)** stop the visitor at the entrance: *Are you logged in? Do you have the right role? Is this IP allowed?* If any check fails, the request is turned away immediately.
2. **Front-desk clerk (Controller)** receives the validated visitor, checks the ticket form (via a **FormRequest / Paperwork Inspector**) and asks the **Access-control list (Policy)**: *"Is this person allowed to create a ticket?"*
3. Once the paperwork is accepted, the clerk hands the sealed envelope of typed data (**DTO**) to the **Department Manager (Service)**, who orchestrates the work: *"Find the right equipment record (via the **Librarian / Repository**), assign the ticket, and log the event."*
4. The **Librarian (Repository)** looks up or stores the data in the **Filing Cabinet (Model / Database)** and returns a confirmed record.
5. Back in the manager's office, a **PA announcement (Event)** goes out: *"A new ticket was just created!"* The **Follow-up team (Listeners)** springs into action — one sends an email notification, another updates a dashboard counter, a third might queue a heavy data-export **assembly-line job (Job)** so the front desk can immediately reply.
6. The front-desk clerk wraps the result in clean outgoing mail (**API Resource**) and sends it back to the visitor's browser.

All of this happens in fractions of a second, but every step has a clear owner, a clear responsibility, and a clear place in the code. That is the power of the layered architecture inside `app/`.

---

## Directory Structure

| Directory | Purpose | Files |
|---|---|---|
| **Actions/** | Single-purpose command/action classes (CQRS-style) | CreateUserAction, CreateTicketAction, etc. |
| **Concerns/** | Shared PHP traits used across models and services | Auditable trait, logging concerns |
| **Console/** | Artisan console commands | DatabaseBackup, PartitionAudits, UpdateCurrencyRates |
| **Domain/** | Domain-specific modules with their own Actions | Ticket workflow, payment processing |
| **DTOs/** | Data Transfer Objects for typed data passing | CreateTicketData, BudgetSubmissionData, etc. |
| **Enums/** | PHP 8.1+ enums for domain value sets | TicketStatusEnum, TicketPriorityEnum, UserRoleEnum |
| **Events/** | Laravel event classes (domain events) | TicketCreated, TicketStatusChanged |
| **Exports/** | CSV/PDF/Excel export implementations | TicketsExport, StockCostsExport |
| **Http/** | Controllers, middleware, requests, resources | Web + API controllers, FormRequests, API Resources |
| **Jobs/** | Queued jobs for async background processing | ExportCsvJob, SendTestEmailJob, GenerateAiRecommendationJob |
| **Listeners/** | Event listener classes | LogTicketWorkflowChange, NotifyAssignedTechnician |
| **Mail/** | Mailable classes for email sending | PasswordResetMail, TicketCreated |
| **Models/** | Eloquent ORM models (database table mappings) | User, Ticket, Equipment, Room, etc. |
| **Notifications/** | User notification classes | NewTicketNotification, TicketStatusChanged |
| **Observers/** | Eloquent model lifecycle observers | TicketObserver, EquipmentObserver |
| **OpenApi/** | OpenAPI/Swagger documentation classes and specs | OpenApiSpec with 121 endpoints documented |
| **Policies/** | Authorization policy classes (gates & roles) | TicketPolicy, EquipmentPolicy, UserPolicy |
| **Providers/** | Laravel service providers (dependency injection) | AppServiceProvider, EventServiceProvider |
| **Repositories/** | Repository pattern (data access abstraction) | UserRepository, TicketRepository with interfaces |
| **Services/** | Business logic orchestration & domain services | TicketService, UserService, CurrencyRateService |
| **Traits/** | Shared PHP traits for models & services | Auditable (auto-tracking changes) |
| **ValueObjects/** | Immutable value objects (strongly-typed data) | Email, Money, SerialNumber |

---

## Architecture & Design Patterns

### Layered Architecture

The application is organized in clear, separable layers with distinct responsibilities:

```
┌─────────────────────────────────────────────────────────────┐
│  Routes (routes/web.php, routes/api.php)                    │
│  ↓
│  Middleware (Auth, RBAC, Rate Limiting, Localization)      │
├─────────────────────────────────────────────────────────────┤
│  HTTP Layer: Controllers                                    │
│  ✓ Parse & validate requests via FormRequest              │
│  ✓ Authorize via Policies                                 │
│  ✓ Delegate to Services/Actions                           │
│  ✓ Return API Resources or Blade views                    │
├─────────────────────────────────────────────────────────────┤
│  Service Layer: Business Logic Orchestration                │
│  ✓ Coordinate multiple repositories & actions             │
│  ✓ Implement domain rules & workflows                      │
│  ✓ Manage transactions & side effects                      │
├─────────────────────────────────────────────────────────────┤
│  Action Layer: Single-Purpose Commands                      │
│  ✓ Execute discrete business operations                    │
│  ✓ Wrapped in jobs for async processing where needed      │
│  ✓ Throw domain exceptions on validation failures         │
├─────────────────────────────────────────────────────────────┤
│  Repository Layer: Data Access Abstraction                  │
│  ✓ Abstract Eloquent queries behind interfaces            │
│  ✓ Implement query optimization (eager loading, caching)  │
│  ✓ Provide typed collection results via DTOs              │
├─────────────────────────────────────────────────────────────┤
│  Model Layer: Eloquent ORM                                  │
│  ✓ Define table structure, relationships, casts           │
│  ✓ Attach observers for lifecycle hooks                   │
│  ✓ Implement accessors for computed properties            │
├─────────────────────────────────────────────────────────────┤
│  Database: MySQL (production) / SQLite (development)        │
└─────────────────────────────────────────────────────────────┘
```

### Key Patterns

#### 1. **Repository Pattern**
- **Purpose**: Abstract data access away from business logic
- **Location**: `app/Repositories/`
- **Example**: `UserRepository` implements `UserRepositoryContract`
- **Benefit**: Testable, swappable data sources

#### 2. **Action Classes (CQRS-style)**
- **Purpose**: Encapsulate single business operations
- **Location**: `app/Actions/` and `app/Domain/*/Actions/`
- **Example**: `CreateUserAction`, `SubmitBudgetAction`
- **Benefit**: Reusable across web and API, easily queued

#### 3. **Data Transfer Objects (DTOs)**
- **Purpose**: Strongly-typed data passing between layers
- **Location**: `app/DTOs/`
- **Example**: `CreateTicketData` with validated fields
- **Benefit**: Type safety, immutability, clear contracts

#### 4. **Service Layer**
- **Purpose**: Orchestrate business logic across repositories & actions
- **Location**: `app/Services/`
- **Example**: `TicketService::createTicket()` coordinates multiple steps
- **Benefit**: Reusable logic, separation of concerns

#### 5. **Eloquent Observers**
- **Purpose**: React to model lifecycle events (created, updated, deleted)
- **Location**: `app/Observers/`
- **Example**: `TicketObserver` invalidates cache and creates audit entries
- **Benefit**: Automatic side-effects, no manual wiring

#### 6. **Policy-Based Authorization**
- **Purpose**: Centralize permission logic per model
- **Location**: `app/Policies/`
- **Example**: `TicketPolicy::update()` checks user ownership
- **Benefit**: Auditable, testable permissions

#### 7. **Value Objects**
- **Purpose**: Represent domain concepts as immutable objects
- **Location**: `app/ValueObjects/`
- **Example**: `Money` class for monetary amounts
- **Benefit**: Type safety, validation at construction time

---

## Layer Responsibilities

### Controllers (`Http/Controllers/`)
- ✓ Receive HTTP requests from routes
- ✓ Validate input via `FormRequest` classes
- ✓ Check authorization via Policies
- ✓ Call Services or Actions
- ✓ Transform results into Views or API Resources
- ✗ Should NOT contain business logic, queries, or database access

### Services (`Services/`)
- ✓ Orchestrate complex business workflows
- ✓ Coordinate multiple repositories
- ✓ Implement domain rules (e.g., "only admins can approve budgets")
- ✓ Manage transactions and side effects
- ✓ Throw domain-specific exceptions
- ✗ Should NOT handle HTTP concerns (requests, responses)

### Actions (`Actions/`)
- ✓ Execute discrete, single-purpose operations
- ✓ Implement domain commands (e.g., CreateUserAction)
- ✓ Return typed results (DTOs, Models)
- ✓ Throw validation exceptions on failure
- ✗ Should NOT orchestrate multiple steps (use Services instead)

### Repositories (`Repositories/`)
- ✓ Abstract database queries
- ✓ Implement query optimization (eager loading, caching)
- ✓ Return typed collections via DTOs or Models
- ✓ Handle soft deletes, filtering, pagination
- ✗ Should NOT contain business logic or authorization checks

### Models (`Models/`)
- ✓ Define table structure and relationships
- ✓ Implement casts and mutators
- ✓ Attach observers and scopes
- ✓ Provide accessors for computed properties
- ✗ Should NOT contain business logic (use Services instead)

---

## Code Standards & Conventions

### Type Safety
- ✅ **Declare strict types** at file top: `declare(strict_types=1);`
- ✅ **Type all parameters**: `function handle(User $user, string $name): void`
- ✅ **Type all returns**: `public function getUser(): ?User`
- ✅ **Use proper return types**: Not just `mixed` or no type

### Naming Conventions
- **Classes**: PascalCase (e.g., `CreateUserAction`, `UserService`)
- **Methods**: camelCase (e.g., `createUser()`, `getActiveTickets()`)
- **Variables**: camelCase (e.g., `$userId`, `$ticketStatus`)
- **Constants**: UPPERCASE with underscores (e.g., `MAX_RETRIES`)
- **Routes**: snake_case with dots (e.g., `tickets.create`, `api.users.store`)

### Authorization
- ✅ Use Policies for model-specific authorization
- ✅ Use Gates for application-wide rules
- ✅ Call `$this->authorize()` in controllers before mutating data
- ✗ Never trust `$request->user()` without authorization

### Validation
- ✅ Use dedicated FormRequest classes
- ✅ Implement `authorize()` method for permission checks
- ✅ Define rules in `rules()` method
- ✗ Never use `$request->all()` or `$request->validate()` in controllers

### Error Handling
- ✅ Throw domain-specific exceptions from Services/Actions
- ✅ Catch and handle exceptions at controller level
- ✅ Return appropriate HTTP status codes
- ✗ Never return error objects in successful responses

### Database Access
- ✅ Use Repositories for querying
- ✅ Use Eager loading (`with()`) to prevent N+1 queries
- ✅ Use `select()` to fetch only needed columns
- ✅ Use `lazy()` or `chunk()` for large datasets
- ✗ Never use raw SQL unless absolutely necessary

---

## Key Subdirectories Explained

### `Http/Controllers/`
**Purpose**: HTTP request handlers for web and API

- **Web Controllers**: Return Blade views (TicketController, EquipmentController)
- **API Controllers**: Return JSON responses (AnalyticsController, MaintenancePlanController)
- **Authorization**: Use Policies to gate access
- **Convention**: Thin controllers delegating to Services/Actions

### `Http/Requests/`
**Purpose**: Form request validation classes

- **Structure**: One class per form (CreateTicketRequest, UpdateProfileRequest)
- **Authorization**: Implement `authorize()` for permission checks
- **Validation**: Define all rules in `rules()` method
- **Messages**: Use localized validation messages via `messages()`

### `Http/Resources/`
**Purpose**: API resource transformations (JSON)

- **Structure**: One class per model (UserResource, TicketResource)
- **Transform**: Shape and format model data for API responses
- **Collections**: Implement ResourceCollection for paginated results
- **Relationships**: Use `$this->whenLoaded()` for optional includes

### `Services/`
**Purpose**: Business logic orchestration

- **Naming**: ServiceName pattern (UserService, TicketService)
- **Methods**: Public methods are the service contract
- **Dependencies**: Injected via constructor
- **Exceptions**: Throw domain-specific exceptions

### `Actions/`
**Purpose**: Single-purpose command handlers

- **Naming**: VerbNounAction pattern (CreateUserAction, UpdateTicketAction)
- **Single Method**: Implement `__invoke()` or dedicated method
- **Return Types**: Typed DTOs or Models
- **Reusability**: Can be called from controllers, jobs, or CLI

### `Repositories/`
**Purpose**: Data access abstraction

- **Interfaces**: Define contract in Contracts subdirectory
- **Implementation**: Implement interface with Eloquent queries
- **Optimization**: Use eager loading, select(), caching
- **Return Types**: Typed collections, DTOs, or Models

### `DTOs/`
**Purpose**: Data Transfer Objects for typed data passing

- **Immutability**: Use readonly properties
- **Validation**: Validate in static `fromRequest()` or `fromArray()` methods
- **Transformation**: Provide `toArray()` for easy conversion
- **Type Safety**: Typed properties prevent runtime errors

### `Enums/`
**Purpose**: Type-safe enums for domain value sets

- **Usage**: Instead of magic strings or constants
- **Database**: Store enum values in database
- **UI**: Localize enum labels in views/API responses
- **Examples**: TicketStatusEnum, UserRoleEnum, TicketPriorityEnum

### `Models/`
**Purpose**: Eloquent ORM model definitions

- **Fillable**: Define `$fillable` for mass assignment protection
- **Casts**: Define attribute casts for type coercion
- **Relationships**: Define belongs-to, has-many, many-to-many
- **Observers**: Attach observers for lifecycle hooks
- **Scopes**: Define query scopes for common filters

### `Observers/`
**Purpose**: React to model lifecycle events

- **Hooks**: `created()`, `updated()`, `deleted()`, `restoring()`
- **Side Effects**: Audit trail, cache invalidation, notifications
- **Exception Safety**: Wrap in try/catch to prevent business transaction rollback
- **Registration**: Auto-discovered by Laravel

### `Policies/`
**Purpose**: Authorization logic per model

- **Methods**: One method per action (view, create, update, delete)
- **Parameters**: Receive authenticated user and resource model
- **Return**: Boolean indicating permission
- **Registration**: Auto-discovered or manually registered

### `Jobs/`
**Purpose**: Queued background job classes

- **Async**: Implement `ShouldQueue` for background processing
- **Retries**: Configure `$tries` and `$backoff` for resilience
- **Timeout**: Set `$timeout` appropriate to job duration
- **Serialization**: Use `$this->delay()` for scheduled execution

### `Events/`
**Purpose**: Domain events for decoupled communication

- **Broadcasting**: Implement `ShouldBroadcast` for real-time notifications
- **Listeners**: Register listeners in EventServiceProvider
- **Queueable**: Make listeners queueable for async processing
- **Contracts**: Define event properties as public

### `Mail/`
**Purpose**: Mailable classes for email sending

- **Queue**: Implement `ShouldQueue` for background sending
- **Content**: Build email body in `content()` method
- **Attachments**: Add attachments via `attachments()` method
- **Markdown**: Use Markdown templates for email body

### `Notifications/`
**Purpose**: Multi-channel notifications (database, mail, broadcast)

- **Channels**: Implement `via()` to return [mail, database, broadcast]
- **Content**: Implement methods for each channel
- **Routing**: Implement `routeNotificationFor()` for custom routing
- **Queue**: Make queued for performance

---

## Testing

Tests are organized to mirror the `app/` structure:

```
tests/
├── Unit/
│   ├── Actions/               # Action class tests
│   ├── Services/              # Service class tests
│   ├── Repositories/          # Repository tests
│   ├── Models/                # Model tests
│   ├── DTOs/                  # DTO tests
│   └── ValueObjects/          # Value object tests
├── Feature/
│   ├── Http/Controllers/      # Controller & route tests
│   ├── Actions/               # Integration tests
│   └── Domain/                # Workflow tests
├── Security/                  # Security-focused tests
├── Performance/               # Performance benchmarks
└── Database/                  # Schema validation tests
```

---

## Related Documentation

- [HTTP Controllers & Routing](Http/Controllers/README.md)
- [Model Architecture](Models/README.md)
- [Database Migrations](../database/migrations/README.md)
- [Testing Conventions](../tests/README.md)

---

## Quick Reference

### Creating a New Feature

1. **Define the domain** (model, relationships, business rules)
2. **Create a FormRequest** for input validation
3. **Create a Policy** for authorization
4. **Create a DTO** for typed data passing
5. **Create an Action** for the core operation
6. **Create a Service** to orchestrate if needed
7. **Create a Controller** to wire HTTP to Action
8. **Create a Resource** if API endpoint
9. **Write tests** for all layers
10. **Document** if complex business logic

### Running Tests
```bash
php artisan test                    # All tests
php artisan test --filter=UserTest  # Specific test
php artisan test tests/Unit/        # Test directory
php artisan test --coverage         # With coverage report
```

### Static Analysis
```bash
vendor/bin/phpstan analyse app/     # Static analysis
vendor/bin/pint app/                # Format code
```

---

**Last Updated**: September 1, 2026  
**Status**: Production-Ready

- `docs/refactor/` — Codebase normalization progress and tracking
- `docs/i18n/` — Internationalization project tracking
