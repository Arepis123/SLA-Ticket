<?php

namespace App\Notifications;

use App\Enums\EmailNotification;
use App\Models\Ticket;
use Illuminate\Notifications\Messages\MailMessage;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Sent to the reporter of a duplicate when it is folded into another ticket.
 */
class TicketMerged extends TicketNotification
{
    public function __construct(Ticket $ticket, public Ticket $primary)
    {
        parent::__construct($ticket);
    }

    public function emailType(): EmailNotification
    {
        return EmailNotification::TicketMerged;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->baseMessage(__('Ticket merged'))
            ->line(__('Your report describes the same issue as :reference, so we have merged it there to work on it in one place.', [
                'reference' => $this->primary->reference,
            ]))
            ->line(__('You will receive updates on :reference from now on.', ['reference' => $this->primary->reference]))
            ->action(__('Follow :reference', ['reference' => $this->primary->reference]), route('tickets.show', $this->primary));
    }

    public function toWebPush(object $notifiable): WebPushMessage
    {
        return $this->pushMessage(
            "[{$this->ticket->reference}] ".__('Ticket merged'),
            __('Merged into :reference. Updates continue there.', ['reference' => $this->primary->reference]),
            route('tickets.show', $this->primary),
            $this->primary->reference,
        );
    }
}
