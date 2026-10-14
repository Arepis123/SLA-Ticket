<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Tracks how far a user has read a ticket's work log, so entries added since
 * then can be badged on the tab they would otherwise sit unnoticed behind.
 *
 * @property int $id
 * @property int $ticket_id
 * @property int $user_id
 * @property Carbon $last_read_at
 * @property-read Ticket $ticket
 * @property-read User $user
 */
#[Fillable(['ticket_id', 'user_id', 'last_read_at'])]
class TicketWorkLogRead extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_read_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
