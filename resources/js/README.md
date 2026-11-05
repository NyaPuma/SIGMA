# resources/js

> **New to programming?** See [NON_TECHNICAL_PROJECT_GUIDE.md](../../NON_TECHNICAL_PROJECT_GUIDE.md)

Client-side JavaScript modules for the application frontend.

## Structure

| Directory | Purpose |
|-----------|---------|
| `auth/` | Authentication UI (login form, password strength, utilities) |
| `bootstrap/` | App initialization and bootstrap logic |
| `components/` | Reusable UI components (inputs, listing, modals) |
| `core/` | Core managers (auth, navigation, theme, notifications, sidebar, etc.) |
| `pages/` | Page-specific scripts organized by feature |
| `services/` | Service layer for API interactions |
| `utils/` | Utility functions (API client, locale formatting, etc.) |

## Root Files

Only `app.js` and `early-theme.js` are Vite inputs (see `vite.config.js`); the other root modules are imported by `app.js`.

| File | Purpose |
|------|---------|
| `app.js` | Entry point — pulls in the API client, Alpine, the localization modal, layout and auth login, then boots the page modules registered for the current page |
| `alpine.js` | Registers the Alpine.js data components (OTP, combobox, autocomplete, password strength) and exposes `window.Alpine` |
| `api-client.js` | Centralized axios wrapper with auth, interceptors, and error handling |
| `early-theme.js` | Preloads the saved theme to prevent flash of unstyled content (FOUC) |

## Recent Refactorings

- Root files (`alpine.js`, `api-client.js`, `app.js`, `early-theme.js`): No changes needed — all are plain infrastructure/bootstrap modules with no inline styles, no hardcoded design classes, and no typography or button markup concerns.
