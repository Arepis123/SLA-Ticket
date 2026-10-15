<?php

namespace App\Models;

use App\Enums\SlaState;
use App\Enums\TicketCategory;
use App\Enums\TicketEnvironment;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Support\BusinessCalendar;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string|null $reference
 * @property int $company_id
 * @property int $project_id
 * @property int $reporter_id
 * @property int|null $assignee_id
 * @property int|null $merged_into_id
 * @property string $title
 * @property string $description
 * @property string|null $steps_to_reproduce
 * @property TicketEnvironment $environment
 * @property TicketCategory $category
 * @property TicketPriority $priority
 * @property TicketStatus $status
 * @property CarbonImmutable|null $response_due_at
 * @property CarbonImmutable|null $resolution_due_at
 * @property CarbonImmutable|null $first_responded_at
 * @property CarbonImmutable|null $resolved_at
 * @property CarbonImmutable|null $closed_at
 * @property CarbonImmutable|null $paused_at
 * @property int $paused_minutes
 * @property int $paused_business_minutes
 * @property CarbonImmutable|null $response_breached_at
 * @property CarbonImmutable|null $resolution_breached_at
 * @property CarbonImmutable|null $at_risk_notified_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Company $company
 * @property-read Project $project
 * @property-read User $reporter
 * @property-read User|null $assignee
 * @property-read Ticket|null $mergedInto
 */
