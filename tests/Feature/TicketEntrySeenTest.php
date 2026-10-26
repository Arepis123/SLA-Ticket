<?php

namespace Tests\Feature;

use App\Enums\ResolutionType;
use App\Enums\TicketStatus;
use App\Livewire\Tickets\Show;
use App\Models\Ticket;
use App\Models\TicketEntryView;
use App\Models\User;
use App\Services\TicketManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class TicketEntrySeenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Storage::fake('local');
    }

    public function test_entries_on_screen_are_recorded_once_with_the_ticket_status(): void
    {
        $ticket = Ticket::factory()->create();
        $client = $ticket->reporter;
        $admin = User::factory()->admin()->create();
        $manager = app(TicketManager::class);

        $reply = $manager->comment($ticket, $admin, 'We are looking into it.');
        $note = $manager->comment($ticket, $admin, 'Team only', internal: true);
        $log = $manager->logWork($ticket, $admin, '<p>Checked the logs.</p>');
        $own = $manager->comment($ticket, $client, 'Thanks!');

        Livewire::actingAs($client)->test(Show::class, ['ticket' => $ticket])
            ->call('markEntriesSeen', [
                TicketEntryView::keyFor($reply),
                TicketEntryView::keyFor($log),
                // Not shown to clients, their own entry, and made-up keys are ignored.
                TicketEntryView::keyFor($note),
                TicketEntryView::keyFor($own),
                'work_log-999999',
                'nonsense',
                ['not-a-string'],
            ]);

        $views = TicketEntryView::where('user_id', $client->id)->get();

        $this->assertEqualsCanonicalizing(
            [TicketEntryView::keyFor($reply), TicketEntryView::keyFor($log)],
            $views->map->key()->all(),
        );
        $this->assertTrue($views->every(fn (TicketEntryView $view) => $view->ticket_status === $ticket->status));

        // Seeing it again later keeps the first sighting.
        $firstSeen = $views->first()->seen_at;
        $statusThen = $ticket->status;
        $this->travel(1)->hour();
        $ticket->refresh()->update(['status' => TicketStatus::InProgress]);

        Livewire::actingAs($client)->test(Show::class, ['ticket' => $ticket])
            ->call('markEntriesSeen', [TicketEntryView::keyFor($reply)]);

        $view = TicketEntryView::forEntry($reply)->where('user_id', $client->id)->sole();
        $this->assertEquals($firstSeen, $view->seen_at);
        $this->assertSame($statusThen, $view->ticket_status);
    }

    public function test_only_unseen_entries_by_others_are_marked_for_watching(): void
    {
        $ticket = Ticket::factory()->create();
        $client = $ticket->reporter;
        $admin = User::factory()->admin()->create();
        $manager = app(TicketManager::class);

        $reply = $manager->comment($ticket, $admin, 'We are looking into it.');
        $own = $manager->comment($ticket, $client, 'Thanks!');

        Livewire::actingAs($client)->test(Show::class, ['ticket' => $ticket])
            ->assertSeeHtml('data-seen="'.TicketEntryView::keyFor($reply).'"')
            ->assertDontSeeHtml('data-seen="'.TicketEntryView::keyFor($own).'"')
            ->call('markEntriesSeen', [TicketEntryView::keyFor($reply)]);

        Livewire::actingAs($client)->test(Show::class, ['ticket' => $ticket])
            ->assertDontSeeHtml('data-seen="'.TicketEntryView::keyFor($reply).'"');
    }

    public function test_nothing_is_tracked_once_the_ticket_is_closed(): void
    {
        $ticket = Ticket::factory()->create();
        $admin = User::factory()->admin()->create();
        $manager = app(TicketManager::class);

        $reply = $manager->comment($ticket, $admin, 'Fixed now.');
        $manager->changeStatus($ticket, TicketStatus::Closed, $admin, ResolutionType::cases()[0]);

        Livewire::actingAs($ticket->reporter)->test(Show::class, ['ticket' => $ticket->refresh()])
            ->assertDontSeeHtml('data-seen=')
            ->call('markEntriesSeen', [TicketEntryView::keyFor($reply)]);

        $this->assertSame(0, TicketEntryView::count());
    }

    public function test_only_system_admins_see_who_has_seen_an_entry(): void
    {
        $ticket = Ticket::factory()->create();
        $client = $ticket->reporter;
        $admin = User::factory()->admin()->create();
        $internal = User::factory()->internal()->create();

        $reply = app(TicketManager::class)->comment($ticket, $internal, 'We are looking into it.');
        app(TicketManager::class)->logWork($ticket, $internal, '<p>Nobody has looked at this one.</p>');
        $ticket->markEntriesSeenBy($client, [TicketEntryView::keyFor($reply)]);

        $page = Livewire::actingAs($admin)->test(Show::class, ['ticket' => $ticket])
            ->assertSee(__('First time on screen, with the ticket status then.'))
            ->assertSee(__('Nobody else has seen this yet.'));

        $this->assertSame([$client->id], $page->instance()->entrySeenBy->get(TicketEntryView::keyFor($reply))->pluck('user_id')->all());

        Livewire::actingAs($internal)->test(Show::class, ['ticket' => $ticket])
            ->assertDontSee(__('First time on screen, with the ticket status then.'));

        Livewire::actingAs($client)->test(Show::class, ['ticket' => $ticket])
            ->assertDontSee(__('First time on screen, with the ticket status then.'));
    }

    public function test_deleting_an_entry_removes_its_seen_records(): void
    {
        $ticket = Ticket::factory()->create();
        $internal = User::factory()->internal()->create();
        $manager = app(TicketManager::class);

        $log = $manager->logWork($ticket, $internal, '<p>Checked the logs.</p>');
        $reply = $manager->comment($ticket, $internal, 'We are looking into it.');
        $ticket->markEntriesSeenBy($ticket->reporter, [TicketEntryView::keyFor($log), TicketEntryView::keyFor($reply)]);

        $manager->deleteWorkLog($log);

        $this->assertSame(0, TicketEntryView::forEntry($log)->count());
        $this->assertSame(1, TicketEntryView::forEntry($reply)->count());
    }
}
