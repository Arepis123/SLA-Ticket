<?php

namespace App\Notifications;

use App\Models\TicketWorkLog;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

class NewTicketWorkLog extends TicketNotification
{
    public function __construct(public TicketWorkLog $workLog)
    {
        parent::__construct($workLog->ticket);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->baseMessage(__('New work log entry on your ticket'))
            ->line(__(':name logged:', ['name' => $this->workLog->user->name]))
            ->line('> '.Str::limit($this->workLog->body, 500))
            ->action(__('View ticket'), $this->ticketUrl());
    }
}
