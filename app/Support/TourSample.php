<?php

namespace App\Support;

use App\Enums\TicketCategory;
use App\Enums\TicketEnvironment;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Example figures, tickets and a bug report the client pages show during their
 * guided tours, so a new client sees what the pages look like in use.
 * Nothing here is saved; their real page comes back as soon as the tour ends.
 */
class TourSample
{
    /**
     * Same keys as Dashboard::stats() reads on the client dashboard.
     *
     * @return array<string, mixed>
     */
    public static function stats(): array
    {
        return [
            'open' => 3,
            'pending_client' => 1,
            'awaiting_confirmation' => 1,
            'resolved_30d' => 12,
            'compliance' => 92,
        ];
    }

    /**
     * @return Collection<int, Ticket>
     */
    public static function tickets(User $user): Collection
    {
        // The client's own system, when they have one, makes the example feel like theirs.
        $project = new Project(['name' => $user->company?->projects()->value('name') ?? __('Customer Portal')]);

        $samples = [
            [
                'title' => __('Invoice PDF shows the wrong total'),
                'priority' => TicketPriority::High,
                'status' => TicketStatus::InProgress,
                'age' => 180,
                'resolution' => 2 * 1440,
                'responded' => 30,
            ],
            [
                'title' => __('Cannot upload a profile photo on mobile'),
                'priority' => TicketPriority::Medium,
                'status' => TicketStatus::PendingClient,
                'age' => 1440,
                'resolution' => 5 * 1440,
                'responded' => 60,
                'paused' => 120,
            ],
            [
                'title' => __('Search results load slowly'),
                'priority' => TicketPriority::Medium,
                'status' => TicketStatus::Acknowledged,
                'age' => 45,
                'resolution' => 5 * 1440,
                'responded' => 20,
            ],
            [
                'title' => __('Export to Excel times out'),
                'priority' => TicketPriority::Low,
                'status' => TicketStatus::Resolved,
                'age' => 3 * 1440,
                'resolution' => 10 * 1440,
                'responded' => 90,
                'resolved' => 1440,
            ],
        ];

        return collect($samples)->map(function (array $sample, int $i) use ($project, $user) {
            $created = now()->subMinutes($sample['age']);

            $ticket = (new Ticket)->forceFill([
                'reference' => sprintf('%s-%s-%05d', config('sla.reference_prefix'), now()->format('Y'), 42 - $i),
                'title' => $sample['title'],
                'priority' => $sample['priority'],
                'status' => $sample['status'],
                'created_at' => $created,
                'updated_at' => now()->subMinutes(($i + 1) * 25),
                'first_responded_at' => $created->addMinutes($sample['responded']),
                'response_due_at' => $created->addMinutes($sample['responded'] * 4),
                'resolution_due_at' => $created->addMinutes($sample['resolution']),
                'paused_at' => isset($sample['paused']) ? now()->subMinutes($sample['paused']) : null,
                'resolved_at' => isset($sample['resolved']) ? now()->subMinutes($sample['resolved']) : null,
            ]);

            return $ticket->setRelation('project', $project)->setRelation('reporter', $user);
        });
    }

    /**
     * The example tickets as one page of the ticket list.
     *
     * @return LengthAwarePaginator<int, Ticket>
     */
    public static function ticketPage(User $user): LengthAwarePaginator
    {
        $tickets = self::tickets($user);

        return new LengthAwarePaginator($tickets, $tickets->count(), $tickets->count());
    }

    /**
     * A well-written bug report for the Report a Bug form, keyed by its fields.
     *
     * @return array<string, string>
     */
    public static function bugReport(string $projectId): array
    {
        return [
            'project_id' => $projectId,
            'title' => __('Invoice PDF shows the wrong total'),
            'category' => TicketCategory::Bug->value,
            'priority' => TicketPriority::High->value,
            'environment' => TicketEnvironment::Production->value,
            'description' => __('When I download the PDF of an invoice that has a discount, the total at the bottom ignores the discount. For invoice INV-1043 the screen shows 900.00 but the PDF shows 1,000.00. I expected the PDF to show the same total as the screen. No error message appears.'),
            'steps_to_reproduce' => implode(PHP_EOL, [
                __('1. Go to Invoices and open INV-1043'),
                __('2. Click Download PDF'),
                __('3. Compare the total in the PDF with the one on screen'),
            ]),
        ];
    }
}
