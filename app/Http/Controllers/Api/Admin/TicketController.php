<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\TicketComment;
use App\Models\TicketWorkLog;
use App\Support\TicketFilters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    /**
     * The ticket list, with the same filters as the tickets page.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string'],
            'priority' => ['nullable', 'string'],
            'company' => ['nullable', 'integer'],
            'project' => ['nullable', 'integer'],
            'assignee' => ['nullable', 'string'],
            'sla' => ['nullable', Rule::in(['breached', 'at_risk'])],
            'sort' => ['nullable', Rule::in(TicketFilters::SORTABLE)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $filters = new TicketFilters(
            search: (string) ($data['search'] ?? ''),
            status: (string) ($data['status'] ?? ''),
            priority: (string) ($data['priority'] ?? ''),
            project: (string) ($data['project'] ?? ''),
            assignee: (string) ($data['assignee'] ?? ''),
            sla: (string) ($data['sla'] ?? ''),
            sort: (string) ($data['sort'] ?? 'created_at'),
            direction: (string) ($data['direction'] ?? 'desc'),
            company: (string) ($data['company'] ?? ''),
        );

        $tickets = $filters->apply(Ticket::query(), $request->user())
            ->with(['project', 'company', 'assignee', 'reporter', 'mergedInto:id,reference'])
            ->paginate((int) ($data['per_page'] ?? 20))
            ->withQueryString();

        return TicketResource::collection($tickets);
    }

    /**
     * One ticket with its description, conversation, activity and work log.
     * Admins see internal notes too, as on the web.
     */
    public function show(Ticket $ticket): JsonResponse
    {
        $ticket->load(['project', 'company', 'assignee', 'reporter', 'mergedInto:id,reference', 'merges:id,merged_into_id,reference,title']);

        $comments = $ticket->comments()->with(['user', 'attachments'])->get()->map(fn (TicketComment $comment) => [
            'type' => 'comment',
            'id' => $comment->id,
            'at' => $comment->created_at?->toIso8601String(),
            'user' => $comment->user->name,
            'body' => $comment->body,
            'internal' => $comment->is_internal,
            'attachments' => $comment->attachments->pluck('original_name'),
        ]);

        $activities = $ticket->activities()->with('user')->get()->map(fn (TicketActivity $activity) => [
            'type' => 'activity',
            'id' => $activity->id,
            'at' => $activity->created_at?->toIso8601String(),
            'user' => $activity->user?->name,
            'kind' => $activity->type,
            'description' => $activity->description(),
        ]);

        return response()->json([
            'data' => [
                ...(new TicketResource($ticket))->resolve(),
                'description' => $ticket->description,
                'steps_to_reproduce' => $ticket->steps_to_reproduce,
                'attachments' => $ticket->attachments()->whereNull('ticket_comment_id')->whereNull('ticket_work_log_id')->pluck('original_name'),
                'duplicates' => $ticket->merges->map(fn (Ticket $duplicate) => ['reference' => $duplicate->reference, 'title' => $duplicate->title]),
                // Comments sort before activity logged in the same second, as on the web.
                'timeline' => $comments->toBase()->merge($activities)
                    ->sortBy(fn (array $entry) => [$entry['at'], $entry['type'] === 'activity' ? 1 : 0])
                    ->values(),
                'work_logs' => $ticket->workLogs()->with(['user', 'attachments'])->get()->map(fn (TicketWorkLog $log) => [
                    'id' => $log->id,
                    'at' => $log->created_at?->toIso8601String(),
                    'user' => $log->user->name,
                    'body_html' => $log->body,
                    'body' => trim(html_entity_decode(strip_tags(str_replace(['<br>', '</p>', '</li>'], "\n", $log->body)))),
                    'attachments' => $log->attachments->pluck('original_name'),
                ]),
            ],
        ]);
    }
}
