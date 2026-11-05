# resources/views/ui/tickets/public

Public-facing ticket views (no login required, but the success page requires a valid URL signature).

## Files

| File | Purpose |
|---|---|
| `create.blade.php` | Public fault reporting form. Standalone page (does not extend `ui.layout`) with its own head, background effects, equipment info header, problem type select, description, photo upload, reporter fields and submit button. |
| `success.blade.php` | Ticket submission success confirmation page, reachable only through a signed URL. Shows reference number, equipment, priority and status details. |

## Notes for developers / AI

- Both files are standalone HTML documents (not extending `ui.layout`) — they include their own Vite assets, theme meta, locale config and locale trigger.
- `create.blade.php` receives an `$equipment` model and renders its name, room, asset tag and category.
- `GET /ticket/success/{ticket}` carries the `signed` middleware (`routes/web.php`), so the page is authentication-free but signature-required: an unsigned (or tampered) link is rejected with 403. Without the signature, sequential ticket ids would let any anonymous visitor read other people's reports.

## Recent Refactorings

- `create.blade.php`:
  - Inline photo-filename `<script>` moved to `resources/js/pages/ticket-public.js` (`initPublicTicketForm()`), registered as a global init in `resources/js/bootstrap/page-registry.js`. Default label now read from the span's `data-placeholder` attribute (i18n key rendered server-side).
  - Error banners/messages: `rose` → `danger`; background glow `bg-blue-500/10` → `bg-info/10`.
- `success.blade.php`: background glows → `bg-success/10` / `bg-info/10`; success icon badge → `border-success/25 bg-success/10`.
