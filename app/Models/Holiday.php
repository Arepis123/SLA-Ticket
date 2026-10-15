<?php

namespace App\Models;

use App\Support\BusinessCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A day the office is closed, so the business-hours SLA clock does not run.
 *
 * @property int $id
 * @property CarbonImmutable $date
 * @property string $name
 */
#[Fillable(['date', 'name'])]
class Holiday extends Model
{
    protected static function booted(): void
    {
        static::saved(fn () => BusinessCalendar::flush());
        static::deleted(fn () => BusinessCalendar::flush());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }
}
