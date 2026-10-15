<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A named set of ticket-list filters, kept as the query string that reproduces it.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property array<string, string> $filters
 * @property-read User $user
 */
#[Fillable(['user_id', 'name', 'filters'])]
class SavedView extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'filters' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
