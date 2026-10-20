<?php

namespace App\Policies;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketComment;
use App\Models\TicketWorkLog;
use App\Models\User;

class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Ticket $ticket): bool
    {
        if ($user->isStaff()) {
            return true;
        }

        return $user->company_id !== null
            && $user->company_id === $ticket->company_id
            && ($user->isClientAdmin()
                || $ticket->reporter_id === $user->id
                || $ticket->merges()->where('reporter_id', $user->id)->exists());
    }

    /**
     * Merge a duplicate into another ticket, or undo that.
     */
    public function merge(User $user, Ticket $ticket): bool
    {
        return $user->isStaff();
    }

    /**
     * Only clients report bugs; staff work on them.
     */
    public function create(User $user): bool
    {
        return $user->isClient() && $user->company_id !== null;
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
     * Only staff can log work; visible to anyone who can view the ticket.
     */
    public function addWorkLog(User $user, Ticket $ticket): bool
    {
        return $user->isStaff() && $this->view($user, $ticket);
    }

    /**
     * Staff can delete their own replies and internal notes.
     */
    public function deleteComment(User $user, Ticket $ticket, TicketComment $comment): bool
    {
        return $user->isStaff() && $comment->ticket_id === $ticket->id && $comment->user_id === $user->id;
    }

    /**
     * Staff can delete their own work log entries.
     */
    public function deleteWorkLog(User $user, Ticket $ticket, TicketWorkLog $workLog): bool
    {
        return $user->isStaff() && $workLog->ticket_id === $ticket->id && $workLog->user_id === $user->id;
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
