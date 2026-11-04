<?php

declare(strict_types=1);

namespace App\Domain\Ticket\Concerns;

use App\Models\Ticket;

/**
 * Re-reads a ticket row under a write lock before a workflow transition.
 *
 * The actions take the model the controller already loaded. Two requests can hold copies
 * taken before either of them wrote — an admin closing while a technician starts, or two
 * technicians claiming the same ticket. Without a re-read the guards below evaluate a
 * status that is no longer true and the save drops whatever the other request committed.
 */
trait LocksTicketRow
{
    /**
     * Puts the caller's copy in sync with the committed row, locked until this transaction ends.
     */
    private function lockTicketRow(Ticket $ticket): void
    {
        $locked = Ticket::whereKey($ticket->getKey())->lockForUpdate()->firstOrFail();

        // Only the column values are replaced; the model identity stays with the caller, so the
        // response and the broadcast describe the row that was actually written.
        $ticket->setRawAttributes($locked->getAttributes(), true);

        // Both belongs-to keys can move during a transition. A relation loaded before the lock
        // would keep pointing at the previous status or technician, and the resources render the
        // cached model, so the API would report a closed ticket as still in progress.
        $ticket->unsetRelation('status');
        $ticket->unsetRelation('technician');
    }
}
