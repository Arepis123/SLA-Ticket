<?php

namespace App\Enums;

enum TicketEnvironment: string implements HasLabel
{
    case Production = 'production';
    case Staging = 'staging';
    case Development = 'development';

    public function label(): string
    {
        return match ($this) {
            self::Production => __('Production'),
            self::Staging => __('Staging / UAT'),
            self::Development => __('Development'),
        };
    }
}
