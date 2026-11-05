# resources/js/components

> **New to programming?** See [NON_TECHNICAL_PROJECT_GUIDE.md](../../../NON_TECHNICAL_PROJECT_GUIDE.md)

Reusable UI components shared across pages.

| Directory / File | Purpose |
|------------------|---------|
| `input/` | Input components (autocomplete, combobox, OTP, password strength, select) |
| `listing/` | Listing/table components (pagination, filters, feedback) |
| `localization-modal.js` | Unified localization modal with tabs for language, currency, date, time and decimal format |
| `notifications-modal.js` | Notifications modal with backdrop blur, fetching and display |

## Recent Refactorings

- `input/password-strength.js`: Swapped base palette colors (`rose-500`/`amber-500`/`emerald-500/600`) for design-token state colors (`danger`/`warning`/`success`), which auto-adapt to dark mode — removed the `dark:` variants.
- `listing/feedback.js`: Replaced `text-red-700 dark:text-red-400` and raw `text-(--color-danger)` with the `text-danger` token utility; pagination already used the `ui-button ui-button--primary` kit classes.
- `localization-modal.js`, `input/autocomplete.js`, `input/combobox.js`, `input/otp.js`, `notifications-modal.js`: No changes needed — use BEM design classes or are state-only modules without markup/colors.
