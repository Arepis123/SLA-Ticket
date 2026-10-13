<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Notifications\Messages\MailMessage;

class SlaAlert extends TicketNotification
{
    public function __construct(Ticket $ticket, public string $type)
    {
        parent::__construct($ticket);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = match ($this->type) {
            'response_breached' => __('SLA breached: no first response'),
            'resolution_breached' => __('SLA breached: resolution overdue'),
            default => __('SLA at risk'),
        };

        $message = $this->baseMessage($subject)
            ->line(__('Assignee: :name', ['name' => $this->ticket->assignee?->name ?? __('Unassigned')]));

        $message = $this->type === 'response_breached'
            ? $message->line(__('Response was due :date.', ['date' => $this->ticket->response_due_at->format('d M Y H:i')]))
            : $message->line(__('Resolution due :date (:countdown).', [
                'date' => $this->ticket->resolution_due_at->format('d M Y H:i'),
                'countdown' => $this->ticket->resolutionCountdown(),
            ]));

        return $message->error()->action(__('Open ticket'), $this->ticketUrl());
    }
}
