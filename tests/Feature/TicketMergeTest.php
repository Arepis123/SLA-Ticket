<?php

namespace Tests\Feature;

use App\Enums\SlaState;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Livewire\Admin\Reports;
use App\Livewire\Tickets\Index;
use App\Livewire\Tickets\Show;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\NewTicketComment;
use App\Notifications\TicketMerged;
use App\Notifications\TicketStatusChanged;
use App\Services\TicketManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class TicketMergeTest extends TestCase
{
    use RefreshDatabase;

    protected TicketManager $manager;

    protected User $admin;

    protected Project $project;

    /** Reporter of the original ticket. */
    protected User $alice;

    /** Reporter of the duplicate, at the same client. */
    protected User $bob;

    protected Ticket $primary;

    protected Ticket $duplicate;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->freezeSecond();

        $this->manager = app(TicketManager::class);
        $this->admin = User::factory()->admin()->create();
        $this->project = Project::factory()->create();
        $this->alice = User::factory()->client($this->project->company)->create();
        $this->bob = User::factory()->client($this->project->company)->create();

        $this->primary = $this->openTicket($this->alice, 'Checkout page is down');
        $this->travel(10)->minutes();
        $this->duplicate = $this->openTicket($this->bob, 'Cannot pay for orders');
    }

    protected function openTicket(User $reporter, string $title, ?Project $project = null): Ticket
    {
        return $this->manager->create($reporter, [
            'project_id' => ($project ?? $this->project)->id,
            'title' => $title,
            'description' => 'It fails with an error.',
            'priority' => TicketPriority::High,
            'category' => 'bug',
            'environment' => 'production',
        ]);
    }

    public function test_merging_closes_the_duplicate_and_tells_its_reporter(): void
    {
        $this->manager->merge($this->duplicate, $this->primary, $this->admin);

        $duplicate = $this->duplicate->fresh();
        $this->assertSame($this->primary->id, $duplicate->merged_into_id);
        $this->assertSame(TicketStatus::Closed, $duplicate->status);
        $this->assertNotNull($duplicate->paused_at, 'The SLA clock stops.');
        $this->assertSame(SlaState::Merged, $duplicate->slaState());
        $this->assertSame("Merged into {$this->primary->reference}", $duplicate->resolutionCountdown());
        $this->assertTrue($this->primary->merges()->whereKey($duplicate->id)->exists());

        $this->assertDatabaseHas('ticket_activities', ['ticket_id' => $duplicate->id, 'type' => 'merged_into']);
        $this->assertDatabaseHas('ticket_activities', ['ticket_id' => $this->primary->id, 'type' => 'merged_from']);
        Notification::assertSentTo($this->bob, TicketMerged::class, fn (TicketMerged $notification) => $notification->primary->is($this->primary));
        Notification::assertNotSentTo($this->alice, TicketMerged::class);
    }

    public function test_the_duplicate_reporter_follows_the_primary_ticket(): void
    {
        $this->manager->merge($this->duplicate, $this->primary, $this->admin);
        Notification::fake();

        $this->manager->changeStatus($this->primary->fresh(), TicketStatus::Resolved, $this->admin);
        Notification::assertSentTo([$this->alice, $this->bob], TicketStatusChanged::class);

        $this->manager->comment($this->primary->fresh(), $this->admin, 'Deployed a fix, please check.');
        Notification::assertSentTo([$this->alice, $this->bob], NewTicketComment::class);

        Notification::fake();
        $this->manager->comment($this->primary->fresh(), $this->admin, 'Root cause: expired cert', internal: true);
        Notification::assertNotSentTo([$this->alice, $this->bob], NewTicketComment::class);
    }

    public function test_the_duplicate_reporter_can_open_the_primary_but_other_clients_cannot(): void
    {
        $carol = User::factory()->client($this->project->company)->create();

        $this->actingAs($this->bob)->get(route('tickets.show', $this->primary))->assertForbidden();

        $this->manager->merge($this->duplicate, $this->primary, $this->admin);

        $this->actingAs($this->bob)->get(route('tickets.show', $this->primary))->assertOk()->assertSee('Checkout page is down');
        $this->actingAs($this->bob)->get(route('tickets.show', $this->duplicate))->assertOk()->assertSee("Merged into {$this->primary->reference}");
        $this->actingAs($carol)->get(route('tickets.show', $this->primary))->assertForbidden();

        Livewire::actingAs($this->bob)->test(Index::class)
            ->assertSee($this->primary->reference)
            ->assertSee($this->duplicate->reference);
    }

    public function test_merging_is_refused_when_it_would_not_make_sense(): void
    {
        $otherClient = $this->openTicket(User::factory()->client(($otherProject = Project::factory()->create())->company)->create(), 'Elsewhere', $otherProject);

        $this->assertSame('A ticket cannot be merged into itself.', $this->manager->mergeError($this->primary, $this->primary));
        $this->assertSame('Tickets can only be merged within the same client.', $this->manager->mergeError($this->duplicate, $otherClient));

        $this->manager->changeStatus($this->primary, TicketStatus::Closed, $this->admin);
        $this->assertStringContainsString('is closed', $this->manager->mergeError($this->duplicate, $this->primary->fresh()));

        $this->expectException(InvalidArgumentException::class);
        $this->manager->merge($this->duplicate, $otherClient, $this->admin);
    }

    public function test_merging_into_a_merged_ticket_points_at_its_primary_instead(): void
    {
        $third = $this->openTicket($this->alice, 'Payments failing');
        $this->manager->merge($this->duplicate, $this->primary, $this->admin);

        $this->assertStringContainsString(
            "Merge into {$this->primary->reference} instead",
            $this->manager->mergeError($third, $this->duplicate->fresh()),
        );
    }

    public function test_duplicates_follow_their_ticket_when_it_is_merged_onwards(): void
    {
        $newest = $this->openTicket($this->alice, 'Orders stuck');
        $this->manager->merge($this->duplicate, $newest, $this->admin);

        // Turns out the oldest ticket is the real original.
        $this->manager->merge($newest->fresh(), $this->primary, $this->admin);

        $this->assertSame($this->primary->id, $this->duplicate->fresh()->merged_into_id);
        $this->assertSame($this->primary->id, $newest->fresh()->merged_into_id);
        $this->assertSame(0, $newest->merges()->count());
    }

    public function test_unmerging_reopens_the_ticket_and_credits_the_paused_time(): void
    {
        $this->manager->merge($this->duplicate, $this->primary, $this->admin);

        $this->travel(3)->hours();
        $this->manager->unmerge($this->duplicate->fresh(), $this->admin);

        $duplicate = $this->duplicate->fresh();
        $this->assertNull($duplicate->merged_into_id);
        $this->assertSame(TicketStatus::Open, $duplicate->status);
        $this->assertNull($duplicate->paused_at);
        $this->assertSame(180, $duplicate->paused_minutes);
        $this->assertDatabaseHas('ticket_activities', ['ticket_id' => $duplicate->id, 'type' => 'unmerged']);
        $this->assertDatabaseHas('ticket_activities', ['ticket_id' => $this->primary->id, 'type' => 'duplicate_removed']);
    }

    public function test_staff_merge_from_the_ticket_page(): void
    {
        Livewire::actingAs($this->admin)->test(Show::class, ['ticket' => $this->duplicate])
            ->assertSee('Merge into another ticket')
            ->call('merge')
            ->assertHasErrors('mergeTarget')
            ->set('mergeTarget', (string) $this->primary->id)
            ->call('merge')
            ->assertHasNoErrors()
            ->assertSee("Merged into {$this->primary->reference}")
            ->assertSee('Unmerge')
            ->assertDontSee('Merge into another ticket');

        $this->assertTrue($this->duplicate->fresh()->isMerged());

        Livewire::actingAs($this->admin)->test(Show::class, ['ticket' => $this->primary->fresh()])
            ->assertSee('Merged duplicates')
            ->assertSee($this->duplicate->reference);
    }

    public function test_the_page_reports_why_a_merge_was_refused(): void
    {
        $this->manager->changeStatus($this->primary, TicketStatus::Closed, $this->admin);

        Livewire::actingAs($this->admin)->test(Show::class, ['ticket' => $this->duplicate])
            ->set('mergeTarget', (string) $this->primary->id)
            ->call('merge')
            ->assertHasErrors('mergeTarget');

        $this->assertFalse($this->duplicate->fresh()->isMerged());
    }

    public function test_clients_cannot_merge(): void
    {
        Livewire::actingAs($this->bob)->test(Show::class, ['ticket' => $this->duplicate])
            ->assertDontSee('Merge into another ticket')
            ->set('mergeTarget', (string) $this->primary->id)
            ->call('merge')
            ->assertForbidden();

        $this->assertFalse($this->duplicate->fresh()->isMerged());
    }

    public function test_merged_duplicates_are_left_out_of_reports(): void
    {
        $this->manager->merge($this->duplicate, $this->primary, $this->admin);

        $tickets = Livewire::actingAs($this->admin)->test(Reports::class)->instance()->tickets;

        $this->assertTrue($tickets->contains($this->primary));
        $this->assertFalse($tickets->contains($this->duplicate));
    }
}
