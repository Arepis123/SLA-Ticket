<?php

namespace Tests\Feature;

use App\Enums\ResolutionType;
use App\Enums\TicketStatus;
use App\Livewire\Tickets\Show;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class TicketResolutionTypeTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->admin = User::factory()->admin()->create();
        $this->ticket = Ticket::factory()->create(['status' => TicketStatus::InProgress]);
    }

    public function test_choosing_resolved_in_the_status_dropdown_waits_for_the_fix_type(): void
    {
        Livewire::actingAs($this->admin)->test(Show::class, ['ticket' => $this->ticket])
            ->set('status', TicketStatus::Resolved->value)
            ->assertSet('status', TicketStatus::Resolved->value);

        $this->assertSame(TicketStatus::InProgress, $this->ticket->fresh()->status);
    }

    public function test_resolving_requires_a_fix_type(): void
    {
        Livewire::actingAs($this->admin)->test(Show::class, ['ticket' => $this->ticket])
            ->set('status', TicketStatus::Resolved->value)
            ->call('resolve')
            ->assertHasErrors(['resolutionType' => 'required']);

        $this->assertSame(TicketStatus::InProgress, $this->ticket->fresh()->status);
    }

    public function test_resolving_records_the_fix_type(): void
    {
        Livewire::actingAs($this->admin)->test(Show::class, ['ticket' => $this->ticket])
            ->set('status', TicketStatus::Resolved->value)
            ->set('resolutionType', ResolutionType::DataFix->value)
            ->call('resolve')
            ->assertHasNoErrors();

        $ticket = $this->ticket->fresh();
        $this->assertSame(TicketStatus::Resolved, $ticket->status);
        $this->assertSame(ResolutionType::DataFix, $ticket->resolution_type);
        $this->assertSame('data_fix', $ticket->activities()->where('type', 'status_changed')->latest('id')->first()->properties['resolution_type']);
    }

    public function test_cancelling_the_resolve_modal_restores_the_status(): void
    {
        Livewire::actingAs($this->admin)->test(Show::class, ['ticket' => $this->ticket])
            ->set('status', TicketStatus::Resolved->value)
            ->call('cancelResolve')
            ->assertSet('status', TicketStatus::InProgress->value);
    }

    public function test_resolving_from_a_reply_requires_a_fix_type(): void
    {
        $page = Livewire::actingAs($this->admin)->test(Show::class, ['ticket' => $this->ticket])
            ->set('body', 'Fixed and deployed.')
            ->set('replyStatus', TicketStatus::Resolved->value)
            ->call('reply')
            ->assertHasErrors(['resolutionType' => 'required']);

        $this->assertSame(0, $this->ticket->comments()->count());

        $page->set('resolutionType', ResolutionType::CodeChange->value)
            ->call('reply')
            ->assertHasNoErrors();

        $ticket = $this->ticket->fresh();
        $this->assertSame(TicketStatus::Resolved, $ticket->status);
        $this->assertSame(ResolutionType::CodeChange, $ticket->resolution_type);
    }

    public function test_reopening_clears_the_fix_type(): void
    {
        $manager = app(TicketManager::class);
        $manager->changeStatus($this->ticket, TicketStatus::Resolved, $this->admin, ResolutionType::CodeChange);
        $manager->changeStatus($this->ticket, TicketStatus::Reopened, $this->ticket->reporter);

        $this->assertNull($this->ticket->fresh()->resolution_type);
    }

    public function test_closing_from_the_status_dropdown_asks_and_allows_not_related(): void
    {
        Livewire::actingAs($this->admin)->test(Show::class, ['ticket' => $this->ticket])
            ->set('status', TicketStatus::Closed->value)
            ->call('resolve')
            ->assertHasErrors(['resolutionType' => 'required'])
            ->set('resolutionType', ResolutionType::NotRelated->value)
            ->call('resolve')
            ->assertHasNoErrors();

        $ticket = $this->ticket->fresh();
        $this->assertSame(TicketStatus::Closed, $ticket->status);
        $this->assertSame(ResolutionType::NotRelated, $ticket->resolution_type);
    }

    public function test_not_related_is_not_allowed_when_resolving(): void
    {
        Livewire::actingAs($this->admin)->test(Show::class, ['ticket' => $this->ticket])
            ->set('status', TicketStatus::Resolved->value)
            ->set('resolutionType', ResolutionType::NotRelated->value)
            ->call('resolve')
            ->assertHasErrors(['resolutionType' => 'in']);

        $this->assertSame(TicketStatus::InProgress, $this->ticket->fresh()->status);
    }

    public function test_closing_a_resolved_ticket_keeps_its_fix_type_without_asking(): void
    {
        app(TicketManager::class)->changeStatus($this->ticket, TicketStatus::Resolved, $this->admin, ResolutionType::DataFix);

        Livewire::actingAs($this->admin)->test(Show::class, ['ticket' => $this->ticket->fresh()])
            ->set('status', TicketStatus::Closed->value)
            ->assertHasNoErrors();

        $ticket = $this->ticket->fresh();
        $this->assertSame(TicketStatus::Closed, $ticket->status);
        $this->assertSame(ResolutionType::DataFix, $ticket->resolution_type);
    }

    public function test_closing_from_a_reply_requires_a_fix_type(): void
    {
        Livewire::actingAs($this->admin)->test(Show::class, ['ticket' => $this->ticket])
            ->set('body', 'This is a feature request, not a bug.')
            ->set('replyStatus', TicketStatus::Closed->value)
            ->call('reply')
            ->assertHasErrors(['resolutionType' => 'required'])
            ->set('resolutionType', ResolutionType::NotRelated->value)
            ->call('reply')
            ->assertHasNoErrors();

        $this->assertSame(ResolutionType::NotRelated, $this->ticket->fresh()->resolution_type);
    }

    public function test_the_service_refuses_staff_resolving_or_closing_without_a_fix_type(): void
    {
        $manager = app(TicketManager::class);

        foreach ([TicketStatus::Resolved, TicketStatus::Closed] as $status) {
            try {
                $manager->changeStatus($this->ticket->fresh(), $status, $this->admin);
                $this->fail("Expected {$status->value} without a fix type to be refused.");
            } catch (InvalidArgumentException) {
                $this->assertSame(TicketStatus::InProgress, $this->ticket->fresh()->status);
            }
        }
    }

    public function test_client_confirming_the_fix_keeps_the_fix_type(): void
    {
        app(TicketManager::class)->changeStatus($this->ticket, TicketStatus::Resolved, $this->admin, ResolutionType::CodeChange);

        Livewire::actingAs($this->ticket->reporter)->test(Show::class, ['ticket' => $this->ticket->fresh()])
            ->call('confirmFix');

        $ticket = $this->ticket->fresh();
        $this->assertSame(TicketStatus::Closed, $ticket->status);
        $this->assertSame(ResolutionType::CodeChange, $ticket->resolution_type);
    }

    public function test_merged_duplicates_are_closed_as_not_related(): void
    {
        $duplicate = Ticket::factory()->create(['project_id' => $this->ticket->project_id]);

        app(TicketManager::class)->merge($duplicate, $this->ticket, $this->admin);

        $this->assertSame(ResolutionType::NotRelated, $duplicate->fresh()->resolution_type);
    }
}
