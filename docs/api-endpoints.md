# API Endpoints & Interactive Documentation

The complete interactive documentation (where you can test parameters and view JSON responses) is available locally at
 `http://localhost:8000/docs/openapi` (Swagger UI Interface). It is not public: `config/l5-swagger.php` places it behind
 `web` + `custom.auth` + `role:admin`, so an authenticated administrator session is required.

### 1. Authentication & Profile Management
| Method | Endpoint | Protection | Description / Behavior |
| :--- | :--- | :--- | :--- |
| **POST** | `/api/login` | `guest` | Validates credentials and injects the Authentication Cookie/Token. |
| **POST** | `/api/password/change` | `auth` | Changes the authenticated user's password and rotates the API token, so every previously issued token (this device included) must authenticate again. |
| **POST** | `/api/profile/update` | `auth` | Updates the authenticated user's profile registration data; rotating the token as well when the password is part of the change. |

Session logout is not an API endpoint: it is the web route `POST /logout` (`routes/web.php`), which destroys the session, invalidates the token, and securely clears cookies.

### 2. Ticket, Photo, and Comment Workflow (Operational)
All listing endpoints (`GET`) support **search and advanced filter parameters** (e.g., `?search=motor&status=em_curso&sala_id=5`).

| Method | Endpoint | Required Permission | Business Rules & Effects |
| :--- | :--- | :--- | :--- |
| **POST** | `/api/tickets` | `auth` | **Create Ticket (Global In-House Workflow):** Allows any logged-in user (Worker, Technician, or Admin) to open faults. Associates `Auth::id()` and sets state to 'Open'. |
| **GET** | `/api/tickets` | `auth` | **General Listing:** Returns active tickets and faults with pagination and search filter support. |
| **GET** | `/api/tickets/{id}` | `auth` | **Contextualized Detail:** Returns raw ticket data, bringing relationships (`equipment.category`, `room`, `user`) and injecting the real-time `AIService` suggestion. |
| **POST** | `/api/tickets/{id}/cancel` | `role:user` | **Cancel Ticket:** Allows the worker to cancel the ticket they created, provided it remains in the 'Open' state. |
| **GET** | `/api/technician/tickets/open` | `role:technician,admin`| **Global Queue:** Lists all faults in open state ready for triage and technical allocation. |
| **PUT** | `/api/technician/tickets/{id}/start` | `role:technician` | **Start Repair:** Transitions the ticket state to 'In Progress', injects the `in_progress_at` timestamp via server, and dispatches the Broadcast. |
| **PUT** | `/api/technician/tickets/{id}/close` | `role:technician` | **Close Intervention:** Moves the state to 'Closed', requiring the technical report, minutes spent record, and internal stock parts costs. |
| **POST** | `/api/tickets/{id}/comments` | `auth` | **Comment System:** Adds a message to the ticket. Workers can only comment on their own tickets; Technicians and Admins comment globally. |
| **GET** | `/api/tickets/{id}/comments` | `custom.auth` | **Dialogue History:** Lists the complete tree of comments and technical notes associated with the ticket. Protected at route level only by `custom.auth`; access to the ticket itself is enforced by the `view` policy in the controller. |
| **POST** | `/api/tickets/{id}/photos` | `auth` | **Photo Upload:** Allows attaching image files as visual evidence of the problem or resolution in the factory. |
| **GET** | `/api/tickets/{id}/photos` | `auth` | **Evidence Gallery:** Returns metadata and URLs of multimedia attachments loaded in the context of the ticket. |

### 3. Administration, Backoffice, AI & Reports
| Method | Endpoint | Protection | Description / Structural Actions |
| :--- | :--- | :--- | :--- |
| **POST** | `/api/admin/users/register` | `role:admin` | **Restricted Registration (Hardened Security):** Exclusive Administrator endpoint for creating and registering new users and employees in the company. |
| **PATCH** | `/api/admin/tickets/{id}/assign` | `role:admin` | **AI-Assisted Dispatch:** Permanently saves the technician allocation suggested by the `AIService` NLP engine or manually selected. |
| **PATCH**| `/api/admin/tickets/{id}/approve-budget` | `role:admin` | **Budget Decision:** Analyzes and approves the budget request submitted by a technician for high-cost interventions. |
| **POST** | `/api/admin/preventive` | `role:admin` | **Preventive Maintenance:** Schedules and injects a planned work order directly into the technicians' operational queue. |
| **GET** | `/api/admin/users` | `role:admin` | **Employee Listing:** Lists all registered employees on the platform for human resource management purposes. |
| **PATCH**| `/api/admin/users/{id}/inactive` | `role:admin` | **Account Deactivation:** Revokes platform access by logically changing the user state to inactive. |
| **GET** | `/api/admin/audits` | `role:admin` | **Audit Logs:** Returns the immutable and JSON-structured audit trail with changes (`old_values` and `new_values`) made in the system. |
| **CRUD** | `/api/admin/rooms/*` | `role:admin` | **Infrastructure Management:** Creates, updates, and deactivates the tree of rooms, buildings, and physical factory locations. |
| **CRUD** | `/api/admin/equipment/*` | `role:admin` | **Inventory Management:** Complete control over asset registration, serial numbers, brands, and equipment categories. |
| **GET** | `/api/analytics/*` | `role:technician,admin`| **Analytics Module:** Consumes aggregated statistics and data buses for KPI and MTTR chart rendering. |
