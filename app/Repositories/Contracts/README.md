# app/Repositories/Contracts

## What is a Contract (Interface)?

Imagine you need to hire a librarian. Before you interview anyone, you write down a **job description**: "must be able to find books by title, check out books, and accept returns." That job description doesn't tell the librarian *how* to do the work — it only lists *what* they must be able to do.

In programming, that "job description" is called an **interface** or **contract**. Each file in this folder is a contract that says: "any repository that handles this type of data *must* provide these specific methods." The actual code that does the work lives in `app/Repositories/` (see the parent README), but this folder defines the rules those repositories agree to follow.

Contracts are powerful because other parts of the app (like controllers) only type-hint the contract, never the concrete class. That means you could swap in a completely different implementation without changing a single line in the controller — like replacing one librarian with another, as long as the new one follows the same job description.

> **New to programming?** See [NON_TECHNICAL_PROJECT_GUIDE.md](../../../NON_TECHNICAL_PROJECT_GUIDE.md) -- contracts are the "job descriptions" that tell the Librarians (repositories) exactly which skills they must have.

## How they are wired up

In `app/Providers/AppServiceProvider.php`, Laravel's service container binds each interface to its concrete implementation:

```php
$this->app->bind(UserRepositoryInterface::class, UserRepository::class);
$this->app->bind(TicketRepositoryInterface::class, TicketRepository::class);
$this->app->bind(EquipmentRepositoryInterface::class, EquipmentRepository::class);
$this->app->bind(RoomRepositoryInterface::class, RoomRepository::class);
```

When a controller asks for `TicketRepositoryInterface` in its constructor, Laravel automatically hands it a `TicketRepository` instance. The controller never knows (or cares) which class it actually received.

---

## The Contracts

### EquipmentRepositoryInterface

**File:** `app/Repositories/Contracts/EquipmentRepositoryInterface.php`
**Implements:** `App\Repositories\EquipmentRepository`

The job description for the equipment librarian. It promises these methods:

| Method | Signature | What it does |
|---|---|---|
| `findById` | `findById(int $id): ?Equipment` | Looks up a single piece of equipment by its unique primary key. Returns the `Equipment` model or `null` if nothing matches. |
| `getAll` | `getAll(array $relations = [], ?string $search = null, ?string $status = null, ?string $category = null): LengthAwarePaginator` | Fetches a paginated list of equipment (default 15/page, newest-first). The caller can ask for related data (e.g. the equipment's `room` or `category`) via `$relations`. Supports three optional filters that stack: `$search` (fuzzy-matches across `name`, `serial`, `brand`, and `model` columns), `$status` (exact match on `status` column), and `$category` (filters by related `category` model's `name`). Returns a `LengthAwarePaginator`. |
| `create` | `create(array $data): Equipment` | Saves a brand-new equipment record to the database. `$data` is an associative array of column values. Returns the created `Equipment` model with its `id` populated. |
| `update` | `update(Equipment $equipment, array $data): bool` | Updates an existing equipment record with new values. `$data` is an associative array of columns to change. Returns `true` on success. |
| `delete` | `delete(Equipment $equipment): bool` | Permanently removes an equipment record from the database. Returns `true` on success. |

**PHPDoc types:** `getAll` uses `@param array<int, string>` for `$relations` and `@return LengthAwarePaginator<Equipment>`. `create` and `update` use `@param array<string, mixed>` for `$data`.

---

### RoomRepositoryInterface

**File:** `app/Repositories/Contracts/RoomRepositoryInterface.php`
**Implements:** `App\Repositories\RoomRepository`

The job description for the room librarian:

| Method | Signature | What it does |
|---|---|---|
| `findById` | `findById(int $id): ?Room` | Looks up a single room by its unique primary key. Returns the `Room` model or `null`. |
| `getAll` | `getAll(array $relations = [], array $withCounts = []): LengthAwarePaginator` | Fetches a paginated list of rooms (15/page, newest-first). `$relations` lists relations to eager-load (e.g. `['equipments']`). `$withCounts` lists relations to count via sub-queries (e.g. `['equipments']` adds an `equipments_count` column to each room). Returns a `LengthAwarePaginator`. |
| `getActive` | `getActive(): array` | Returns **all** active rooms in one shot — not paginated. Selects only four lightweight columns: `id`, `name`, `code`, and `location`. Sorted by `location` then `name`. Ideal for populating dropdown menus, assignment forms, or API endpoints that need a flat list of available rooms. Returns a plain PHP array. |
| `create` | `create(array $data): Room` | Saves a new room record. Returns the created `Room` model. |
| `update` | `update(Room $room, array $data): bool` | Updates an existing room. Returns `true` on success. |
| `inactivate` | `inactivate(Room $room): bool` | Soft-deactivates a room by setting `active` to `false`. The room is **not** deleted — it stays in the database for historical reference. Returns `true` on success. |

**PHPDoc types:** `getAll` uses `@param array<int, string>` for both `$relations` and `$withCounts`, and `@return LengthAwarePaginator<Room>`. `getActive` uses `@return array<int, Room>`.

---

### TicketRepositoryInterface

**File:** `app/Repositories/Contracts/TicketRepositoryInterface.php`
**Implements:** `App\Repositories\TicketRepository`

The job description for the ticket (maintenance request) librarian. This is the **most feature-rich contract** in the system:

