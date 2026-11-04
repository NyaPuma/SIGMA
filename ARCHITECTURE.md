# Architecture Overview

## SGM (Integrated Maintenance Management System)

A comprehensive guide to the architecture, design patterns, and technical decisions behind the maintenance management platform.

---

## Table of Contents

1. [High-Level Architecture](#high-level-architecture)
2. [Layered Architecture](#layered-architecture)
3. [Core Design Patterns](#core-design-patterns)
4. [Directory Structure](#directory-structure)
5. [Data Flow](#data-flow)
6. [Key Services & Repositories](#key-services--repositories)
7. [Security Architecture](#security-architecture)
8. [Performance Considerations](#performance-considerations)
9. [Testing Strategy](#testing-strategy)
10. [Deployment Architecture](#deployment-architecture)

---

## High-Level Architecture

```
┌──────────────────────────────────────────────────────────────┐
│                        User Interface Layer                   │
│  (Blade Templates, Alpine.js, Tailwind CSS, Vite)           │
└────────────────┬─────────────────────────────────────────────┘
                 │
                 ▼
┌──────────────────────────────────────────────────────────────┐
│                      HTTP Layer                              │
│  Routes → Middleware → Controllers → FormRequests            │
└────────────────┬─────────────────────────────────────────────┘
                 │
                 ▼
┌──────────────────────────────────────────────────────────────┐
│                    Service Layer                             │
│  Services (Business Logic) + Actions (Discrete Operations)   │
└────────────────┬─────────────────────────────────────────────┘
                 │
                 ▼
┌──────────────────────────────────────────────────────────────┐
│                   Repository Layer                           │
│  Repositories (Data Access Abstraction)                      │
└────────────────┬─────────────────────────────────────────────┘
                 │
                 ▼
┌──────────────────────────────────────────────────────────────┐
│                    Model Layer                               │
│  Eloquent ORM Models + Observers + Scopes                    │
└────────────────┬─────────────────────────────────────────────┘
                 │
                 ▼
┌──────────────────────────────────────────────────────────────┐
│                  Database Layer                              │
│  MySQL (Production) / SQLite (Development) + Redis Cache     │
└──────────────────────────────────────────────────────────────┘
```

---

## Layered Architecture

### 1. **HTTP/Web Layer** (`app/Http/`)

**Responsibilities:**
- Parse HTTP requests (query strings, form data, JSON body)
- Validate input via FormRequest classes
- Check authorization via Policies
- Call appropriate Services or Actions
- Transform results into responses (JSON, Blade views, redirects)

**Key Components:**
- **Controllers** — HTTP request handlers
- **FormRequests** — Input validation and authorization
- **Middleware** — Request/response pipeline (auth, RBAC, rate limiting)
- **Resources** — API response transformation (JSON)
- **Routes** — HTTP endpoint definitions

**Constraints:**
- ✗ Should NOT contain business logic
- ✗ Should NOT directly query the database
- ✗ Should NOT perform complex calculations

### 2. **Service Layer** (`app/Services/`)

**Responsibilities:**
- Orchestrate complex business workflows
- Coordinate multiple repositories and external services
- Implement domain rules and business logic
- Manage transactions and data consistency
- Throw domain-specific exceptions

**Examples:**
- `TicketService::createTicket()` — Orchestrates ticket creation workflow
- `UserService::generateAuthToken()` — Creates and hashes authentication tokens
- `CurrencyRateService::convert()` — Performs currency conversion with caching

**Key Characteristics:**
- Stateless (no instance state)
- Dependency-injected collaborators
- Single responsibility per service
- Throws typed exceptions

### 3. **Action Layer** (`app/Actions/`, `app/Domain/*/Actions/`)

**Responsibilities:**
- Execute discrete, single-purpose business operations
- Implement commands (CQRS-style)
- Validate inputs and throw exceptions
- Return typed results (Models, DTOs)
- Support both sync and async execution (via Jobs)

**Examples:**
- `CreateUserAction` — Single operation to create a user
- `SubmitBudgetAction` — Validates and submits ticket budget for approval
- `AssignTechnicianAction` — Assigns technician to a ticket

**Key Characteristics:**
- Single public method (usually `__invoke()`)
- Immutable parameters passed as DTOs
- Typed return values
- Can be queued/delayed
- Easy to test and reuse

### 4. **Repository Layer** (`app/Repositories/`)

**Responsibilities:**
- Provide data access abstraction over Eloquent ORM
- Implement query optimization (eager loading, caching)
- Return typed collections via DTOs or Models
- Handle filtering, pagination, sorting
- Enforce data access constraints

**Pattern:**
```
Repositories/
├── Contracts/
│   ├── TicketRepositoryContract
│   ├── UserRepositoryContract
│   └── ...
└── Implementations/
    ├── TicketRepository
    ├── UserRepository
    └── ...
```

**Benefits:**
- Testability (mock repositories in tests)
- Database independence (can swap implementations)
- Reusability across Services and Controllers
- Query optimization in one place

### 5. **Model Layer** (`app/Models/`)

**Responsibilities:**
- Define table structure, columns, and types
- Implement Eloquent relationships
- Attach observers and scopes
- Implement casts and mutators
- Provide computed properties via accessors

**Key Characteristics:**
- Minimal logic (calculations left to Services)
- Explicit `$fillable` (mass assignment protection)
- Type-safe casts
- Relationship definitions
- Observer attachment

**Observer Pattern:**
```php
class Ticket extends Model
{
    protected static function booted(): void
    {
        static::observe(TicketObserver::class);
    }
}

class TicketObserver
{
    public function created(Ticket $ticket): void
    {
        // Audit trail, notifications, cache invalidation
    }
}
```

### 6. **Database Layer**

**Configuration:**
- **Development**: SQLite (`database.sqlite`)
- **Production**: MySQL with replication support
- **Caching**: Redis (session, cache, queue)

**Optimization:**
- Foreign key indexes
- Soft-delete indexes
- Query filter column indexes
- Composite indexes for WHERE/ORDER BY combinations

---

## Core Design Patterns

### Pattern 1: Repository Pattern

**Purpose**: Decouple business logic from data access implementation

**Implementation**:
```php
// Contract
interface UserRepositoryContract
{
    public function find(int $id): ?User;
    public function all(): Collection;
    public function save(User $user): bool;
}

// Implementation
class UserRepository implements UserRepositoryContract
{
    public function find(int $id): ?User
    {
        return User::with(['profile', 'roles'])->find($id);
    }
}

// Usage
class UserService
{
    public function __construct(
        private readonly UserRepositoryContract $users,
    ) {}

    public function getUser(int $id): ?User
    {
        return $this->users->find($id);
    }
}
```

### Pattern 2: Action/Command Pattern

**Purpose**: Encapsulate single business operations for reuse

**Implementation**:
```php
class CreateUserAction
{
    public function __invoke(CreateUserData $data): User
    {
        $user = User::create([
            'name' => $data->name,
            'email' => $data->email,
            'password' => Hash::make($data->password),
        ]);

        event(new UserCreated($user));

        return $user;
    }
}

// Usage in Controller
public function store(StoreUserRequest $request): Response
{
    $data = CreateUserData::fromRequest($request);
    $user = (new CreateUserAction())($data);
    
    return redirect()->route('users.show', $user);
}

// Usage in Job
class SendWelcomeEmailJob implements ShouldQueue
{
    public function handle(): void
    {
        $data = CreateUserData::fromArray([...]);
        $user = (new CreateUserAction())($data);
        // Send email...
    }
}
```

### Pattern 3: Data Transfer Object (DTO) Pattern

**Purpose**: Provide strongly-typed, immutable data containers

**Implementation**:
```php
final readonly class CreateTicketData
{
    public function __construct(
        public string $title,
        public string $description,
        public string $priority,
        public int $equipmentId,
        public int $reportedById,
    ) {}

    public static function fromRequest(StoreTicketRequest $request): self
    {
        return new self(
            title: $request->validated('title'),
            description: $request->validated('description'),
            priority: $request->validated('priority'),
            equipmentId: $request->validated('equipment_id'),
            reportedById: $request->user()->id,
        );
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority,
            'equipment_id' => $this->equipmentId,
            'reported_by' => $this->reportedById,
        ];
    }
}
```

### Pattern 4: Observer Pattern

**Purpose**: React to model lifecycle events automatically

**Implementation**:
```php
class TicketObserver
{
    public function created(Ticket $ticket): void
    {
        // Create audit entry
        $ticket->createAudit('created', auth()->id());
        
        // Notify assigned technician
        Notification::send($ticket->assignedTechnician, 
            new TicketAssignedNotification($ticket)
        );
        
        // Invalidate dashboard cache
        Cache::forget('dashboard_tickets');
    }

    public function updated(Ticket $ticket): void
    {
        // Update audit trail
        $ticket->createAudit('updated', auth()->id());
    }
}
```

### Pattern 5: Policy-Based Authorization

**Purpose**: Centralize permission logic per resource

**Implementation**:
```php
class TicketPolicy
{
    public function view(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin() 
            || $user->id === $ticket->reported_by
            || $user->id === $ticket->assigned_to;
    }

    public function update(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin() 
            || $user->isTechnician() && $user->id === $ticket->assigned_to;
    }

    public function approve(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin() && $ticket->hasPendingBudget();
    }
}

// Usage in Controller
public function update(UpdateTicketRequest $request, Ticket $ticket): Response
{
    $this->authorize('update', $ticket);
    // ... update logic
}
```

### Pattern 6: Value Objects

**Purpose**: Represent domain concepts as immutable, type-safe objects

**Implementation**:
```php
final readonly class Money
{
    private function __construct(
        public int $cents,
        public string $currency = 'EUR',
    ) {}

    public static function fromFloat(float $amount, string $currency = 'EUR'): self
    {
        return new self(
            cents: (int) round($amount * 100),
            currency: $currency,
        );
    }

    public function add(Money $other): Money
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Cannot add different currencies');
        }

        return new Money(
            cents: $this->cents + $other->cents,
            currency: $this->currency,
        );
    }
}
```

---

## Directory Structure

### Application Code (`app/`)

```
app/
├── Actions/                      # Single-purpose command handlers
│   ├── CreateUserAction.php
│   ├── SubmitBudgetAction.php
│   └── UpdateEquipmentAction.php
├── Console/                      # Artisan console commands
│   └── Commands/
│       ├── DatabaseBackup.php
│       ├── UpdateCurrencyRates.php
│       └── PartitionAudits.php
├── Domain/                       # Domain-specific modules
│   └── Ticket/
│       ├── Actions/
│       ├── Events/
│       └── Queries/
├── DTOs/                         # Data Transfer Objects
│   ├── CreateUserData.php
│   ├── CreateTicketData.php
│   └── BudgetSubmissionData.php
├── Enums/                        # PHP 8.1+ Enums
│   ├── TicketStatusEnum.php
│   ├── TicketPriorityEnum.php
│   └── UserRoleEnum.php
├── Events/                       # Domain events
│   ├── TicketCreated.php
│   ├── TicketStatusChanged.php
│   └── UserRegistered.php
├── Exports/                      # CSV/PDF/Excel exporters
│   ├── TicketsExport.php
│   └── StockCostsExport.php
├── Http/                         # HTTP layer
│   ├── Controllers/
│   │   ├── TicketController.php
│   │   ├── EquipmentController.php
│   │   └── Api/
│   ├── Middleware/
│   │   ├── CustomAuthMiddleware.php
│   │   ├── RoleMiddleware.php
│   │   └── SetLocaleMiddleware.php
│   ├── Requests/                 # FormRequest validation
│   │   ├── StoreTicketRequest.php
│   │   └── UpdateProfileRequest.php
│   └── Resources/                # API response transformation
│       ├── TicketResource.php
│       └── UserResource.php
├── Jobs/                         # Queued background jobs
│   ├── ExportCsvJob.php
│   ├── SendTestEmailJob.php
│   └── GenerateAiRecommendationJob.php
├── Listeners/                    # Event listeners
│   ├── LogTicketStatusChange.php
│   └── NotifyAssignedTechnician.php
├── Mail/                         # Mailable classes
│   ├── PasswordResetMail.php
│   └── TicketCreated.php
├── Models/                       # Eloquent ORM models
│   ├── User.php
│   ├── Ticket.php
│   ├── Equipment.php
│   └── Room.php
├── Notifications/                # User notifications
│   ├── NewTicketNotification.php
│   └── TicketStatusChanged.php
├── Observers/                    # Model lifecycle observers
│   ├── TicketObserver.php
│   └── EquipmentObserver.php
├── OpenApi/                      # OpenAPI documentation
│   └── OpenApiSpec.php
├── Policies/                     # Authorization policies
│   ├── TicketPolicy.php
│   └── EquipmentPolicy.php
├── Repositories/                 # Data access layer
│   ├── Contracts/
│   │   ├── TicketRepositoryContract.php
│   │   └── UserRepositoryContract.php
│   └── Implementations/
│       ├── TicketRepository.php
│       └── UserRepository.php
├── Services/                     # Business logic services
│   ├── TicketService.php
│   ├── UserService.php
│   ├── CurrencyRateService.php
│   └── LocalizationService.php
├── Traits/                       # Shared PHP traits
│   ├── Auditable.php
│   └── HasTimestamps.php
└── ValueObjects/                 # Immutable value objects
    ├── Email.php
    ├── Money.php
    └── SerialNumber.php
```

### Configuration (`config/`)

```
config/
├── app.php                       # App name, locale, timezone
├── auth.php                      # Authentication guards & providers
├── cache.php                     # Cache stores (database, Redis)
├── database.php                  # Database connections
├── queue.php                     # Queue drivers (database, Redis)
├── session.php                   # Session driver & settings
├── octane.php                    # Octane/FrankenPHP settings
└── locales.php                   # Supported locales
```

### Database (`database/`)

```
database/
├── factories/                    # Model factories for testing
├── migrations/                   # Database schema migrations
│   ├── 0001_01_01_000001_create_users_table.php
│   ├── 0001_01_01_000002_create_tickets_table.php
│   └── ...
└── seeders/                      # Database seeders
    ├── DatabaseSeeder.php
    ├── UsersSeeder.php
    └── ...
```

### Frontend (`resources/`)

```
resources/
├── views/                        # Blade templates
│   ├── app.blade.php
│   ├── layouts/
│   ├── pages/
│   ├── components/
│   └── emails/
├── js/                           # JavaScript source
│   ├── bootstrap.ts
│   ├── core/
│   ├── utils/
│   ├── pages/
│   └── components/
└── css/                          # Stylesheets
    ├── app.css
    ├── tokens.css
    └── pages/
```

### Routes (`routes/`)

```
routes/
├── api.php                       # REST API routes (JSON)
├── web.php                       # Web UI routes (Blade)
└── console.php                   # Artisan schedule
```

### Tests (`tests/`)

```
tests/
├── Feature/                      # Feature/integration tests
├── Unit/                         # Unit tests
├── Security/                     # Security tests
├── Performance/                  # Performance tests
├── Database/                     # Database schema tests
└── Fixtures/                     # Test fixtures & builders
```

---

## Data Flow

### Typical Request Flow

```
HTTP Request
    ↓
Route Dispatcher (routes/web.php or routes/api.php)
    ↓
Global Middleware (CORS, body parsing, session)
    ↓
Route Middleware (auth, RBAC, rate limiting)
    ↓
Controller
    ├── Parse input
    ├── FormRequest validation
    ├── Policy authorization check
    ├── Delegate to Service/Action
    └── Return Response (View/JSON/Redirect)
        ↓
    Service/Action
    ├── Validate business logic
    ├── Call Repository/Models
    ├── Dispatch events
    ├── Return result
    └── ↓
    Repository/Model
    ├── Query database
    ├── Apply eager loading
    ├── Use caching
    └── Return Models/Collections
        ↓
    Eloquent ORM
    ├── Execute SQL
    ├── Trigger observers
    └── Return results
        ↓
    Database (MySQL/SQLite)
    └── Persistent storage
```

### Async Job Flow

```
Enqueued Job
    ↓
Queue Worker (Redis/database)
    ↓
Job Class (implements ShouldQueue)
    ├── Deserialize job data
    ├── Call Action or Service
    ├── Handle success or failure
    └── Log and monitor
        ↓
    Database/External Service
    ├── Execute side effects
    └── Send notifications
```

---

## Key Services & Repositories

### Core Services

| Service | Purpose | Key Methods |
|---------|---------|-------------|
| **TicketService** | Ticket workflow orchestration | `createTicket()`, `updateStatus()`, `assignTechnician()` |
| **UserService** | User management | `createUser()`, `generateToken()`, `updateProfile()` |
| **EquipmentService** | Equipment management | `createEquipment()`, `updateStatus()` |
| **CurrencyRateService** | Currency conversion | `updateRates()`, `convert()`, `getRate()` |
| **LocalizationService** | User preference formatting | `formatDate()`, `formatCurrency()`, `formatNumber()` |
| **NotificationCreatorService** | Notification creation | `createForUser()`, `createForAdminsMany()` |

### Core Repositories

| Repository | Purpose | Key Methods |
|-----------|---------|-------------|
| **TicketRepository** | Ticket data access | `find()`, `all()`, `search()`, `getForUser()` |
| **UserRepository** | User data access | `find()`, `findByEmail()`, `all()` |
| **EquipmentRepository** | Equipment data access | `find()`, `getByRoom()`, `getLowStock()` |

---

## Security Architecture

### Authentication

**Mechanism**: JWT tokens (API) + Session (Web)

```
Web Request
    ├── Session cookie → Laravel session store
    └── Session verified → User authenticated

API Request
    ├── Authorization: Bearer {token}
    ├── Token verified via HMAC-SHA256
    └── User authenticated
```

### Authorization (RBAC)

**Roles**:
- **User** — Can view/create own tickets, view assigned equipment
- **Technician** — Can manage assigned tickets, view stock
- **Admin** — Full access to all resources

**Enforcement Points**:
- Route middleware (`role:admin`, `role:technician`)
- Controller: `$this->authorize('action', $model)`
- Blade: `@can('action', $model)` / `@auth('role')`

### Input Validation

**Layers**:
1. **FormRequest** — HTTP-level validation (Laravel rules)
2. **DTO Construction** — Type validation (readonly properties)
3. **Service Layer** — Business logic validation (exceptions)

### Data Protection

- Passwords hashed with Bcrypt
- Tokens hashed with HMAC-SHA256
- Sensitive data in `$hidden` on models
- Rate limiting on auth endpoints
- CSRF tokens on all state-changing requests

---

## Performance Considerations

### Query Optimization

1. **Eager Loading**: Always use `->with()` to prevent N+1 queries
2. **Selective Columns**: Use `->select()` to fetch only needed fields
3. **Lazy Loading**: Use `->lazy()` for large datasets
4. **Indexing**: All foreign keys and filter columns indexed

### Caching

1. **Query Results**: Heavy queries cached via `Cache::remember()`
2. **Cache Invalidation**: Observers clear cache on model changes
3. **Redis Integration**: Production uses Redis for better performance
4. **TTL Strategy**: 1-hour theme settings, 60-second dashboards

### Worker Mode (Octane)

- Single app bootstrap per worker (not per request)
- Memory efficiency through worker pooling
- FrankenPHP integration in production Docker image
- Max execution time: 30 seconds per request

---

## Testing Strategy

### Test Organization

```
tests/
├── Unit/              # Test individual classes
├── Feature/           # Test HTTP endpoints
├── Security/          # Security-focused tests
├── Performance/       # Load testing
└── Database/          # Schema validation
```

### Test Coverage

- **Unit Tests**: Models, Services, Actions, Value Objects
- **Feature Tests**: Controllers, Workflows, Integration points
- **Security Tests**: Authorization, input validation, SQL injection
- **Performance Tests**: Query counts, memory usage, endpoint speed

### Running Tests

```bash
php artisan test                          # All tests (1410+)
php artisan test --filter=AuthFlow        # Specific suite
php artisan test tests/Unit/              # Directory
php artisan test --coverage               # Coverage report
```

---

## Deployment Architecture

### Production Stack

```
┌─────────────────┐
│  Docker Image   │
│ (FrankenPHP)    │
├─────────────────┤
│ Laravel Octane  │
│ Worker Mode     │
├─────────────────┤
│ PHP 8.5 + ext   │
│ OPcache enabled │
├─────────────────┤
│ MySQL Database  │
│ Redis Cache     │
└─────────────────┘
```

### Build Process

1. **Frontend**: Vite builds assets to `public/build/`
2. **Vendor**: Composer installs production dependencies with optimizations
3. **Image**: Multi-stage Docker build (frontend → vendor → production)

### Runtime Commands

```bash
# Before startup
php artisan config:cache
php artisan route:cache
php artisan migrate --force

# Startup
php artisan octane:frankenphp --host=0.0.0.0 --port=80

# Health check
GET http://127.0.0.1/health
```

---

## Key Design Decisions

### Why Repositories?
- Abstraction over ORM implementation
- Easier testing (mock repositories)
- Centralized query optimization

### Why Actions?
- Single responsibility principle
- Reusable across web and API
- Easily queued for async execution

### Why DTOs?
- Type safety (catch bugs at construction time)
- Immutability (prevent accidental modifications)
- Clear contracts between layers

### Why Services?
- Business logic orchestration
- Reusability across controllers
- Dependency injection for testability

### Why Observers?
- Automatic side-effects (audit, notifications, cache)
- Decoupled from business logic
- Runs after database transaction commits

---

**Last Updated**: September 1, 2026  
**Status**: Production-Ready
