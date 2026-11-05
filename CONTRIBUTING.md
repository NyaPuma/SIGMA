# Contributing to SGM

Thank you for your interest in contributing to the Integrated Maintenance Management System (SGM). This guide provides information about contributing code, reporting issues, and participating in development.

---

## Table of Contents

1. [Code of Conduct](#code-of-conduct)
2. [Getting Started](#getting-started)
3. [Development Setup](#development-setup)
4. [Code Standards](#code-standards)
5. [Commit & Pull Request Workflow](#commit--pull-request-workflow)
6. [Testing Requirements](#testing-requirements)
7. [Documentation](#documentation)
8. [Reporting Bugs](#reporting-bugs)
9. [Suggesting Features](#suggesting-features)

---

## Code of Conduct

This project is committed to providing a welcoming and inspiring community. We respect all contributors regardless of background, experience level, or identity. Please be kind, inclusive, and constructive in all interactions.

---

## Getting Started

### Prerequisites

- **PHP 8.5+** with common extensions (PDO, OpenSSL, JSON, etc.)
- **Composer 2.0+** for dependency management
- **Node.js 22** with npm for frontend assets
- **MySQL 8.0+** or **SQLite** for the database
- **Git** for version control
- **Docker** (optional, for containerized development)

### Development Environment

1. **Clone the repository**
   ```bash
   git clone https://github.com/NyaPuma/Projeto-Final-Cesae.git
   cd Projeto-Final-Cesae
   ```

2. **Install dependencies**
   ```bash
   composer install
   npm install
   ```

3. **Configure environment**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Setup database**
   ```bash
   php artisan migrate --seed
   php artisan storage:link
   ```

5. **Start development servers**
   ```bash
   # Terminal 1: Queue worker
   php artisan queue:work

   # Terminal 2: Scheduler
   php artisan schedule:work

   # Terminal 3: PHP and assets
   php artisan serve
   npm run dev
   ```

6. **Run tests to verify setup**
   ```bash
   php artisan test
   ```

---

## Development Setup

### IDE Setup (VS Code)

**Recommended Extensions:**
- PHP Intelephense
- Laravel Extension Pack
- ESLint
- Prettier
- PHP Sniffer

**Settings** (`.vscode/settings.json`):
```json
{
    "php.validate.executablePath": "/usr/bin/php",
    "editor.formatOnSave": true,
    "[php]": {
        "editor.defaultFormatter": "open-vscode.php-pack",
        "editor.codeActionsOnSave": {
            "source.fixAll.eslint": true
        }
    }
}
```

### Database Debugging

```bash
# View current migrations
php artisan migrate:status

# Rollback one migration
php artisan migrate:rollback

# Rollback all (use with caution!)
php artisan migrate:reset

# Re-run migrations with seeding
php artisan migrate:fresh --seed

# Open database browser (SQLite)
sqlite3 database/database.sqlite
```

### Queue Debugging

```bash
# Process jobs synchronously in testing
export QUEUE_CONNECTION=sync
php artisan test

# Failed job insights
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Flush all failed jobs
php artisan queue:flush
```

---

## Code Standards

### PHP Standards

#### 1. Strict Types Declaration
Every PHP file must declare strict types at the top:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;
```

#### 2. Type Hints
All methods must have parameter and return type hints:

```php
public function getUserEmail(User $user): string
{
    return $user->email;
}

public function createUser(string $name, string $email): User
{
    return User::create([
        'name' => $name,
        'email' => $email,
    ]);
}
```

#### 3. Naming Conventions

| Element | Convention | Example |
|---------|-----------|---------|
| **Classes** | PascalCase | `UserService`, `CreateTicketAction` |
| **Methods** | camelCase | `createUser()`, `getUserByEmail()` |
| **Variables** | camelCase | `$userId`, `$isActive` |
| **Constants** | UPPER_SNAKE_CASE | `MAX_RETRIES`, `DEFAULT_TIMEOUT` |
| **Routes** | snake_case with dots | `tickets.show`, `api.users.store` |
| **Database** | snake_case | `user_id`, `created_at` |

#### 4. Code Style
The project uses **Laravel Pint** for code formatting. Run before committing:

```bash
vendor/bin/pint app/
vendor/bin/pint tests/
```

#### 5. Static Analysis
Use **PHPStan** (Level max, set in `phpstan.neon`) to catch type and logic errors:

```bash
vendor/bin/phpstan analyse app/
```

#### 6. Comments & Documentation
- Write self-documenting code (clear names reduce need for comments)
- Use docblocks for classes and public methods:

```php
/**
 * Creates a new user and generates authentication token.
 *
 * @param CreateUserData $data User creation data
 * @throws DuplicateUserException If email already exists
 * @return User The created user
 */
public function createUser(CreateUserData $data): User
{
    // ...
}
```

### JavaScript/TypeScript Standards

#### 1. Strict Mode
Use strict checking in TypeScript:

```typescript
// tsconfig.json
{
    "compilerOptions": {
        "strict": true,
        "noImplicitAny": true,
        "strictNullChecks": true
    }
}
```

#### 2. Type Safety
Always provide types:

```typescript
// Good
function formatAmount(amount: number): string {
    return `$${(amount / 100).toFixed(2)}`;
}

// Bad
function formatAmount(amount) {
    return `$${(amount / 100).toFixed(2)}`;
}
```

#### 3. Code Formatting
Use **Prettier** for consistent formatting:

```bash
npx prettier --write resources/js/
npx prettier --write resources/css/
```

#### 4. Linting
Run the project's own quality gates (there is no ESLint configuration or `npm run lint` script):

```bash
vendor/bin/pint            # PHP code style (use --test to check only)
vendor/bin/phpstan analyse # static analysis, level max (phpstan.neon)
```

### Database Standards

#### 1. Migrations
- Use English names for tables and columns
- Always include foreign key constraints
- Add indexes to filtered columns

```php
Schema::create('tickets', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->foreignId('equipment_id')->constrained();
    $table->enum('status', ['open', 'closed'])->default('open');
    $table->index('status');  // Column used in WHERE
    $table->timestamps();
});
```

#### 2. Models
- Define explicit `$fillable` (mass assignment protection)
- Implement casts for type safety
- Add relationships with clear names

```php
class Ticket extends Model
{
    protected $fillable = ['title', 'description', 'status'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'status' => TicketStatusEnum::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

---

## Commit & Pull Request Workflow

### Branch Management
- **`main`**: Production release branch (never commit directly)
- **`develop`**: Integration branch for features
- **`feature/name`**: Feature branches from `develop`
- **`bugfix/name`**: Bug fix branches from `develop`
- **`hotfix/name`**: Production hotfixes from `main`

### Feature Branch Workflow

1. **Create feature branch from `develop`**
   ```bash
   git checkout develop
   git pull origin develop
   git checkout -b feature/amazing-feature
   ```

2. **Make atomic commits with clear messages**
   ```bash
   git commit -m "Add user authentication endpoint"
   git commit -m "Add unit tests for auth service"
   git commit -m "Update API documentation"
   ```

3. **Keep branch up-to-date**
   ```bash
   git fetch origin
   git rebase origin/develop
   ```

4. **Push to your fork**
   ```bash
   git push origin feature/amazing-feature
   ```

5. **Open a Pull Request** with:
   - Clear title describing the change
   - Description of what and why
   - Reference to related issues (#123)
   - Screenshots if UI changes

### Commit Message Format

```
<type>: <subject>

<body>

<footer>
```

**Types:**
- `feat` — New feature
- `fix` — Bug fix
- `refactor` — Code restructuring (no behavior change)
- `perf` — Performance improvement
- `test` — Test additions/changes
- `docs` — Documentation
- `chore` — Build, dependencies, CI

**Examples:**
```
feat: Add currency conversion to budget display

- Implement CurrencyRateService for live rates
- Add per-user currency preference
- Display in preferred currency on budget form

Fixes #456
```

```
fix: Prevent N+1 queries in ticket listing

- Add eager loading with ->with('equipment', 'user')
- Reduce queries from 50+ to 3

Performance: -45% query time
```

### Pull Request Checklist

Before submitting a PR:

- [ ] Branch is up-to-date with `develop`
- [ ] All tests pass: `php artisan test`
- [ ] Code is formatted: `vendor/bin/pint app/`
- [ ] Static analysis passes: `vendor/bin/phpstan analyse app/`
- [ ] CSS builds: `npm run build`
- [ ] No debugging code (dd(), dump(), var_dump())
- [ ] Documentation updated
- [ ] Commit messages are clear and atomic

### Code Review Process

1. **Automated checks** run first (linting, testing)
2. **Maintainers review** for:
   - Functionality and correctness
   - Code quality and style adherence
   - Test coverage (>80%)
   - Security implications
   - Performance impact
3. **Address feedback** by pushing additional commits
4. **Merge** once approved (squash if requested)

---

## Testing Requirements

### Test Coverage Goals

- **Minimum**: 80% statement coverage
- **Target**: 85%+ across all layers

### Test Organization

```
tests/
├── Unit/                # Individual class tests
├── Feature/             # HTTP endpoint tests
├── Security/            # Authorization & validation tests
├── Performance/         # Load & query count tests
└── Database/            # Schema validation
```

### Writing Tests

**Unit Test Example:**
```php
class UserServiceTest extends TestCase
{
    #[Test]
    public function it_creates_user_with_hashed_password(): void
    {
        $data = new CreateUserData(
            name: 'John Doe',
            email: 'john@example.com',
            password: 'secret123',
        );

        $user = (new CreateUserAction())($data);

        expect($user->email)->toBe('john@example.com');
        expect(Hash::check('secret123', $user->password))->toBeTrue();
    }
}
```

**Feature Test Example:**
```php
class CreateTicketTest extends TestCase
{
    #[Test]
    public function admin_can_create_ticket(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->post('/api/tickets', [
                'title' => 'Equipment broken',
                'description' => 'Cannot start',
                'equipment_id' => 1,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('tickets', [
            'title' => 'Equipment broken',
        ]);
    }
}
```

### Running Tests

```bash
php artisan test                    # All tests
php artisan test --filter=UserTest  # Specific test
php artisan test tests/Unit/        # Test directory
php artisan test --coverage         # With coverage report
```

---

## Documentation

### When to Update Docs

1. **New features** → Add to [ARCHITECTURE.md](ARCHITECTURE.md)
2. **API changes** → Update [API documentation](docs/api-endpoints.md)
3. **Database schema** → Update migrations and [data architecture](docs/diagrama-arquitetura.md)
4. **User-facing changes** → Update [user guide](docs/guia-utilizador.md)
5. **Setup instructions** → Update this README and [development guide](ARCHITECTURE.md)

### Documentation Standards

- Write in English
- Use clear, concise language
- Provide code examples where relevant
- Include diagrams for complex concepts
- Keep documentation in sync with code

---

## Reporting Bugs

### Before Reporting

1. **Check existing issues** — Your bug may already be reported
2. **Reproduce consistently** — Steps to reliably reproduce
3. **Check documentation** — The behavior may be documented
4. **Test latest code** — Bug may already be fixed

### Bug Report Template

```markdown
## Description
Clear description of the bug.

## Steps to Reproduce
1. First step
2. Second step
3. ...

## Expected Behavior
What should happen.

## Actual Behavior
What actually happens.

## Environment
- PHP version: 8.2.x
- Laravel version: 12.x
- Database: MySQL 8.0 / SQLite
- OS: Windows 11 / macOS / Linux

## Additional Context
Screenshots, error logs, code snippets, etc.
```

---

## Suggesting Features

### Feature Request Template

```markdown
## Description
Clear description of the requested feature.

## Motivation
Why is this feature needed? What problem does it solve?

## Proposed Solution
How you envision implementing or using this feature.

## Alternative Solutions
Other approaches you've considered.

## Additional Context
Examples, mockups, references, etc.
```

### Good Feature Requests Include

- ✅ Clear use case and motivation
- ✅ How it fits with existing features
- ✅ Rough implementation idea
- ✅ Examples or mockups
- ✅ Acceptance criteria

### Feature Evaluation

Features are evaluated based on:

1. **Alignment with project goals** — Fits the product vision
2. **User demand** — Multiple users need it
3. **Complexity** — Effort to implement vs. benefit
4. **Maintenance burden** — Ongoing support required
5. **Security implications** — No new vulnerabilities

---

## Development Checklists

### Creating a New Feature

```
[ ] Feature is planned in the product backlog
[ ] Issue is created and labeled
[ ] Feature branch is created from `develop`
[ ] Code follows all standards (types, naming, style)
[ ] Unit tests written (>80% coverage)
[ ] Feature tests written (HTTP endpoints)
[ ] Integration tests added (cross-layer)
[ ] Database migrations created (if needed)
[ ] Models/DTOs created with type safety
[ ] Services/Actions implemented
[ ] Controllers created (thin, delegate logic)
[ ] Routes registered and documented
[ ] API resources created (if applicable)
[ ] Validation rules added (FormRequest)
[ ] Authorization policies implemented
[ ] Error handling in place
[ ] Logging added (important operations)
[ ] Documentation updated
[ ] README updated (if needed)
[ ] Static analysis passes (phpstan)
[ ] Code formatting passes (pint)
[ ] All tests pass (>80% coverage)
[ ] Manual testing completed
[ ] Pull request opened with description
[ ] Code review completed
[ ] Approved and merged
```

### Before Releasing

```
[ ] All tests passing (100%)
[ ] No code coverage regression
[ ] Static analysis clean
[ ] Dependencies updated and audited
[ ] Database migrations tested
[ ] Performance benchmarks run
[ ] Security audit completed
[ ] Documentation finalized
[ ] CHANGELOG updated
[ ] Version bumped (semver)
[ ] Release notes prepared
```

---

## Additional Resources

- [Architecture Overview](ARCHITECTURE.md) — System design and patterns
- [Laravel Documentation](https://laravel.com/docs) — Official Laravel docs
- [PHP Standards](https://www.php-fig.org/psr/) — PSR coding standards
- [Testing Guide](tests/README.md) — Testing conventions and patterns

---

## Getting Help

- **Issues**: Open a [GitHub issue](https://github.com/NyaPuma/Projeto-Final-Cesae/issues)
- **Documentation**: Check [ARCHITECTURE.md](ARCHITECTURE.md) or [docs/](docs/)
- **Code Examples**: Review [tests/](tests/) for usage patterns
- **Contact**: Reach out to maintainers via GitHub

---

## Recognition

Contributors who submit accepted PRs will be:
- Thanked in release notes
- Added to contributors list
- Recognized in project documentation

---

**Last Updated**: September 1, 2026  
**Status**: Production-Ready
