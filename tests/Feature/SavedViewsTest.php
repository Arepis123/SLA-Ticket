<?php

namespace Tests\Feature;

use App\Enums\TicketPriority;
use App\Livewire\Tickets\Index;
use App\Models\SavedView;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SavedViewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_the_current_filters_as_a_view(): void
    {
        $internal = User::factory()->internal()->create();

        Livewire::actingAs($internal)->test(Index::class)
            ->set('status', 'unresolved')
            ->set('sla', 'breached')
            ->call('sortBy', 'resolution_due_at')
            ->assertSet('activeView', null)
            ->assertSet('filterSummary', ['All unresolved', 'SLA breached', 'Assigned to me', 'Sorted by SLA, ascending'])
            ->set('viewName', 'My breaches')
            ->call('saveView')
            ->assertHasNoErrors()
            ->assertSee('My breaches')
            ->assertSet('activeView.name', 'My breaches'); // the list now matches it, so it shows as selected

        $view = $internal->savedViews()->sole();
        $this->assertSame('My breaches', $view->name);
        $this->assertEquals(
            ['status' => 'unresolved', 'assignee' => 'me', 'sla' => 'breached', 'sort' => 'resolution_due_at', 'direction' => 'asc'],
            $view->filters,
        );
    }

    public function test_opening_a_view_restores_its_filters(): void
    {
        $internal = User::factory()->internal()->create();
        $view = $internal->savedViews()->create([
            'name' => 'Critical, anyone',
            'filters' => ['priority' => 'critical', 'assignee' => ''],
        ]);

        $mine = Ticket::factory()->priority(TicketPriority::Critical)->create(['assignee_id' => $internal->id]);
        $theirs = Ticket::factory()->priority(TicketPriority::Critical)->create(['assignee_id' => User::factory()->internal()->create()->id]);
        $low = Ticket::factory()->priority(TicketPriority::Low)->create(['assignee_id' => $internal->id]);

        // An empty assignee in the view means "Anyone", not the support team's "Assigned to me" default.
        $this->actingAs($internal)->get(route('tickets.index', $view->filters))
            ->assertOk()
            ->assertSee($mine->reference)
            ->assertSee($theirs->reference)
            ->assertDontSee($low->reference);

        Livewire::actingAs($internal)->withQueryParams($view->filters)->test(Index::class)
            ->assertSet('priority', 'critical')
            ->assertSet('assignee', '')
            ->assertSet('activeView.id', $view->id);
    }

    public function test_view_names_are_unique_per_user(): void
    {
        $alice = User::factory()->internal()->create();
        $bob = User::factory()->internal()->create();
        $alice->savedViews()->create(['name' => 'Breached', 'filters' => ['sla' => 'breached', 'assignee' => '']]);

        Livewire::actingAs($alice)->test(Index::class)
            ->set('viewName', 'Breached')
            ->call('saveView')
            ->assertHasErrors(['viewName' => 'unique'])
            ->set('viewName', '')
            ->call('saveView')
            ->assertHasErrors(['viewName' => 'required']);

        Livewire::actingAs($bob)->test(Index::class)
            ->set('viewName', 'Breached')
            ->call('saveView')
            ->assertHasNoErrors();
    }

    public function test_there_is_a_cap_on_saved_views(): void
    {
        $user = User::factory()->internal()->create();

        foreach (range(1, 20) as $i) {
            $user->savedViews()->create(['name' => "View {$i}", 'filters' => ['status' => 'open', 'assignee' => (string) $i]]);
        }

        Livewire::actingAs($user)->test(Index::class)
            ->set('viewName', 'One too many')
            ->call('saveView')
            ->assertHasErrors('viewName');

        $this->assertSame(20, $user->savedViews()->count());
    }

    public function test_views_are_private_and_only_the_owner_can_delete_them(): void
    {
        $alice = User::factory()->internal()->create();
        $bob = User::factory()->admin()->create();
        $view = $alice->savedViews()->create(['name' => 'Alice only', 'filters' => ['status' => 'open', 'assignee' => '']]);

        Livewire::actingAs($bob)->test(Index::class)
            ->assertDontSee('Alice only')
            ->call('deleteView', $view->id)
            ->assertNotFound();

        $this->assertModelExists($view);

        Livewire::actingAs($alice)->test(Index::class)
            ->assertSee('Alice only')
            ->call('deleteView', $view->id)
            ->assertDontSee('Alice only');

        $this->assertModelMissing($view);
    }

    public function test_clients_can_save_views_too(): void
    {
        $client = Ticket::factory()->create()->reporter;

        Livewire::actingAs($client)->test(Index::class)
            ->set('status', 'resolved')
            ->assertSet('filterSummary', ['Resolved'])
            ->set('viewName', 'Waiting on me')
            ->call('saveView')
            ->assertHasNoErrors();

        $this->assertSame(['status' => 'resolved', 'assignee' => ''], SavedView::query()->sole()->filters);
    }
}
