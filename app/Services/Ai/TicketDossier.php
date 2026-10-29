<?php

namespace App\Services\Ai;

use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\TicketComment;
use App\Models\TicketWorkLog;
use Illuminate\Support\Collection;

/**
 * A ticket written out as plain text for the model: facts first, then the
 * history oldest to newest. Long histories keep the most recent entries and
 * say how many were left out, so nothing is dropped silently.
 */
class TicketDossier
{
    public static function for(Ticket $ticket, int $maxEntries = 40): string
    {
        $ticket->loadMissing(['company', 'project', 'assignee', 'reporter']);

        $comments = $ticket->comments()->with('user')->get()->map(fn (TicketComment $comment) => [
            'at' => $comment->created_at,
            'line' => sprintf('%s %s: %s', $comment->user->name, $comment->is_internal ? '(internal note)' : '(reply)', self::clean($comment->body)),
        ]);
        $activities = $ticket->activities()->with('user')->get()->map(fn (TicketActivity $activity) => [
            'at' => $activity->created_at,
            'line' => trim(($activity->user->name ?? 'System').' '.$activity->description()),
        ]);
        $workLogs = $ticket->workLogs()->with('user')->get()->map(fn (TicketWorkLog $log) => [
            'at' => $log->created_at,
            'line' => sprintf('%s (work log): %s', $log->user->name, self::clean($log->body)),
        ]);

        /** @var Collection<int, array{at: mixed, line: string}> $history */
        $history = $comments->toBase()->merge($activities)->merge($workLogs)->sortBy('at')->values();
        $omitted = max(0, $history->count() - $maxEntries);

        $lines = [
            "Ticket {$ticket->reference}: {$ticket->title}",
            "Client: {$ticket->company->name} · System: {$ticket->project->name}",
            "Status: {$ticket->status->label()} · Priority: {$ticket->priority->label()} · Category: {$ticket->category->label()}",
            'SLA: '.self::sla($ticket),
            'Reported by: '.$ticket->reporter->name.' · Assignee: '.($ticket->assignee->name ?? 'unassigned'),
            'Opened: '.$ticket->created_at->format('j M Y H:i'),
            '',
            'Description:',
            self::clean($ticket->description),
        ];

        if (filled($ticket->steps_to_reproduce)) {
            array_push($lines, '', 'Steps to reproduce:', self::clean($ticket->steps_to_reproduce));
        }

        $lines[] = '';
        $lines[] = 'History (oldest first):';
        if ($omitted > 0) {
            $lines[] = "[{$omitted} earlier entries not shown]";
        }
        foreach ($history->slice($omitted) as $entry) {
            $lines[] = '- '.$entry['at']->format('j M H:i').' '.$entry['line'];
        }

        return implode("\n", $lines);
    }

    /**
     * "Breached: first response missed · resolution: 1 day 18 hours left", so a
     * missed response target and a deadline still ahead do not read as a contradiction.
     */
    public static function sla(Ticket $ticket): string
    {
        $state = $ticket->slaState()->label();

        if ($ticket->isResponseBreached() && ! $ticket->isResolutionBreached()) {
            $state .= ': first response missed';
        }

        return "{$state} · resolution: {$ticket->resolutionCountdown()}";
    }

    protected static function clean(?string $text): string
    {
        $plain = html_entity_decode(strip_tags(str_replace(['<br>', '</p>', '</li>'], "\n", (string) $text)));

        return trim(preg_replace("/\n{3,}/", "\n\n", $plain) ?? $plain);
    }
}
