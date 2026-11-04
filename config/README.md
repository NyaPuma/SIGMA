# `config/`

Laravel configuration files for the SGM application.

> **New to programming?** See [NON_TECHNICAL_PROJECT_GUIDE.md](../NON_TECHNICAL_PROJECT_GUIDE.md) -- this folder is explained as "The Settings Panel" with all the knobs and switches that control how the system behaves.

## What are config files, really?

Think of the `config/` folder as the **settings panel / control room** of the whole application. Just like a car's dashboard lets you adjust the radio, the mirrors, and the air conditioning from one place, this folder is where ALL of the app's global choices live in one obvious place.

If you want to change the app's name, switch the default timezone, pick a different database, enable or disable a feature, or swap which email service sends out notifications — you reach into this folder (or the `.env` file it reads from) and turn the knob. The rest of the application reads these files and automatically behaves differently.

Each file controls one area of the app. The value of every setting can usually be overridden in the `.env` file, which is how production and development servers can use completely different settings (e.g. a real database in production, a test one on your laptop).

## Files

### `app.php`
- **What it controls:** The identity and core behaviour of the whole app — its name, environment (dev vs. production), debug mode, timezone, and which language is the default.
- **How it works:** Nearly every screen and notification uses the app name, and the timezone/locale here (default `pt-PT`) decide how dates and numbers are shown. Turning on debug mode reveals detailed error messages while developing; turning it off in production hides them.

### `auth.php`
- **What it controls:** How people sign in — what "guards" exist for web (browser sessions) vs. API (token) logins, plus password reset behaviour.
- **How it works:** The `web` guard remembers you with a browser session; the `api` guard checks a token on each request. It also configures how users are looked up (the "provider") and how password resets are handled.

### `backup.php`
- **What it controls:** How daily database backups are taken — where they're saved, whether they're compressed, how long they're kept, and which tables are skipped.
- **How it works:** The scheduled backup command reads this to build a compressed `.sql` dump, keep 30 days of history (removing anything older), and exclude log-like tables (e.g. `failed_jobs`). Offsite storage (like an S3 bucket) can also be enabled.

### `broadcasting.php`
- **What it controls:** How the app pushes live events to the browser in real time (e.g. Pusher).
- **How it works:** If enabled, the app publishes notifications/sockets through Reverb/Pusher so pages can update instantly without a refresh. Defaults to a harmless `log`/`null` driver so nothing is broadcast unless set up.

### `cache.php`
- **What it controls:** Where the app stores temporarily-cached data (like repeated lookups) to make things faster.
- **How it works:** Defaults to the database store (a `cache` table), but can use files, Redis, or memory. Cached results are served instantly instead of recomputing, so the app feels snappier and the database works less.

### `database.php`
- **What it controls:** The database connections available to the app — which server, which database name, credentials, port, and whether foreign-key checks run.
- **How it works:** Defaults to MySQL, and the app runs every query against the configured connection. This file defines the "one place" to say where the data lives (also using SQLite, Redis connections, etc.).

### `features.php`
- **What it controls:** A set of **feature switches** that turn optional capabilities on or off.
- **How it works:** Each flag is a simple on/off read from `.env` (e.g. `FEATURE_AI_RECOMMENDATIONS`, `FEATURE_EXTERNAL_CURRENCY_RATES`). When a flag is off the related code is bypassed; turning it on enables the feature. This is how you enable/disable whole parts of the app without rewriting code.

### `filesystems.php`
- **What it controls:** Where uploaded files live — the disks the app can store and serve files on (local disk, public web-accessible files, or cloud S3).
- **How it works:** Defines named "disks". The `public` disk is where photo attachments go so they're reachable by URL; `local`/`s3` are for private or cloud-stored files. The default disk handles generic file storage.

### `hashing.php`
- **What it controls:** How passwords are scrambled (hashed) before being stored.
- **How it works:** Uses `bcrypt` by default (with 12 rounds of work), which is a one-way scramble — passwords are never stored in plain text. It also configures the Argon2 algorithm options as an alternative. This is why stored passwords can't be read, even by admins.

### `l5-swagger.php`
- **What it controls:** The interactive API documentation (Swagger/OpenAPI) — the address where it lives and how it's generated.
- **How it works:** Makes a browsable, self-updating documentation page available (at `docs/openapi`) showing every API endpoint, what it accepts, and what it returns — useful for anyone integrating with the API. It also hosts the generated `api-docs.json`/`.yaml` files.

