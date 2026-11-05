# resources/js/auth

> **New to programming?** See [NON_TECHNICAL_PROJECT_GUIDE.md](../../../NON_TECHNICAL_PROJECT_GUIDE.md)

Authentication-related UI scripts.

| File | Purpose |
|------|---------|
| `login.js` | Login form handler (credentials) |
| `utils.js` | Auth UI helpers (OTP inputs, select/radio enhancements, password toggle, fetch wrappers) |

## Recent Refactorings

- `login.js`: Replaced hardcoded Tailwind color classes in the status message with design-token utilities (`text-danger`/`bg-danger/5`/`border-danger/20`, `text-success`, `text-warning`), aligned the container base with the view (`mb-6 min-h-[48px]`), and made the button loading state restore the original (translated) label + RTL-aware icon instead of a hardcoded Portuguese string. Added `aria-busy` for WCAG.
- `utils.js`: No changes needed — pure fetch/DOM utilities with no markup or design concerns.
