<?php

namespace App\Models;

use App\Enums\ResolutionType;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $ticket_id
 * @property int|null $user_id
 * @property string $type
 * @property array<string, mixed>|null $properties
 * @property-read User|null $user
 */
#[Fillable(['ticket_id', 'user_id', 'type', 'properties'])]
class TicketActivity extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'properties' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Human readable description of the activity.
     */
    public function description(): string
    {
        $props = $this->properties ?? [];

        return match ($this->type) {
            'created' => __('opened this ticket'),
            'status_changed' => isset($props['resolution_type'])
                ? __('changed status from :from to :to (:fix)', [
                    'from' => TicketStatus::tryFrom($props['from'] ?? '')?->label() ?? '—',
                    'to' => TicketStatus::tryFrom($props['to'] ?? '')?->label() ?? '—',
                    'fix' => ResolutionType::tryFrom($props['resolution_type'])?->label() ?? '—',
                ])
                : __('changed status from :from to :to', [
                    'from' => TicketStatus::tryFrom($props['from'] ?? '')?->label() ?? '—',
                    'to' => TicketStatus::tryFrom($props['to'] ?? '')?->label() ?? '—',
                ]),
            'priority_changed' => __('changed priority from :from to :to', [
                'from' => TicketPriority::tryFrom($props['from'] ?? '')?->label() ?? '—',
                'to' => TicketPriority::tryFrom($props['to'] ?? '')?->label() ?? '—',
            ]),
            'assigned' => isset($props['to_name'])
                ? __('assigned this ticket to :name', ['name' => $props['to_name']])
                : __('unassigned this ticket'),
            'response_breached' => __('First response SLA breached'),
            'resolution_breached' => __('Resolution SLA breached'),
            'at_risk' => __('Resolution SLA is at risk'),
            'auto_closed' => __('Ticket closed automatically after no client response'),
            'merged_into' => __('merged this ticket into :reference', ['reference' => $props['reference'] ?? '—']),
            'merged_from' => __('merged :reference into this ticket', ['reference' => $props['reference'] ?? '—']),
            'unmerged' => __('unmerged this ticket from :reference', ['reference' => $props['reference'] ?? '—']),
            'duplicate_removed' => __('unmerged :reference from this ticket', ['reference' => $props['reference'] ?? '—']),
            default => $this->type,
        };
    }

    public function icon(): string
    {
        return match ($this->type) {
            'created' => 'plus-circle',
            'status_changed' => 'arrow-path',
            'priority_changed' => 'flag',
            'assigned' => 'user-circle',
            'response_breached', 'resolution_breached' => 'x-circle',
            'at_risk' => 'exclamation-triangle',
            'auto_closed' => 'lock-closed',
            'merged_into', 'merged_from' => 'arrows-pointing-in',
            'unmerged', 'duplicate_removed' => 'arrows-pointing-out',
            default => 'information-circle',
        };
    }

    public function isSlaEvent(): bool
    {
        return in_array($this->type, ['response_breached', 'resolution_breached', 'at_risk'], true);
    }
}
