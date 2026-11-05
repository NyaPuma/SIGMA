# app/Policies

Authorization policies defining which users can perform actions on each model.

> **New to programming?** See [NON_TECHNICAL_PROJECT_GUIDE.md](../../NON_TECHNICAL_PROJECT_GUIDE.md) -- this folder is explained as "The Access Control List" that decides who is allowed to do what (view, edit, delete).

Every class in this folder is a **Laravel Policy**. Laravel automatically resolves the correct policy for a model when you call `$this->authorize('ability', $model)` from a controller or `Gate::` from anywhere. The policies are **registered** in `app/Providers/AppServiceProvider.php::registerPolicies()` so they take effect app-wide.

All policies are `final` classes with **no constructor injection** — they are stateless and receive the acting `User` as the first parameter of every method.

## Role helper reference

All role checks rely on the `User` model methods (defined in `app/Models/User.php:91-104`):
- `$user->isAdmin()` → true when the user's role profile name equals `UserRoleEnum::Admin->value`
- `$user->isTechnician()` → true when profile name equals `UserRoleEnum::Technician->value`
- `$user->isCommonUser()` → true when profile name equals `UserRoleEnum::User->value`

---

## File table

| File | Governed model | Abilities | Registered in AppServiceProvider |
|---|---|---|---|
| `AuditPolicy.php` | `App\Models\Audit` | viewAny | ✔ |
| `EquipmentPolicy.php` | `App\Models\Equipment` | viewAny, view, create, update, delete, manage, manageAny | ✔ |
| `MaintenancePlanPolicy.php` | `App\Models\MaintenancePlan` | viewAny, view, create, update, delete | ✔ |
| `PartCategoryPolicy.php` | `App\Models\PartCategory` | viewAny, view, create, update, delete | ✔ |
| `PartPolicy.php` | `App\Models\Part` | viewAny, view, create, update, delete | ✔ |
| `RoomPolicy.php` | `App\Models\Room` | viewAny, view, create, update, delete, manage, manageAny | ✔ |
| `StockMovementPolicy.php` | `App\Models\StockMovement` | viewAny, view, create, delete | ✔ |
| `SupplierPolicy.php` | `App\Models\Supplier` | viewAny, view, create, update, delete | ✔ |
| `TaxRatePolicy.php` | `App\Models\TaxRate` | viewAny, view, create, update, delete | ✔ |
| `TicketPolicy.php` | `App\Models\Ticket` | 20 abilities (see below) | ✔ |
| `UserPolicy.php` | `App\Models\User` | viewAny, view, create, update, delete, updateProfile, inactivate, manage, manageAny | ✔ |
| `UserProfilePolicy.php` | `App\Models\UserProfile` | viewAny | ✔ |

> **Note on model parameters:** Policy methods that receive a model instance (e.g. `view(User $user, Room $room)`) are bound to that model; Laravel passes the route-model-bound instance automatically. Methods receiving no model (e.g. `create(User $user)`) only govern whether the class-wide action is allowed. The model instance body is **not** used by most policies here (authorization is purely role-based), except in `TicketPolicy` (ownership via `user_id`) and `UserPolicy`/`UserProfilePolicy` (self vs. target checks).

---

## `AuditPolicy.php`

**File:** `app/Policies/AuditPolicy.php`
**Governs:** `App\Models\Audit` (records of model lifecycle events written by the `App\Traits\Auditable` trait)

| Ability | Signature | Authorization logic | Called from |
|---|---|---|---|
| `viewAny` | `viewAny(User $user): bool` | `$user->isAdmin()` — **admin only** | `app/Http/Controllers/AuditController.php:32` (`$this->authorize('viewAny', Audit::class)`) |

**Note:** This policy is **not** bound via `Gate::policy()` in `AppServiceProvider` (it is not in the list). Laravel's convention-based discovery still resolves it because the policy class name `App\Policies\AuditPolicy` matches the model `App\Models\Audit`. The `AuditController::index()` flash/list endpoint both route through this ability.

---

## `EquipmentPolicy.php`

**File:** `app/Policies/EquipmentPolicy.php`
**Governs:** `App\Models\Equipment`
**Registered:** `Gate::policy(Equipment::class, EquipmentPolicy::class)` (`AppServiceProvider.php:115`)

