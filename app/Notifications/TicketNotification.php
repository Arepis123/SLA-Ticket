<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\WebPushMessage;

abstract class TicketNotification extends Notification implements ShouldQueue
{
    use Queueable, SendsWebPush;

    public function __construct(public Ticket $ticket)
    {
        $this->afterCommit();
    }

    abstract public function toWebPush(object $notifiable): WebPushMessage;

    /**
     * A mail message pre-filled with the ticket header and link.
     */
    protected function baseMessage(string $subject): MailMessage
    {
        return (new MailMessage)
            ->subject("[{$this->ticket->reference}] {$subject}")
            ->greeting($subject)
            ->line("**{$this->ticket->reference}** — {$this->ticket->title}")
            ->line(__('Project: :project', ['project' => $this->ticket->project->name]))
            ->line(__('Priority: :priority · Status: :status', [
                'priority' => $this->ticket->priority->label(),
                'status' => $this->ticket->status->label(),
            ]));
    }

    /**
     * A push titled like the mail subject, opening the ticket when clicked.
     */
    protected function basePush(string $subject, string $body): WebPushMessage
    {
        return $this->pushMessage("[{$this->ticket->reference}] {$subject}", $body, $this->ticketUrl(), $this->ticket->reference);
    }

    /**
     * Rich text squeezed into a line short enough for a notification popup.
     */
    protected function excerpt(string $text, int $limit = 120): string
    {
        return Str::of(html_entity_decode(strip_tags($text)))->squish()->limit($limit)->toString();
    }

    protected function ticketUrl(): string
    {
        return route('tickets.show', $this->ticket);
    }
}