| Method | Signature | What it does |
|---|---|---|
| `findById` | `findById(int $id): ?Ticket` | Looks up a single ticket by its primary key without loading any relations. Returns `Ticket` or `null`. |
| `findWithRelations` | `findWithRelations(int $id, array $relations = []): ?Ticket` | Like `findById`, but eagerly loads specified relationships in one query (e.g. `['equipment', 'room', 'status', 'technician']`). This avoids the N+1 problem when you need a single ticket with its related data. Returns `Ticket` or `null`. |
| `getAll` | `getAll(array $relations = []): LengthAwarePaginator` | Fetches a paginated list of **all** tickets, newest-first. Caller specifies which relations to eager-load. Per-page is read from `config('services.custom.pagination.default_per_page')`, defaulting to 15. Returns `LengthAwarePaginator`. |
| `create` | `create(array $data): Ticket` | Saves a new ticket. Returns the created `Ticket` model. |
| `update` | `update(Ticket $ticket, array $data): bool` | Updates an existing ticket. Returns `true` on success. |
| `delete` | `delete(Ticket $ticket): bool` | Removes a ticket from the database. Returns `true` on success. |
| `getOpenTickets` | `getOpenTickets(): LengthAwarePaginator` | Returns only tickets that are still open (applies the Ticket model's `open()` scope). Eager-loads **five** relationships: `equipment`, `room`, `user`, `status`, and `technician` — giving the caller the full picture of each open ticket in one query. Paginated, newest-first. |
| `getTicketsByTechnician` | `getTicketsByTechnician(int $technicianId): LengthAwarePaginator` | Returns only tickets assigned to a specific technician (applies the `forTechnician($technicianId)` scope on the model). Same five eager-loaded relationships as `getOpenTickets()`. Paginated, newest-first. Powers the technician's "my assignments" view. |
| `getTicketsByUser` | `getTicketsByUser(int $userId): LengthAwarePaginator` | Returns only tickets created by a specific user (`WHERE user_id = $userId`). Eager-loads four relationships: `equipment`, `room`, `technician`, and `status` (notably does *not* load `user` since the caller *is* the user). Paginated, newest-first. Powers the "my requests" view. |

**PHPDoc types:** `findWithRelations` and `getAll` use `@param array<int, string>` for `$relations`. `getAll`, `getOpenTickets`, `getTicketsByTechnician`, and `getTicketsByUser` all use `@return LengthAwarePaginator<Ticket>`.

---

### UserRepositoryInterface

**File:** `app/Repositories/Contracts/UserRepositoryInterface.php`
**Implements:** `App\Repositories\UserRepository`

The job description for the user librarian:

| Method | Signature | What it does |
|---|---|---|
| `findById` | `findById(int $id): ?User` | Looks up a single user by their primary key. Returns `User` or `null`. |
| `findByEmail` | `findByEmail(string $email): ?User` | Looks up a user by their email address (case-insensitive — normalizes to lowercase before querying). Returns `User` or `null`. Useful for authentication flows. |
| `getAll` | `getAll(array $relations = [], ?string $search = null, ?string $role = null, ?string $status = null): LengthAwarePaginator` | Fetches a paginated, filterable list of users (15/page, newest-first). Supports: `$search` (fuzzy-matches across `name` and `email`; the value is bound as a parameter and its `%`/`_` characters are escaped with an explicit `ESCAPE` clause by `App\Support\SqlPattern`, so a search term can never act as a wildcard or alter the SQL); `$role` (filters by the related `UserProfile` model's `name`, e.g. "Technician" or "Admin"); `$status` (`'active'` or `'inactive'` maps to the `active` boolean column). All filters stack. |
| `getActiveTechnicians` | `getActiveTechnicians(): array` | Returns all active users whose profile role is "Technician" — just `id` and `name` (lightweight). Not paginated. Designed for populating technician-assignment dropdowns. Returns a plain PHP array. |
| `getAdmins` | `getAdmins(): array` | Returns all users whose profile role is "Admin" — just `id` and `name`. Not paginated. Returns a plain PHP array. |
| `create` | `create(array $data): User` | Saves a new user. Returns the created `User` model. |
| `update` | `update(User $user, array $data): bool` | Updates an existing user. Returns `true` on success. |
| `inactivate` | `inactivate(User $user): bool` | Soft-deactivates a user by setting `active` to `false`. The record stays in the database for historical integrity. Returns `true` on success. |
| `delete` | `delete(User $user): bool` | Deletes a user from the database. Returns `true` on success. |

**PHPDoc types:** `getAll` uses `@param array<int, string>` for `$relations` and `@return LengthAwarePaginator<User>`. `getActiveTechnicians` and `getAdmins` use `@return array<int, User>`.

---

## Notes for developers / AI

- All interfaces use `LengthAwarePaginator` for paginated results.
- PHPDoc `@param` / `@return` types are documented on every method for IDE support.
- Implementations live in `app/Repositories/`.
- All four interfaces are bound in `AppServiceProvider::register()` using `$this->app->bind()`.
- Controllers depend on these interfaces via constructor injection — they never import the concrete repository classes.
- The `TicketRepositoryInterface` is the largest (9 methods) because tickets are the core domain entity of the SGM maintenance platform.
- The `UserRepositoryInterface` includes `findByEmail()` and two specialized lookup methods (`getActiveTechnicians`, `getAdmins`) that the other interfaces do not have, reflecting the unique query needs of user management.
