<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

final class TicketPolicy
{
    /**
     * Determines whether the user can list tickets.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determines whether the user can view a specific ticket.
     */
    public function view(User $user, Ticket $ticket): bool
    {
        return $this->canAccessTicket($user, $ticket);
    }

    /**
     * Determines whether the user can create new tickets.
     *
     * Technicians resolve and close tickets; they do not open new ones.
     * Only admins and regular users create tickets.
     */
    public function create(User $user): bool
    {
        return ! $user->isTechnician();
    }

    /**
     * Determines whether the user can update a ticket.
     */
    public function update(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin() || $user->isTechnician();
    }

    /**
     * Determines whether the user can delete a ticket.
     */
    public function delete(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determines whether the user can comment on a ticket.
     */
    public function comment(User $user, Ticket $ticket): bool
    {
        return $this->canAccessTicket($user, $ticket);
    }

    /**
     * Determines whether the user can attach photos to a ticket.
     */
    public function attachPhoto(User $user, Ticket $ticket): bool
    {
        return $this->canAccessTicket($user, $ticket);
    }

    /**
     * Determines whether the user can remove photos from a ticket.
     */
    public function deletePhoto(User $user, Ticket $ticket): bool
    {
        return $this->canAccessTicket($user, $ticket);
    }

    /**
     * Determines whether the user can cancel their own ticket.
     */
    public function cancel(User $user, Ticket $ticket): bool
    {
        if (! $user->isCommonUser()) {
            return false;
        }

        return (int) $ticket->user_id === (int) $user->id;
    }

    /**
     * Determines whether the technician can start working on the ticket.
     *
     * An unassigned ticket is claimed by whoever starts it (see StartTicketAction),
     * so only that technician or the current assignee may start.
     */
    public function start(User $user, Ticket $ticket): bool
    {
        return $user->isTechnician() && $this->isAssignedOrFree($user, $ticket);
    }

    /**
     * Determines whether the technician can close the ticket.
     */
    public function close(User $user, Ticket $ticket): bool
    {
        return $user->isTechnician() && $this->isAssignedTo($user, $ticket);
    }

    /**
     * Determines whether the user can reopen the ticket.
     */
    public function reopen(User $user, Ticket $ticket): bool
    {
        return $user->isTechnician() || $user->isAdmin();
    }

    /**
     * Determines whether the user can schedule an intervention.
     */
    public function schedule(User $user, Ticket $ticket): bool
    {
        return $this->canAccessTicket($user, $ticket);
    }

    /**
     * Determines whether the technician or admin can submit a budget/quote.
     *
     * A technician may quote a ticket they own or one nobody claimed yet (submitting
     * assigns it), but never a ticket already assigned to a colleague.
     */
    public function submitBudget(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin() || ($user->isTechnician() && $this->isAssignedOrFree($user, $ticket));
    }

    /**
     * Determines whether the admin can approve the budget/quote.
     */
    public function approveBudget(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determines whether the technician can start the repair.
     */
    public function startRepair(User $user, Ticket $ticket): bool
    {
        return $user->isTechnician();
    }

    /**
     * Determines whether the assigned technician can request a budget/quote.
     */
    public function requestBudget(User $user, Ticket $ticket): bool
    {
        return $user->isTechnician() && $this->isAssignedTo($user, $ticket);
    }

    /**
     * Determines whether the admin can assign the ticket to a technician.
     */
    public function assign(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determines whether the user can access analytics.
     */
    public function viewAnalytics(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determines whether the user can export analytics data.
     */
    public function exportAnalytics(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determines whether the admin can create preventive maintenance.
     */
    public function createPreventive(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Checks whether the ticket is assigned to this exact user.
     */
    private function isAssignedTo(User $user, Ticket $ticket): bool
    {
        return $ticket->assigned_to !== null && (int) $ticket->assigned_to === (int) $user->id;
    }

    /**
     * Checks whether the ticket is assigned to the user or still free to claim.
     */
    private function isAssignedOrFree(User $user, Ticket $ticket): bool
    {
        return $ticket->assigned_to === null || $this->isAssignedTo($user, $ticket);
    }

    /**
     * Checks whether the user has general access to the ticket (Admin/Technician or Creator).
     */
    private function canAccessTicket(User $user, Ticket $ticket): bool
    {
        if ($user->isAdmin() || $user->isTechnician()) {
            return true;
        }

        return (int) $ticket->user_id === (int) $user->id;
    }
}
