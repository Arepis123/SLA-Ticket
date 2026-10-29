<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Services\Ai\QueueAssistant;
use App\Services\Ai\TicketSummarizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Claude-powered help for the admin app. Both endpoints only read data, and
 * answer 503 with a message the app can show when AI is unavailable.
 */
class AiController extends Controller
{
    public function summary(Request $request, Ticket $ticket, TicketSummarizer $summarizer): JsonResponse
    {
        $data = $request->validate(['refresh' => ['sometimes', 'boolean']]);

        return response()->json($summarizer->summarize($ticket, refresh: (bool) ($data['refresh'] ?? false)));
    }

    public function ask(Request $request, QueueAssistant $assistant): JsonResponse
    {
        $data = $request->validate(['question' => ['required', 'string', 'min:3', 'max:500']]);

        $result = $assistant->ask($request->user(), $data['question']);

        return response()->json([
            'answer' => $result['answer'],
            'tickets' => TicketResource::collection($result['tickets']),
        ]);
    }
}
