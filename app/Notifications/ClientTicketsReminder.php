<?php

namespace App\Notifications;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Twice-daily digest of the tickets waiting on a client: ones we need more
 * information on, and resolved ones they still need to confirm.
 */
class ClientTicketsReminder extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Collection<int, Ticket>  $tickets
     */
    public function __construct(public Collection $tickets) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        [$pending, $resolved] = $this->tickets->partition(fn (Ticket $ticket) => $ticket->status === TicketStatus::PendingClient);

        $message = (new MailMessage)
            ->subject(trans_choice('{1} 1 ticket is waiting for you|[2,*] :count tickets are waiting for you', $this->tickets->count()))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]));

        if ($pending->isNotEmpty()) {
            $message->line(__('**Waiting for your reply** — our team needs more information to continue:'));
            $pending->each(fn (Ticket $ticket) => $message->line($this->ticketLine($ticket)));
        }

        if ($resolved->isNotEmpty()) {
            $message->line(__('**Resolved** — please check the fix and confirm, or reopen the ticket if the problem remains:'));
            $resolved->each(fn (Ticket $ticket) => $message->line($this->ticketLine($ticket)));

            if (($days = config('sla.auto_close_days')) > 0) {
                $message->line(trans_choice('{1} Resolved tickets close automatically 1 day after they are resolved.|[2,*] Resolved tickets close automatically :count days after they are resolved.', $days));
            }
        }

        return $message->action(__('View my tickets'), route('tickets.index'));
    }

    protected function ticketLine(Ticket $ticket): string
    {
        return "[{$ticket->reference}](".route('tickets.show', $ticket).") — {$ticket->title} ({$ticket->project->name})";
    }
}
