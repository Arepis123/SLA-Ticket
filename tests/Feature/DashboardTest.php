<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_staff_see_the_ticket_heatmap(): void
    {
        $this->actingAs(User::factory()->internal()->create());

        $this->get(route('dashboard'))->assertOk()->assertSee('Tickets opened — last 12 weeks');
    }

    public function test_client_admins_see_the_ticket_heatmap(): void
    {
        $this->actingAs(User::factory()->clientAdmin()->create());

        $this->get(route('dashboard'))->assertOk()->assertSee('Tickets opened — last 12 weeks');
    }

    public function test_regular_clients_do_not_see_the_ticket_heatmap(): void
    {
        $this->actingAs(User::factory()->client()->create());

        $this->get(route('dashboard'))->assertOk()->assertDontSee('Tickets opened — last 12 weeks');
    }
}
