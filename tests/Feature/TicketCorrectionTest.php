<?php

namespace Tests\Feature;

use App\Enums\TicketCategory;
use App\Livewire\Tickets\Show;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class TicketCorrectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    public function test_staff_can_correct_the_system_and_it_is_logged(): void
    {
        $ticket = Ticket::factory()->create();
        $from = $ticket->project;
        $to = Project::factory()->create(['company_id' => $ticket->company_id, 'name' => 'Billing Portal']);
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)->test(Show::class, ['ticket' => $ticket])
            ->set('project_id', (string) $to->id)
            ->assertHasNoErrors();

        $this->assertSame($to->id, $ticket->fresh()->project_id);

        $activity = $ticket->activities()->where('type', 'project_changed')->sole();
        $this->assertSame($admin->id, $activity->user_id);
        $this->assertSame("moved this ticket from system {$from->name} to Billing Portal", $activity->description());

        $this->actingAs($ticket->reporter)->get(route('tickets.show', $ticket))
            ->assertSee('moved this ticket from system');
    }

    public function test_staff_can_correct_the_type_and_it_is_logged(): void
    {
        $ticket = Ticket::factory()->create(['category' => TicketCategory::Bug]);
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)->test(Show::class, ['ticket' => $ticket])
            ->set('category', TicketCategory::Access->value)
            ->assertHasNoErrors();

        $this->assertSame(TicketCategory::Access, $ticket->fresh()->category);
        $this->assertSame(
            'changed type from Bug to Login / Access',
            $ticket->activities()->where('type', 'category_changed')->sole()->description(),
        );
    }

    public function test_a_ticket_cannot_move_to_another_clients_system(): void
    {
        $ticket = Ticket::factory()->create();
        $foreign = Project::factory()->create();
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)->test(Show::class, ['ticket' => $ticket])
            ->set('project_id', (string) $foreign->id);

        $this->assertNotSame($foreign->id, $ticket->fresh()->project_id);
        $this->assertFalse($ticket->activities()->where('type', 'project_changed')->exists());

        $this->expectException(InvalidArgumentException::class);
        app(TicketManager::class)->changeProject($ticket, $foreign, $admin);
    }

    public function test_clients_cannot_change_the_system_or_type(): void
    {
        $ticket = Ticket::factory()->create(['category' => TicketCategory::Bug]);

        Livewire::actingAs($ticket->reporter)->test(Show::class, ['ticket' => $ticket])
            ->set('category', TicketCategory::Other->value)
            ->assertForbidden();

        $this->assertSame(TicketCategory::Bug, $ticket->fresh()->category);
    }
}