| Ability | Signature | Authorization logic | Called from |
|---|---|---|---|
| `viewAny` | `viewAny(User $user): bool` | `$user->isAdmin() \|\| $user->isTechnician()` | `AdminEquipmentController.php:31` |
| `view` | `view(User $user, Equipment $equipment): bool` | `$user->isAdmin() \|\| $user->isTechnician()` (model body unused) | same as viewAny path |
| `create` | `create(User $user): bool` | `$user->isAdmin()` — **admin only** | `AdminEquipmentController.php:52` |
| `update` | `update(User $user, Equipment $equipment): bool` | `$user->isAdmin()` | `AdminEquipmentController.php:72` |
| `delete` | `delete(User $user, Equipment $equipment): bool` | `$user->isAdmin()` | `AdminEquipmentController.php:92` |
| `manage` | `manage(User $user, ?Equipment $equipment = null): bool` | `$user->isAdmin()` — the `? Equipment` param is nullable; **compatibility passthrough** | not invoked via `authorize`/`Gate` anywhere in this codebase |
| `manageAny` | `manageAny(User $user): bool` | `$user->isAdmin()` — **compatibility passthrough** | not invoked anywhere in this codebase |

**Note:** `manage()`/`manageAny()` are kept strictly for backward compatibility and are currently **unused** by any controller or gate call — they are pure `isAdmin()` passthroughs.

---

## `MaintenancePlanPolicy.php`

**File:** `app/Policies/MaintenancePlanPolicy.php`
**Governs:** `App\Models\MaintenancePlan`
**Registered:** `Gate::policy(MaintenancePlan::class, MaintenancePlanPolicy::class)` (`AppServiceProvider.php:123`)

| Ability | Signature | Authorization logic | Called from |
|---|---|---|---|
| `viewAny` | `viewAny(User $user): bool` | `$user->isAdmin() \|\| $user->isTechnician()` | `MaintenancePlanController.php:41` |
| `view` | `view(User $user, MaintenancePlan $plan): bool` | `$user->isAdmin() \|\| $user->isTechnician()` | `MaintenancePlanController.php:79` |
| `create` | `create(User $user): bool` | `$user->isAdmin()` — **admin only** | `MaintenancePlanController.php:127` |
| `update` | `update(User $user, MaintenancePlan $plan): bool` | `$user->isAdmin()` | `MaintenancePlanController.php:195` |
| `delete` | `delete(User $user, MaintenancePlan $plan): bool` | `$user->isAdmin()` | `MaintenancePlanController.php:236` |

---

## `PartCategoryPolicy.php`

**File:** `app/Policies/PartCategoryPolicy.php`
**Governs:** `App\Models\PartCategory`
**Registered:** `Gate::policy(PartCategory::class, PartCategoryPolicy::class)` (`AppServiceProvider.php:122`)

| Ability | Signature | Authorization logic | Called from |
|---|---|---|---|
| `viewAny` | `viewAny(User $user): bool` | `$user->isAdmin() \|\| $user->isTechnician()` | `PartCategoryController.php:35` |
| `view` | `view(User $user, PartCategory $category): bool` | `$user->isAdmin() \|\| $user->isTechnician()` | same as viewAny path |
| `create` | `create(User $user): bool` | `$user->isAdmin()` — **admin only** | `PartCategoryController.php:69` |
| `update` | `update(User $user, PartCategory $category): bool` | `$user->isAdmin()` | `PartCategoryController.php:110` |
| `delete` | `delete(User $user, PartCategory $category): bool` | `$user->isAdmin()` | `PartCategoryController.php:142` |

---

## `PartPolicy.php`

**File:** `app/Policies/PartPolicy.php`
**Governs:** `App\Models\Part` (spare parts in the inventory / stock)
**Registered:** `Gate::policy(Part::class, PartPolicy::class)` (`AppServiceProvider.php:118`)

| Ability | Signature | Authorization logic | Called from |
|---|---|---|---|
| `viewAny` | `viewAny(User $user): bool` | `$user->isAdmin() \|\| $user->isTechnician()` | `PartController.php:52`, `StockDashboardController.php:37,68,98,126,154,182`, `StockReportController.php:35,86,146` |
| `view` | `view(User $user, Part $part): bool` | `$user->isAdmin() \|\| $user->isTechnician()` | `PartController.php:89` |
| `create` | `create(User $user): bool` | `$user->isAdmin()` — **admin only** | `PartController.php:136` |
| `update` | `update(User $user, Part $part): bool` | `$user->isAdmin()` | `PartController.php:189` |
| `delete` | `delete(User $user, Part $part): bool` | `$user->isAdmin()` | `PartController.php:219` |

**Note:** `viewAny` on `Part` is the most widely shared ability — the stock dashboard, stock reports, and the parts CRUD all gate themselves on it, meaning technicians may read all stock views but only admins may write.

---

## `RoomPolicy.php`

