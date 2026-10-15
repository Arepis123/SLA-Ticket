<?php

namespace App\Models;

use App\Enums\TicketPriority;
use App\Support\BusinessCalendar;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property TicketPriority $priority
 * @property int $response_minutes
 * @property int $resolution_minutes
 * @property bool $business_hours
 */
#[Fillable(['priority', 'response_minutes', 'resolution_minutes', 'business_hours'])]
class SlaPolicy extends Model
{
    protected static function booted(): void
    {
        static::saved(fn () => Cache::memo()->forget('sla-policies'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => TicketPriority::class,
            'response_minutes' => 'integer',
            'resolution_minutes' => 'integer',
            'business_hours' => 'boolean',
        ];
    }

    /**
     * Response and resolution targets (minutes) for a priority, falling back
     * to the built-in defaults when no policy row exists.
     *
     * @return array{response: int, resolution: int}
     */
    public static function targetsFor(TicketPriority $priority): array
    {
        $policy = static::cached()[$priority->value] ?? null;

        return $policy ? ['response' => $policy['response'], 'resolution' => $policy['resolution']] : $priority->defaultTargets();
    }

    /**
     * Whether this priority's clock only runs during business hours. It needs opening
     * hours to be configured too; without them every priority runs 24/7.
     */
    public static function usesBusinessHours(TicketPriority $priority): bool
    {
        return (static::cached()[$priority->value]['business_hours'] ?? false)
            && app(BusinessCalendar::class)->hasHours();
    }

    /**
     * Every policy row, memoized for the request since tickets ask for it constantly.
     *
     * @return array<string, array{response: int, resolution: int, business_hours?: bool}>
     */
    protected static function cached(): array
    {
        return Cache::memo()->rememberForever('sla-policies', fn () => static::query()
            ->get()
            ->mapWithKeys(fn (SlaPolicy $policy) => [$policy->priority->value => [
                'response' => $policy->response_minutes,
                'resolution' => $policy->resolution_minutes,
                'business_hours' => $policy->business_hours,
            ]])
            ->all());
    }

    /**
     * Human readable duration, e.g. "4h", "3d", "1d 4h".
     */
    public static function formatMinutes(int $minutes): string
    {
        $days = intdiv($minutes, 1440);
        $hours = intdiv($minutes % 1440, 60);
        $mins = $minutes % 60;

        return trim(implode(' ', array_filter([
            $days ? "{$days}d" : null,
            $hours ? "{$hours}h" : null,
            $mins ? "{$mins}m" : null,
        ]))) ?: '0m';
    }
}
