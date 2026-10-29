<?php

namespace Tests\Feature;

use App\Enums\ResolutionType;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Tests\TestCase;

class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    /**
     * @param  array<string, string>  $extra
     * @return array<string, string>
     */
    protected function credentials(User $user, array $extra = []): array
    {
        return ['email' => $user->email, 'password' => 'password', 'device_name' => 'iPhone', ...$extra];
    }

    public function test_an_admin_signs_in_and_gets_a_token_that_expires(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->postJson(route('api.auth.token'), $this->credentials($admin))
            ->assertCreated()
            ->assertJsonPath('user.email', $admin->email)
            ->assertJsonPath('user.role.value', 'admin');

        $this->assertNotEmpty($response->json('token'));
        $this->assertNotNull($admin->tokens()->first()->expires_at);

        $this->withToken($response->json('token'))->getJson(route('api.me'))->assertOk()->assertJsonPath('data.id', $admin->id);
    }

    public function test_sign_in_is_refused_for_wrong_passwords_non_admins_and_inactive_accounts(): void
    {
        $admin = User::factory()->admin()->create();

        $this->postJson(route('api.auth.token'), $this->credentials($admin, ['password' => 'wrong']))
            ->assertUnprocessable()->assertJsonValidationErrors('email');

        foreach ([User::factory()->internal()->create(), User::factory()->client()->create(), User::factory()->admin()->inactive()->create(), User::factory()->admin()->unverified()->create()] as $user) {
            $this->postJson(route('api.auth.token'), $this->credentials($user))
                ->assertUnprocessable()->assertJsonValidationErrors('email');

            $this->assertSame(0, $user->tokens()->count());
        }
    }

    public function test_an_admin_with_two_factor_must_send_a_valid_code_before_getting_a_token(): void
    {
        $admin = User::factory()->admin()->withTwoFactor()->create();

        $this->postJson(route('api.auth.token'), $this->credentials($admin))
            ->assertOk()
            ->assertExactJson(['two_factor' => true]);

        $this->mock(TwoFactorAuthenticationProvider::class, function (MockInterface $mock) {
            $mock->shouldReceive('verify')->with('secret', '000000')->andReturn(false);
            $mock->shouldReceive('verify')->with('secret', '123456')->andReturn(true);
        });

        $this->postJson(route('api.auth.token'), $this->credentials($admin, ['code' => '000000']))
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->assertSame(0, $admin->tokens()->count());

        $this->postJson(route('api.auth.token'), $this->credentials($admin, ['code' => '123456']))->assertCreated();
        $this->assertSame(1, $admin->tokens()->count());
    }

    public function test_a_recovery_code_works_once(): void
    {
        $admin = User::factory()->admin()->withTwoFactor()->create();

        $this->postJson(route('api.auth.token'), $this->credentials($admin, ['recovery_code' => 'recovery-code-1']))->assertCreated();

        $this->assertNotContains('recovery-code-1', $admin->fresh()->recoveryCodes());

        $this->postJson(route('api.auth.token'), $this->credentials($admin, ['recovery_code' => 'recovery-code-1']))
            ->assertUnprocessable()->assertJsonValidationErrors('recovery_code');
    }

    public function test_admin_routes_need_an_admin_token(): void
    {
        $this->getJson(route('api.admin.dashboard'))->assertUnauthorized();

        Sanctum::actingAs(User::factory()->internal()->create());
        $this->getJson(route('api.admin.dashboard'))->assertForbidden();

        Sanctum::actingAs(User::factory()->client()->create());
        $this->getJson(route('api.admin.tickets.index'))->assertForbidden();
    }

    public function test_a_deactivated_admin_loses_every_token(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('iPhone')->plainTextToken;
        $admin->createToken('iPad');

        $admin->update(['is_active' => false]);

        $this->withToken($token)->getJson(route('api.admin.dashboard'))->assertForbidden();
        $this->assertSame(0, $admin->tokens()->count());
    }

    public function test_expired_tokens_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('iPhone', ['monitor'], now()->subMinute())->plainTextToken;

        $this->withToken($token)->getJson(route('api.admin.dashboard'))->assertUnauthorized();
    }

    public function test_signing_out_revokes_the_token(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('iPhone')->plainTextToken;

        $this->withToken($token)->deleteJson(route('api.auth.logout'))->assertNoContent();

        $this->assertSame(0, $admin->tokens()->count());
    }

    public function test_dashboard_reports_the_same_figures_as_the_web_dashboard(): void
    {
        $admin = User::factory()->admin()->create();
        $breached = Ticket::factory()->priority(TicketPriority::Critical)->create(['created_at' => now()->subDays(3)]);
        Ticket::factory()->priority(TicketPriority::Low)->create(['assignee_id' => $admin->id]);

        Sanctum::actingAs($admin);

        $this->getJson(route('api.admin.dashboard'))
            ->assertOk()
            ->assertJsonPath('stats.open', 2)
            ->assertJsonPath('stats.unassigned', 1)
            ->assertJsonPath('stats.breached', 1)
            ->assertJsonPath('by_priority.critical', 1)
            ->assertJsonPath('by_priority.low', 1)
            ->assertJsonPath('attention.0.reference', $breached->reference)
            ->assertJsonPath('attention.0.sla.state.value', 'breached')
            ->assertJsonCount(14, 'trend');
    }

    public function test_ticket_list_filters_like_the_tickets_page(): void
    {
        $admin = User::factory()->admin()->create();
        $breached = Ticket::factory()->priority(TicketPriority::Critical)->create(['created_at' => now()->subDays(3)]);
        $onTrack = Ticket::factory()->priority(TicketPriority::Low)->create();

        Sanctum::actingAs($admin);

        $this->getJson(route('api.admin.tickets.index'))->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 2);

        $this->getJson(route('api.admin.tickets.index', ['sla' => 'breached']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reference', $breached->reference);

        $this->getJson(route('api.admin.tickets.index', ['priority' => 'low', 'company' => $onTrack->company_id]))
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reference', $onTrack->reference);

        $this->getJson(route('api.admin.tickets.index', ['search' => $onTrack->reference]))
            ->assertJsonCount(1, 'data');

        $this->getJson(route('api.admin.tickets.index', ['sla' => 'nonsense']))->assertUnprocessable();
    }

    public function test_ticket_detail_includes_internal_notes_activity_and_work_log(): void
    {
        $admin = User::factory()->admin()->create();
        $ticket = Ticket::factory()->create();
        $manager = app(TicketManager::class);

        $manager->assign($ticket, $admin, $admin);
        $manager->comment($ticket, $admin, 'Visible reply');
        $manager->comment($ticket, $admin, 'Internal note', internal: true);
        $manager->logWork($ticket, $admin, '<p>Checked the <strong>logs</strong></p>');

        Sanctum::actingAs($admin);

        $response = $this->getJson(route('api.admin.tickets.show', $ticket))
            ->assertOk()
            ->assertJsonPath('data.reference', $ticket->reference)
            ->assertJsonPath('data.description', $ticket->description)
            ->assertJsonPath('data.work_logs.0.body', 'Checked the logs');

        $timeline = collect($response->json('data.timeline'));

        $this->assertSame(['Visible reply', 'Internal note'], $timeline->where('type', 'comment')->pluck('body')->all());
        $this->assertTrue($timeline->firstWhere('body', 'Internal note')['internal']);
        $this->assertSame(__('assigned this ticket to :name', ['name' => $admin->name]), $timeline->firstWhere('kind', 'assigned')['description']);
    }

    public function test_reports_summarise_a_period(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = app(TicketManager::class);

        $met = Ticket::factory()->priority(TicketPriority::Low)->create();
        $manager->changeStatus($met, TicketStatus::Resolved, $admin, ResolutionType::CodeChange);
        Ticket::factory()->create(['created_at' => now()->subDays(60)]);

        Sanctum::actingAs($admin);

        $this->getJson(route('api.admin.reports'))
            ->assertOk()
            ->assertJsonPath('summary.opened', 1)
            ->assertJsonPath('summary.compliance', 100)
            ->assertJsonPath('breakdowns.company.rows.0.opened', 1);

        $this->getJson(route('api.admin.reports', ['start' => now()->subDays(90)->toDateString()]))
            ->assertJsonPath('summary.opened', 2)
            ->assertJsonPath('period.start', now()->subDays(90)->toDateString());

        $this->getJson(route('api.admin.reports', ['start' => 'yesterday']))->assertUnprocessable();
    }
}
