<?php

namespace App\Models;

use App\Support\BusinessCalendar;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * The opening window for one day of the week. Days without a row are closed.
 *
 * @property int $id
 * @property int $weekday ISO weekday, 1 = Monday ... 7 = Sunday
 * @property string $opens_at
 * @property string $closes_at
 */
#[Fillable(['weekday', 'opens_at', 'closes_at'])]
class BusinessHour extends Model
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
            'weekday' => 'integer',
        ];
    }
}
