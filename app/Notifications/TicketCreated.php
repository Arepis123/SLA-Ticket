<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class TicketCreated extends TicketNotification
{
    public function toMail(object $notifiable): MailMessage
    {
        return $this->baseMessage(__('New ticket submitted'))
            ->line(__('Reported by :name (:company)', [
                'name' => $this->ticket->reporter->name,
                'company' => $this->ticket->company->name,
            ]))
            ->line(__('Response due: :date', ['date' => $this->ticket->response_due_at->format('d M Y H:i')]))
            ->action(__('View ticket'), $this->ticketUrl());
    }
}
