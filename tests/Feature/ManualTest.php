<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_read_the_manual(): void
    {
        $this->get(route('manual'))->assertRedirect(route('login'));
    }

    public function test_client_only_sees_the_client_guide(): void
    {
        $this->actingAs(User::factory()->client()->create())
            ->get(route('manual'))
            ->assertOk()
            ->assertSee('Client guide')
            ->assertSee('Reporting a bug')
            ->assertSee('Seeing what the team has done')
            ->assertSee('Daily reminders')
            ->assertSee('Enable on this device')
            ->assertSee('Choosing which emails you get')
            ->assertSee('Settings → Appearance')
            ->assertDontSee('Support team guide')
            ->assertDontSee('Replies and internal notes')
            ->assertDontSee('Administrator guide')
            ->assertDontSee('SLA_AT_RISK_THRESHOLD')
            ->assertDontSee('SLA_CRON_TOKEN');
    }

    public function test_internal_sees_the_support_team_and_client_guides(): void
    {
        $this->actingAs(User::factory()->internal()->create())
            ->get(route('manual'))
            ->assertOk()
            ->assertSee('Support team guide')
            ->assertSee('Replies and internal notes')
            ->assertSee('The work log')
            ->assertSee('Choosing which emails you get')
            ->assertSee('Client guide')
            ->assertSee('Reporting a bug')
            ->assertDontSee('Administrator guide')
            ->assertDontSee('SLA_AT_RISK_THRESHOLD')
            ->assertDontSee('SLA_CRON_TOKEN')
            ->assertDontSee('VAPID_PUBLIC_KEY');
    }

    public function test_internal_cannot_request_the_admin_guide_through_the_url(): void
    {
        $this->actingAs(User::factory()->internal()->create())
            ->get(route('manual', ['guide' => 'admin']))
            ->assertOk()
            ->assertDontSee('SLA_AT_RISK_THRESHOLD');
    }

    public function test_admin_sees_every_guide(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('manual'))
            ->assertOk()
            ->assertSee('Administrator guide')
            ->assertSee('Support team guide')
            ->assertSee('Client guide')
            ->assertSee('SLA_AT_RISK_THRESHOLD')
            ->assertSee('SLA_CRON_TOKEN')
            ->assertSee('SLA_REMINDER_TIMES')
            ->assertSee('VAPID_PUBLIC_KEY')
            ->assertSee('Replies and internal notes')
            ->assertSee('The work log')
            ->assertSee('Reporting a bug');
    }

    public function test_a_guide_for_another_role_cannot_be_requested_through_the_url(): void
    {
        $this->actingAs(User::factory()->client()->create())
            ->get(route('manual', ['guide' => 'admin']))
            ->assertOk()
            ->assertSee('Client guide')
            ->assertDontSee('SLA_AT_RISK_THRESHOLD');
    }
}
