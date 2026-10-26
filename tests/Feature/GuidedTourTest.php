<?php

namespace Tests\Feature;

use App\Enums\Tour;
use App\Livewire\Dashboard;
use App\Livewire\Tickets;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GuidedTourTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A new client with a system to report bugs against.
     */
    protected function newClient(): User
    {
        return User::factory()->client(Project::factory()->create()->company)->newToTours()->create();
    }

    public function test_a_new_client_gets_the_tour_on_the_dashboard(): void
    {
        $this->actingAs($this->newClient())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('x-data="tour(', false)
            ->assertSee('Skip tour')
            ->assertSee('data-tour="report-bug"', false);
    }

    public function test_each_page_has_its_own_tour(): void
    {
        $this->actingAs($this->newClient());

        $this->get(route('tickets.index'))->assertOk()->assertSee('Find a ticket')->assertDontSee('Your tickets at a glance');
        $this->get(route('tickets.create'))->assertOk()->assertSee('How severe is it?')->assertSee('Details and screenshots');
    }

    public function test_finishing_one_tour_leaves_the_others_waiting(): void
    {
        $client = $this->newClient();
        $this->actingAs($client);

        Livewire::test(Dashboard::class)
            ->assertSet('showTour', true)
            ->call('finishTour')
            ->assertSet('showTour', false);

        $client->refresh();
        $this->assertFalse($client->needsTour(Tour::Dashboard));
        $this->assertTrue($client->needsTour(Tour::Tickets));
        $this->assertTrue($client->needsTour(Tour::ReportBug));

        $this->get(route('dashboard'))->assertOk()->assertDontSee('Skip tour');
        $this->get(route('tickets.index'))->assertOk()->assertSee('Skip tour');

        Livewire::test(Tickets\Create::class)->call('finishTour');
        Livewire::test(Tickets\Index::class)->assertSet('showTour', true);

        $this->assertEqualsCanonicalizing(['dashboard', 'report-bug'], array_keys($client->fresh()->completed_tours));
    }

    public function test_the_dashboard_tour_shows_example_tickets_until_it_ends(): void
    {
        $this->actingAs($this->newClient());

        Livewire::test(Dashboard::class)
            ->assertSee('Example data')
            ->assertSee('Invoice PDF shows the wrong total')
            ->assertSee('1 ticket is waiting for your reply')
            ->assertDontSee('You have not reported any issues yet.')
            ->call('finishTour')
            ->assertDontSee('Example data')
            ->assertDontSee('Invoice PDF shows the wrong total')
            ->assertDontSee('1 ticket is waiting for your reply')
            ->assertSee('You have not reported any issues yet.');
    }

    public function test_the_ticket_list_tour_shows_example_tickets_until_it_ends(): void
    {
        $this->actingAs($this->newClient());

        Livewire::test(Tickets\Index::class)
            ->assertSee('Example data')
            ->assertSee('Cannot upload a profile photo on mobile')
            ->assertDontSee('No tickets match your filters.')
            ->call('finishTour')
            ->assertDontSee('Example data')
            ->assertDontSee('Cannot upload a profile photo on mobile')
            ->assertSee('No tickets match your filters.');
    }

    public function test_the_report_a_bug_tour_fills_the_form_with_an_example_until_it_ends(): void
    {
        $client = $this->newClient();
        $project = $client->company->projects()->first();
        $this->actingAs($client);

        Livewire::test(Tickets\Create::class)
            ->assertSet('project_id', (string) $project->id)
            ->assertSet('title', 'Invoice PDF shows the wrong total')
            ->assertSet('priority', 'high')
            ->assertSet('steps_to_reproduce', implode(PHP_EOL, [
                '1. Go to Invoices and open INV-1043',
                '2. Click Download PDF',
                '3. Compare the total in the PDF with the one on screen',
            ]))
            ->assertSee('Example data')
            ->assertSee('invoice-INV-1043.png')
            ->call('finishTour')
            ->assertSet('title', '')
            ->assertSet('description', '')
            ->assertSet('steps_to_reproduce', '')
            ->assertSet('priority', 'medium')
            // Their only system stays chosen, as on a normal visit.
            ->assertSet('project_id', (string) $project->id)
            ->assertDontSee('Example data')
            ->assertDontSee('invoice-INV-1043.png');
    }

    public function test_the_report_a_bug_tour_waits_until_the_client_has_a_system(): void
    {
        $this->actingAs(User::factory()->client()->newToTours()->create())
            ->get(route('tickets.create'))
            ->assertOk()
            ->assertSee('No systems available')
            ->assertDontSee('Skip tour');
    }

    public function test_a_client_can_replay_a_tour(): void
    {
        $client = $this->newClient();
        $client->completeTour(Tour::Tickets);

        $this->actingAs($client)->get(route('tickets.index'))->assertOk()->assertDontSee('Skip tour');
        $this->get(route('tickets.index', ['tour' => 1]))->assertOk()->assertSee('Skip tour');
    }

    public function test_staff_do_not_get_the_tours(): void
    {
        $this->actingAs(User::factory()->internal()->newToTours()->create());

        $this->get(route('dashboard', ['tour' => 1]))->assertOk()->assertDontSee('Skip tour');
        $this->get(route('tickets.index', ['tour' => 1]))->assertOk()->assertDontSee('Skip tour');
    }

    public function test_the_manual_offers_the_tours_to_clients_only(): void
    {
        $this->actingAs(User::factory()->client()->create())
            ->get(route('manual'))
            ->assertSee('Dashboard tour')
            ->assertSee('My Tickets tour')
            ->assertSee('Report a Bug tour');

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('manual', ['guide' => 'client']))
            ->assertDontSee('Dashboard tour');
    }
}
