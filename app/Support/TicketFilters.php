<?php

namespace App\Support;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The ticket list's filters and sort order, shared by the tickets page and the
 * mobile API so both narrow the list down the same way.
 */
final readonly class TicketFilters
{
    /** @var list<string> */
    public const SORTABLE = ['reference', 'title', 'priority', 'status', 'resolution_due_at', 'created_at', 'updated_at'];

    public function __construct(
        public string $search = '',
        public string $status = '',
        public string $priority = '',
        public string $project = '',
        public string $assignee = '',
        public string $sla = '',
        public string $sort = 'created_at',
        public string $direction = 'desc',
        public string $company = '',
    ) {}

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public function apply(Builder $query, User $user): Builder
    {
        return $query
            ->visibleTo($user)
            ->when($this->search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('reference', 'like', "%{$this->search}%")
                ->orWhere('title', 'like', "%{$this->search}%")))
            ->when($this->status === 'unresolved', fn (Builder $query) => $query->unresolved())
            ->when(TicketStatus::tryFrom($this->status), fn (Builder $query, TicketStatus $status) => $query->where('status', $status))
            ->when(TicketPriority::tryFrom($this->priority), fn (Builder $query, TicketPriority $priority) => $query->where('priority', $priority))
            ->when($this->company !== '' && $user->isStaff(), fn (Builder $query) => $query->where('company_id', (int) $this->company))
            ->when($this->project !== '', fn (Builder $query) => $query->where('project_id', (int) $this->project))
            ->when($user->isStaff() && $this->assignee !== '', fn (Builder $query) => match ($this->assignee) {
                'me' => $user->isInternal() ? $query->where('assignee_id', $user->id) : $query,
                'none' => $query->whereNull('assignee_id'),
                default => $query->where('assignee_id', (int) $this->assignee),
            })
            ->when($this->sla === 'breached', fn (Builder $query) => $query->whereNull('merged_into_id')->where(fn (Builder $query) => $query
                ->whereNotNull('resolution_breached_at')
                ->orWhereNotNull('response_breached_at')
                ->orWhere(fn (Builder $query) => $query->running()->where('resolution_due_at', '<', now()))))
            ->when($this->sla === 'at_risk', fn (Builder $query) => $query
                ->running()
                ->whereNotNull('at_risk_notified_at')
                ->whereNull('resolution_breached_at')
                ->whereNull('response_breached_at')
                ->where('resolution_due_at', '>=', now()))
            ->when($this->sort === 'priority', fn (Builder $query) => $query->orderByRaw(
                "case priority when 'critical' then 1 when 'high' then 2 when 'medium' then 3 else 4 end ".($this->direction === 'asc' ? 'asc' : 'desc'),
            ), fn (Builder $query) => $query->orderBy(
                in_array($this->sort, self::SORTABLE, true) ? $this->sort : 'created_at',
                $this->direction === 'asc' ? 'asc' : 'desc',
            ))
            ->orderByDesc('id');
    }
}
