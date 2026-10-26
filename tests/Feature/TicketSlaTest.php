<?php

namespace Tests\Feature;

use App\Enums\ResolutionType;
use App\Enums\SlaState;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\NewTicketComment;
use App\Notifications\SlaAlert;
use App\Notifications\TicketCreated;
use App\Services\TicketManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TicketSlaTest extends TestCase
{
    use RefreshDatabase;

    protected TicketManager $manager;

    protected User $admin;

    protected User $client;

    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->freezeSecond();

        $this->manager = app(TicketManager::class);
        $this->admin = User::factory()->admin()->create();
        $this->project = Project::factory()->create();
        $this->client = User::factory()->client($this->project->company)->create();
    }

    protected function openTicket(TicketPriority $priority = TicketPriority::High): Ticket
    {
        return $this->manager->create($this->client, [
            'project_id' => $this->project->id,
            'title' => 'Login broken',
            'description' => 'Cannot log in',
            'priority' => $priority,
            'category' => 'bug',
            'environment' => 'production',
        ]);
    }

    public function test_new_ticket_gets_reference_due_dates_and_notifies_admins(): void
    {
        $ticket = $this->openTicket(TicketPriority::High);

        $this->assertSame(sprintf('TKT-%s-%05d', now()->format('Y'), $ticket->id), $ticket->fresh()->reference);
        $this->assertSame($this->project->company_id, $ticket->company_id);
        $this->assertTrue($ticket->response_due_at->equalTo(now()->addHours(4)));
        $this->assertTrue($ticket->resolution_due_at->equalTo(now()->addHours(24)));
        $this->assertSame(SlaState::OnTrack, $ticket->slaState());

        Notification::assertSentTo($this->admin, TicketCreated::class);
        $this->assertDatabaseHas('ticket_activities', ['ticket_id' => $ticket->id, 'type' => 'created']);
    }

    public function test_staff_reply_records_first_response_but_internal_note_does_not(): void
    {
        $ticket = $this->openTicket();

        $this->manager->comment($ticket, $this->admin, 'Internal only', internal: true);
        $this->assertNull($ticket->fresh()->first_responded_at);

        $this->travel(30)->minutes();
        $this->manager->comment($ticket, $this->admin, 'Looking into it');

        $this->assertTrue($ticket->fresh()->first_responded_at->equalTo(now()));
        Notification::assertSentTo($this->client, NewTicketComment::class);
    }

    public function test_pending_client_pauses_the_clock_and_pushes_due_dates_back(): void
    {
        $ticket = $this->openTicket(TicketPriority::High);
        $originalDue = $ticket->resolution_due_at;

        $this->travel(2)->hours();
        $this->manager->changeStatus($ticket, TicketStatus::PendingClient, $this->admin);
        $this->assertSame(SlaState::Paused, $ticket->fresh()->slaState());

        // Waiting on the client for 30 hours would otherwise breach the 24h target.
        $this->travel(30)->hours();
        $this->assertSame(SlaState::Paused, $ticket->fresh()->slaState());

        $this->manager->comment($ticket->fresh(), $this->client, 'Here is the screenshot');

        $ticket->refresh();
        $this->assertSame(TicketStatus::InProgress, $ticket->status);
        $this->assertSame(30 * 60, $ticket->paused_minutes);
        $this->assertTrue($ticket->resolution_due_at->equalTo($originalDue->addHours(30)));
        $this->assertSame(SlaState::OnTrack, $ticket->slaState());
    }

    public function test_priority_change_recalculates_targets(): void
    {
        $ticket = $this->openTicket(TicketPriority::Low);

        $this->manager->changePriority($ticket, TicketPriority::Critical, $this->admin);

        $this->assertTrue($ticket->fresh()->resolution_due_at->equalTo($ticket->created_at->addHours(8)));
    }

    public function test_check_sla_flags_at_risk_and_breaches_once(): void
    {
        $ticket = $this->openTicket(TicketPriority::Critical); // 1h response, 8h resolution

        $this->travel(2)->hours();
        $stats = $this->manager->checkSla();
        $this->assertSame(1, $stats['response_breached']);
        $this->assertSame(0, $stats['resolution_breached']);

        $this->manager->comment($ticket->fresh(), $this->admin, 'On it');

        $this->travel(4)->hours(); // 6h of 8h used = 75%
        $this->assertSame(1, $this->manager->checkSla()['at_risk']);
        $this->assertSame(0, $this->manager->checkSla()['at_risk'], 'At-risk alert is only sent once.');

        $this->travel(3)->hours();
        $this->assertSame(1, $this->manager->checkSla()['resolution_breached']);
        $this->assertSame(0, $this->manager->checkSla()['resolution_breached']);

        $ticket->refresh();
        $this->assertNotNull($ticket->response_breached_at);
        $this->assertNotNull($ticket->resolution_breached_at);
        $this->assertSame(SlaState::Breached, $ticket->slaState());
        Notification::assertSentToTimes($this->admin, SlaAlert::class, 3);
    }

    public function test_resolved_within_target_counts_as_met_and_reopen_resumes_clock(): void
    {
        $ticket = $this->openTicket(TicketPriority::High);

        $this->travel(5)->hours();
        $this->manager->changeStatus($ticket, TicketStatus::Resolved, $this->admin, ResolutionType::CodeChange);
        $this->assertSame(SlaState::Met, $ticket->fresh()->slaState());

        $this->travel(3)->days();
        $this->manager->changeStatus($ticket->fresh(), TicketStatus::Reopened, $this->client);

        $ticket->refresh();
        $this->assertNull($ticket->resolved_at);
        $this->assertSame(3 * 24 * 60, $ticket->paused_minutes);
        $this->assertSame(SlaState::OnTrack, $ticket->slaState());
    }

    public function test_stale_resolved_tickets_are_closed_automatically(): void
    {
        $ticket = $this->openTicket();
        $this->manager->changeStatus($ticket, TicketStatus::Resolved, $this->admin, ResolutionType::CodeChange);

        $this->travel(config('sla.auto_close_days') + 1)->days();
        $this->assertSame(1, $this->manager->checkSla()['auto_closed']);

        $this->assertSame(TicketStatus::Closed, $ticket->fresh()->status);
        $this->artisan('sla:check')->assertSuccessful();
    }
}
