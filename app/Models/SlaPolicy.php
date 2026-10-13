<?php

namespace App\Models;

use App\Enums\TicketPriority;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property TicketPriority $priority
 * @property int $response_minutes
 * @property int $resolution_minutes
 */
#[Fillable(['priority', 'response_minutes', 'resolution_minutes'])]
class SlaPolicy extends Model
{
    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('sla-policies'));
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
        $policies = Cache::rememberForever('sla-policies', fn () => static::query()
            ->get()
            ->mapWithKeys(fn (SlaPolicy $policy) => [$policy->priority->value => [
                'response' => $policy->response_minutes,
                'resolution' => $policy->resolution_minutes,
            ]])
            ->all());

        return $policies[$priority->value] ?? $priority->defaultTargets();
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
