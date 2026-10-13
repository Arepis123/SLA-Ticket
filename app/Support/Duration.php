<?php

namespace App\Support;

class Duration
{
    /**
     * Short human label for a number of minutes, e.g. "45m", "3h 20m" or "2d 4h".
     */
    public static function human(float $minutes): string
    {
        $minutes = (int) round($minutes);

        return match (true) {
            $minutes < 60 => "{$minutes}m",
            $minutes < 1440 => sprintf('%dh %dm', intdiv($minutes, 60), $minutes % 60),
            default => sprintf('%dd %dh', intdiv($minutes, 1440), intdiv($minutes % 1440, 60)),
        };
    }
}
