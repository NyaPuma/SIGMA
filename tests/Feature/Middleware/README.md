# Middleware -- Automated Middleware Tests

> **New to programming?** See [NON_TECHNICAL_PROJECT_GUIDE.md](../../../NON_TECHNICAL_PROJECT_GUIDE.md) -- middleware are "The Security Guards" that check every request before it proceeds.

## What is this folder?

Tests for the **security checkpoints** that intercept every request. They verify that each guard correctly allows legitimate users through and blocks unauthorized access.

## What Gets Tested

| Test | What It Verifies |
|------|------------------|
| `CustomAuthMiddlewareTest` | Token authentication works (valid token allowed, invalid/missing token blocked) |
| `MiddlewareAuthTest` | Auth middleware interaction with routes |
| `RateLimitMiddlewareTest` | Rate limiting works (too many requests get blocked) |
| `RoleMiddlewareTest` | Role checks work (admin-only routes block technicians, etc.) |
| `SetLocaleMiddlewareTest` | Language detection works (session → cookie → user → browser → default) |

## How to run these tests

```bash
# All middleware tests
php artisan test tests/Feature/Middleware

# A single test
php artisan test tests/Feature/Middleware --filter=RoleMiddlewareTest
```
