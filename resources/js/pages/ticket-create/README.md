# resources/js/pages/ticket-create

> **New to programming?** See [NON_TECHNICAL_PROJECT_GUIDE.md](../../../../NON_TECHNICAL_PROJECT_GUIDE.md)

Ticket creation form.

- `autocomplete.js` keeps the original module name but no longer autocompletes: it turns
  `#equipmentSearchInput` into a read-only trigger that opens
  `components/item-selector-modal.js` (single equipment) and writes the id into
  `#selectedEquipmentId`. The placeholder is owned by `ui/ticket-create.blade.php` so it
  follows the locale — do not set it here.