**File:** `app/Policies/RoomPolicy.php`
**Governs:** `App\Models\Room`
**Registered:** `Gate::policy(Room::class, RoomPolicy::class)` (`AppServiceProvider.php:116`)

| Ability | Signature | Authorization logic | Called from |
|---|---|---|---|
| `viewAny` | `viewAny(User $user): bool` | `$user->isAdmin() \|\| $user->isTechnician()` | `RoomController.php:31` |
| `view` | `view(User $user, Room $room): bool` | `$user->isAdmin() \|\| $user->isTechnician()` | same as viewAny path |
| `create` | `create(User $user): bool` | `$user->isAdmin()` — **admin only** | `RoomController.php:53` |
| `update` | `update(User $user, Room $room): bool` | `$user->isAdmin()` | `RoomController.php:71` (updateRoom) and `RoomController.php:89` (inactivateRoom) |
| `delete` | `delete(User $user, Room $room): bool` | `$user->isAdmin()` | — |
| `manage` | `manage(User $user, ?Room $room = null): bool` | `$user->isAdmin()` — nullable param, **compatibility passthrough** | not invoked anywhere |
| `manageAny` | `manageAny(User $user): bool` | `$user->isAdmin()` — **compatibility passthrough** | not invoked anywhere |

**Note:** `RoomController::inactivateRoom()` reuses the `update` ability to authorize toggling a room's active state. `manage()`/`manageAny()` are unused compatibility passthroughs.

---

## `StockMovementPolicy.php`

**File:** `app/Policies/StockMovementPolicy.php`
**Governs:** `App\Models\StockMovement` (stock in/out/transfer/return movements)
**Registered:** `Gate::policy(StockMovement::class, StockMovementPolicy::class)` (`AppServiceProvider.php:120`)

| Ability | Signature | Authorization logic | Called from |
|---|---|---|---|
| `viewAny` | `viewAny(User $user): bool` | `$user->isAdmin() \|\| $user->isTechnician()` | `StockMovementController.php:47` |
| `view` | `view(User $user, StockMovement $movement): bool` | `$user->isAdmin() \|\| $user->isTechnician()` | same as viewAny path |
| `create` | `create(User $user): bool` | `$user->isAdmin() \|\| $user->isTechnician()` — **both roles may record movements** | `StockMovementController.php:107` |
| `delete` | `delete(User $user, StockMovement $movement): bool` | `$user->isAdmin()` — **admin only** | — |

**Note:** This is the only policy (besides some `TicketPolicy` abilities) where technicians get write (`create`) access — technicians can register stock movements but cannot delete them.

---

## `SupplierPolicy.php`

**File:** `app/Policies/SupplierPolicy.php`
**Governs:** `App\Models\Supplier`
**Registered:** `Gate::policy(Supplier::class, SupplierPolicy::class)` (`AppServiceProvider.php:119`)

| Ability | Signature | Authorization logic | Called from |
|---|---|---|---|
| `viewAny` | `viewAny(User $user): bool` | `$user->isAdmin() \|\| $user->isTechnician()` | `SupplierController.php:46` |
| `view` | `view(User $user, Supplier $supplier): bool` | `$user->isAdmin() \|\| $user->isTechnician()` | `SupplierController.php:88` |
| `create` | `create(User $user): bool` | `$user->isAdmin()` — **admin only** | `SupplierController.php:124` |
| `update` | `update(User $user, Supplier $supplier): bool` | `$user->isAdmin()` | `SupplierController.php:167` |
| `delete` | `delete(User $user, Supplier $supplier): bool` | `$user->isAdmin()` | `SupplierController.php:196` |

---

## `TaxRatePolicy.php`

**File:** `app/Policies/TaxRatePolicy.php`
**Governs:** `App\Models\TaxRate`
**Registered:** `Gate::policy(TaxRate::class, TaxRatePolicy::class)` (`AppServiceProvider.php:121`)

| Ability | Signature | Authorization logic | Called from |
|---|---|---|---|
| `viewAny` | `viewAny(User $user): bool` | `$user->isAdmin() \|\| $user->isTechnician()` | `TaxRateController.php:35` |
| `view` | `view(User $user, TaxRate $taxRate): bool` | `$user->isAdmin() \|\| $user->isTechnician()` | same as viewAny path |
| `create` | `create(User $user): bool` | `$user->isAdmin()` — **admin only** | `TaxRateController.php:71` |
| `update` | `update(User $user, TaxRate $taxRate): bool` | `$user->isAdmin()` | `TaxRateController.php:116` |
| `delete` | `delete(User $user, TaxRate $taxRate): bool` | `$user->isAdmin()` | `TaxRateController.php:150` |

