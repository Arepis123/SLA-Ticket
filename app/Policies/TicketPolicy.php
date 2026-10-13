<?php

namespace App\Policies;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;

class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return $user->isStaff() || ($user->company_id !== null && $user->company_id === $ticket->company_id);
    }

    public function create(User $user): bool
    {
        return $user->isStaff() || $user->company_id !== null;
    }

    /**
     * Change status, priority and assignee.
     */
    public function manage(User $user, Ticket $ticket): bool
    {
        return $user->isStaff();
    }

    public function comment(User $user, Ticket $ticket): bool
    {
        return $this->view($user, $ticket) && ($user->isStaff() || $ticket->status !== TicketStatus::Closed);
    }

    /**
     * Client confirms the fix (closes) or rejects it (reopens).
     */
    public function respondToResolution(User $user, Ticket $ticket): bool
    {
        return $user->isClient() && $this->view($user, $ticket) && $ticket->status === TicketStatus::Resolved;
    }

    public function downloadAttachment(User $user, Ticket $ticket, TicketAttachment $attachment): bool
    {
        return $this->view($user, $ticket)
            && $attachment->ticket_id === $ticket->id
            && ($user->isStaff() || ! $attachment->comment?->is_internal);
    }
}
