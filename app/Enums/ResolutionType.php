<?php

namespace App\Enums;

/**
 * How a resolved or closed ticket was dealt with.
 */
enum ResolutionType: string
{
    case CodeChange = 'code_change';
    case DataFix = 'data_fix';
    case NotRelated = 'not_related';

    public function label(): string
    {
        return match ($this) {
            self::CodeChange => __('Code change'),
            self::DataFix => __('Data fix'),
            self::NotRelated => __('Not related'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::CodeChange => __('The fix changed application code and needs a deployment.'),
            self::DataFix => __('Only data in the database was corrected; no code was changed.'),
            self::NotRelated => __('Closed without a fix: out of scope, not a system issue, or no work was needed.'),
        };
    }

    /**
     * Choices offered when moving a ticket to $status. "Not related" only fits
     * closing, since resolving means something was fixed.
     *
     * @return list<self>
     */
    public static function for(TicketStatus $status): array
    {
        return $status === TicketStatus::Closed ? self::cases() : [self::CodeChange, self::DataFix];
    }
}
