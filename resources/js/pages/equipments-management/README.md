# resources/js/pages/equipments-management

> **New to programming?** See [NON_TECHNICAL_PROJECT_GUIDE.md](../../../../NON_TECHNICAL_PROJECT_GUIDE.md)

Equipment management page.

| File | Purpose |
|------|---------|
| `api.js` | API calls for equipment CRUD |
| `dom.js` | DOM element references and filter helpers |
| `render.js` | Table row rendering, status badges, pagination |
| `state.js` | Page state (current page tracking) |
| `index.js` | Page initialization and event wiring |

## Status badges in `render.js`

The tone map is a literal object (`statusTones`) with the full class strings written out —
`bg-${color}/10`-style interpolation is invisible to the Tailwind v4 scanner, so an
interpolated map silently produced unstyled badges unless the same classes happened to exist
elsewhere. The values mirror `resources/views/components/ui/text/badge.blade.php`, which is
the single source for the equipment-status palette.

Labels come from `window.SGM_EQUIPMENT_I18N` (`maintenance` / `broken` / `withdrawn`),
injected by `resources/views/ui/partials/locale-config.blade.php`; without them the JS
renderer wrote English text into an otherwise Portuguese table.
