# Development Operations

## Local setup

Use the existing Composer setup command for a clean local environment:

```bash
composer setup
```

The Docker Compose stack is `compose.yaml` (`app`, `queue`, `schedule`, `redis`) and reads its configuration from the git-ignored `.env.production` file each service declares in `env_file`. Create it from `.env.example` before `docker compose up`, and never commit those credentials. The app listens on port 80 inside the image (Laravel Octane with FrankenPHP), which is published as `8000:80`.

## Operational checks

```bash
composer docs:generate
php artisan backup:run --help
  CACHE_STORE=array php artisan schedule:list
vendor/bin/pint --test
vendor/bin/phpstan analyse
php artisan test
```

Configure `SENTRY_LARAVEL_DSN` to enable error tracking. Configure `BACKUP_OFFSITE_ENABLED=true`, `BACKUP_OFFSITE_DISK`, `BACKUP_OFFSITE_PATH`, and the provider credentials to enable private off-site backups. Keep `SENTRY_SEND_DEFAULT_PII=false` unless a documented privacy review approves a change.

## Indentation

YAML indentation differs per file and the root `.editorconfig` describes it: `compose.yaml` uses 4 spaces for its service entries while the rest of the YAML uses 2.

## Locale

`SetLocaleMiddleware` checks the request session first, then the authenticated user's `users.locale` column, then the cookie, then `Accept-Language`. `LocaleController::switch()` writes the session value, the user column and the cookie, so a browser session keeps the selected locale across logins and devices.