### `locales.php`
- **What it controls:** Every language the app can display, including each language's name, flag, currency list, number/date format, and continent grouping.
- **How it works:** When a user picks Portuguese, English, Spanish, etc., the app looks here to know how to format dates, numbers, and currencies for that person. Contains 596 lines covering many languages so the app can feel "at home" for people worldwide.

### `logging.php`
- **What it controls:** Where and how the app writes its activity and error logs — which channel (stack, daily files, Slack, syslog, papertrail), and what context is attached.
- **How it works:** Every error or informational message is written to one of the configured channels. The app adds request context (user, URL) automatically, and logs can be routed to Slack or a monitoring service when something important happens.

### `mail.php`
- **What it controls:** How emails are sent — which mailer service to use (SMTP, Mailgun, SendGrid, SES, etc.), the "from" address, and the template theme.
- **How it works:** Used for password-reset links, notifications, and test emails. Defaults to `log` (writes emails to a log file instead of truly sending them), handy during development; production uses a real mailer service.

### `observability.php`
- **What it controls:** Performance-monitoring thresholds — what counts as a "slow" request, a "high memory" situation, or a "slow" background job, plus "circuit breaker" behaviour that trips when things keep failing.
- **How it works:** The app watches request times and memory usage against these thresholds, logging warnings when they're exceeded. The circuit breaker pauses an unreliable service after repeated failures and retries after a cooldown, protecting the app from cascading errors.

### `octane.php`
- **What it controls:** Performance-enhancement server settings used by Laravel Octane (which server to use: Swoole, RoadRunner, or FrankenPHP) and how it handles requests/lifecycle events.
- **How it works:** Octane keeps the app "warm" in memory between requests so pages load dramatically faster. This file chooses the server and configures cleanup/listener behaviour. (Usually tuned in production only.)

### `openai.php`
- **What it controls:** The OpenAI API setup for the app's AI-powered features — API key, organisation, project, base URL, and request timeout.
- **How it works:** When the AI-recommendation feature is enabled, the app calls OpenAI to suggest actions (e.g. recommended maintenance). This file holds the credentials and timeout (5 seconds) for those calls.

### `pulse.php`
- **What it controls:** Laravel Pulse — the built-in health/perf dashboard, including the URL path it's served on and what it monitors (requests, jobs, slow queries, cache, etc.).
- **How it works:** Provides a real-time dashboard (default at `/pulse`) showing how healthy and fast the app is. This file decides the path, which recorders are active, and who is allowed to view it.

### `queue.php`
- **What it controls:** How time-consuming background jobs (like sending emails or generating reports) are handled — via the database queue, Redis, SQS, or synchronously.
- **How it works:** Long tasks get queued and processed "behind the scenes" so the user isn't left waiting. Defaults to a `database`-backed queue. Also defines how failed jobs are stored for retrying.

### `sanctum.php`
- **What it controls:** Laravel Sanctum — how API tokens and SPA (single-page-app) authentication work, which domains get stateful auth cookies, and token expiry.
- **How it works:** Grants token-based access to the API, lists trusted domains (localhost + app domains), and controls token expiration and middleware. This is the "pass" system that lets the dashboard's JavaScript authenticate.

### `sentry.php`
- **What it controls:** Error tracking via Sentry — the endpoint (DSN), environment, per-service sampling rates, and what gets recorded (SQL queries, HTTP requests, etc.).
- **How it works:** When an error or slow request happens, Sentry captures it and sends it to the Sentry dashboard so developers can see and fix bugs in production. This file decides how much detail is captured.

### `services.php`
- **What it controls:** Third-party service credentials and internal tuning — keys for Mailgun, Postmark, SendGrid, SES, Slack, plus budget threshold, analytics SLA targets, auth limits (max attempts, lockout, token expiry), upload limits, pagination size, and slow-query logging.
- **How it works:** Provides one location for external-service keys and for app-level tuning like "how many failed logins before lockout", "max photo upload size", and "items per page". Fine-tunes the everyday behaviour of the app.

### `services.custom.php`
- **What it controls:** The single definition of the application tunables read as `services.custom.*` — auth attempts, lockout and token expiry, budget threshold, availability KPI, AI model/temperature, photo upload limits, pagination defaults, API token length, and the notification mailer.
- **How it works:** It wins over the `services.custom` tree of any other file, because Laravel merges config in name order with `array_set()`; `tests/Unit/Config/ServiceConfigTest.php` keeps the readers and these keys aligned.

### `session.php`
- **What it controls:** How the app remembers a logged-in user's browser session — where sessions are stored (default database), how long they last (default 120 minutes), and whether they're encrypted.
- **How it works:** When you log in, a session is created that the server remembers. After the lifetime (120 min of inactivity) or expiry, you're logged out. This is what keeps you logged in between page visits.

