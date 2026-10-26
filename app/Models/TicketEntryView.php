<?php

namespace App\Models;

use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The first time a user had a timeline or work log entry on screen long enough
 * to count as seen. Only System Admins can see these.
 *
 * @property int $id
 * @property int $ticket_id
 * @property int $user_id
 * @property string $entry_type
 * @property int $entry_id
 * @property TicketStatus $ticket_status
 * @property Carbon $seen_at
 * @property-read Ticket $ticket
 * @property-read User $user
 */
#[Fillable(['ticket_id', 'user_id', 'entry_type', 'entry_id', 'ticket_status', 'seen_at'])]
class TicketEntryView extends Model
{
    public const TYPES = [
        'comment' => TicketComment::class,
        'activity' => TicketActivity::class,
        'work_log' => TicketWorkLog::class,
    ];

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ticket_status' => TicketStatus::class,
            'seen_at' => 'datetime',
        ];
    }

    /**
     * The key the page and this table use for an entry, e.g. "work_log-12".
     */
    public static function keyFor(TicketComment|TicketActivity|TicketWorkLog $entry): string
    {
        return array_search($entry::class, self::TYPES, true).'-'.$entry->id;
    }

    /**
     * @param  Builder<TicketEntryView>  $query
     */
    #[Scope]
    protected function forEntry(Builder $query, TicketComment|TicketActivity|TicketWorkLog $entry): void
    {
        $query->where('entry_type', array_search($entry::class, self::TYPES, true))->where('entry_id', $entry->id);
    }

    public function key(): string
    {
        return $this->entry_type.'-'.$this->entry_id;
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
