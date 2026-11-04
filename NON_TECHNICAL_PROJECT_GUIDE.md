# Plain-English Project Guide: SGM (Integrated Maintenance Management System)

> **What is this project?**
> SGM is a complete software system for managing maintenance work orders, equipment, spare parts, and teams. Think of it as a digital maintenance department that tracks every broken machine, every repair, every spare part, and every technician -- all in one place.

---

## Table of Contents

1. [Project Overview](#project-overview)
2. [How to Run This Project](#how-to-run-this-project)
3. [Root-Level Files & Folders](#root-level-files--folders)
4. [The `app/` Folder -- The Brain](#the-app-folder--the-brain)
5. [The `config/` Folder -- Settings Panel](#the-config-folder--settings-panel)
6. [The `database/` Folder -- Blueprint Room](#the-database-folder--blueprint-room)
7. [The `resources/` Folder -- Design Studio](#the-resources-folder--design-studio)
8. [The `routes/` Folder -- Switchboard](#the-routes-folder--switchboard)
9. [The `tests/` Folder -- Quality Assurance Lab](#the-tests-folder--quality-assurance-lab)
10. [The `docs/` Folder -- Library](#the-docs-folder--library)
11. [Key Workflows Explained](#key-workflows-explained)
12. [Glossary of Terms](#glossary-of-terms)

---

## Project Overview

### What Does SGM Do?

SGM helps companies manage their maintenance operations. Here is what it handles:

| Feature | What It Means in Plain English |
|---------|-------------------------------|
| **Ticket Management** | When something breaks, someone reports it. The system tracks the report from "broken" to "fixed." |
| **Preventive Maintenance** | Instead of waiting for things to break, the system schedules regular check-ups. |
| **Equipment Tracking** | A catalog of every machine, device, and piece of hardware the company owns. |
| **Spare Parts & Stock** | Tracks inventory of replacement parts, monitors low stock, and logs every part used. |
| **Room/Location Management** | Maps equipment to physical rooms and buildings. |
| **Team Roles** | Three types of users: Operators (report issues), Technicians (fix things), and Admins (manage everything). |
| **Budget Approvals** | When repairs cost money, the system routes approval requests to managers. |
| **Notifications** | Real-time alerts via email, browser pop-ups, and in-app messages. |
| **AI Recommendations** | Uses artificial intelligence (OpenAI) to suggest the best technician for each job. |
| **Reporting & Analytics** | Dashboards with charts, exportable reports (CSV, Excel, PDF). |
| **Audit Trail** | An unchangeable log of every action taken in the system. |
| **Multi-Language** | Supports 50+ languages with per-user preferences. |

### Who Built This?

This is a final-year project by **Andre Moreira** at **CESAE Digital**, completed in 2026.

### What Technology Runs It?

| Component | Technology | Plain English |
|-----------|-----------|---------------|
| Server | PHP 8.5+ with Laravel 13 | The main engine that processes requests |
| Database | MySQL (production) / SQLite (development) | The filing cabinet where all data is stored |
| Caching | Redis (optional) | A fast-access memory for frequently needed data |
| Frontend | Blade templates + Tailwind CSS + Bootstrap + Alpine.js | The visual interface users see |
| Calendar | FullCalendar v6 | Interactive scheduling calendar |
| Build Tool | Vite | Packages and optimizes frontend files |
| AI | OpenAI API | Artificial intelligence for smart recommendations |
| Testing | PHPUnit with 1410+ tests | Automated quality checks |
| Deployment | Docker with FrankenPHP/Octane | Containerized deployment for production |

---

## How to Run This Project

### Prerequisites (What You Need First)

1. **PHP 8.5 or newer** -- the programming language the server uses
2. **Composer** -- PHP's package manager (like an app store for code libraries)
3. **Node.js and npm** -- for building the frontend (the visual parts)
4. **MySQL 8+ or SQLite** -- the database (where all data lives)
5. **Redis** (optional but recommended) -- for caching and background jobs

### Step-by-Step Setup

#### Step 1: Clone the project
```bash
git clone <repository-url>
cd Projeto-Final-Cesae
```

#### Step 2: Install PHP dependencies
```bash
composer install
```
This downloads all the PHP libraries the project needs (like ordering supplies for an office).

#### Step 3: Install JavaScript dependencies
```bash
npm install
```
This downloads all the frontend libraries needed for the visual interface.

#### Step 4: Create your environment file
```bash
cp .env.example .env
```
Then open `.env` and fill in your database credentials, API keys, and other settings. Think of this as filling out your office address and phone number in a form.

#### Step 5: Generate the application key
```bash
php artisan key:generate
```
This creates a security lock for encrypting data.

#### Step 6: Set up the database
```bash
php artisan migrate
```
This creates all the tables in your database (like building empty filing cabinets).

#### Step 7: Add starter data
```bash
php artisan db:seed
```
This fills the database with sample data so you can see the system in action immediately.

#### Step 8: Build the frontend
```bash
npm run build
```
This packages all the visual files into optimized bundles for the browser.

#### Step 9: Start the server
```bash
php artisan serve
```
Now visit `http://localhost:8000` in your browser. You should see the login page.

#### Step 10: Run all automated tests (optional)
```bash
php artisan test
```
This runs all 1410+ tests to verify everything works correctly.

### Quick Start with Docker

If you have Docker installed, you can skip most of the above:
```bash
docker compose up -d
docker compose exec app php artisan migrate --seed
```
This builds and starts everything automatically.

---

## Root-Level Files & Folders

### 📂 Folder: Root Directory

**Real-World Analogy & Purpose:** This is the top of the filing cabinet -- everything in the project is organized into sub-folders here.

---

### 📄 File: `README.md`

**🎯 What is this file's main job?**
This is the project's front page -- it tells developers what SGM is, how to set it up, and how to contribute.

**🔄 Step-by-Step Breakdown:**
1. **Project Introduction:** Explains what SGM does (maintenance management)
2. **Tech Stack Table:** Lists all the technologies used
3. **Feature List:** Describes every major feature
4. **Setup Instructions:** Step-by-step guide to get the project running
5. **Architecture Overview:** High-level explanation of how the code is organized

**📥 Inputs & 📤 Outputs:**
- **Information Needed:** None (this is a read-only document)
- **End Result:** Developer understands the project and can set it up

---

### 📄 File: `composer.json`

**🎯 What is this file's main job?**
This is the shopping list for PHP libraries. It tells the computer which pre-built tools and features to download so the project can work.

**🔄 Step-by-Step Breakdown:**
1. **Project Identity:** Declares the project name (`sgm/app`), description, and license (MIT)
2. **PHP Version Requirement:** Requires PHP 8.5 or newer
3. **Required Libraries (47 packages):** Includes Laravel framework, database tools, authentication, Excel export, PDF generation, email sending, API documentation, AI integration (OpenAI), and more
4. **Development Libraries (12 packages):** Testing tools, code quality checkers, static analysis
5. **Auto-Discovery:** Tells Laravel which library features to load automatically

**📥 Inputs & 📤 Outputs:**
- **Information Needed:** None (read by `composer install` command)
- **End Result:** A `vendor/` folder with all PHP libraries downloaded

---

### 📄 File: `package.json`

**🎯 What is this file's main job?**
This is the shopping list for JavaScript libraries -- the tools needed to build the visual interface.

**🔄 Step-by-Step Breakdown:**
1. **Project Scripts:** Defines commands like `dev` (development), `build` (production), `lint` (code quality check)
2. **Frontend Libraries:** Includes Tailwind CSS (styling), Alpine.js (interactivity), FullCalendar (scheduling), Vite (build tool), Lucide icons
3. **Development Tools:** ESLint (code quality), PostCSS (CSS processing)

**📥 Inputs & 📤 Outputs:**
- **Information Needed:** None (read by `npm install` command)
- **End Result:** A `node_modules/` folder with all JavaScript libraries downloaded

---

### 📄 File: `artisan`

**🎯 What is this file's main job?**
This is the command-line remote control for the system. It lets developers run tasks without opening a web browser -- things like creating databases, clearing caches, or running background jobs.

**🔄 Step-by-Step Breakdown:**
1. **Trigger:** Developer types `php artisan <command>` in the terminal
2. **Bootstrap:** Loads the system configuration and connects to the database
3. **Command Execution:** Runs the requested task (migrate, seed, serve, test, etc.)

**📥 Inputs & 📤 Outputs:**
- **Information Needed:** A command name (e.g., `migrate`, `serve`, `test`)
- **End Result:** The requested task is performed and results are printed to the terminal

---

### 📄 File: `Dockerfile`

**🎯 What is this file's main job?**
This is a recipe for building a self-contained "box" (Docker container) that has everything the system needs to run -- like a pre-built kitchen that can be shipped anywhere and work immediately.

**🔄 Step-by-Step Breakdown:**
1. **Base Image:** Starts with a PHP 8.5 image with all necessary extensions
2. **System Dependencies:** Installs libraries for ZIP, GD (image processing), and other needs
3. **PHP Configuration:** Enables required PHP extensions and adjusts memory/timeout settings
4. **Application Code:** Copies the project files into the container
5. **Dependencies:** Installs PHP and JavaScript dependencies
6. **Build Assets:** Compiles frontend files for production
7. **Security:** Creates a non-root user for safety
8. **Startup:** Configures the server to start when the container launches

**📥 Inputs & 📤 Outputs:**
- **Information Needed:** The project source code and `.env` configuration
- **End Result:** A Docker image that can run the application anywhere

---

### 📄 File: `docker-compose.yml` / `compose.yaml`

**🎯 What is this file's main job?**
This is the orchestration plan for running multiple "boxes" together -- the application server, database, cache, and web server -- all connected and working as a team.

**🔄 Step-by-Step Breakdown:**
1. **Services Defined:**
   - `app`: The main PHP application server
   - `mysql`: The database (MySQL 8)
   - `redis`: The cache server
   - `caddy`: The web server that faces the internet
2. **Networking:** All services can talk to each other via internal names
3. **Storage:** Persistent volumes so data survives restarts
4. **Health Checks:** The system monitors each service and restarts if something fails

**📥 Inputs & 📤 Outputs:**
- **Information Needed:** `docker compose up` command
- **End Result:** All services running and accessible at `http://localhost`

---

### 📄 File: `phpunit.xml`

**🎯 What is this file's main job?**
This is the configuration for the automated testing system. It tells the test runner where to find tests, how to set up the test environment, and what to measure.

**🔄 Step-by-Step Breakdown:**
1. **Test Suites:** Defines three test groups:
   - `unit`: Tests individual pieces in isolation (fastest)
   - `feature`: Tests complete user workflows (medium speed)
   - `database`: Tests database interactions (slower)
2. **Environment:** Uses SQLite in-memory database for tests (fast, no permanent changes)
3. **Coverage:** Measures code coverage with V8 provider (tracks which lines are tested)
4. **Logging:** Records test results in JUnit XML format

**📥 Inputs & 📤 Outputs:**
- **Information Needed:** `php artisan test` or `vendor/bin/phpunit` command
- **End Result:** Test results showing pass/fail status for 1410+ tests

---

### 📄 File: `.env.example`

**🎯 What is this file's main job?**
This is a blank form showing all the settings the system needs. Developers copy this to `.env` and fill in their own values -- like filling out a template application form.

**🔄 Step-by-Step Breakdown:**
1. **App Settings:** Application name, default language (Portuguese), maintenance mode toggle
2. **Database:** MySQL connection details (host, port, username, password, database name)
3. **Session & Cache:** Where to store user sessions and cached data
4. **Queue:** Background job processing settings
5. **Mail:** Email sending configuration (supports SendGrid, Mailgun, Postmark, Resend, Slack)
6. **Security:** JWT token settings, password lockout policy (5 failed attempts = 15-minute lockout)
7. **AI/OpenAI:** API key for artificial intelligence features
8. **Business Rules:** Budget approval threshold (1500 EUR), SLA target (5 days)
9. **Uploads:** Photo size limits (2MB max, 4096px max dimension)
10. **Backups:** 30-day retention with optional cloud storage

**📥 Inputs & 📤 Outputs:**
- **Information Needed:** Developer fills in their own values
- **End Result:** System knows how to connect to databases, send emails, and use AI

---

### 📄 File: `vite.config.js`

**🎯 What is this file's main job?**
This is the blueprint for the frontend build system. It tells the build tool how to package CSS, JavaScript, and other assets for the browser.

**🔄 Step-by-Step Breakdown:**
1. **Entry Points:** Identifies which files to compile (`resources/css/app.css`, `resources/js/app.js`)
2. **Plugins:** Tailwind CSS (styling), Laravel plugin (asset management)
3. **Code Splitting:** Automatically breaks large files into smaller chunks for faster loading
4. **Asset Hashing:** Adds unique filenames so browsers always get the latest version

**📥 Inputs & 📤 Outputs:**
- **Information Needed:** Source files in `resources/css/` and `resources/js/`
- **End Result:** Optimized files in `public/build/` ready for browsers

---

## The `app/` Folder -- The Brain

**Real-World Analogy & Purpose:** This is the headquarters of the entire system. Every decision, every action, every piece of business logic lives here. If the project were a hospital, this folder would be the doctors, nurses, and medical records -- everything that makes things actually happen.

### How the Code is Organized (The Layered Architecture)

Think of the system like a restaurant:

1. **HTTP Layer (Reception):** Takes the customer's order (request) and passes it to the kitchen
2. **Services (Head Chef):** Coordinates the cooking, making sure all ingredients come together
3. **Actions (Line Cooks):** Each does one specific task perfectly (chop, grill, plate)
4. **Repositories (Pantry Staff):** Fetch ingredients (data) from storage (database)
5. **Models (Ingredient Labels):** Define what each ingredient is and how it relates to others

This separation means if one part breaks, the rest keeps working -- and it's easy to test each part independently.

---

### 📂 Folder: `app/Actions/`

**Real-World Analogy & Purpose:** These are the specialized workers. Each one does exactly one job and does it well. They never get distracted by other tasks.

**📄 File: `ApproveBudgetAction.php`**
- **Main Job:** Processes a manager's decision on a repair budget request
- **What Happens:** When a technician says "this repair costs 500 EUR," a manager either approves or rejects it. This action records that decision and notifies the technician.
- **Input:** Ticket ID, decision (approve/reject), optional feedback message
- **Output:** Budget decision recorded, notification sent to the requester
- **Business Rule:** You cannot approve a budget on a ticket that is already closed

**📄 File: `AssignTechnicianAction.php`**
- **Main Job:** Assigns or removes a technician from a repair job
- **What Happens:** An admin picks which technician should handle a specific ticket
- **Input:** Ticket ID, technician ID (or null to unassign)
- **Output:** Technician assignment updated, assignment record created
- **Business Rule:** Only admins can assign technicians

**📄 File: `CreateTicketAction.php`**
- **Main Job:** Creates a new repair report when something breaks
- **What Happens:** A user fills out a form describing what broke, where it is, and how urgent it is. This action saves it as a ticket in the system.
- **Input:** Title, description, priority level, optional equipment/room location
- **Output:** New ticket created with "Open" status, admin notified
- **Business Rule:** The reporter is automatically recorded as the ticket creator

**📄 File: `CreatePublicTicketAction.php`**
- **Main Job:** Lets anyone report a problem by scanning a QR code -- no account needed
- **What Happens:** A visitor scans a QR code on a machine, fills out a simple form, and the system creates a ticket
- **Input:** Problem type, description, equipment ID, optional photos
- **Output:** New ticket created, admin notified
- **Business Rule:** Public tickets cannot be assigned a priority above "Medium" for safety

**📄 File: `SubmitBudgetAction.php`**
- **Main Job:** Records an estimated cost for a repair
- **What Happens:** A technician estimates how much a repair will cost and submits it for approval
- **Input:** Ticket ID, estimated amount, optional details
- **Output:** Budget request created in "Pending" status
- **Business Rule:** Cannot submit a budget on a closed ticket; cannot submit duplicate budgets

**📄 File: `ScheduleTicketAction.php`**
- **Main Job:** Puts a repair job on the calendar
- **What Happens:** A technician picks a date/time window for when the repair will happen
- **Input:** Ticket ID, scheduled date/time, optional end time
- **Output:** Ticket marked as scheduled, calendar event created
- **Business Rule:** Cannot schedule a ticket that is already closed or cancelled

**📄 File: `MaintenancePlanActions.php`**
- **Main Job:** Manages preventive maintenance schedules
- **What Happens:** An admin creates a plan like "check HVAC every 30 days" and the system automatically generates tickets when it's time
- **Input:** Equipment ID, interval type (days/hours/cycles), interval value, associated parts
- **Output:** Maintenance plan created, future tickets will be auto-generated

**💡 Extra Notes:**
- All actions use database transactions -- if any step fails, everything is undone so no partial data is saved
- Each action is a `final readonly` class with a single `execute()` method
- Guard clauses at the top prevent invalid operations before any work begins

---

### 📂 Folder: `app/DTOs/` (Data Transfer Objects)

**Real-World Analogy & Purpose:** These are sealed envelopes that carry validated data from one part of the system to another. They make sure the information inside is complete, properly formatted, and safe before it gets processed.

**📄 Key Files:**
- `CreateTicketData.php` -- Carries new ticket information (title, description, priority)
- `StoreEquipmentData.php` -- Carries equipment creation data (name, serial number, location)
- `BudgetSubmissionData.php` -- Carries budget estimate with amount normalization
- `TicketFilters.php` -- Carries search/filter criteria for listing tickets
- `PasswordChangeData.php` -- Carries old and new passwords with validation

**How They Work:**
1. User submits a form
2. Form data is validated by a FormRequest class
3. Validated data is packaged into a DTO (sealed envelope)
4. DTO is passed to an Action or Service
5. Action/Service processes the data without worrying about validation

**Business Rule:** DTOs never contain business logic -- they only validate and sanitize input.

---

### 📂 Folder: `app/Enums/` (Enumeration Classes)

**Real-World Analogy & Purpose:** These are the drop-down menus of the system -- predefined lists of allowed values that keep everyone speaking the same language.

**📄 Key Enums:**

| Enum | What It Controls | Example Values |
|------|-----------------|----------------|
| `UserRoleEnum` | Who can do what | Operator, Technician, Admin |
| `TicketStatusEnum` | Where a ticket is in its lifecycle | Open, In Progress, Closed, Cancelled |
| `TicketPriorityEnum` | How urgent a repair is | Low, Medium, High, Critical |
| `BudgetStatusEnum` | Budget request state | Pending, Approved, Rejected |
| `StockMovementTypeEnum` | Inventory transaction type | In, Out, Adjust, Return |
| `NotificationTypeEnum` | What triggered a notification | Budget Request, Ticket Created, Low Stock |

**How They Work:**
- Each enum has a `label()` method that returns a human-readable name in the user's language
- Each has a `color()` method for visual display (green = good, red = bad, yellow = warning)
- Each has a `normalize()` method that safely converts any input to the correct enum value

**Business Rule:** The string values (like 'baixa' for Low priority) match the database -- changing them requires a database migration.

---

### 📂 Folder: `app/Models/` (Database Tables)

**Real-World Analogy & Purpose:** These are the blueprints for every type of data the system stores. Each model defines what columns a table has and how different tables relate to each other.

**📄 Key Models:**

| Model | What It Stores | Key Relationships |
|-------|---------------|-------------------|
| `User` | System users (name, email, password, role) | Has many tickets, belongs to a role profile |
| `Ticket` | Repair reports (title, description, status, priority) | Belongs to a user, has many comments/attachments |
| `Equipment` | Physical machines and devices | Belongs to a room, has many tickets and maintenance plans |
| `Room` | Physical locations (buildings, floors) | Has many equipment |
| `Part` | Spare parts inventory | Belongs to a category, has many stock movements |
| `StockMovement` | Inventory transactions | Belongs to a part, ticket, and user |
| `MaintenancePlan` | Preventive maintenance schedules | Belongs to equipment, has many parts |
| `Audit` | Unchangeable log of all actions | Belongs to a user, tracks any model |
| `Notification` | In-app and email alerts | Belongs to a user |
| `TicketWorkflowHistory` | Step-by-step ticket lifecycle log | Belongs to a ticket, records every status change |

**Business Rules:**
- Most models use "soft deletes" -- data is marked as deleted but kept in the database
- Core models (Equipment, Ticket, Room, Part) automatically create audit records when changed
- The `Audit` model is completely immutable -- it cannot be updated or deleted by anyone

---

### 📂 Folder: `app/Services/` (Business Logic)

**Real-World Analogy & Purpose:** These are the department managers. They handle complex tasks that involve multiple steps, decisions, and coordination between different parts of the system.

**📄 Key Services:**

| Service | What It Manages |
|---------|----------------|
| `AIService` | Recommends the best technician for a ticket using AI |
| `TicketWorkflowService` | Manages ticket lifecycle transitions |
| `BudgetCalculatorService` | Calculates repair costs and breakdowns |
| `NotificationService` | Coordinates sending alerts across all channels |
| `AnalyticsService` | Gathers data for dashboards and reports |
| `StockDashboardService` | Inventory statistics and reports |
| `CalendarService` | Scheduled events for the calendar view |
| `ThemePresetService` | 28 visual themes (14 color families x light/dark) |
| `TechnicianAssignmentService` | Logic for matching technicians to tickets |
| `LocalizationService` | Formats dates, numbers, and currency per locale |

---

### 📂 Folder: `app/Http/Controllers/` (Request Handlers)

**Real-World Analogy & Purpose:** These are the front desk clerks. When a user clicks a button or submits a form, the request comes here first. The controller figures out what to do and delegates to the right department.

**📄 Key Controllers:**

| Controller | Handles |
|-----------|---------|
| `TicketController` | Creating, editing, closing, and managing tickets |
| `EquipmentController` | Managing equipment records |
| `RoomController` | Managing rooms and locations |
| `UserController` | Managing user accounts |
| `PartController` | Managing spare parts inventory |
| `StockMovementController` | Recording stock in/out/adjustments |
| `BudgetController` | Budget approval workflow |
| `AnalyticsController` | Dashboard data and reports |
| `DashboardController` | Main dashboard with charts and summaries |
| `PublicTicketController` | Guest ticket submission via QR codes |
| `QrCodeController` | Generating QR codes for equipment |

**How They Work:**
1. User sends a request (click, form submit, API call)
2. Controller checks if the user is allowed to do this (authorization)
3. Controller passes the request to a Service or Action
4. Service/Action does the work
5. Controller formats the result and sends it back (JSON response or HTML page)

---

### 📂 Folder: `app/Http/Middleware/` (Security Guards)

**Real-World Analogy & Purpose:** These are the security checkpoints. Every request passes through them before reaching a controller. They verify identity, check permissions, and enforce rules.

**📄 Key Middleware:**

| Middleware | What It Checks |
|-----------|----------------|
| `CustomAuthMiddleware` | Is the user logged in? Is their token valid? |
| `RoleMiddleware` | Does the user have the right role (admin, technician, user)? |
| `CsrfMiddleware` | Is this request legitimate (not a fake form submission)? |
| `RateLimitMiddleware` | Is this user making too many requests (spam/abuse prevention)? |
| `SecurityHeaders` | Adds security headers to prevent common attacks |
| `SetLocaleMiddleware` | What language should this user see? |

**Business Rule:** The system locks accounts after 5 failed login attempts for 15 minutes.

---

### 📂 Folder: `app/Policies/` (Access Control)

**Real-World Analogy & Purpose:** These are the permission cards. Before any action is taken, the system checks: "Is this person allowed to do this?"

**📄 Key Policies:**

| Policy | Rules |
|--------|-------|
| `TicketPolicy` | Operators can view/create; Technicians can start/close; Admins can do everything |
| `EquipmentPolicy` | Admins/Technicians can view; only Admins can create/edit/delete |
| `UserPolicy` | Admins manage users; users can edit their own profile; no self-deletion |
| `AuditPolicy` | Only Admins can view the audit trail |
| `StockMovementPolicy` | Admins/Technicians can view and record stock movements; only Admins can delete |

---

### 📂 Folder: `app/Repositories/` (Data Access)

**Real-World Analogy & Purpose:** These are the librarians. When a Service needs data from the database, it asks the Repository -- never touching the database directly. This keeps data access organized and testable.

**📄 Key Repositories:**
- `TicketRepository` -- Fetches tickets with filters, pagination, and relationships
- `EquipmentRepository` -- CRUD operations for equipment
- `RoomRepository` -- CRUD for rooms with active/inactive status
- `UserRepository` -- User queries with role-based filtering

**Business Rule:** All database queries go through Eloquent ORM (Laravel's database toolkit) -- no raw SQL queries.

---

### 📂 Folder: `app/Events/` & `app/Listeners/` (Event System)

**Real-World Analogy & Purpose:** Events are announcements ("A ticket was just created!"). Listeners are the follow-up teams that react to those announcements (send an email, log the change, notify the admin).

**📄 How It Works:**
1. A ticket is created --> `TicketCreated` event fires
2. `SendTicketCreatedNotification` listener catches it --> sends email to admin
3. A ticket status changes --> `TicketStatusChanged` event fires
4. `LogTicketWorkflowChange` listener catches it --> records the change in history
5. `NotifyAssignedTechnician` listener catches it --> sends notification to the assigned tech

---

### 📂 Folder: `app/Jobs/` (Background Tasks)

**Real-World Analogy & Purpose:** These are tasks that take time to complete, so they're sent to a background queue to process while the user keeps working. Like ordering food at a restaurant -- you don't stand in the kitchen waiting; you sit down and the kitchen handles it.

**📄 Key Jobs:**
- `ExportCsvJob` -- Generates a CSV report (runs in background)
- `ExportExcelJob` -- Generates an Excel spreadsheet
- `ExportPdfJob` -- Generates a PDF report
- `ExportEquipmentQrPdfJob` -- Creates QR codes for all equipment
- `GenerateAiRecommendationJob` -- Asks AI to suggest the best technician
- `CheckLowStockJob` -- Daily check for parts running low
- `SendTestEmailJob` -- Sends a test email from system settings

**Business Rule:** The AI recommendation job has a 2-minute uniqueness window -- it won't run twice for the same ticket within 2 minutes.

---

### 📂 Folder: `app/Providers/` (Wiring Diagram)

**Real-World Analogy & Purpose:** This is the electrical wiring that connects everything. When the system starts up, these files tell it where to find each component and how they connect.

**📄 Key Providers:**
- `AppServiceProvider` -- The master wiring file. Registers all repositories, services, policies, observers, and Blade formatting rules
- `EventServiceProvider` -- Maps events to their listeners (which reaction goes with which announcement)

---

## The `config/` Folder -- Settings Panel

**Real-World Analogy & Purpose:** This is the control room. Every setting the system needs -- database connections, email servers, cache rules, security policies -- is defined here.

**📄 Key Config Files:**

| File | What It Controls |
|------|-----------------|
| `app.php` | Application name, timezone, locale, encryption |
| `database.php` | Database connection settings |
| `auth.php` | Authentication rules (token length, lockout policy) |
| `mail.php` | Email sending configuration |
| `session.php` | How user sessions are stored |
| `queue.php` | Background job processing settings |
| `cache.php` | Temporary data storage settings |
| `openai.php` | AI service configuration |
| `locales.php` | Supported languages and formatting rules |

**Business Rule:** The system defaults to Portuguese (pt-PT) but supports 50+ languages.

---

## The `database/` Folder -- Blueprint Room

**Real-World Analogy & Purpose:** This room contains the architectural blueprints for the database (migrations), test data generators (factories), and starter data (seeders).

**📄 Key Subdirectories:**

### `database/migrations/`
These are the step-by-step instructions for building the database. Each migration either creates a new table, adds a column, or modifies existing structure. They run in order and cannot be renamed (the system tracks which ones have run).

### `database/seeders/`
These fill the database with starter data so you can see the system working immediately after setup. Includes sample users, equipment, tickets, parts, and rooms.

### `database/factories/`
These generate realistic test data. Need 100 fake tickets for testing? A factory creates them with random but valid data.

### `database/seeders/Data/`
Reference data in CSV format -- things like equipment categories, part categories, and tax rates.

---

## The `resources/` Folder -- Design Studio

**Real-World Analogy & Purpose:** This is where all the visual elements are designed and built -- the pages users see, the styles that make them look good, and the scripts that make them interactive.

### 📂 Folder: `resources/views/` (Page Templates)

**Real-World Analogy & Purpose:** These are the blueprints for every page in the application. They define the layout, structure, and content of each screen.

**📄 Key View Categories:**

| Category | What It Renders |
|----------|----------------|
| `layouts/` | The master page template (head, navigation, footer) that all pages extend |
| `ui/` | Main application pages (dashboard, tickets, equipment, rooms, stock, settings) |
| `components/` | Reusable building blocks (buttons, cards, forms, modals, alerts) |
| `emails/` | HTML email templates (password reset, ticket created, test mail) |
| `errors/` | Error pages (403 Forbidden, 404 Not Found, 500 Server Error) |
| `calendar/` | Full-page calendar for scheduling |
| `reports/` | Report generation pages |
| `preferences/` | User preference settings (language, currency, date format) |

**Business Rule:** All user-facing text uses translation keys (`__('key')`) -- no hardcoded English or Portuguese in templates.

### 📂 Folder: `resources/js/` (Client-Side Scripts)

**Real-World Analogy & Purpose:** These are the behind-the-scenes actors that make buttons work, forms validate in real-time, and pages feel alive without reloading.

**📄 Key JavaScript Categories:**

| Directory | Purpose |
|-----------|---------|
| `core/` | Essential managers: authentication, theme toggling, sidebar, search, notifications |
| `components/` | Reusable UI pieces: dropdowns, modals, autocomplete inputs, password strength meter |
| `pages/` | Page-specific scripts: ticket forms, equipment management, analytics charts, stock tracking |
| `services/` | API interaction helpers (autocomplete orchestration) |
| `utils/` | Utility functions: API client with auth headers, localized number/currency formatting |
| `auth/` | Login form handling, MFA (multi-factor authentication) support |

**Business Rule:** JavaScript uses design tokens (CSS variables) for colors, so dark mode works automatically.

### 📂 Folder: `resources/css/` (Styles)

**Real-World Analogy & Purpose:** This is the paint department. It defines all colors, fonts, spacing, and visual rules.

**📄 Key CSS Files:**
- `tokens.css` -- The master color palette and spacing rules (design tokens)
- `base.css` -- Reset styles and base element formatting
- `layout.css` -- Page shell structure (sidebar, main content area)
- `rtl.css` -- Right-to-left language support (Arabic, Hebrew)
- `components/` -- Styles for buttons, cards, forms, navigation, modals
- `pages/` -- Styles for specific pages (calendar, tickets, login, settings)
- `theme/` -- Semantic color aliases that bridge tokens to components

**How the Theme System Works:**
1. `tokens.css` defines raw values (e.g., `--color-blue-500: #3b82f6`)
2. `theme/variables.css` creates semantic names (e.g., `--primary: var(--color-blue-500)`)
3. Dark mode swaps these values when `.dark` class is active
4. Components use semantic names, so they automatically adapt to light/dark

---

## The `routes/` Folder -- Switchboard

**Real-World Analogy & Purpose:** This is the switchboard that routes every incoming request to the right handler. When a user visits a URL, the routes file decides which controller handles it.

**📄 Key Route Files:**

| File | Handles |
|------|---------|
| `api.php` | API endpoints (JSON responses) -- protected by authentication |
| `web.php` | Web pages (HTML views) -- mix of public and protected pages |
| `console.php` | Scheduled tasks (daily backup, low stock check, telemetry simulation) |

**📄 Example Routes:**

| URL | Method | What Happens |
|-----|--------|-------------|
| `/api/tickets` | GET | List all tickets (paginated, filtered) |
| `/api/tickets` | POST | Create a new ticket |
| `/api/tickets/{id}` | GET | View a specific ticket |
| `/api/tickets/{id}` | PUT | Update a ticket |
| `/api/equipment` | GET | List all equipment |
| `/api/stock-movements` | POST | Record a stock movement |
| `/api/reports/export/csv` | GET | Download a CSV report |
| `/dashboard` | GET | Show the main dashboard page |
| `/tickets` | GET | Show the ticket management page |

**Business Rule:** Most API routes require authentication. Guest-only routes (login, register, password reset) use special middleware to prevent authenticated users from accessing them.

---

## The `tests/` Folder -- Quality Assurance Lab

**Real-World Analogy & Purpose:** This is the testing laboratory where automated tests verify that every feature works correctly. There are 1410+ tests organized by type.

### 📂 Folder: `tests/Unit/` (Isolated Tests)

**Real-World Analogy & Purpose:** These test individual components in isolation -- like testing that a car's engine works without needing to drive the whole car.

**📄 Test Categories:**
- `Actions/` -- Tests for each action class (create ticket, approve budget, etc.)
- `DTOs/` -- Tests that data packages validate correctly
- `Enums/` -- Tests that enum values are correct
- `Models/` -- Tests for database model relationships and behavior
- `Services/` -- Tests for business logic services
- `Policies/` -- Tests that access control rules work
- `Events/` -- Tests that events fire correctly
- `Jobs/` -- Tests that background jobs work
- `ValueObjects/` -- Tests for special value types

### 📂 Folder: `tests/Feature/` (Workflow Tests)

**Real-World Analogy & Purpose:** These test complete user workflows -- like testing that a customer can walk in, order food, eat, pay, and leave without issues.

**📄 Test Categories:**
- `API/Controllers/` -- Tests for every API endpoint (~30 test files)
- `Middleware/` -- Tests for security middleware
- `Domain/` -- Tests for ticket lifecycle and status management
- `Web/Controllers/` -- Tests for page rendering
- `Web/Views/` -- Tests for visual components
- `Repositories/` -- Tests for data access layer
- `Validation/` -- Tests for input validation edge cases

### 📂 Folder: `tests/Security/` (Penetration Testing)

**Real-World Analogy & Purpose:** These tests try to break into the system to find vulnerabilities -- like a security firm hired to test the locks.

**📄 Security Test Categories:**

| Test | What It Checks |
|------|----------------|
| `SQLInjection/` | Can someone steal data by typing malicious database commands? |
| `XSS/` | Can someone inject harmful scripts into pages? |
| `CSRF/` | Can someone forge requests from other websites? |
| `IDOR/` | Can someone access other users' data by changing IDs in URLs? |
| `Password/` | Are passwords stored securely? Is the lockout policy enforced? |
| `RateLimiting/` | Can someone spam the system with requests? |
| `Session/` | Are user sessions protected from hijacking? |
| `PrivilegeEscalation/` | Can a regular user do admin things? |
| `MassAssignment/` | Can someone change fields they shouldn't be able to? |
| `PathTraversal/` | Can someone access files outside the allowed directory? |
| `FileUpload/` | Can someone upload dangerous files? |
| `UserEnumeration/` | Can someone figure out which email addresses exist? |
| `Headers/` | Are security headers properly configured? |

### 📂 Folder: `tests/Performance/` (Stress Testing)

**Real-World Analogy & Purpose:** These tests measure how fast the system runs and how much load it can handle -- like testing a bridge to see how many cars can cross at once.

**📄 Performance Test Categories:**

| Test | What It Measures |
|------|-----------------|
| `APIPerformance/` | Response times for API endpoints |
| `DatabasePerformance/` | Query speed, N+1 query detection, lazy loading issues |
| `CachePerformance/` | How well caching improves speed |
| `MemoryPerformance/` | Memory usage and leak detection |
| `DashboardPerformance/` | Dashboard loading speed with large datasets |
| `SearchPerformance/` | Search and filtering speed |
| `ReportsPerformance/` | Report generation speed |

### 📂 Folder: `tests/Integration/` (Connected Systems Tests)

**Real-World Analogy & Purpose:** These test that different parts of the system work together correctly -- like testing that the kitchen, cash register, and delivery service all coordinate properly.

**📄 Test Categories:**
- `Database/` -- Foreign key integrity, soft deletes, model lifecycle
- `Broadcasting/` -- Real-time WebSocket updates
- `Mail/` -- Email delivery via Mailgun

### 📂 Folder: `tests/Fixtures/` (Test Helpers)

**Real-World Analogy & Purpose:** These are the props and scripts used during testing -- fake data generators, helper functions, and reusable test setups.

**📄 Key Fixtures:**
- `Builders/` -- Create test data with specific configurations (e.g., "a high-priority ticket assigned to a technician")
- `Datasets/` -- Reference data for testing (all possible priority levels, statuses, roles)
- `Fakes/` -- Mock replacements for external services (e.g., a fake notification service that doesn't actually send emails)
- `Helpers/` -- Common test utilities (API interaction, event simulation, storage management)

---

## The `docs/` Folder -- Library

**Real-World Analogy & Purpose:** This is the developer library -- reference documentation for understanding architectural decisions and the API.

**📄 Key Files:**
- `docs/architecture/README.md` -- Architecture Decision Records (ADRs) that explain *why* certain technical choices were made
- `docs/openapi/README.md` -- API documentation generated from code annotations (run `composer docs:generate` to update)

**How API Docs Work:**
1. Developers add special annotations to controller methods
2. Running `composer docs:generate` reads these annotations
3. It produces a JSON/YAML file describing every API endpoint
4. An interactive documentation page is available at `/docs/openapi` for admin users

---

## Key Workflows Explained

### Workflow 1: Reporting a Broken Machine

```
1. User scans QR code on the machine (or logs into the app)
2. System shows a form with the machine's info pre-filled
3. User describes the problem and selects a priority level
4. System creates a ticket with status "Open"
5. System sends notification to all admins
6. System logs the creation in the audit trail
7. Admin assigns a technician
8. Technician starts working (status: "In Progress")
9. Technician schedules the repair on the calendar
10. If parts are needed, technician records stock movements
11. If cost exceeds threshold, budget approval is requested
12. Manager approves/rejects the budget
13. Technician completes the repair
14. Ticket is closed with a technical report
15. System sends "ticket closed" notification to the reporter
```

### Workflow 2: Preventive Maintenance

```
1. Admin creates a maintenance plan (e.g., "Check HVAC every 30 days")
2. Plan is linked to an equipment item and optional parts
3. System calculates the next due date
4. When the date arrives, a new ticket is auto-created
5. Technician is assigned and notified
6. Technician performs the check and records findings
7. Ticket is closed
8. System calculates the next due date and repeats
```

### Workflow 3: Stock Management

```
1. Admin adds a new spare part (name, category, initial stock)
2. Part appears in the inventory list
3. When a technician uses a part in a repair, they record a "stock out" movement
4. System checks if stock is below the threshold
5. If low, system sends a notification to admins
6. Admin orders more stock and records a "stock in" movement
7. Stock levels update in real-time on the dashboard
```

---

## Glossary of Terms

| Term | Plain English |
|------|---------------|
| **Ticket** | A repair report -- tracks a problem from report to resolution |
| **DTO** | A sealed data package that carries validated information between system parts |
| **Middleware** | A security checkpoint that every request passes through |
| **Policy** | A permission rule that decides who can do what |
| **Repository** | A data librarian that fetches and stores information |
| **Action** | A single-purpose worker that does one job perfectly |
| **Service** | A coordinator that manages complex multi-step tasks |
| **Observer** | A watchdog that automatically reacts when data changes |
| **Event** | An announcement that something important happened |
| **Listener** | A follow-up team that reacts to events |
| **Job** | A background task that runs while the user keeps working |
| **Migration** | A blueprint for creating or modifying database tables |
| **Seeder** | A script that fills the database with starter data |
| **Factory** | A machine that generates realistic test data |
| **Enum** | A predefined list of allowed values (like a drop-down menu) |
| **Blade** | Laravel's template system for creating HTML pages |
| **Eloquent** | Laravel's tool for talking to the database without writing raw SQL |
| **RBAC** | Role-Based Access Control -- permissions based on job role |
| **SLA** | Service Level Agreement -- target time to resolve a ticket |
| **CSRF** | Cross-Site Request Forgery -- a type of attack that fake forms try to exploit |
| **XSS** | Cross-Site Scripting -- an attack where harmful code is injected into pages |
| **IDOR** | Insecure Direct Object Reference -- an attack where someone accesses others' data by changing IDs |
| **MFA** | Multi-Factor Authentication -- extra security step beyond password |
| **JWT** | JSON Web Token -- a digital ID card the system uses to verify users |
| **Octane** | Laravel's high-performance server that keeps the app in memory for speed |
| **FrankenPHP** | A modern PHP web server that makes applications run faster |
| **Vite** | A build tool that packages frontend files for the browser |
| **Tailwind CSS** | A styling framework that uses utility classes for rapid design |
| **Alpine.js** | A lightweight JavaScript library for adding interactivity |
| **FullCalendar** | A calendar component for scheduling and event display |
| **OpenAI** | The company behind ChatGPT -- used for AI-powered technician recommendations |

---

*This guide was generated for the SGM project by Andre Moreira at CESAE Digital, 2026.*
