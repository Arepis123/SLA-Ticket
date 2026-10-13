<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;

/**
 * @property int $id
 * @property int $ticket_id
 * @property int|null $ticket_comment_id
 * @property int $user_id
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string|null $mime_type
 * @property int $size
 * @property-read Ticket $ticket
 * @property-read TicketComment|null $comment
 */
#[Fillable(['ticket_id', 'ticket_comment_id', 'user_id', 'disk', 'path', 'original_name', 'mime_type', 'size'])]
class TicketAttachment extends Model
{
    protected static function booted(): void
    {
        static::deleted(fn (TicketAttachment $attachment) => Storage::disk($attachment->disk)->delete($attachment->path));
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * @return BelongsTo<TicketComment, $this>
     */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(TicketComment::class, 'ticket_comment_id');
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    public function humanSize(): string
    {
        return Number::fileSize($this->size, precision: 1);
    }
}
