<?php

namespace App\Notifications;

use App\Enums\EmailNotification;
use App\Models\Ticket;
use Illuminate\Notifications\Messages\MailMessage;
use NotificationChannels\WebPush\WebPushMessage;

class SlaAlert extends TicketNotification
{
    public function __construct(Ticket $ticket, public string $type)
    {
        parent::__construct($ticket);
    }

    public function sendsToPhone(): bool
    {
        return true;
    }

    public function emailType(): EmailNotification
    {
        return EmailNotification::SlaAlert;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->baseMessage($this->subject())
            ->line(__('Assignee: :name', ['name' => $this->ticket->assignee?->name ?? __('Unassigned')]))
            ->line($this->dueLine())
            ->error()
            ->action(__('Open ticket'), $this->ticketUrl());
    }

    public function toWebPush(object $notifiable): WebPushMessage
    {
        return $this->basePush($this->subject(), $this->ticket->title.' · '.$this->dueLine())->requireInteraction();
    }

    protected function subject(): string
    {
        return match ($this->type) {
            'response_breached' => __('SLA breached: no first response'),
            'resolution_breached' => __('SLA breached: resolution overdue'),
            default => __('SLA at risk'),
        };
    }

    protected function dueLine(): string
    {
        return $this->type === 'response_breached'
            ? __('Response was due :date.', ['date' => $this->ticket->response_due_at->format('d M Y H:i')])
            : __('Resolution due :date (:countdown).', [
                'date' => $this->ticket->resolution_due_at->format('d M Y H:i'),
                'countdown' => $this->ticket->resolutionCountdown(),
            ]);
    }
}
