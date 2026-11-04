# app/Repositories

## What is a Repository?

Think of the repositories in this folder as **The Librarians**. The rest of the app — controllers, services, CLI commands — never talks to the database directly. Instead, they ask a librarian: "get me all tickets assigned to technician #5" or "save this new room." The librarian knows exactly which shelf to look on, which index to use, and how to format the results, so the rest of the app doesn't have to.

Each repository wraps one Eloquent model (Laravel's way of representing a database table as a PHP class) and implements the contract (interface) defined in `app/Repositories/Contracts/`. That contract acts as a job description: the librarian promises certain methods exist, and the code in this folder is the actual implementation.

**How are they wired up?** Laravel's service container binds each interface to its concrete class inside `app/Providers/AppServiceProvider.php`. When a controller's constructor asks for `TicketRepositoryInterface`, Laravel automatically creates a `TicketRepository` and hands it over. The controller never needs to import the concrete class — it only depends on the contract.

> **New to programming?** See [NON_TECHNICAL_PROJECT_GUIDE.md](../../NON_TECHNICAL_PROJECT_GUIDE.md) -- this folder is explained as "The Librarians" who fetch and store data from the database on behalf of other workers.

## The Repositories

---

### EquipmentRepository

**File:** `app/Repositories/EquipmentRepository.php`
**What it is:** Manages all database operations for the `equipment` table — listing, searching, creating, updating, and deleting pieces of equipment.
**Implements:** `EquipmentRepositoryInterface` (from `app/Repositories/Contracts/EquipmentRepositoryInterface.php`)
**Constructor dependencies:** None (uses the static `Equipment` model directly).

#### Public methods

##### `findById(int $id): ?Equipment`

Finds a single equipment record by its primary key.

- **Query logic:** Calls `Equipment::find($id)`. This is a simple primary-key lookup — one `SELECT * FROM equipment WHERE id = ? LIMIT 1` query with no eager loading.
- **Returns:** The `Equipment` model instance, or `null` if no record matches.
- **Who calls it:** Not called directly by any controller or service in the current codebase (the controllers resolve equipment via route-model binding instead). Available for use by any caller that needs a single equipment by ID.

##### `getAll(array $relations = [], ?string $search = null, ?string $status = null, ?string $category = null): LengthAwarePaginator`

Fetches a paginated, filterable list of all equipment records, ordered newest-first.

- **Query logic:**
  1. Starts with `Equipment::with($relations)->latest()` — eager-loads the requested relations and orders by `created_at DESC`.
  2. **Search filter** (`$search`): If provided, delegates to `App\Support\SqlPattern::whereContains($query, ['name', 'serial', 'brand', 'model'], $search)`, which emits one bracketed group of `LIKE ? ESCAPE '!'` clauses joined with `OR`, so typing "dell" matches equipment in *any* of those fields and a literal `%`/`_` in the search is not treated as a wildcard.
  3. **Status filter** (`$status`): If provided, adds `->where('status', $status)` — an exact-match `WHERE` clause on the `status` column.
  4. **Category filter** (`$category`): If provided, uses `->whereHas('category', fn ($q) => $q->where('name', $category))` — a relational sub-query that checks the related `category` model's `name` column.
  5. All three filters can be combined (they stack with implicit `AND`).
  6. Paginates at **15 items per page** via `->paginate(15)`.
- **Returns:** A `LengthAwarePaginator<Equipment>` with items, current page, total pages, etc.
- **Who calls it:**
  - `AdminEquipmentController::index()` at `app/Http/Controllers/AdminEquipmentController.php:39` — passes `['room', 'category']` as relations and forwards `$search`, `$status`, `$category` from the request query string. This powers the `GET /admin/equipment` and `GET /api/admin/equipment` routes (both web and API, admin-only).

##### `create(array $data): Equipment`

Creates and persists a new equipment record.

- **Query logic:** Calls `Equipment::create($data)`, which performs an `INSERT INTO equipment ...` with all key-value pairs in `$data`. Returns the newly created model (with its `id` populated).
- **Returns:** The created `Equipment` instance.
- **Who calls it:** Not called directly by controllers (the `AdminEquipmentController` delegates creation to `CreateEquipmentAction`, which calls `Equipment::create()` on the model directly). Available for programmatic use.

##### `update(Equipment $equipment, array $data): bool`

Updates an existing equipment record with the provided data.

- **Query logic:** Calls `$equipment->update($data)`, which performs `UPDATE equipment SET ... WHERE id = ?` for every key in `$data`.
- **Returns:** `true` on success, `false` on failure.
- **Who calls it:** Not called directly by controllers (the `AdminEquipmentController` delegates updates to `UpdateEquipmentAction`, which calls `$equipment->update()` on the model directly). Available for programmatic use.

##### `delete(Equipment $equipment): bool`

Permanently removes an equipment record from the database.

- **Query logic:** Calls `$equipment->delete()`, which performs `DELETE FROM equipment WHERE id = ?`.
- **Returns:** `true` on success.
- **Who calls it:**
  - `AdminEquipmentController::destroy()` at `app/Http/Controllers/AdminEquipmentController.php:95` — after authorizing the `delete` policy, calls `$this->equipmentRepository->delete($equipment)`. This powers `DELETE /admin/equipment/{equipment}` and `DELETE /api/admin/equipment/{equipment}`.

---

### RoomRepository

**File:** `app/Repositories/RoomRepository.php`
**What it is:** Manages all database operations for the `rooms` table — listing, creating, updating, and soft-deactivating rooms.
**Implements:** `RoomRepositoryInterface` (from `app/Repositories/Contracts/RoomRepositoryInterface.php`)
**Constructor dependencies:** None (uses the static `Room` model directly).

#### Public methods

##### `findById(int $id): ?Room`

Finds a single room by its primary key.

- **Query logic:** `Room::find($id)` — one `SELECT * FROM rooms WHERE id = ? LIMIT 1`.
- **Returns:** The `Room` model or `null`.
- **Who calls it:** Not called directly by any controller (rooms are resolved via route-model binding in `RoomController`). Available for programmatic use.

##### `getAll(array $relations = [], array $withCounts = []): LengthAwarePaginator`

Fetches a paginated list of rooms, ordered newest-first, with optional eager-loaded relations and relation counts.

- **Query logic:** `Room::with($relations)->withCount($withCounts)->latest()->paginate(15)`.
  - `$relations` — an array of relation names to eager-load (e.g. `['equipments']`).
  - `$withCounts` — an array of relation names to count via sub-queries (e.g. `['equipments']` adds an `equipments_count` column).
  - Orders by `created_at DESC`, paginates at **15 items per page**.
- **Returns:** `LengthAwarePaginator<Room>`.
- **Who calls it:**
  - `RoomController::indexRoom()` at `app/Http/Controllers/RoomController.php:34` — passes `withCounts: ['equipments']` so the response includes how many pieces of equipment each room has. Powers `GET /admin/rooms`, `GET /api/admin/rooms`, `GET /api/rooms`, and `GET /rooms` routes (web + API, admin-only for the admin routes).

##### `getActive(): array`

Returns **all** active rooms in one shot — not paginated, just the essential fields.

- **Query logic:**
  1. `Room::where('active', true)` — filters to only rooms where `active = true`.
  2. `->orderBy('location')->orderBy('name')` — sorts by `location` first, then by `name` alphabetically.
  3. `->get(['id', 'name', 'code', 'location'])` — selects only four columns (lightweight).
  4. `->toArray()` — converts the Eloquent collection to a plain PHP array.
- **Returns:** `array<int, Room>` — an indexed array of room data, each containing `id`, `name`, `code`, and `location`.
- **Who calls it:** Not currently called by any controller or service in the codebase. Designed for populating dropdown menus or assignment forms where you need a flat list of active rooms without pagination. Available for programmatic use.

##### `create(array $data): Room`

Creates and persists a new room.

- **Query logic:** `Room::create($data)` — `INSERT INTO rooms ...`.
- **Returns:** The created `Room` instance.
- **Who calls it:** Not called directly by controllers (the `RoomController` delegates creation to `CreateRoomAction`). Available for programmatic use.

##### `update(Room $room, array $data): bool`

Updates an existing room.

- **Query logic:** `$room->update($data)` — `UPDATE rooms SET ... WHERE id = ?`.
- **Returns:** `true` on success.
- **Who calls it:** Not called directly by controllers (the `RoomController` delegates updates to `UpdateRoomAction`). Available for programmatic use.

##### `inactivate(Room $room): bool`

Soft-deactivates a room by setting its `active` flag to `false`. The room is **not** deleted — it remains in the database for historical reference.

- **Query logic:** `$room->update(['active' => false])` — `UPDATE rooms SET active = 0 WHERE id = ?`.
- **Returns:** `true` on success.
- **Who calls it:**
  - `RoomController::inactivateRoom()` at `app/Http/Controllers/RoomController.php:92` — after authorization, calls `$this->roomRepository->inactivate($room)`. Powers `PATCH /admin/rooms/{room}/inactive` and `PATCH /api/admin/rooms/{room}/inactive`.

---

### TicketRepository

**File:** `app/Repositories/TicketRepository.php`
**What it is:** Manages all database operations for maintenance tickets (work orders) — including specialized queries that filter tickets by status, technician, or creating user.
**Implements:** `TicketRepositoryInterface` (from `app/Repositories/Contracts/TicketRepositoryInterface.php`)
**Constructor dependencies:** None (uses the static `Ticket` model directly).

This is the **busiest repository** in the system — maintenance tickets are the core domain entity of the SGM platform.

#### Public methods

##### `findById(int $id): ?Ticket`

Finds a single ticket by its primary key, without eager-loading any relations.

- **Query logic:** `Ticket::find($id)` — one `SELECT * FROM tickets WHERE id = ? LIMIT 1`.
- **Returns:** The `Ticket` model or `null`.
- **Who calls it:** Not called directly by controllers (tickets are resolved via route-model binding in `TicketController::show()`). Available for programmatic use.

##### `findWithRelations(int $id, array $relations = []): ?Ticket`

Finds a single ticket by ID while eagerly loading specified relations in one query — avoids N+1.

- **Query logic:** `Ticket::with($relations)->find($id)` — performs `SELECT *, relation.* FROM tickets LEFT JOIN ... WHERE tickets.id = ?` for each requested relation.
- **Returns:** The `Ticket` model with loaded relations, or `null`.
- **Who calls it:** Not called directly by any controller or service in the current codebase. Available for use when a caller needs a single ticket with specific related data loaded eagerly (e.g. for a detail page or an action that needs equipment + room + technician).

##### `getAll(array $relations = []): LengthAwarePaginator`

Fetches a paginated list of all tickets, ordered newest-first, with optional eager-loaded relations.

- **Query logic:**
  1. Reads the per-page value from `config('services.custom.pagination.default_per_page', 15)` — defaults to 15.
  2. `Ticket::with($relations)->latest()->paginate($perPage)` — eager-loads requested relations, orders by `created_at DESC`.
- **Returns:** `LengthAwarePaginator<Ticket>`.
- **Who calls it:**
  - `TicketController::index()` at `app/Http/Controllers/TicketController.php:44` — when the authenticated user is an admin, calls `$this->ticketRepository->getAll(['equipment', 'room', 'user', 'technician', 'status'])` to fetch all tickets with five eagerly-loaded relations. Powers `GET /tickets`, `GET /api/tickets`.

##### `create(array $data): Ticket`

Creates and persists a new ticket.

- **Query logic:** `Ticket::create($data)` — `INSERT INTO tickets ...`.
- **Returns:** The created `Ticket` instance.
- **Who calls it:** Not called directly by controllers (the `TicketController` delegates creation to `CreateTicketAction`). Available for programmatic use.

##### `update(Ticket $ticket, array $data): bool`

Updates an existing ticket.

- **Query logic:** `$ticket->update($data)` — `UPDATE tickets SET ... WHERE id = ?`.
- **Returns:** `true` on success.
- **Who calls it:** Not called directly by controllers (ticket updates go through domain actions like `CloseTicketAction`, `SubmitBudgetAction`, `ScheduleTicketAction`, etc., which call `$ticket->update()` directly on the model). Available for programmatic use.

##### `delete(Ticket $ticket): bool`

Permanently removes a ticket from the database.

- **Query logic:** `$ticket->delete()` — `DELETE FROM tickets WHERE id = ?`.
- **Returns:** `true` on success.
- **Who calls it:** Not called directly by any controller or service in the current codebase. Tickets are typically transitioned to a closed/cancelled state rather than deleted.

##### `getOpenTickets(): LengthAwarePaginator`

Returns only tickets that are still open (not yet resolved or closed), with full relationship data.

- **Query logic:**
  1. Reads per-page from `config('services.custom.pagination.default_per_page', 15)`.
  2. `Ticket::with(['equipment', 'room', 'user', 'status', 'technician'])` — eagerly loads five relationships: the equipment the ticket is about, the room where the issue occurred, the user who created the ticket, the current status, and the assigned technician.
  3. `->open()` — applies the `open` scope defined on the `Ticket` model (filters to tickets whose status indicates they are still open).
  4. `->latest()->paginate($perPage)` — newest-first pagination.
- **Returns:** `LengthAwarePaginator<Ticket>`.
- **Who calls it:**
  - `TicketController::openTickets()` at `app/Http/Controllers/TicketController.php:172` — restricted to technicians and admins. Powers `GET /technician/tickets/open` and the admin panel's open-tickets view.

##### `getTicketsByTechnician(int $technicianId): LengthAwarePaginator`

Returns only tickets assigned to a specific technician, with full relationship data.

- **Query logic:**
  1. Reads per-page from config (default 15).
  2. `Ticket::with(['equipment', 'room', 'user', 'status', 'technician'])` — same five eager-loaded relations.
  3. `->forTechnician($technicianId)` — applies the `forTechnician` scope defined on the `Ticket` model, which filters by the `technician_id` column.
  4. `->latest()->paginate($perPage)` — newest-first pagination.
- **Returns:** `LengthAwarePaginator<Ticket>`.
- **Who calls it:**
  - `TicketController::index()` at `app/Http/Controllers/TicketController.php:42` — when the authenticated user has the "Technician" profile role, only their assigned tickets are returned. Powers `GET /tickets` and `GET /api/tickets` for technician users.

##### `getTicketsByUser(int $userId): LengthAwarePaginator`

Returns only tickets created by a specific user (their "my requests" view), with four relationships loaded.

- **Query logic:**
  1. Reads per-page from config (default 15).
  2. `Ticket::with(['equipment', 'room', 'technician', 'status'])` — eagerly loads four relations (notably does *not* load `user` since the caller *is* the user).
  3. `->where('user_id', $userId)` — filters to the user's own tickets.
  4. `->latest()->paginate($perPage)` — newest-first pagination.
- **Returns:** `LengthAwarePaginator<Ticket>`.
- **Who calls it:**
  - `TicketController::index()` at `app/Http/Controllers/TicketController.php:47` — for regular (non-admin, non-technician) users, only their own tickets are returned. Powers `GET /tickets` and `GET /api/tickets` for standard users.

---

### UserRepository

**File:** `app/Repositories/UserRepository.php`
**What it is:** Manages all database operations for user accounts — listing, searching, creating, updating, soft-deactivating, hard-deleting, plus special lookups for technicians and admins.
**Implements:** `UserRepositoryInterface` (from `app/Repositories/Contracts/UserRepositoryInterface.php`)
**Constructor dependencies:** None (uses the static `User` model directly).

This is the **most security-conscious repository** — it includes SQL injection protection in its search logic.

#### Public methods

##### `findById(int $id): ?User`

Finds a single user by their primary key.

- **Query logic:** `User::find($id)` — `SELECT * FROM users WHERE id = ? LIMIT 1`.
- **Returns:** The `User` model or `null`.
- **Who calls it:** Not called directly by controllers (users are resolved via route-model binding in `AdminUserController`). Available for programmatic use.

##### `findByEmail(string $email): ?User`

Looks up a user by email address (case-insensitive).

- **Query logic:** `User::where('email', strtolower($email))->first()` — normalizes the email to lowercase before the query, so `Admin@Example.COM` matches `admin@example.com`. Performs `SELECT * FROM users WHERE email = ? LIMIT 1`.
- **Returns:** The `User` model or `null`.
- **Who calls it:** Not called directly by any controller or service in the current codebase. Designed for authentication flows, password resets, or any logic that needs to resolve a user from an email. Available for programmatic use.

##### `getAll(array $relations = [], ?string $search = null, ?string $role = null, ?string $status = null): LengthAwarePaginator`

Fetches a paginated, filterable list of users — the most feature-rich query method across all repositories.

- **Query logic:**
  1. Starts with `User::with($relations)->latest()` — eager-loads requested relations and orders by `created_at DESC`.
  2. **Search filter** (`$search`):
     - Delegates to `App\Support\SqlPattern::whereContains($query, ['name', 'email'], $search)`, which escapes the value and emits `LIKE ? ESCAPE '!'` so `%` and `_` typed by a user match literally instead of acting as wildcards. Injection safety comes from parameter binding — the value never enters the SQL string.
     - Applies the escaped contains-match across two columns: `name` and `email`, joined with `OR` inside one bracketed group.
  3. **Role filter** (`$role`): If provided, uses `->whereHas('profile', fn ($q) => $q->where('name', $role))` — a relational sub-query that checks the related `UserProfile` model's `name` column (e.g. "Technician", "Admin").
  4. **Status filter** (`$status`): If `'active'`, adds `->where('active', true)`. If `'inactive'`, adds `->where('active', false)`. Any other value is ignored.
  5. Paginates at **15 items per page**.
- **Returns:** `LengthAwarePaginator<User>`.
- **Who calls it:**
  - `AdminUserController::index()` at `app/Http/Controllers/AdminUserController.php:41` — passes `['profile']` as relations and forwards `$search`, `$role`, `$status` from the request query string. Powers `GET /admin/users` and `GET /api/admin/users`.

##### `getActiveTechnicians(): array`

Returns all active users whose profile role is "Technician" — just their ID and name.

- **Query logic:**
  1. `User::whereHas('profile', fn ($q) => $q->where('name', UserRoleEnum::Technician->value))` — filters to users whose related `UserProfile` has `name = 'Technician'`.
  2. `->where('active', true)` — only active users.
  3. `->get(['id', 'name'])` — selects only two columns (lightweight).
  4. `->toArray()` — converts to a plain PHP array.
- **Returns:** `array<int, User>` — an indexed array, each containing `id` and `name`.
- **Who calls it:** Not called directly by any controller or service in the current codebase. Designed for populating technician-assignment dropdowns or API endpoints that list available technicians. Available for programmatic use.

##### `getAdmins(): array`

Returns all users whose profile role is "Admin" — just their ID and name.

- **Query logic:**
  1. `User::whereHas('profile', fn ($q) => $q->where('name', UserRoleEnum::Admin->value))` — filters by admin profile.
  2. `->get(['id', 'name'])` — lightweight select.
  3. `->toArray()`.
- **Returns:** `array<int, User>`.
- **Who calls it:** Not called directly by any controller or service in the current codebase. Available for programmatic use.

##### `create(array $data): User`

Creates and persists a new user.

- **Query logic:** `User::create($data)` — `INSERT INTO users ...`.
- **Returns:** The created `User` instance.
- **Who calls it:** Not called directly by controllers (the `AdminUserController` delegates creation to `CreateUserAction`). Available for programmatic use.

##### `update(User $user, array $data): bool`

Updates an existing user.

- **Query logic:** `$user->update($data)` — `UPDATE users SET ... WHERE id = ?`.
- **Returns:** `true` on success.
- **Who calls it:** Not called directly by controllers (the `AdminUserController` delegates updates to `UpdateUserAction`). Available for programmatic use.

##### `inactivate(User $user): bool`

Soft-deactivates a user by setting `active` to `false`. The record stays in the database.

- **Query logic:** `$user->update(['active' => false])` — `UPDATE users SET active = 0 WHERE id = ?`.
- **Returns:** `true` on success.
- **Who calls it:**
  - `AdminUserController::inactivate()` at `app/Http/Controllers/AdminUserController.php:104` — after authorization and a self-deactivation check, calls `$this->userRepository->inactivate($targetUser)`. Powers `PATCH /admin/users/{targetUser}/inactive` and `PATCH /api/admin/users/{targetUser}/inactive`.

##### `delete(User $user): bool`

Permanently deletes (hard-deletes) a user from the database. The return value is cast to `bool` for consistent return types.

- **Query logic:** `(bool) $user->delete()` — `DELETE FROM users WHERE id = ?`. The `(bool)` cast ensures the method always returns `true`/`false` even if Eloquent returns `int`.
- **Returns:** `true` on success.
- **Who calls it:**
  - `AdminUserController::destroy()` at `app/Http/Controllers/AdminUserController.php:130` — after authorization and a self-deletion check, calls `$this->userRepository->delete($targetUser)`. Powers `DELETE /admin/users/{targetUser}` and `DELETE /api/admin/users/{targetUser}`.

---

## Files

| File | Purpose |
|---|---|
| `EquipmentRepository.php` | CRUD operations for `Equipment` model with multi-column search (`name`, `serial`, `brand`, `model`), status filter, and category filter via `whereHas`. Implements `EquipmentRepositoryInterface`. |
| `RoomRepository.php` | CRUD operations for `Room` model, includes `getActive()` for dropdown lists (returns `id`, `name`, `code`, `location` for all active rooms) and `inactivate()` for soft-deactivation. Implements `RoomRepositoryInterface`. |
| `TicketRepository.php` | CRUD operations for `Ticket` model, plus three specialized paginated queries: `getOpenTickets()` (applies `open()` scope, loads 5 relations), `getTicketsByTechnician()` (applies `forTechnician()` scope), and `getTicketsByUser()` (filters by `user_id`, loads 4 relations). Per-page from `config('services.custom.pagination.default_per_page')`. Implements `TicketRepositoryInterface`. |
| `UserRepository.php` | CRUD operations for `User` model, plus `getActiveTechnicians()` and `getAdmins()` (lightweight lookups via `whereHas` on `profile`), `findByEmail()` (case-insensitive), `inactivate()`, and wildcard-safe search through `App\Support\SqlPattern`. Implements `UserRepositoryInterface`. |

## Notes for developers / AI

- All classes are `final` and implement their respective `Contracts/` interface.
- Each repository uses `LengthAwarePaginator` with a default of 15 items per page (TicketRepository reads from config).
- Method `{@inheritDoc}` docblocks delegate documentation to the interface.
- No raw SQL — all queries use Eloquent's query builder.
- Repositories are bound to interfaces in `AppServiceProvider::register()` via `$this->app->bind(...)`.
- Controllers depend on the interface types, not the concrete classes (constructor injection).
- The `TicketRepository` is the most heavily used — `TicketController` calls four of its methods depending on user role.
- `RoomRepository::getActive()`, `UserRepository::getActiveTechnicians()`, and `UserRepository::getAdmins()` are currently unused by any controller/service but are available for future use.
