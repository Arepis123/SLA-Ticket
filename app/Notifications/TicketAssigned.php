<?php

namespace App\Notifications;

use App\Enums\EmailNotification;
use Illuminate\Notifications\Messages\MailMessage;
use NotificationChannels\WebPush\WebPushMessage;

class TicketAssigned extends TicketNotification
{
    public function emailType(): EmailNotification
    {
        return EmailNotification::TicketAssigned;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->baseMessage(__('A ticket was assigned to you'))
            ->line(__('Resolution due: :date', ['date' => $this->ticket->resolution_due_at->format('d M Y H:i')]))
            ->action(__('View ticket'), $this->ticketUrl());
    }

    public function toWebPush(object $notifiable): WebPushMessage
    {
        return $this->basePush(__('A ticket was assigned to you'), __(':title · Resolution due :date', [
            'title' => $this->ticket->title,
            'date' => $this->ticket->resolution_due_at->format('d M Y H:i'),
        ]));
    }
}
