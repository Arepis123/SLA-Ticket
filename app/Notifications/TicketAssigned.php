<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class TicketAssigned extends TicketNotification
{
    public function toMail(object $notifiable): MailMessage
    {
        return $this->baseMessage(__('A ticket was assigned to you'))
            ->line(__('Resolution due: :date', ['date' => $this->ticket->resolution_due_at->format('d M Y H:i')]))
            ->action(__('View ticket'), $this->ticketUrl());
    }
}
