# resources/views/components/ui/page-actions

> **New to programming?** See [NON_TECHNICAL_PROJECT_GUIDE.md](../../../../../NON_TECHNICAL_PROJECT_GUIDE.md)

Reusable button and link components for page-level actions (create, back, export, etc.).

## Files

| File | Purpose |
|---|---|
| `back-button.blade.php` | Back-navigation link with a default left-arrow SVG icon. Delegates to `base-link`. |
| `base-button.blade.php` | Core `<button>` component. Supports variant, size, weight, icon and type props. |
| `base-link.blade.php` | Core `<a>` component. Same props as base-button but renders an anchor. |
| `create-link.blade.php` | "Add/create" link with a default plus SVG icon. Delegates to `base-link`. |
| `export-link.blade.php` | Download/export link with a default download SVG icon. Delegates to `base-link`. |
| `group.blade.php` | Flex container for grouping multiple action buttons/links. |

## Notes for developers / AI

- `base-button` and `base-link` are the foundational building blocks; all other components delegate to one of them.
- Variant classes are resolved via `match` expressions — add new variants in both base components simultaneously to stay in sync.
- Icons are passed as raw HTML strings (use `{!! !!}` rendering). Sanitize any user-provided icon content.

## Recent Refactorings

- `base-button.blade.php` + `base-link.blade.php` (kept in sync): `success`/`danger`/`warning` variants migrated from base-palette solids (`emerald-600`, `rose-500`, `amber-500`) and `neutral` from a buggy red-hover to the design **ghost state-token pattern** — `border-<state>/30 bg-<state>/10 text-<state> hover:bg-<state>/20` (token colors auto-adapt in dark mode with AA light-mode values). `neutral` hover now uses `hover:bg-[var(--surface-hover,var(--surface))]` (previously incorrectly turned red on hover). `primary`/`accent`/`secondary`/`dark` unchanged.
- `back-button`, `create-link`, `export-link`, `group`: no markup changes needed — thin token/component wrappers.
