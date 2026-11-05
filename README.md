# Integrated Maintenance Management System (SGM)

> **New to programming?** See [NON_TECHNICAL_PROJECT_GUIDE.md](NON_TECHNICAL_PROJECT_GUIDE.md)

![Status](https://img.shields.io/badge/status-production--ready-brightgreen)
![Tests](https://img.shields.io/badge/tests-~1774%20total-brightgreen)
![PHP Version](https://img.shields.io/badge/PHP-8.5%2B-blue)
![Laravel Version](https://img.shields.io/badge/Laravel-13-red)

An enterprise-grade web platform built with **Laravel 13** for digitalization, centralization, and optimization of the complete maintenance and fault management lifecycle within an organization's **Maintenance Department**.

The system mitigates communication failures and extended infrastructure downtime by organizing workflows, improving traceability, and distributing operational intelligence across three user roles: **Operator** (Standard User), **Technician**, and **Administrator** (Operations Director).

---

## 🎯 Key Features

- **Fault/Ticket Management**: Create, assign, track, and resolve maintenance tickets with full workflow control
- **Preventive Maintenance**: Schedule and manage preventive maintenance plans with automatic alerts
- **Stock & Inventory**: Track parts inventory, movements, costs, and low-stock notifications
- **Role-Based Access Control (RBAC)**: Three-tier permission system (Operator, Technician, Admin)
- **Real-Time Notifications**: Dashboard alerts and notifications via database/email/broadcast
- **Audit Trail**: Complete audit history of all changes with user tracking and timestamps
- **AI-Assisted**: Smart ticket categorization and technician recommendations via OpenAI
- **Multi-Language Support**: 50+ locales with per-user preferences (currency, date format, language)
- **Advanced Reporting**: CSV/PDF/Excel exports with analytics and insights
- **Responsive Design**: Mobile-friendly interface with Tailwind CSS and component-based UI

---

## 🗺️ A Plain-English Tour of the System

> *No jargon. No code. Just how it works in the real world.*

### What is SGM, in one sentence?

SGM is a **digital help-desk for a maintenance team**. When something breaks in a building — a faulty air-conditioner, a leaking pipe, a jammed printer — someone reports it, it gets tracked, a technician is sent to fix it, and the whole process is recorded so management knows what happened, how much it cost, and how long it took.

Think of it as a **"fault ticketing app"** combined with a **"spare-parts warehouse manager"** and a **"management report generator"**, all in one place.

---

### The Three People Who Use It

| Role | Who they are | What they do |
|------|-------------|--------------|
| **Operator** (Standard User) | A regular employee in any department — an office worker, a factory floor supervisor, a warehouse clerk. | They **spot the problem** and **report it**: "The light in room 204 is broken." They can track the status of their tickets, view their own history, and receive updates. They cannot assign work or see system-wide reports. |
| **Technician** | A maintenance worker — electrician, plumber, HVAC specialist, general handy-person. | They **receive assigned tickets**, go fix the problem, log what they did, record which spare parts they used, and mark the ticket as resolved. They can view their own workload and schedule. |
| **Administrator** (Operations Director) | The head of the Maintenance Department or a senior operations manager. | They **manage everything**: assign tickets to technicians, oversee all equipment and rooms, control stock/inventory, manage budgets and costs, view analytics dashboards and reports, and manage user accounts and permissions. They have a bird's-eye view of the entire operation. |

---

### A Day in the Life — The Workflow as a Story

Here is what happens when something breaks, told step by step:

1. **A problem is spotted.** Maria, an office worker in Room 204, notices the air-conditioning unit is making a strange noise and blowing warm air. She opens SGM on her phone.

2. **A ticket is created.** Maria fills out a simple form: "AC unit making noise, not cooling." She selects the equipment (the specific AC unit), the room, and optionally snaps a photo. She hits **Submit**. The system generates a ticket with a unique ID and a timestamp. The AI module even suggests a category ("HVAC — Mechanical Noise") automatically.

3. **The admin reviews and assigns.** Paulo, the Maintenance Director, sees the new ticket on his dashboard. He checks who is available, sees that technician Carlos is scheduled for building B today, and **assigns** the ticket to Carlos. He can also set a priority level and a deadline.

4. **The technician is notified.** Carlos receives a notification: "New ticket #1247 assigned to you — AC noise in Room 204." He opens it on his phone and sees the full details, the location, and the equipment history (is this the same unit that broke last month?).

5. **The technician goes to fix it.** Carlos goes to Room 204, inspects the unit, and determines the issue is a faulty fan bearing. He checks the **stock system** — there is a compatible bearing in the warehouse. He requests it (a stock movement is recorded: warehouse → technician, quantity −1).

6. **The fix is logged.** Carlos replaces the bearing, tests the unit, and it works. He goes back into SGM, logs the repair details: what was replaced, what parts were used (and their cost), and how long it took. He marks the ticket as **Resolved**.

7. **Everything is recorded.** Behind the scenes, the system logs the entire history: who reported it, when, who fixed it, what parts were used, how much the parts cost (charged against the department's **budget**), and when it was closed. A notification goes back to Maria: "Your ticket #1247 has been resolved."

8. **Management sees the big picture.** Paulo opens the **Analytics Dashboard**. He sees that HVAC tickets are up 15% this month, that the average resolution time is 4.2 hours, and that stock for fan bearings is running low. He exports a PDF report for the monthly operations review.

---

### What Each Major Folder Does (In Plain English)

The project's source code is organized into folders. Here is what each one is for, explained like a real-world analogy:

| Folder | Plain-English Analogy | What It Actually Contains |
|--------|----------------------|--------------------------|
| `app/` | **The brain of the operation.** All the logic, rules, and decisions live here. | Controllers, services, models, actions, policies — all the code that makes the app *do* things. |
| `routes/` | **The address book.** Tells the system which URL leads to which page or action. | Route definitions mapping URLs (like `/tickets/create`) to the code that handles them. |
| `database/` | **The filing cabinet.** The permanent records — who exists, what equipment is where, every ticket ever filed. | Database table definitions (migrations), seed data (test/demo records), and factory definitions. |
| `resources/views/` | **The design studio.** Where the visual pages are assembled — what users actually see on screen. | Blade templates, CSS styles, JavaScript interactions for every page in the app. |
| `config/` | **The settings panel.** All the knobs and dials that control how the app behaves. | Configuration files for mail, database, cache, sessions, permissions, and more. |
| `public/` | **The front door.** The only folder the web server shows to the outside world. | The `index.php` entry point, compiled CSS/JS assets, and uploaded files. |
| `tests/` | **The quality-control lab.** Every test is a "what if?" scenario — "what if a technician tries to delete another technician's ticket?" | PHPUnit test files that verify the app works correctly. |
| `lang/` | **The translation room.** All the text the user sees, translated into 50+ languages. | JSON translation files for every supported locale. |
| `storage/` | **The warehouse.** Uploaded files, logs, cached data — things the app generates while running. | User uploads, application logs, session files, and cached views. |
| `docs/` | **The instruction manual.** Architecture diagrams, business rules, API specs — everything a new developer or stakeholder needs to understand the project. | Markdown documentation covering strategy, requirements, workflows, and user guides. |

---

### How It All Fits Together

When a user clicks a button or opens a page, here is what happens behind the scenes — explained like ordering food in a restaurant:

1. **The Menu (Routes):** The user picks a page — say, "Create Ticket." The system looks up its **route file** (the menu) and finds the right address.

2. **The Hostess (Middleware):** Before you reach the kitchen, a hostess checks: Are you logged in? Are you allowed to do this? Operators can create tickets; guests cannot. This is **middleware** — a gatekeeper that enforces rules before your request proceeds.

3. **The Waiter (Controller):** Once approved, the request is handed to a **controller** — the waiter who takes your order to the kitchen. The controller reads what the user submitted and passes it along.

4. **The Chef (Service / Action):** The controller hands the work to a **service** or **action class** — the chef who actually prepares the meal. This is where the business rules live: "Does this ticket have all required fields? Is the equipment valid? Send a notification to the assigned technician."

5. **The Pantry (Database):** When the chef needs ingredients — or needs to store the finished dish — the **database** is the pantry and cold-storage. Data is saved, updated, or retrieved using the **model layer** (the pantry's inventory system).

6. **The Plate (Response):** The finished result is packaged up and sent back to the user's screen — a success message, a updated dashboard, or a confirmation email.

And just like in a real restaurant, if something goes wrong at any step — wrong order, missing ingredient, allergy conflict — the system has an **error handler** (the manager) who catches the problem and gives the user a clear, friendly message instead of a crash.

---

## 🛠️ Technology Stack

### Backend
| Technology | Purpose |
|---|---|
| **PHP 8.5+** | Runtime language |
| **Laravel 13** | Web framework |
| **MySQL / SQLite** | Database (SQLite for dev, MySQL for prod) |
| **Redis** | Caching, sessions, and queue driver |
| **Octane (FrankenPHP)** | Production app server with worker mode |
| **OpenAI API** | AI-powered ticket classification |

### Frontend
| Technology | Purpose |
|---|---|
| **Blade Templates** | Server-side templating |
| **Tailwind CSS** | Utility-first CSS framework |
| **FullCalendar v6** | Event scheduling and visualization |
| **Vite** | Asset bundling and development server |
| **Alpine.js** | Lightweight DOM interactions |

### Quality & DevOps
| Technology | Purpose |
|---|---|
| **PHPUnit** | Testing framework (≈1774 tests in 8 suites) |
| **PHPStan** | Static analysis (Level max) |
| **Laravel Pint** | Code formatting |
| **Docker** | Containerization (Multi-stage build) |
| **OpenAPI/Swagger** | API documentation |

---

## 📦 Installation & Setup

### Prerequisites
- **PHP 8.5+** with common extensions (PDO, OpenSSL, JSON)
- **Composer 2.0+**
- **Node.js 22** and npm
- **MySQL 8.0+** or SQLite (for development)
- **Redis** (optional but recommended for production)

### Local Development Setup

#### 1. Clone the repository
```bash
git clone https://github.com/NyaPuma/Projeto-Final-Cesae.git
cd Projeto-Final-Cesae
```

#### 2. Install dependencies
```bash
composer install
npm install
```

#### 3. Configure environment
```bash
cp .env.example .env
php artisan key:generate
```

Then edit `.env` with your database credentials:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sgm
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=database
CACHE_STORE=database
```

#### 4. Set up database and assets
```bash
php artisan migrate --seed
php artisan storage:link
npm run dev
```

#### 5. Start the application

**Terminal 1 - Queue Worker** (processes emails, exports, notifications):
```bash
php artisan queue:work
```

**Terminal 2 - Scheduler** (runs daily tasks like low-stock checks):
```bash
php artisan schedule:work
```

**Terminal 3 - Development Servers** (Laravel + Vite):
```bash
php artisan serve   # Runs on http://localhost:8000
npm run dev         # Vite dev server
```

The application will be available at **http://localhost:8000**

---

## 🧪 Testing

Run the full test suite:
```bash
php artisan test
```

Expected output:
```
Tests:    ~1774 passed
Assertions: 4563
Duration: ~240s
Failures: 0
```

Run specific test suites:
```bash
php artisan test --filter=AuthFlow              # Authentication tests
php artisan test --filter=Performance           # Performance benchmarks
php artisan test --filter=Security              # Security tests
php artisan test --filter=Feature               # Feature tests
php artisan test tests/Unit/Models/            # Model unit tests
```

Generate coverage report:
```bash
php artisan test --coverage
```

---

## 🚀 Production Deployment

### Docker Deployment

#### Build the image:
```bash
docker build -t sgm:latest .
```

#### Run the container:
```bash
docker run -d \
  --name sgm \
  -p 8000:80 \
  -e APP_ENV=production \
  -e APP_DEBUG=false \
  -e DB_HOST=postgres.internal \
  -e DB_PORT=5432 \
  -e REDIS_HOST=redis.internal \
  sgm:latest
```

#### Health check:
```bash
curl http://127.0.0.1:8000/health
# Response: {"status":"ok","timestamp":"...","uptime":"..."}
```

### Pre-Deployment Checklist

```bash
# 1. Run migrations (if pending)
php artisan migrate --force

# 2. Cache configuration (Octane workers boot once per container)
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 3. Optimize autoloader
composer install --no-dev --optimize-autoloader

# 4. Check environment
php artisan config:show octane
# Expected: server = frankenphp

# 5. Verify all tests pass
php artisan test

# 6. Check security
composer audit --locked
```

### Runtime Configuration

Key `.env` variables for production:

```env
APP_ENV=production
APP_DEBUG=false

LOG_CHANNEL=stderr
LOG_LEVEL=error

DB_CONNECTION=mysql
REDIS_HOST=redis.internal
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

OCTANE_SERVER=frankenphp
OCTANE_WORKERS=8
OCTANE_MAX_EXECUTION_TIME=30

MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
```

---

## 📖 New to Programming?

**See [NON_TECHNICAL_PROJECT_GUIDE.md](NON_TECHNICAL_PROJECT_GUIDE.md)** -- a step-by-step plain-English guide explaining every folder, file, and workflow in this project with real-world analogies. Written for someone with zero programming experience.

---

## 📚 Documentation

Comprehensive architectural and operational documentation is available in the `/docs` directory:

### Strategy & Management
- [Project Plan](docs/plano-projeto.md) — Sprint planning, team composition, risk matrix
- [Product Backlog](docs/product-backlog.md) — Feature list, priorities, acceptance criteria
- [Process Analysis (As-Is vs To-Be)](docs/analise-pocessos.md) — Operational improvements and AI-assisted workflows

### Engineering & Architecture
- [Requirements](docs/Requisitos.md) — Functional (RF) and Non-Functional (RNF) requirements
- [Authorization & Permissions (RBAC)](docs/permissoes.md) — Role-based access control matrix
- [Data Architecture (ER Diagram)](docs/diagrama-arquitetura.md) — Relational model, indexing, constraints
- [API Endpoints](docs/api-endpoints.md) — REST API specification and contracts

### Quality & Operations
- [User Guide](docs/guia-utilizador.md) — Step-by-step instructions for each user role
- [Test Plan](docs/Plano-Testes.md) — QA scenarios, RBAC security testing, validation matrix
- [Workflow & Integrations](docs/workflow-integracoes.md) — Unified data flow and notification architecture

---

## 🏗️ Project Architecture

### Layered Architecture

```
┌─────────────────────────────────────┐
│  HTTP Layer                         │ → Controllers, Middleware, Routes
├─────────────────────────────────────┤
│  Service Layer                      │ → Business logic orchestration
├─────────────────────────────────────┤
│  Action/Domain Layer                │ → Single-purpose command handlers
├─────────────────────────────────────┤
│  Repository Layer                   │ → Data access abstraction
├─────────────────────────────────────┤
│  Model Layer (Eloquent ORM)         │ → Database mapping
├─────────────────────────────────────┤
│  Database (MySQL/SQLite)            │ → Persistent storage
└─────────────────────────────────────┘
```

### Key Design Patterns

- **Repository Pattern**: Data access abstraction via interfaces and implementations
- **Action Classes**: Single-responsibility command handlers for complex operations
- **Service Layer**: Business logic orchestration across domains
- **DTOs (Data Transfer Objects)**: Strongly-typed data passing between layers
- **Value Objects**: Immutable domain concepts (Email, Money, SerialNumber)
- **Model Observers**: Automatic lifecycle hooks (audit trail, cache invalidation)
- **Policy-based Authorization**: Granular permission checks via Laravel Policies

### Directory Structure

```
app/
├── Actions/                 # Single-purpose command handlers
├── Http/                    # Controllers, middleware, requests, resources
├── Models/                  # Eloquent ORM models
├── Services/                # Business logic services
├── Repositories/            # Data access layer
├── DTOs/                    # Data Transfer Objects
├── Enums/                   # PHP 8.1+ enums
├── Events/                  # Domain events
├── Jobs/                    # Queued background jobs
├── Listeners/               # Event listeners
├── Mail/                    # Mailable classes
├── Notifications/           # User notifications
├── Observers/               # Model lifecycle observers
├── Policies/                # Authorization policies
├── Traits/                  # Shared traits (Auditable, etc.)
├── ValueObjects/            # Immutable value objects
└── Console/                 # Artisan commands
```

---

## 📊 Project Status

### ✅ Current State
- **Test Suite**: ≈1774 tests passing (100% green)
- **Security**: Enterprise-grade hardening applied
- **Performance**: Optimized for Octane worker mode with Redis caching
- **Deployment**: Production-ready Docker setup with multi-stage build
- **Code Quality**: Strict types, clean architecture, SOLID principles
- **Localization**: 100% English code identifiers, 50+ language support

### 🚀 Recent Improvements (2026-08-28 to 2026-09-01)

1. **Security Hardening**
   - Rate limiting on 35 sensitive endpoints
   - CSRF token validation
   - SQL injection prevention verified
   - HMAC-SHA256 token hashing

2. **Performance Optimization**
   - N+1 query elimination via eager loading
   - Query result caching (analytics, dashboard, themes)
   - Lazy loading for large datasets
   - Async job processing for heavy operations

3. **Production Readiness**
   - Octane/FrankenPHP worker mode configured
   - Multi-stage Docker build
   - Health check endpoints
   - OPcache enabled with production settings

4. **Code Quality**
   - 185 files with strict types declaration
   - API Resources for all endpoints
   - Error handling standardization
   - Dependency injection container

5. **Localization**
   - 100% English code identifiers (routes, methods, classes)
   - 50+ language packs
   - Per-user preferences (language, currency, date format)
   - Currency conversion support

---

## 🔒 Security Features

- ✅ **Authentication**: opaque API tokens (HMAC-SHA256 hashed at rest, not JWT), session management, password reset via secure links
- ✅ **Authorization**: Role-based access control (RBAC) with three tiers
- ✅ **Input Validation**: Strict FormRequest validation on all endpoints
- ✅ **SQL Injection Prevention**: Parameterized queries, safe aggregation functions
- ✅ **CSRF Protection**: Token validation on all state-changing requests
- ✅ **Rate Limiting**: Throttling on auth, API, and sensitive operations
- ✅ **Secure Headers**: HTTPS-only cookies, HttpOnly flag, SameSite policy
- ✅ **Audit Trail**: Complete change tracking with user and timestamp
- ✅ **Dependency Security**: Regular audits, no known vulnerabilities

---

## ⚡ Performance Features

- ✅ **Query Optimization**: Eager loading, select-only columns, chunk processing
- ✅ **Database Indexes**: All foreign keys and filter columns indexed
- ✅ **Caching Strategy**: Redis integration, cache invalidation via observers
- ✅ **Async Processing**: Queue-based job handling (emails, exports, AI)
- ✅ **Octane Worker Mode**: Single-boot app serving multiple requests
- ✅ **OPcache**: Enabled in production with no timestamp validation
- ✅ **Asset Optimization**: Vite code splitting, minified CSS/JS, hashed filenames

---

## 🤝 Contributing

To contribute to this project:

1. **Fork the repository**
2. **Create a feature branch** (`git checkout -b feature/amazing-feature`)
3. **Follow code standards** (see `phpstan.neon`; Pint runs with its Laravel defaults — there is no `.pint.json`)
4. **Write tests** for new features (maintain 80%+ coverage)
5. **Run the full test suite** (`php artisan test`)
6. **Commit with clear messages** (`git commit -m 'Add amazing feature'`)
7. **Push to the branch** (`git push origin feature/amazing-feature`)
8. **Open a Pull Request**

### Code Standards

- Declare strict types: `declare(strict_types=1);` at file top
- Use type hints on all parameters and returns
- Follow PSR-12 coding standard (enforced by Laravel Pint)
- Run `vendor/bin/pint` before committing (there is no `composer lint` script)
- Maintain or improve test coverage

---

## 📄 License

This project is licensed under the MIT License — see the [LICENSE](LICENSE) file for details.

---

## 👥 Team & Attribution

**Project Lead**: André Moreira  
**Organization**: CESAE Digital  
**Program**: Final Project (Integrated Systems & Database Administration)  
**Year**: 2026

---

## 📞 Support & Contact

For issues, questions, or feature requests:

1. **Check existing documentation** in `/docs`
2. **Search GitHub issues** for similar problems
3. **Review test cases** in `/tests` for usage examples
4. **Open an issue** with clear reproduction steps
5. **Contact**: Project maintainers via GitHub

---

**Last Updated**: September 1, 2026  
**Status**: Production-Ready
