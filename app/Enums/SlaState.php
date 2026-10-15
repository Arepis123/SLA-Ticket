<?php

namespace App\Enums;

enum SlaState: string
{
    case OnTrack = 'on_track';
    case AtRisk = 'at_risk';
    case Breached = 'breached';
    case Paused = 'paused';
    case Met = 'met';
    case Merged = 'merged';

    public function label(): string
    {
        return match ($this) {
            self::OnTrack => __('On track'),
            self::AtRisk => __('At risk'),
            self::Breached => __('Breached'),
            self::Paused => __('Paused'),
            self::Met => __('Met'),
            self::Merged => __('Merged'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::OnTrack => 'green',
            self::AtRisk => 'amber',
            self::Breached => 'red',
            self::Paused => 'zinc',
            self::Met => 'emerald',
            self::Merged => 'zinc',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::OnTrack => 'clock',
            self::AtRisk => 'exclamation-triangle',
            self::Breached => 'x-circle',
            self::Paused => 'pause-circle',
            self::Met => 'check-circle',
            self::Merged => 'arrows-pointing-in',
        };
    }
}
