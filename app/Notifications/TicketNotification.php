<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

abstract class TicketNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Ticket $ticket)
    {
        $this->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

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

    protected function ticketUrl(): string
    {
        return route('tickets.show', $this->ticket);
    }
}