#[Fillable([
    'company_id', 'project_id', 'reporter_id', 'assignee_id', 'title', 'description',
    'steps_to_reproduce', 'environment', 'category', 'priority', 'status',
])]
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'open',
        'paused_minutes' => 0,
        'paused_business_minutes' => 0,
    ];

    protected static function booted(): void
    {
        static::creating(function (Ticket $ticket) {
            $ticket->company_id ??= Project::query()->whereKey($ticket->project_id)->value('company_id');
            $ticket->created_at ??= $ticket->freshTimestamp();
            $ticket->recalculateDueDates();
        });

        static::created(function (Ticket $ticket) {
            $ticket->reference = sprintf('%s-%s-%05d', config('sla.reference_prefix'), $ticket->created_at->format('Y'), $ticket->id);
            $ticket->saveQuietly();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'environment' => TicketEnvironment::class,
            'category' => TicketCategory::class,
            'priority' => TicketPriority::class,
            'status' => TicketStatus::class,
            'response_due_at' => 'datetime',
            'resolution_due_at' => 'datetime',
            'first_responded_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'paused_at' => 'datetime',
            'paused_minutes' => 'integer',
            'paused_business_minutes' => 'integer',
            'response_breached_at' => 'datetime',
            'resolution_breached_at' => 'datetime',
            'at_risk_notified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * The ticket this duplicate was merged into.
     *
     * @return BelongsTo<Ticket, $this>
     */
    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'merged_into_id');
    }

    /**
     * Duplicate reports merged into this ticket.
     *
     * @return HasMany<Ticket, $this>
     */
    public function merges(): HasMany
    {
        return $this->hasMany(Ticket::class, 'merged_into_id');
    }

    public function isMerged(): bool
    {
        return $this->merged_into_id !== null;
    }

    /**
     * @return HasMany<TicketComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class);
    }

    /**
     * @return HasMany<TicketAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class);
    }

    /**
     * @return HasMany<TicketActivity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(TicketActivity::class);
    }

    /**
     * @return HasMany<TicketWorkLog, $this>
     */
    public function workLogs(): HasMany
    {
        return $this->hasMany(TicketWorkLog::class);
    }

    /**
     * @return HasMany<TicketWorkLogRead, $this>
     */
    public function workLogReads(): HasMany
    {
        return $this->hasMany(TicketWorkLogRead::class);
    }

    /**
     * Work log entries written by someone else since the given user last opened
     * the work log tab. Their own entries never count as unread.
     */
    public function unreadWorkLogCountFor(User $user): int
    {
        $lastReadAt = $this->workLogReads()
            ->where('user_id', $user->id)
            ->value('last_read_at');

        return $this->workLogs()
            ->where('user_id', '!=', $user->id)
            ->when($lastReadAt, fn (Builder $query) => $query->where('created_at', '>', $lastReadAt))
            ->count();
    }

    /**
     * Remember that the given user has now seen the work log up to this moment.
     */
    public function markWorkLogReadBy(User $user): void
    {
        $this->workLogReads()->updateOrCreate(
            ['user_id' => $user->id],
            ['last_read_at' => now()],
        );
    }

    /**
     * Limit tickets to the ones the given user may see: staff see everything,
     * client admins their whole company, other clients their own reports plus
     * any ticket one of their reports was merged into.
     *
     * @param  Builder<Ticket>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if ($user->isClient()) {
            $query->where('company_id', $user->company_id ?? 0);

            if (! $user->isClientAdmin()) {
                $query->where(fn (Builder $query) => $query
                    ->where('reporter_id', $user->id)
                    ->orWhereHas('merges', fn (Builder $query) => $query->where('reporter_id', $user->id)));
            }
        }
    }

    /**
     * Tickets whose SLA clock is currently running.
     *
     * @param  Builder<Ticket>  $query
     */
    #[Scope]
    protected function running(Builder $query): void
    {
        $query->whereIn('status', TicketStatus::active());
    }

    /**
     * Tickets that are not resolved or closed.
     *
     * @param  Builder<Ticket>  $query
     */
    #[Scope]
    protected function unresolved(Builder $query): void
    {
        $query->whereIn('status', TicketStatus::unresolved());
    }

    /**
     * Recompute due dates from the creation time, the SLA policy for the
     * current priority and the total time the clock has been paused.
     */
    public function recalculateDueDates(): void
    {
        $targets = SlaPolicy::targetsFor($this->priority);
        $created = CarbonImmutable::parse($this->created_at);

        if ($this->usesBusinessHours()) {
            $calendar = app(BusinessCalendar::class);

            $this->response_due_at = $calendar->addMinutes($created, $this->paused_business_minutes + $targets['response']);
            $this->resolution_due_at = $calendar->addMinutes($created, $this->paused_business_minutes + $targets['resolution']);

            return;
        }

        $start = $created->addMinutes($this->paused_minutes);

        $this->response_due_at = $start->addMinutes($targets['response']);
        $this->resolution_due_at = $start->addMinutes($targets['resolution']);
    }

    /**
     * Whether this ticket's SLA clock only runs during business hours.
     */
    public function usesBusinessHours(): bool
    {
        return SlaPolicy::usesBusinessHours($this->priority);
    }

    /**
     * Whether the clock is standing still right now only because the office is closed.
     */
    public function isOutsideBusinessHours(): bool
    {
        return $this->status->isActive()
            && $this->usesBusinessHours()
            && ! app(BusinessCalendar::class)->isOpenAt(now());
    }

    public function isPaused(): bool
    {
        return $this->paused_at !== null;
    }

    /**
     * The point in time the resolution clock is measured against.
     */
    protected function clockNow(): CarbonInterface
    {
        return $this->paused_at ?? $this->resolved_at ?? now();
    }

    /**
     * Percentage of the resolution window that has been used, measured on the
     * ticket's own clock (24/7 or business hours) with paused time left out.
     */
    public function resolutionProgress(): float
    {
        if ($this->usesBusinessHours()) {
            $calendar = app(BusinessCalendar::class);
            $paused = $this->paused_business_minutes * 60;
            $window = $calendar->secondsBetween($this->created_at, $this->resolution_due_at) - $paused;
            $elapsed = $calendar->secondsBetween($this->created_at, $this->clockNow()) - $paused;
        } else {
            $start = $this->created_at->addMinutes($this->paused_minutes);
            $window = $start->diffInSeconds($this->resolution_due_at);
            $elapsed = $start->diffInSeconds($this->clockNow(), false);
        }

        if ($window <= 0) {
            return 100.0;
        }

        return round(max(0, $elapsed) / $window * 100, 1);
    }

    public function isResponseBreached(): bool
    {
        $respondedOrNow = $this->first_responded_at ?? $this->clockNow();

        return $respondedOrNow->greaterThan($this->response_due_at);
    }

    public function isResolutionBreached(): bool
    {
        return $this->clockNow()->greaterThan($this->resolution_due_at);
    }

    public function slaState(): SlaState
    {
        if ($this->isMerged()) {
            return SlaState::Merged;
        }

        if ($this->status->isFinished()) {
            return $this->isResolutionBreached() ? SlaState::Breached : SlaState::Met;
        }

        if ($this->isResolutionBreached() || $this->isResponseBreached()) {
            return SlaState::Breached;
        }

        if ($this->isPaused()) {
            return SlaState::Paused;
        }

        return $this->resolutionProgress() >= config('sla.at_risk_threshold')
            ? SlaState::AtRisk
            : SlaState::OnTrack;
    }

    /**
     * Short "time left" / "overdue by" label for the resolution target.
     */
    public function resolutionCountdown(): string
    {
        if ($this->isMerged()) {
            // The foreign key nulls itself if the primary is deleted, so a merged ticket always has one.
            return __('Merged into :reference', ['reference' => $this->mergedInto->reference]);
        }

        if ($this->status->isFinished()) {
            return __('Resolved in :time', ['time' => $this->created_at->addMinutes($this->paused_minutes)
                ->diffForHumans($this->resolved_at ?? $this->closed_at, syntax: CarbonInterface::DIFF_ABSOLUTE, parts: 2)]);
        }

        $now = $this->clockNow();

        return $now->greaterThan($this->resolution_due_at)
            ? __('Overdue by :time', ['time' => $this->resolution_due_at->diffForHumans($now, syntax: CarbonInterface::DIFF_ABSOLUTE, parts: 2)])
            : __(':time left', ['time' => $now->diffForHumans($this->resolution_due_at, syntax: CarbonInterface::DIFF_ABSOLUTE, parts: 2)]);
    }
}
