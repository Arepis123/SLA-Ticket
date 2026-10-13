<?php

namespace App\Notifications;

use App\Models\TicketComment;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

class NewTicketComment extends TicketNotification
{
    public function __construct(public TicketComment $comment)
    {
        parent::__construct($comment->ticket);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = $this->comment->is_internal ? __('New internal note') : __('New reply on your ticket');

        return $this->baseMessage($subject)
            ->line(__(':name wrote:', ['name' => $this->comment->user->name]))
            ->line('> '.Str::limit($this->comment->body, 500))
            ->action(__('View conversation'), $this->ticketUrl());
    }
}