## Configuration Keys Reference (for developers / AI)

Below is the complete key map for every config file. All values read from `.env` via `env()` with sensible defaults. This is the exhaustive reference.

### `app.php`
| Key | Env var | Default |
|---|---|---|
| `name` | `APP_NAME` | `Laravel` |
| `env` | `APP_ENV` | `production` |
| `debug` | `APP_DEBUG` | `false` |
| `url` | `APP_URL` | `http://localhost` |
| `timezone` | — | `UTC` |
| `locale` | `APP_LOCALE` | `pt-PT` |
| `fallback_locale` | `APP_FALLBACK_LOCALE` | `pt-PT` |
| `faker_locale` | `APP_FAKER_LOCALE` | `en_US` |
| `cipher` | — | `AES-256-CBC` |
| `key` | `APP_KEY` | — |
| `previous_keys` | `APP_PREVIOUS_KEYS` | `[]` |
| `maintenance.driver` | `APP_MAINTENANCE_DRIVER` | `file` |
| `maintenance.store` | `APP_MAINTENANCE_STORE` | `database` |

### `backup.php` (custom — used by the `DatabaseBackup` command)
| Key | Env var | Default |
|---|---|---|
| `database.connection` | `DB_BACKUP_CONNECTION` | `null` (uses default) |
| `database.destination.path` | — | `storage/app/backups` |
| `database.destination.filename` | — | `backup_<date>.sql` |
| `database.compression` | `DB_BACKUP_COMPRESSION` | `true` |
| `database.exclude_tables` | — | `['failed_jobs', 'personal_access_tokens']` |
| `retention.days` | `DB_BACKUP_RETENTION_DAYS` | `30` |
| `storage.enabled` | `BACKUP_STORAGE_ENABLED` | `true` |
| `storage.path` | — | `storage/app` |
| `offsite.enabled` | `BACKUP_OFFSITE_ENABLED` | `false` |
| `offsite.disk` | `BACKUP_OFFSITE_DISK` | `s3` |
| `offsite.path` | `BACKUP_OFFSITE_PATH` | `application-backups` |

### `features.php` (feature switches)
| Key | Env var | Default |
|---|---|---|
| `flags.ai_recommendations` | `FEATURE_AI_RECOMMENDATIONS` | `true` |
| `flags.external_currency_rates` | `FEATURE_EXTERNAL_CURRENCY_RATES` | `true` |

### `observability.php`
| Key | Env var | Default |
|---|---|---|
| `slow_request_threshold_ms` | `OBSERVABILITY_SLOW_REQUEST_THRESHOLD_MS` | `100` |
| `high_memory_threshold_mb` | `OBSERVABILITY_HIGH_MEMORY_THRESHOLD_MB` | `128` |
| `queue_slow_job_threshold_ms` | `OBSERVABILITY_QUEUE_SLOW_JOB_THRESHOLD_MS` | `1000` |
| `circuit_breaker.failure_threshold` | `CIRCUIT_BREAKER_FAILURE_THRESHOLD` | `3` |
| `circuit_breaker.cooldown_seconds` | `CIRCUIT_BREAKER_COOLDOWN_SECONDS` | `60` |

### `sentry.php`
| Key | Env var | Default |
|---|---|---|
| `dsn` | `SENTRY_LARAVEL_DSN` / `SENTRY_DSN` | — |
| `release` | `SENTRY_RELEASE` | — |
| `environment` | `SENTRY_ENVIRONMENT` | `APP_ENV` |
| `sample_rate` | `SENTRY_SAMPLE_RATE` | `1.0` |
| `traces_sample_rate` | `SENTRY_TRACES_SAMPLE_RATE` | `0.1` |
| `profiles_sample_rate` | `SENTRY_PROFILES_SAMPLE_RATE` | `0.0` |
| `send_default_pii` | — | `false` |
| `ignore_transactions` | — | `['/up']` |
| `breadcrumbs.*`, `tracing.*` | — | various on/off toggles |

