<?php

namespace App\Http\Resources;

use App\Enums\HasLabel;
use App\Models\Ticket;
use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A ticket as the mobile app lists it. The SLA block is worked out on the
 * server, since it depends on business hours, holidays and paused time.
 *
 * @mixin Ticket
 */
class TicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $state = $this->slaState();

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'title' => $this->title,
            'status' => $this->enum($this->status),
            'priority' => $this->enum($this->priority),
            'category' => $this->enum($this->category),
            'environment' => $this->enum($this->environment),
            'resolution_type' => $this->resolution_type ? $this->enum($this->resolution_type) : null,
            'sla' => [
                'state' => $this->enum($state),
                'progress' => $this->resolutionProgress(),
                'countdown' => $this->resolutionCountdown(),
                'response_due_at' => $this->response_due_at?->toIso8601String(),
                'resolution_due_at' => $this->resolution_due_at?->toIso8601String(),
                'response_breached' => $this->isResponseBreached(),
                'paused' => $this->isPaused(),
                'outside_business_hours' => $this->isOutsideBusinessHours(),
            ],
            'company' => $this->whenLoaded('company', fn () => ['id' => $this->company->id, 'name' => $this->company->name]),
            'project' => $this->whenLoaded('project', fn () => ['id' => $this->project->id, 'name' => $this->project->name]),
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee ? ['id' => $this->assignee->id, 'name' => $this->assignee->name] : null),
            'reporter' => $this->whenLoaded('reporter', fn () => ['id' => $this->reporter->id, 'name' => $this->reporter->name]),
            'merged_into' => $this->whenLoaded('mergedInto', fn () => $this->mergedInto?->reference),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'first_responded_at' => $this->first_responded_at?->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{value: string, label: string}
     */
    protected function enum(BackedEnum&HasLabel $enum): array
    {
        return ['value' => (string) $enum->value, 'label' => $enum->label()];
    }
}
