<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\SlaState;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Options for the app's filter pickers, so labels match the web app.
 */
class LookupController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'statuses' => collect(TicketStatus::cases())->map(fn (TicketStatus $status) => ['value' => $status->value, 'label' => $status->label()]),
            'priorities' => collect(TicketPriority::cases())->map(fn (TicketPriority $priority) => ['value' => $priority->value, 'label' => $priority->label()]),
            'sla_states' => collect([SlaState::Breached, SlaState::AtRisk])->map(fn (SlaState $state) => ['value' => $state->value, 'label' => $state->label()]),
            'companies' => Company::query()->orderBy('name')->get(['id', 'name']),
            'projects' => Project::query()->orderBy('name')->get(['id', 'company_id', 'name']),
            'staff' => User::staff()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
