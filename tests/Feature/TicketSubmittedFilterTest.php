<?php

namespace Tests\Feature;

use App\Livewire\Tickets\Index;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TicketSubmittedFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_tickets_can_be_filtered_by_submitted_date(): void
    {
        $project = Project::factory()->create();
        $early = Ticket::factory()->for($project)->create(['title' => 'Early ticket', 'created_at' => '2026-10-01 09:00:00']);
        $inside = Ticket::factory()->for($project)->create(['title' => 'Inside ticket', 'created_at' => '2026-10-15 23:30:00']);
        $late = Ticket::factory()->for($project)->create(['title' => 'Late ticket', 'created_at' => '2026-11-02 09:00:00']);

        Livewire::actingAs(User::factory()->admin()->create())->test(Index::class)
            ->set('submitted', ['start' => '2026-10-10', 'end' => '2026-10-15'])
            ->assertSet('from', '2026-10-10')
            ->assertSet('to', '2026-10-15')
            ->assertSee($inside->title)
            ->assertDontSee($early->title)
            ->assertDontSee($late->title)
            ->assertSet('panelFilters', ['submitted' => 'Submitted: 10 Oct 2026 – 15 Oct 2026'])
            ->call('clearFilter', 'submitted')
            ->assertSet('from', '')
            ->assertSet('to', '')
            ->assertSet('submitted', ['start' => null, 'end' => null])
            ->assertSee($early->title)
            ->assertSee($late->title);
    }

    public function test_invalid_dates_are_ignored(): void
    {
        $ticket = Ticket::factory()->create();

        Livewire::actingAs(User::factory()->admin()->create())->test(Index::class)
            ->set('submitted', ['start' => '2026-02-31', 'end' => 'not-a-date'])
            ->assertSee($ticket->title)
            ->assertSet('from', '')
            ->assertSet('panelFilters', []);
    }

    public function test_the_picker_starts_from_the_dates_in_the_url(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::withQueryParams(['from' => '2026-10-01', 'to' => '2026-10-31'])->test(Index::class)
            ->assertSet('submitted', ['start' => '2026-10-01', 'end' => '2026-10-31']);
    }
}
