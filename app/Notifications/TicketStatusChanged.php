<?php

namespace App\Notifications;

use App\Enums\EmailNotification;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use NotificationChannels\WebPush\WebPushMessage;

class TicketStatusChanged extends TicketNotification
{
    public function __construct(Ticket $ticket, public TicketStatus $from)
    {
        parent::__construct($ticket);
    }

    public function emailType(): EmailNotification
    {
        return EmailNotification::StatusChanged;
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

    public function toWebPush(object $notifiable): WebPushMessage
    {
        $body = __(':from → :to', ['from' => $this->from->label(), 'to' => $this->ticket->status->label()]);

        if ($notifiable instanceof User && $notifiable->isClient()) {
            $body .= match ($this->ticket->status) {
                TicketStatus::Resolved => ' · '.__('Please verify the fix.'),
                TicketStatus::PendingClient => ' · '.__('We need more information from you.'),
                default => '',
            };
        }

        return $this->basePush(__('Ticket status updated'), $body);
    }
}
