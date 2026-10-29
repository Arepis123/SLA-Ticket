<?php

namespace Tests\Feature;

use App\Enums\TicketPriority;
use App\Models\Company;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Ai\LanguageModel;
use App\Services\Ai\ModelTurn;
use App\Services\TicketManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeLanguageModel;
use Tests\TestCase;

class AiAssistantTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->admin = User::factory()->admin()->create();
        Sanctum::actingAs($this->admin);
    }

    /**
     * @param  array<string, mixed>|ModelTurn  ...$replies
     */
    protected function fakeModel(array|ModelTurn ...$replies): FakeLanguageModel
    {
        $fake = new FakeLanguageModel(...$replies);
        $this->app->instance(LanguageModel::class, $fake);

        return $fake;
    }

    /**
     * @return array<string, mixed>
     */
    protected function summary(string $headline = 'Checkout fails for card payments'): array
    {
        return ['headline' => $headline, 'summary' => 'Payments time out.', 'next_steps' => ['Check gateway logs'], 'risk' => 'high', 'risk_reason' => 'Breached.'];
    }

    public function test_a_summary_is_generated_once_and_reused_until_the_ticket_changes(): void
    {
        $ticket = Ticket::factory()->create();
        $fake = $this->fakeModel($this->summary(), $this->summary('After the reply'), $this->summary('Refreshed'));

        $this->postJson(route('api.admin.tickets.summary', $ticket))
            ->assertOk()
            ->assertJsonPath('summary.headline', 'Checkout fails for card payments')
            ->assertJsonPath('cached', false);

        // Same ticket, nothing new: served from the cache, no model call.
        $this->postJson(route('api.admin.tickets.summary', $ticket))->assertJsonPath('cached', true);
        $this->assertCount(1, $fake->calls);

        app(TicketManager::class)->comment($ticket, $this->admin, 'Gateway restarted.', internal: true);

        $this->postJson(route('api.admin.tickets.summary', $ticket))
            ->assertJsonPath('summary.headline', 'After the reply')
            ->assertJsonPath('cached', false);
        // Internal notes are part of what the model reads.
        $this->assertStringContainsString('(internal note): Gateway restarted.', $fake->calls[1]['prompt']);

        $this->postJson(route('api.admin.tickets.summary', $ticket), ['refresh' => true])->assertJsonPath('summary.headline', 'Refreshed');
        $this->assertCount(3, $fake->calls);
    }

    public function test_the_assistant_looks_tickets_up_and_links_the_ones_it_mentions(): void
    {
        $bayu = Company::factory()->create(['name' => 'Bayu Health']);
        $breached = Ticket::factory()->for($bayu)->priority(TicketPriority::Critical)->create(['created_at' => now()->subDays(3)]);
        Ticket::factory()->priority(TicketPriority::Critical)->create(['created_at' => now()->subDays(3)]);

        $fake = $this->fakeModel(
            FakeLanguageModel::toolCall('search_tickets', ['client' => 'bayu', 'sla' => 'breached']),
            FakeLanguageModel::answer("One breached ticket for Bayu Health: {$breached->reference}. Also see TKT-1999-99999."),
        );

        $this->postJson(route('api.admin.ask'), ['question' => 'Which Bayu tickets breached?'])
            ->assertOk()
            ->assertJsonPath('answer', "One breached ticket for Bayu Health: {$breached->reference}. Also see TKT-1999-99999.")
            // Only tickets the tools returned become cards; an invented reference does not.
            ->assertJsonCount(1, 'tickets')
            ->assertJsonPath('tickets.0.reference', $breached->reference);

        $result = $fake->lastToolResult();
        $this->assertSame(1, $result['total']);
        $this->assertSame($breached->reference, $result['tickets'][0]['reference']);
        $this->assertSame('Bayu Health', $result['tickets'][0]['client']);
    }

    public function test_an_unknown_client_name_comes_back_as_options_for_the_model(): void
    {
        Company::factory()->create(['name' => 'Northwind Logistics']);

        $fake = $this->fakeModel(
            FakeLanguageModel::toolCall('search_tickets', ['client' => 'Contoso']),
            FakeLanguageModel::answer('There is no client called Contoso.'),
        );

        $this->postJson(route('api.admin.ask'), ['question' => 'Contoso tickets?'])->assertOk();

        $this->assertStringContainsString('Northwind Logistics', $fake->lastToolResult()['error']);
    }

    public function test_the_last_turn_must_answer_without_tools(): void
    {
        $fake = $this->fakeModel(
            FakeLanguageModel::toolCall('queue_overview', []),
            FakeLanguageModel::toolCall('queue_overview', []),
            FakeLanguageModel::toolCall('queue_overview', []),
            FakeLanguageModel::toolCall('queue_overview', []),
            FakeLanguageModel::answer('Nothing is open.'),
        );

        $this->postJson(route('api.admin.ask'), ['question' => 'How is the queue?'])->assertJsonPath('answer', 'Nothing is open.');

        $this->assertSame([true, true, true, true, false], array_column($fake->calls, 'allowTools'));
    }

    public function test_the_daily_limit_stops_new_calls_but_not_cached_summaries(): void
    {
        config(['services.anthropic.daily_limit' => 1]);
        $ticket = Ticket::factory()->create();
        $this->fakeModel($this->summary());

        $this->postJson(route('api.admin.tickets.summary', $ticket))->assertOk();
        $this->postJson(route('api.admin.tickets.summary', $ticket))->assertOk()->assertJsonPath('cached', true);

        $this->postJson(route('api.admin.ask'), ['question' => 'How is the queue?'])
            ->assertStatus(503)
            ->assertJsonPath('message', 'Today\'s AI limit has been reached. It resets at midnight.');
    }

    public function test_without_an_api_key_the_app_gets_a_clear_message(): void
    {
        config(['services.anthropic.api_key' => null]);

        $this->postJson(route('api.admin.tickets.summary', Ticket::factory()->create()))
            ->assertStatus(503)
            ->assertJsonPath('message', 'AI features are not set up on the server yet.');
    }

    public function test_only_admins_can_use_ai(): void
    {
        $fake = $this->fakeModel();
        Sanctum::actingAs(User::factory()->internal()->create());

        $this->postJson(route('api.admin.ask'), ['question' => 'How is the queue?'])->assertForbidden();
        $this->postJson(route('api.admin.tickets.summary', Ticket::factory()->create()))->assertForbidden();
        $this->assertSame([], $fake->calls);
    }
}
