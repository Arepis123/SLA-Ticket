<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\TicketResource;
use App\Services\DashboardMetrics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $metrics = new DashboardMetrics($request->user());

        return response()->json([
            'stats' => $metrics->stats(),
            'attention' => TicketResource::collection($metrics->attention()),
            'by_priority' => $metrics->byPriority(),
            'by_project' => $metrics->byProject()->map(fn (array $row) => [
                'project' => ['id' => $row['project']->id, 'name' => $row['project']->name],
                'company' => ['id' => $row['company']->id, 'name' => $row['company']->name],
                'count' => $row['count'],
            ]),
            'trend' => $metrics->trend(),
            'generated_at' => now()->toIso8601String(),
        ]);
    }
}
