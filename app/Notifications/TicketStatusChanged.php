<?php

namespace App\Notifications;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use Illuminate\Notifications\Messages\MailMessage;

class TicketStatusChanged extends TicketNotification
{
    public function __construct(Ticket $ticket, public TicketStatus $from)
    {
        parent::__construct($ticket);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = $this->baseMessage(__('Ticket status updated'))
            ->line(__('Status changed from :from to :to.', [
                'from' => $this->from->label(),
                'to' => $this->ticket->status->label(),
            ]));

        if ($this->ticket->status === TicketStatus::Resolved && $notifiable->isClient()) {
            $message->line(__('Please verify the fix and confirm or reopen the ticket.'));
        }

        if ($this->ticket->status === TicketStatus::PendingClient && $notifiable->isClient()) {
            $message->line(__('We need more information from you to continue.'));
        }

        return $message->action(__('View ticket'), $this->ticketUrl());
    }
}
