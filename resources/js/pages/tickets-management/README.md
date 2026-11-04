# resources/js/pages/tickets-management

> **New to programming?** See [NON_TECHNICAL_PROJECT_GUIDE.md](../../../../NON_TECHNICAL_PROJECT_GUIDE.md)

Ticket listing and management page.

## Deep-linked filters

`dom.js::applyFiltersFromUrl()` runs first in `tickets-management.js::init()` and copies
`q`, `status`, `priority`, `date_from` and `date_to` from the page URL into the filter
inputs. Without it, the links built by `ticket-detail/priority-modal.js`
(`/ui/tickets?priority=crítica`) opened an unfiltered list, so the "ver tickets
prioritários" action lost the context the user had just clicked.

The parameter→input-id pairs live in the `filterIdsByParam` map at the top of `dom.js`, and
a parameter with no matching input is skipped, so the function stays safe when the filter
form changes.
