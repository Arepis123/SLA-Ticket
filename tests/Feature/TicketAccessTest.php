<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Livewire\Admin\Users;
use App\Livewire\Tickets\Create;
use App\Livewire\Tickets\Index;
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

    public function test_clients_only_see_the_tickets_they_reported(): void
    {
        $mine = Ticket::factory()->create();
        $colleagues = Ticket::factory()->create(['project_id' => $mine->project_id]);
        $other = Ticket::factory()->create();
        $client = $mine->reporter;

        $this->assertSame($mine->company_id, $colleagues->company_id);

        $this->actingAs($client)->get(route('tickets.show', $mine))->assertOk();
        $this->actingAs($client)->get(route('tickets.show', $colleagues))->assertForbidden();
        $this->actingAs($client)->get(route('tickets.show', $other))->assertForbidden();

        $this->actingAs($client)->get(route('tickets.index'))
            ->assertSee('My Tickets')
            ->assertSee($mine->reference)
            ->assertDontSee($colleagues->reference)
            ->assertDontSee($other->reference);

        $this->actingAs($client)->get(route('dashboard'))->assertOk()->assertDontSee($colleagues->reference);

        Livewire::actingAs($client)->test(Show::class, ['ticket' => $colleagues])->assertForbidden();
    }

    public function test_client_admins_see_every_ticket_of_their_company_only(): void
    {
        $mine = Ticket::factory()->create();
        $colleagues = Ticket::factory()->create(['project_id' => $mine->project_id]);
        $other = Ticket::factory()->create();
        $clientAdmin = User::factory()->clientAdmin($mine->company)->create();

        $this->actingAs($clientAdmin)->get(route('tickets.show', $mine))->assertOk();
        $this->actingAs($clientAdmin)->get(route('tickets.show', $colleagues))->assertOk();
        $this->actingAs($clientAdmin)->get(route('tickets.show', $other))->assertForbidden();

        $this->actingAs($clientAdmin)->get(route('tickets.index'))
            ->assertSee('Company Tickets')
            ->assertSee($mine->reference)
            ->assertSee($colleagues->reference)
            ->assertSee($colleagues->reporter->name)
            ->assertDontSee($other->reference);

        Livewire::actingAs($clientAdmin)->test(Show::class, ['ticket' => $colleagues])
            ->set('body', 'Following up for my colleague')
            ->call('reply')
            ->assertHasNoErrors();

        $this->assertSame(1, $colleagues->comments()->count());

        foreach (['admin.users', 'admin.reports'] as $route) {
            $this->actingAs($clientAdmin)->get(route($route))->assertForbidden();
        }
    }

    public function test_admin_can_make_a_client_user_a_client_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $client = User::factory()->client()->create();

        Livewire::actingAs($admin)->test(Users::class)
            ->call('edit', $client->id)
            ->assertSet('is_client_admin', false)
            ->set('is_client_admin', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue($client->fresh()->isClientAdmin());

        // The flag only applies to clients: it is dropped when the role changes.
        Livewire::actingAs($admin)->test(Users::class)
            ->call('edit', $client->id)
            ->assertSet('is_client_admin', true)
            ->set('userRole', 'internal')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertFalse($client->fresh()->is_client_admin);
    }

    public function test_clients_cannot_open_admin_pages(): void
    {
        $client = User::factory()->client()->create();

        foreach (['admin.companies', 'admin.projects', 'admin.users', 'admin.sla-policies', 'admin.reports'] as $route) {
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

    public function test_staff_can_add_a_work_log_visible_to_everyone_but_clients_cannot_add_one(): void
    {
        $ticket = Ticket::factory()->create();
        $admin = User::factory()->admin()->create();

        $workLog = app(TicketManager::class)->logWork($ticket, $admin, 'Investigated the root cause in the logs');

        $this->assertSame($admin->id, $workLog->user_id);
        $this->actingAs($admin)->get(route('tickets.show', $ticket))->assertSee('Investigated the root cause in the logs');
        $this->actingAs($ticket->reporter)->get(route('tickets.show', $ticket))->assertSee('Investigated the root cause in the logs');

        Livewire::actingAs($ticket->reporter)->test(Show::class, ['ticket' => $ticket])
            ->set('workLogBody', 'I should not be able to do this')
            ->call('addWorkLog')
            ->assertForbidden();
    }

    public function test_work_log_attachments_are_stored_and_downloadable_by_the_client(): void
    {
        $ticket = Ticket::factory()->create();
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)->test(Show::class, ['ticket' => $ticket])
            ->set('workLogBody', '<p>Patched the import job</p>')
            ->set('workLogFiles', [UploadedFile::fake()->create('patch-notes.pdf', 10, 'application/pdf')])
            ->call('addWorkLog')
            ->assertHasNoErrors()
            ->assertSet('workLogFiles', []);

        $workLog = $ticket->workLogs()->with('attachments')->sole();
        $attachment = $workLog->attachments->sole();

        $this->assertNull($attachment->ticket_comment_id);
        Storage::disk('local')->assertExists($attachment->path);

        $this->actingAs($ticket->reporter)->get(route('tickets.show', $ticket))
            ->assertSee("{$ticket->reference}-1.pdf")
            ->assertDontSee('patch-notes.pdf');
        $this->actingAs($ticket->reporter)->get(route('tickets.attachments.show', [$ticket, $attachment]))->assertOk();

        // The filename in the URL is what browsers use as the tab title for an opened file.
        $url = route('tickets.attachments.show', [$ticket, $attachment, $attachment->original_name]);
        $this->assertStringEndsWith("/{$ticket->reference}-1.pdf", $url);
        $this->actingAs($ticket->reporter)->get(route('tickets.show', $ticket))->assertSee($url, escape: false);
        $this->actingAs($ticket->reporter)->get($url)->assertOk();

        // Work log files stay with their entry, not in the original report's attachments.
        Livewire::actingAs($admin)->test(Show::class, ['ticket' => $ticket])
            ->assertCount('ticketAttachments', 0);
    }

    public function test_uploads_are_named_after_the_ticket_instead_of_the_uploaders_filename(): void
    {
        $ticket = Ticket::factory()->create();
        $admin = User::factory()->admin()->create();
        $manager = app(TicketManager::class);

        $first = $manager->comment($ticket, $admin, 'First', files: [
            UploadedFile::fake()->image('Screenshot 2026-10-19 at 7.39.39 PM.png'),
            UploadedFile::fake()->create('export.csv', 1, 'text/csv'),
        ]);
        $second = $manager->comment($ticket, $admin, 'Second', files: [UploadedFile::fake()->create('notes.txt', 1)]);

        $this->assertSame(
            ["{$ticket->reference}-1.png", "{$ticket->reference}-2.csv"],
            $first->attachments->pluck('original_name')->all(),
        );

        // Deleting an earlier file must not hand its number to the next upload.
        $manager->deleteComment($second);
        $third = $manager->comment($ticket, $admin, 'Third', files: [UploadedFile::fake()->create('trace.txt', 1)]);

        $this->assertSame("{$ticket->reference}-3.txt", $third->attachments->sole()->original_name);
    }

    public function test_rename_command_replaces_existing_uploaders_filenames(): void
    {
        $ticket = Ticket::factory()->create();
        $admin = User::factory()->admin()->create();
        $comment = app(TicketManager::class)->comment($ticket, $admin, 'Old upload', files: [UploadedFile::fake()->image('error.png')]);
        $attachment = $comment->attachments->sole();
        $attachment->update(['original_name' => 'Screenshot 2026-10-19 at 7.39.39 PM.png']);

        $this->artisan('attachments:rename', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame('Screenshot 2026-10-19 at 7.39.39 PM.png', $attachment->fresh()->original_name);

        $this->artisan('attachments:rename')->assertSuccessful();
        $this->assertSame("{$ticket->reference}-1.png", $attachment->fresh()->original_name);
    }

    public function test_staff_can_delete_their_own_reply_and_work_log_with_their_files(): void
    {
        $ticket = Ticket::factory()->create();
        $admin = User::factory()->admin()->create();
        $manager = app(TicketManager::class);

        $comment = $manager->comment($ticket, $admin, 'Wrong ticket, sorry', files: [UploadedFile::fake()->create('notes.txt', 1)]);
        $workLog = $manager->logWork($ticket, $admin, '<p>Logged by mistake</p>', [UploadedFile::fake()->create('trace.txt', 1)]);
        $paths = [$comment->attachments->sole()->path, $workLog->attachments->sole()->path];

        Livewire::actingAs($admin)->test(Show::class, ['ticket' => $ticket])
            ->call('deleteComment', $comment->id)
            ->call('deleteWorkLog', $workLog->id)
            ->assertHasNoErrors();

        $this->assertModelMissing($comment);
        $this->assertModelMissing($workLog);
        $this->assertCount(0, $ticket->attachments()->get());

        foreach ($paths as $path) {
            Storage::disk('local')->assertMissing($path);
        }
    }

    public function test_entries_cannot_be_deleted_by_other_staff_or_clients(): void
    {
        $ticket = Ticket::factory()->create();
        $author = User::factory()->admin()->create();
        $otherStaff = User::factory()->admin()->create();
        $manager = app(TicketManager::class);

        $comment = $manager->comment($ticket, $author, 'Looking into it');
        $workLog = $manager->logWork($ticket, $author, '<p>Checked the logs</p>');
        $clientComment = $manager->comment($ticket, $ticket->reporter, 'Any update?');

        Livewire::actingAs($otherStaff)->test(Show::class, ['ticket' => $ticket])
            ->call('deleteComment', $comment->id)
            ->assertForbidden();

        Livewire::actingAs($otherStaff)->test(Show::class, ['ticket' => $ticket])
            ->call('deleteWorkLog', $workLog->id)
            ->assertForbidden();

        // Clients cannot delete anything, not even their own replies.
        Livewire::actingAs($ticket->reporter)->test(Show::class, ['ticket' => $ticket])
            ->call('deleteComment', $clientComment->id)
            ->assertForbidden();

        $this->assertModelExists($comment);
        $this->assertModelExists($workLog);
        $this->assertModelExists($clientComment);
    }

    public function test_unseen_work_log_entries_are_badged_until_the_client_opens_the_tab(): void
    {
        $ticket = Ticket::factory()->create();
        $client = $ticket->reporter;
        $admin = User::factory()->admin()->create();
        $manager = app(TicketManager::class);

        // Nothing logged yet, so nothing to badge.
        Livewire::actingAs($client)->test(Show::class, ['ticket' => $ticket])
            ->assertSet('unseenWorkLogCount', 0);

        $manager->logWork($ticket, $admin, 'First entry');
        $manager->logWork($ticket, $admin, 'Second entry');

        Livewire::actingAs($client)->test(Show::class, ['ticket' => $ticket])
            ->assertSet('unseenWorkLogCount', 2)
            ->call('markWorkLogsSeen')
            ->assertSet('unseenWorkLogCount', 0);

        // The read mark persists for the next visit.
        Livewire::actingAs($client)->test(Show::class, ['ticket' => $ticket])
            ->assertSet('unseenWorkLogCount', 0);

        // A later entry badges again, and only for the people who haven't read it.
        $this->travel(1)->minute();
        $manager->logWork($ticket, $admin, 'Third entry');

        Livewire::actingAs($client)->test(Show::class, ['ticket' => $ticket])
            ->assertSet('unseenWorkLogCount', 1);

        // The author never badges their own entries.
        Livewire::actingAs($admin)->test(Show::class, ['ticket' => $ticket])
            ->assertSet('unseenWorkLogCount', 0);
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

    public function test_support_team_sees_only_their_own_queue_until_they_clear_the_filters(): void
    {
        $internal = User::factory()->internal()->create();
        $mine = Ticket::factory()->create(['assignee_id' => $internal->id]);
        $theirs = Ticket::factory()->create(['assignee_id' => User::factory()->internal()->create()->id]);
        $unassigned = Ticket::factory()->create(['assignee_id' => null]);

        $this->actingAs($internal)->get(route('tickets.index'))
            ->assertSee($mine->reference)
            ->assertDontSee($theirs->reference)
            ->assertDontSee($unassigned->reference);

        Livewire::actingAs($internal)->test(Index::class)
            ->assertSet('assignee', 'me')
            ->call('resetFilters')
            ->assertSet('assignee', '')
            ->assertSee($mine->reference)
            ->assertSee($theirs->reference)
            ->assertSee($unassigned->reference);

        $this->actingAs($internal)->get(route('tickets.index', ['assignee' => '']))
            ->assertSee($mine->reference)
            ->assertSee($theirs->reference)
            ->assertSee($unassigned->reference);
    }

    public function test_admins_have_no_assignee_filter_applied_by_default(): void
    {
        $admin = User::factory()->admin()->create();
        $mine = Ticket::factory()->create(['assignee_id' => $admin->id]);
        $theirs = Ticket::factory()->create(['assignee_id' => User::factory()->internal()->create()->id]);
        $unassigned = Ticket::factory()->create(['assignee_id' => null]);

        Livewire::actingAs($admin)->test(Index::class)
            ->assertSet('assignee', '')
            ->assertSee($mine->reference)
            ->assertSee($theirs->reference)
            ->assertSee($unassigned->reference)
            ->assertDontSee(__('Assigned to me'));

        $this->actingAs($admin)->get(route('tickets.index', ['assignee' => 'me']))
            ->assertSee($mine->reference)
            ->assertSee($theirs->reference)
            ->assertSee($unassigned->reference);
    }

    public function test_clients_have_no_assignee_filter_applied_by_default(): void
    {
        $ticket = Ticket::factory()->create(['assignee_id' => User::factory()->internal()->create()->id]);

        Livewire::actingAs($ticket->reporter)->test(Index::class)
            ->assertSet('assignee', '')
            ->assertSee($ticket->reference);
    }

    public function test_inactive_users_are_signed_out(): void
    {
        $user = User::factory()->client()->inactive()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
