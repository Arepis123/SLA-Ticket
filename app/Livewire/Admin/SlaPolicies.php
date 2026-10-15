<?php

namespace App\Livewire\Admin;

use App\Enums\TicketPriority;
use App\Models\SlaPolicy;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('SLA Policies')]
class SlaPolicies extends Component
{
    /**
     * Targets in hours, keyed by priority value.
     *
     * @var array<string, array{response: float|string, resolution: float|string}>
     */
    public array $targets = [];

    /**
     * Whether each priority's clock only runs during business hours, keyed by priority value.
     *
     * @var array<string, bool>
     */
    public array $businessHours = [];

    public function mount(): void
    {
        $stored = SlaPolicy::query()->get()->mapWithKeys(fn (SlaPolicy $policy) => [$policy->priority->value => $policy->business_hours]);

        foreach (TicketPriority::cases() as $priority) {
            $this->businessHours[$priority->value] = (bool) $stored->get($priority->value, false);

            $minutes = SlaPolicy::targetsFor($priority);

            $this->targets[$priority->value] = [
                'response' => round($minutes['response'] / 60, 2),
                'resolution' => round($minutes['resolution'] / 60, 2),
            ];
        }
    }

    public function save(): void
    {
        $rules = [];

        foreach (TicketPriority::cases() as $priority) {
            $rules["targets.{$priority->value}.response"] = ['required', 'numeric', 'min:0.25', 'max:8760'];
            $rules["targets.{$priority->value}.resolution"] = ['required', 'numeric', 'min:0.25', 'max:8760', "gte:targets.{$priority->value}.response"];
        }

        $this->validate($rules, [
            'targets.*.resolution.gte' => __('Resolution target must be at least the response target.'),
        ], ['targets.*.response' => __('response target'), 'targets.*.resolution' => __('resolution target')]);

        foreach (TicketPriority::cases() as $priority) {
            SlaPolicy::updateOrCreate(['priority' => $priority], [
                'response_minutes' => (int) round($this->targets[$priority->value]['response'] * 60),
                'resolution_minutes' => (int) round($this->targets[$priority->value]['resolution'] * 60),
                'business_hours' => (bool) ($this->businessHours[$priority->value] ?? false),
            ]);
        }

        Flux::toast(variant: 'success', text: __('SLA policies saved. New targets apply to new tickets and priority changes.'));
    }
}
