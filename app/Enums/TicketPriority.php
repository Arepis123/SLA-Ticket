<?php

namespace App\Enums;

enum TicketPriority: string
{
    case Critical = 'critical';
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    public function label(): string
    {
        return match ($this) {
            self::Critical => __('Critical'),
            self::High => __('High'),
            self::Medium => __('Medium'),
            self::Low => __('Low'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Critical => __('System down or unusable, no workaround'),
            self::High => __('Major feature broken, business impacted'),
            self::Medium => __('Feature partially broken, workaround exists'),
            self::Low => __('Minor issue, cosmetic or question'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Critical => 'red',
            self::High => 'orange',
            self::Medium => 'yellow',
            self::Low => 'zinc',
        };
    }

    /**
     * Default first-response and resolution targets, in minutes.
     *
     * @return array{response: int, resolution: int}
     */
    public function defaultTargets(): array
    {
        return match ($this) {
            self::Critical => ['response' => 60, 'resolution' => 8 * 60],
            self::High => ['response' => 4 * 60, 'resolution' => 24 * 60],
            self::Medium => ['response' => 8 * 60, 'resolution' => 3 * 24 * 60],
            self::Low => ['response' => 24 * 60, 'resolution' => 7 * 24 * 60],
        };
    }
}
