<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Open = 'open';
    case Acknowledged = 'acknowledged';
    case InProgress = 'in_progress';
    case PendingClient = 'pending_client';
    case Resolved = 'resolved';
    case Closed = 'closed';
    case Reopened = 'reopened';

    public function label(): string
    {
        return match ($this) {
            self::Open => __('Open'),
            self::Acknowledged => __('Acknowledged'),
            self::InProgress => __('In Progress'),
            self::PendingClient => __('Pending Client'),
            self::Resolved => __('Resolved'),
            self::Closed => __('Closed'),
            self::Reopened => __('Reopened'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'sky',
            self::Acknowledged => 'indigo',
            self::InProgress => 'amber',
            self::PendingClient => 'orange',
            self::Resolved => 'green',
            self::Closed => 'zinc',
            self::Reopened => 'rose',
        };
    }

    /**
     * Statuses where the SLA clock is running.
     *
     * @return list<self>
     */
    public static function active(): array
    {
        return [self::Open, self::Acknowledged, self::InProgress, self::Reopened];
    }

    /**
     * Statuses that are not yet finished (includes paused).
     *
     * @return list<self>
     */
    public static function unresolved(): array
    {
        return [...self::active(), self::PendingClient];
    }

    public function isActive(): bool
    {
        return in_array($this, self::active(), true);
    }

    public function isFinished(): bool
    {
        return $this === self::Resolved || $this === self::Closed;
    }

    /**
     * Statuses staff may move a ticket to.
     *
     * @return list<self>
     */
    public static function staffSelectable(): array
    {
        return [self::Open, self::Acknowledged, self::InProgress, self::PendingClient, self::Resolved, self::Closed];
    }
}
