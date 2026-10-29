<?php

namespace App\Enums;

use App\Models\User;

/**
 * The emails a user can turn on or off one by one on the notification
 * settings page. Each notification class names its type in emailType().
 */
enum EmailNotification: string
{
    case TicketCreated = 'ticket-created';
    case TicketAssigned = 'ticket-assigned';
    case StatusChanged = 'status-changed';
    case NewComment = 'new-comment';
    case WorkLogged = 'work-logged';
    case TicketMerged = 'ticket-merged';
    case SlaAlert = 'sla-alert';
    case ClientReminder = 'client-reminder';

    public function label(): string
    {
        return match ($this) {
            self::TicketCreated => __('New tickets'),
            self::TicketAssigned => __('Ticket assigned to you'),
            self::StatusChanged => __('Status changes'),
            self::NewComment => __('New comments'),
            self::WorkLogged => __('Work updates'),
            self::TicketMerged => __('Merged tickets'),
            self::SlaAlert => __('SLA alerts'),
            self::ClientReminder => __('Waiting on you reminders'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::TicketCreated => __('When a client reports a new ticket.'),
            self::TicketAssigned => __('When a ticket is assigned to you.'),
            self::StatusChanged => __('When a ticket you follow changes status.'),
            self::NewComment => __('When someone replies on a ticket you follow.'),
            self::WorkLogged => __('When the team logs work on your ticket.'),
            self::TicketMerged => __('When your ticket is merged into another one.'),
            self::SlaAlert => __('When a ticket nears or breaches its SLA.'),
            self::ClientReminder => __('A twice-daily digest of tickets that need your reply or confirmation.'),
        };
    }

    /**
     * Whether this email is ever sent to the user, so the settings page only
     * offers the ones that apply to them.
     */
    public function reaches(User $user): bool
    {
        return match ($this) {
            self::TicketCreated => $user->isAdmin(),
            self::TicketAssigned, self::SlaAlert => $user->isStaff(),
            self::StatusChanged, self::NewComment => true,
            self::WorkLogged, self::TicketMerged, self::ClientReminder => $user->isClient(),
        };
    }

    /**
     * @return array<int, self>
     */
    public static function for(User $user): array
    {
        return array_values(array_filter(self::cases(), fn (self $email) => $email->reaches($user)));
    }
}
