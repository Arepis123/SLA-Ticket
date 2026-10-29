<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\SlaReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'start' => ['nullable', 'date_format:Y-m-d'],
            'end' => ['nullable', 'date_format:Y-m-d'],
            'company' => ['nullable', 'integer'],
            'project' => ['nullable', 'integer'],
        ]);

        $report = SlaReport::fromInput($data['start'] ?? null, $data['end'] ?? null, $data['company'] ?? null, $data['project'] ?? null);

        return response()->json([
            'period' => ['start' => $report->start->toDateString(), 'end' => $report->end->toDateString()],
            'summary' => $report->summary(),
            'breakdowns' => $report->breakdowns(),
            'trend' => $report->trend(),
        ]);
    }
}