### `services.php` (third-party + internal tuning)
| Key | Env var | Default |
|---|---|---|
| `postmark.key` | `POSTMARK_API_KEY` | — |
| `mailgun.domain` | `MAILGUN_DOMAIN` | — |
| `mailgun.secret` | `MAILGUN_SECRET` | — |
| `mailgun.endpoint` | `MAILGUN_ENDPOINT` | `api.mailgun.net` |
| `mailgun.scheme` | `MAILGUN_SCHEME` | `https` |
| `sendgrid.username` | `SENDGRID_USERNAME` | `apikey` |
| `sendgrid.password` | `SENDGRID_PASSWORD` | — |
| `sendgrid.host` | `SENDGRID_HOST` | `smtp.sendgrid.net` |
| `sendgrid.port` | `SENDGRID_PORT` | `587` |
| `sendgrid.scheme` | `SENDGRID_SCHEME` | `tls` |
| `resend.key` | `RESEND_API_KEY` | — |
| `ses.key` | `AWS_ACCESS_KEY_ID` | — |
| `ses.secret` | `AWS_SECRET_ACCESS_KEY` | — |
| `ses.region` | `AWS_DEFAULT_REGION` | `us-east-1` |
| `slack.notifications.bot_user_oauth_token` | `SLACK_BOT_USER_OAUTH_TOKEN` | — |
| `slack.notifications.channel` | `SLACK_BOT_USER_DEFAULT_CHANNEL` | — |
| `budget.threshold` | `BUDGET_THRESHOLD` | `50.00` |
| `analytics.sla_target_minutes` | `SLA_TARGET_MINUTES` | `480` |
| `analytics.pdf_export_limit` | `ANALYTICS_PDF_EXPORT_LIMIT` | `2000` |

Laravel sorts config files by name and merges them with `array_set()`, so
`services.php` loads first and `services.custom.php` replaces the whole
`services.custom` tree. Nothing under `custom` may be declared here — the rows this
table used to list (`custom.auth.token_expiry_days`, `custom.upload.*`) were dead and
`AUTH_TOKEN_EXPIRY_DAYS`/`UPLOAD_*` silently did nothing. They now live in
`services.custom.php` below.

### `services.custom.php` (env-specific overrides)
| Key | Env var | Default |
|---|---|---|
| `auth.max_attempts` | `AUTH_MAX_ATTEMPTS` | `5` |
| `auth.lockout_minutes` | `AUTH_LOCKOUT_MINUTES` | `15` |
| `auth.token_expiry_days` | `AUTH_TOKEN_EXPIRY_DAYS` | `30` — read by `CustomAuthMiddleware::isTokenExpired()` |
| `budget.threshold` | `BUDGET_THRESHOLD` | `50.00` |
| `analytics.system_availability` | `SYSTEM_AVAILABILITY` | `99.9` — the only availability key; SystemSettingsService saves the Settings field here and AnalyticsDashboardService reads it back |
| `ai.model` | `AI_MODEL` | `gpt-4o-mini` — the default also applies when the variable is present but empty, which is how `.env.example` ships it |
| `ai.temperature` | `AI_TEMPERATURE` | `0.1` |
| `upload.max_photo_size_kb` | `UPLOAD_MAX_PHOTO_SIZE_KB` | `2048` |
| `upload.max_photo_width` | `UPLOAD_MAX_PHOTO_WIDTH` | `4096` |
| `upload.max_photo_height` | `UPLOAD_MAX_PHOTO_HEIGHT` | `4096` |
| `upload.allowed_photo_mimes` | `UPLOAD_ALLOWED_PHOTO_MIMES` | `jpeg,png,jpg,gif,webp` |
| `pagination.default_per_page` | `PAGINATION_PER_PAGE` | `15` |
| `pagination.admin_per_page` | `ADMIN_PAGINATION_PER_PAGE` | `50` |
| `tokens.length` | `API_TOKEN_LENGTH` | `60` |
| `notification.mailer` | `NOTIFICATION_MAILER` | `mailgun_fallback` |

`tests/Unit/Config/ServiceConfigTest.php` locks both invariants: `services.php` must not
declare a `custom` key, and every key the application reads must resolve with the right
type.

## Notes for developers / AI

- `locales.php` contains language display names in their native scripts (user-facing, i18n domain) — these are intentionally in various languages.
- `l5-swagger.php` title includes the app name `Gestão de Avarias` — user-facing, pending i18n migration.
- `backup.php` is a custom config file (not Laravel default) for the `DatabaseBackup` artisan command.
- `services.custom.php` defines `services.custom.*` and replaces any `custom` tree that `services.php` might declare — keep the tunables in one file only.
- Config values use `env()` helpers with sensible defaults for development.

## Related Folders

| Path | Relationship |
|---|---|
| `app/Console/Commands/` | Commands that read backup and telemetry config |
| `app/Providers/` | Service providers that consume config values |
| `app/Services/` | Services reading OpenAI, mail, and locale config |
| `routes/console.php` | Scheduler referencing backup and telemetry commands |