---

## `TicketPolicy.php` (the most complex — full ticket lifecycle)

**File:** `app/Policies/TicketPolicy.php`
**Governs:** `App\Models\Ticket`
**Registered:** `Gate::policy(Ticket::class, TicketPolicy::class)` (`AppServiceProvider.php:113`)
**Private helper:** `private function canAccessTicket(User $user, Ticket $ticket): bool` returns `true` for any admin/technician, otherwise `true` only if `(int) $ticket->user_id === (int) $user->id` (ticket creator). This is reused by `view`, `comment`, `attachPhoto`, `deletePhoto`, and `schedule`.

| Ability | Signature | Authorization logic | Called from |
|---|---|---|---|
| `viewAny` | `viewAny(User $user): bool` | `true` — **any authenticated user** | `TicketController.php:36,86,165,185` |
| `view` | `view(User $user, Ticket $ticket): bool` | `canAccessTicket()` (admin/tech/creator) | `TicketController.php:145`, `TicketCommentController.php:22,45`, `TicketAttachmentController.php:74` |
| `create` | `create(User $user): bool` | `! $user->isTechnician()` — admins & common users open tickets; technicians only resolve/close | `TicketController.php:61`, `CalendarController.php:54` |
| `update` | `update(User $user, Ticket $ticket): bool` | `$user->isAdmin() \|\| $user->isTechnician()` | checked in `StartTicketRequest.php:18` via `$this->user()?->can('update', $ticket)` |
| `delete` | `delete(User $user, Ticket $ticket): bool` | `$user->isAdmin()` — **admin only** | — |
| `comment` | `comment(User $user, Ticket $ticket): bool` | `canAccessTicket()` (admin/tech/creator) | `StoreCommentRequest.php:15` (currently commented out) |
| `attachPhoto` | `attachPhoto(User $user, Ticket $ticket): bool` | `canAccessTicket()` (admin/tech/creator) | `TicketAttachmentController.php:31` |
| `deletePhoto` | `deletePhoto(User $user, Ticket $ticket): bool` | `canAccessTicket()` (admin/tech/creator) | `TicketAttachmentController.php:89` |
| `cancel` | `cancel(User $user, Ticket $ticket): bool` | `$user->isCommonUser() && (int) $ticket->user_id === (int) $user->id` — only the **original creator**, and only if that creator is a common user | `TicketLifecycleController.php:59` |
| `start` | `start(User $user, Ticket $ticket): bool` | `$user->isTechnician()` — **technician only** | `TicketStartController.php:30` |
| `close` | `close(User $user, Ticket $ticket): bool` | `$user->isTechnician()` — **technician only** | `TicketCloseController.php:33,69` |
| `reopen` | `reopen(User $user, Ticket $ticket): bool` | `$user->isTechnician() \|\| $user->isAdmin()` | `TicketLifecycleController.php:28` |
| `schedule` | `schedule(User $user, Ticket $ticket): bool` | `canAccessTicket()` (admin/tech/creator) — anyone with ticket access may schedule | `TicketScheduleController.php:26` |
| `submitBudget` | `submitBudget(User $user, Ticket $ticket): bool` | `$user->isTechnician() \|\| $user->isAdmin()` | `TicketBudgetController.php:34` |
| `approveBudget` | `approveBudget(User $user, Ticket $ticket): bool` | `$user->isAdmin()` — **admin only** | `AdminController.php:28` |
| `startRepair` | `startRepair(User $user, Ticket $ticket): bool` | `$user->isTechnician()` — **technician only** | — |
| `requestBudget` | `requestBudget(User $user, Ticket $ticket): bool` | `$user->isTechnician() && (int) $ticket->assigned_to === (int) $user->id` — only the **assigned** technician may request an authorization/budget | `TicketBudgetController.php:52` |
| `assign` | `assign(User $user, Ticket $ticket): bool` | `$user->isAdmin()` — **admin only** (admin assigns technicians) | `TicketAssignmentController.php:30` |
| `viewAnalytics` | `viewAnalytics(User $user): bool` | `$user->isAdmin()` — **admin only** | `AnalyticsController.php:36` |
| `exportAnalytics` | `exportAnalytics(User $user): bool` | `$user->isAdmin()` — **admin only** | `AnalyticsController.php:58,85,112` |
| `createPreventive` | `createPreventive(User $user): bool` | `$user->isAdmin()` — **admin only** | `AdminController.php:59` |

