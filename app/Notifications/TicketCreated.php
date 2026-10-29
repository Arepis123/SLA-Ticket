<?php

namespace App\Notifications;

use App\Enums\EmailNotification;
use Illuminate\Notifications\Messages\MailMessage;
use NotificationChannels\WebPush\WebPushMessage;

class TicketCreated extends TicketNotification
{
    public function sendsToPhone(): bool
    {
        return true;
    }

    public function emailType(): EmailNotification
    {
        return EmailNotification::TicketCreated;
    }

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

    public function toWebPush(object $notifiable): WebPushMessage
    {
        return $this->basePush(__('New ticket submitted'), __(':title — :name (:company)', [
            'title' => $this->ticket->title,
            'name' => $this->ticket->reporter->name,
            'company' => $this->ticket->company->name,
        ]));
    }
}
