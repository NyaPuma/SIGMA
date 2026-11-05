# resources/views/ui/partials

> **New to programming?** See [NON_TECHNICAL_PROJECT_GUIDE.md](../../../../NON_TECHNICAL_PROJECT_GUIDE.md)

Reusable partial components included by the main layout and other views.

## Files

Only these nine partials exist, and every one of them is included by a live template:

| File | Included from | Purpose |
|---|---|---|
| `background-effects.blade.php` | `ui/layout.blade.php:197` | Background glow/gradient visual effects. |
| `desktop-sidebar.blade.php` | `ui/layout.blade.php:203` | Desktop sidebar with branding, nav links and auth box. |
| `locale-config.blade.php` | `ui/layout.blade.php:20`, `layouts/layout.blade.php:23`, `components/ui/auth/shell.blade.php:37`, `ui/tickets/public/{create,success}` | Locale configuration exposed to JavaScript (intl formatting). |
| `locale-trigger.blade.php` | `layouts/layout.blade.php:45`, `components/ui/auth/shell.blade.php:48`, `ui/tickets/public/{create,success}` | Locale trigger button for opening the localization modal. |
| `localization-modal.blade.php` | `ui/layout.blade.php:219`, `layouts/layout.blade.php:57`, `components/ui/auth/shell.blade.php:111`, `ui/tickets/public/create.blade.php:153` | Unified localization modal with tabs for language, currency, date, time and decimal format. |
| `mobile-nav.blade.php` | `ui/layout.blade.php:200` | Mobile navigation drawer with overlay, branding, links and auth box. |
| `notifications-modal.blade.php` | `ui/layout.blade.php:220` | Notifications modal with backdrop blur (same pattern as the localization modal). |
| `theme-meta.blade.php` | `ui/layout.blade.php:18`, `layouts/layout.blade.php:21`, `components/ui/auth/shell.blade.php:35`, `ui/tickets/public/create.blade.php:16` | Theme meta tags providing light/dark mode and active theme ID to JavaScript. |
| `topbar.blade.php` | `ui/layout.blade.php:207` | Top navigation bar. |

## Notes for developers / AI

- `theme-meta.blade.php` loads the active theme preset via `ThemePresetService` and sets `<meta>` tags consumed by `early-theme.js` and `core/theme.js`. The inline `<script type="application/json" id="themePresetsData">` is a data-only bridge (no logic) consumed by `core/theme.js` and `pages/definicoes-aparencia.js` — documented exception.
- `locale-config.blade.php` generates a large inline JS configuration block with auth labels, locale metadata and translation strings.
- Topbar locale triggers use `data-action="open-locale-modal"` with `data-tab="language|currency|date|time|number"`; handled by `resources/js/components/localization-modal.js` (reads `btn.dataset.tab`). No inline `onclick="openLocalizationModal(...)"` handlers.
- The modal markup is rendered server-side; the item selector modal is the exception — `resources/js/components/item-selector-modal.js` builds its own overlay in the DOM, so no Blade partial is needed for it.

## Recent Refactorings

- Removed ten orphaned partials that the unified `localization-modal.blade.php` had superseded and that no `@include`, `view()` call or test referenced: `currency-dropdown`, `currency-modal`, `date-format-dropdown`, `date-format-modal`, `item-selector-modal`, `language-dropdown`, `language-modal`, `locale-modal`, `number-format-dropdown` and `preferences-dropdowns-js` (the last one rendered nothing and said so in its own header comment).
- `topbar.blade.php`: five inline `onclick="openLocalizationModal('…')"` handlers replaced with `data-action="open-locale-modal" data-tab="…"`.
- `localization-modal.js`: open-trigger binding now passes `btn.dataset.tab` to `openModal()` so the clicked button opens the matching preference tab.
