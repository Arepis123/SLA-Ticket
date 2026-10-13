<?php

namespace App\Enums;

enum TicketCategory: string
{
    case Bug = 'bug';
    case Error = 'error';
    case Performance = 'performance';
    case DataIssue = 'data_issue';
    case Access = 'access';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Bug => __('Bug'),
            self::Error => __('Error / Crash'),
            self::Performance => __('Performance'),
            self::DataIssue => __('Data Issue'),
            self::Access => __('Login / Access'),
            self::Other => __('Other'),
        };
    }
}
