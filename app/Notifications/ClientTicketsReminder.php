<?php

namespace App\Notifications;

use App\Enums\EmailNotification;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Twice-daily digest of the tickets waiting on a client: ones we need more
 * information on, and resolved ones they still need to confirm.
 */
class ClientTicketsReminder extends Notification implements ShouldQueue
{
    use Queueable, SendsWebPush;

    /**
     * @param  Collection<int, Ticket>  $tickets
     */
    public function __construct(public Collection $tickets) {}

    public function emailType(): EmailNotification
    {
        return EmailNotification::ClientReminder;
    }

    public function toMail(object $notifiable): MailMessage
    {
        [$pending, $resolved] = $this->partitioned();

        $message = (new MailMessage)
            ->subject($this->subject())
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

    public function toWebPush(object $notifiable): WebPushMessage
    {
        [$pending, $resolved] = $this->partitioned();

        $body = collect([
            $pending->isNotEmpty() ? trans_choice('{1} 1 needs your reply|[2,*] :count need your reply', $pending->count()) : null,
            $resolved->isNotEmpty() ? trans_choice('{1} 1 resolved, awaiting your confirmation|[2,*] :count resolved, awaiting your confirmation', $resolved->count()) : null,
        ])->filter()->implode(' · ');

        return $this->pushMessage($this->subject(), $body, route('tickets.index'), 'client-reminder');
    }

    protected function subject(): string
    {
        return trans_choice('{1} 1 ticket is waiting for you|[2,*] :count tickets are waiting for you', $this->tickets->count());
    }

    /**
     * @return array{0: Collection<int, Ticket>, 1: Collection<int, Ticket>}
     */
    protected function partitioned(): array
    {
        [$pending, $resolved] = $this->tickets->partition(fn (Ticket $ticket) => $ticket->status === TicketStatus::PendingClient);

        return [$pending, $resolved];
    }

    protected function ticketLine(Ticket $ticket): string
    {
        return "[{$ticket->reference}](".route('tickets.show', $ticket).") — {$ticket->title} ({$ticket->project->name})";
    }
}
