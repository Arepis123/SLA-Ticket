<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Livewire\Tickets\Create;
use App\Livewire\Tickets\Show;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class TicketAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Storage::fake('local');
    }

    public function test_clients_only_see_their_own_company_tickets(): void
    {
        $mine = Ticket::factory()->create();
        $other = Ticket::factory()->create();
        $client = User::factory()->client($mine->company)->create();

        $this->actingAs($client)->get(route('tickets.show', $mine))->assertOk();
        $this->actingAs($client)->get(route('tickets.show', $other))->assertForbidden();

        $this->actingAs($client)->get(route('tickets.index'))
            ->assertSee($mine->reference)
            ->assertDontSee($other->reference);
    }

    public function test_clients_cannot_open_admin_pages(): void
    {
        $client = User::factory()->client()->create();

        foreach (['admin.companies', 'admin.projects', 'admin.users', 'admin.sla-policies'] as $route) {
            $this->actingAs($client)->get(route($route))->assertForbidden();
        }
    }

    public function test_only_clients_can_report_a_bug(): void
    {
        $client = User::factory()->client()->create();
        $this->actingAs($client)->get(route('tickets.create'))->assertOk();
        $this->actingAs($client)->get(route('dashboard'))->assertSee(route('tickets.create'));

        foreach ([User::factory()->admin()->create(), User::factory()->internal()->create()] as $staff) {
            $this->actingAs($staff)->get(route('tickets.create'))->assertForbidden();
            $this->actingAs($staff)->get(route('dashboard'))->assertOk()->assertDontSee(route('tickets.create'));
            $this->actingAs($staff)->get(route('tickets.index'))->assertOk()->assertDontSee(route('tickets.create'));

            Livewire::actingAs($staff)->test(Create::class)->assertForbidden();
        }
    }

    public function test_client_can_submit_a_ticket_with_attachment_for_own_system_only(): void
    {
        $project = Project::factory()->create();
        $foreign = Project::factory()->create();
        $client = User::factory()->client($project->company)->create();

        Livewire::actingAs($client)->test(Create::class)
            ->set('project_id', (string) $foreign->id)
            ->set('title', 'Broken')
            ->set('description', 'Details')
            ->call('save')
            ->assertHasErrors('project_id');

        Livewire::actingAs($client)->test(Create::class)
            ->set('project_id', (string) $project->id)
            ->set('title', 'Report crashes')
            ->set('priority', 'critical')
            ->set('description', 'The report page crashes.')
            ->set('attachments', [UploadedFile::fake()->image('error.png')])
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $ticket = Ticket::query()->where('title', 'Report crashes')->firstOrFail();
        $this->assertSame($client->id, $ticket->reporter_id);
        $this->assertCount(1, $ticket->attachments);
        Storage::disk('local')->assertExists($ticket->attachments->first()->path);
    }

    public function test_internal_notes_and_their_files_are_hidden_from_clients(): void
    {
        $ticket = Ticket::factory()->create();
        $admin = User::factory()->admin()->create();
        $comment = app(TicketManager::class)->comment($ticket, $admin, 'Secret root cause', internal: true, files: [
            UploadedFile::fake()->create('db-dump.txt', 1),
        ]);

        $this->actingAs($admin)->get(route('tickets.show', $ticket))->assertSee('Secret root cause');
        $this->actingAs($ticket->reporter)->get(route('tickets.show', $ticket))->assertDontSee('Secret root cause');

        $attachment = $comment->attachments->first();
        $this->actingAs($ticket->reporter)->get(route('tickets.attachments.show', [$ticket, $attachment]))->assertForbidden();
        $this->actingAs($admin)->get(route('tickets.attachments.show', [$ticket, $attachment]))->assertOk();
    }

    public function test_clients_cannot_change_status_but_staff_can(): void
    {
        $ticket = Ticket::factory()->create();
        $internal = User::factory()->internal()->create();

        Livewire::actingAs($ticket->reporter)->test(Show::class, ['ticket' => $ticket])
            ->set('status', 'closed')
            ->assertForbidden();

        Livewire::actingAs($internal)->test(Show::class, ['ticket' => $ticket])
            ->set('status', 'in_progress')
            ->set('assignee_id', (string) $internal->id);

        $ticket->refresh();
        $this->assertSame(TicketStatus::InProgress, $ticket->status);
        $this->assertSame($internal->id, $ticket->assignee_id);
        $this->assertNotNull($ticket->first_responded_at);
    }

    public function test_client_confirms_or_reopens_a_resolved_ticket(): void
    {
        $ticket = Ticket::factory()->create();
        $admin = User::factory()->admin()->create();
        app(TicketManager::class)->changeStatus($ticket, TicketStatus::Resolved, $admin);

        Livewire::actingAs($ticket->reporter)->test(Show::class, ['ticket' => $ticket->fresh()])
            ->call('reopen')
            ->assertHasErrors('body')
            ->set('body', 'Still failing on Safari')
            ->call('reopen')
            ->assertHasNoErrors();

        $this->assertSame(TicketStatus::Reopened, $ticket->fresh()->status);

        app(TicketManager::class)->changeStatus($ticket->fresh(), TicketStatus::Resolved, $admin);

        Livewire::actingAs($ticket->reporter)->test(Show::class, ['ticket' => $ticket->fresh()])
            ->call('confirmFix');

        $this->assertSame(TicketStatus::Closed, $ticket->fresh()->status);
    }

    public function test_inactive_users_are_signed_out(): void
    {
        $user = User::factory()->client()->inactive()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