**Lifecycle summary:**
- **Create / list / view / comment / attach photos / schedule:** open to the ticket creator plus admins & technicians (`viewAny` is open to everyone).
- **Close / start / startRepair:** technician-only operational steps.
- **Reopen / submitBudget / update:** technician or admin.
- **Approve budget / assign / delete / analytics / createPreventive / cancel (partially):** admin-gated.
- **`cancel`** is the only ability gated to the ticket creator *and* a common-user role.

---

## `UserPolicy.php`

**File:** `app/Policies/UserPolicy.php`
**Governs:** `App\Models\User`
**Registered:** `Gate::policy(User::class, UserPolicy::class)` (`AppServiceProvider.php:114`)

| Ability | Signature | Authorization logic | Called from |
|---|---|---|---|
| `viewAny` | `viewAny(User $user): bool` | `$user->isAdmin() \|\| $user->isTechnician()` | `AdminUserController.php:33` |
| `view` | `view(User $user, User $model): bool` | `$user->isAdmin() \|\| $user->isTechnician() \|\| $user->id === $model->id` — a user may view their own profile too | — |
| `create` | `create(User $user): bool` | `$user->isAdmin()` — **admin only** | `AdminUserController.php:54` |
| `update` | `update(User $user, User $model): bool` | `$user->isAdmin() \|\| $user->id === $model->id` — admin can edit anyone, a user can edit themselves | `AdminUserController.php:74` |
| `delete` | `delete(User $user, User $model): bool` | `$user->isAdmin() && $user->id !== $model->id` — admin only, and **cannot delete themselves** | `AdminUserController.php:120` |
| `updateProfile` | `updateProfile(User $user, ?User $target = null): bool` | if `$target` is `null` → `true` (self-profile update); otherwise `$user->isAdmin() \|\| $user->id === $target->id` | — (the controller `ProfileController.php:37` handles profile updates directly; this ability is available but not wired via `authorize`) |
| `inactivate` | `inactivate(User $admin, User $target): bool` | `$admin->isAdmin() && $admin->id !== $target->id && ! $target->isAdmin()` — admin may not inactivate themselves **nor another admin** | `AdminUserController.php:94` |
| `manage` | `manage(User $admin, ?User $target = null): bool` | if `$target` is `null` → `$admin->isAdmin()`; otherwise `$admin->isAdmin() && $admin->id !== $target->id` — **compatibility helper** | not invoked anywhere |
| `manageAny` | `manageAny(User $user): bool` | `$user->isAdmin()` — **compatibility helper** | not invoked anywhere |

---

## `UserProfilePolicy.php`

**File:** `app/Policies/UserProfilePolicy.php`
**Governs:** `App\Models\UserProfile` (the role profile a user belongs to)
**Registered:** `Gate::policy(UserProfile::class, UserProfilePolicy::class)` (`AppServiceProvider.php:117`)

| Ability | Signature | Authorization logic | Called from |
|---|---|---|---|
| `viewAny` | `viewAny(User $user): bool` | `$user->isAdmin()` — **admin only** | `AdminUserController.php:143` (`$this->authorize('viewAny', UserProfile::class)` — the profiles list endpoint) |

---

## Notes for developers / AI

- All policies are `final` classes with no constructor injection and are stateless.
- `TicketPolicy` is the most complex with **20 methods** covering the full ticket lifecycle; its `canAccessTicket()` private helper centralizes the "admin/technician/creator" check used by `view`, `comment`, `attachPhoto`, `deletePhoto`, and `schedule`.
- `EquipmentPolicy` and `RoomPolicy` include `manage()`/`manageAny()` compatibility methods that are currently **unused** pass-throughs to `isAdmin()`.
- `UserPolicy::delete()` prevents self-deletion; `UserPolicy::inactivate()` prevents admin-to-admin inactivation (and cannot target the acting admin).
- `TicketPolicy::cancel()` is the only ownership-restricted *write* ability — it requires the actor to be the ticket creator *and* a common user.
- `AuditPolicy` and `UserProfilePolicy` each expose only a single `viewAny` ability (admin-only).
- `Create`/`update`/`delete` for the stock/equipment/room/catalog resources (`Part`, `Supplier`, `TaxRate`, `PartCategory`, `MaintenancePlan`, `Equipment`, `Room`) follow the same pattern: **view for admin+technician, write for admin only.** `StockMovementPolicy` is the exception — technicians may also `create` movements.
- All policies are registered in `AppServiceProvider::registerPolicies()` via `Gate::policy()`, except `AuditPolicy` which relies on Laravel's convention-based auto-discovery (class name `App\Policies\AuditPolicy` → model `App\Models\Audit`).