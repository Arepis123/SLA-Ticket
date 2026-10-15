<?php

namespace Tests\Feature;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Livewire\Admin\SlaPolicies;
use App\Livewire\Admin\Users;
use App\Models\Company;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class PagesRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_page_renders_for_admin_and_client(): void
    {
        Notification::fake();

        $ticket = Ticket::factory()->create();
        $admin = User::factory()->admin()->create();
        $manager = app(TicketManager::class);
        $manager->assign($ticket, $admin, $admin);
        $manager->comment($ticket, $admin, 'We are on it');
        $manager->changeStatus($ticket, TicketStatus::PendingClient, $admin);

        foreach (['dashboard', 'tickets.index', 'admin.companies', 'admin.projects', 'admin.users', 'admin.sla-policies', 'admin.business-hours', 'admin.reports'] as $route) {
            $this->actingAs($admin)->get(route($route))->assertOk();
        }

        $this->actingAs($admin)->get(route('tickets.show', $ticket))->assertOk()->assertSee($ticket->title);

        $client = $ticket->reporter;
        foreach (['dashboard', 'tickets.index', 'tickets.create'] as $route) {
            $this->actingAs($client)->get(route($route))->assertOk();
        }
        $this->actingAs($client)->get(route('tickets.show', $ticket))->assertOk()->assertSee('We need your input');

        $this->actingAs($admin)->get(route('tickets.index', ['status' => 'unresolved', 'sla' => 'breached', 'sort' => 'priority']))->assertOk();
    }

    public function test_admin_can_create_a_client_user(): void
    {
        $admin = User::factory()->admin()->create();
        $company = Company::factory()->create();

        Livewire::actingAs($admin)->test(Users::class)
            ->call('create')
            ->set('name', 'Jane Client')
            ->set('email', 'jane@client.test')
            ->set('userRole', 'client')
            ->set('company_id', (string) $company->id)
            ->set('password', 'secret-password')
            ->call('save')
            ->assertHasNoErrors();

        $user = User::query()->where('email', 'jane@client.test')->firstOrFail();
        $this->assertTrue($user->isClient());
        $this->assertSame($company->id, $user->company_id);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_admin_can_update_sla_policies(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)->test(SlaPolicies::class)
            ->set('targets.critical.response', 0.5)
            ->set('targets.critical.resolution', 4)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(['response' => 30, 'resolution' => 240], SlaPolicy::targetsFor(TicketPriority::Critical));
    }
}
