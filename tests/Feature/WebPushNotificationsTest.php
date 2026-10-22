<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Livewire\Settings\Notifications;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\ClientTicketsReminder;
use App\Notifications\TicketStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use NotificationChannels\WebPush\WebPushChannel;
use Tests\TestCase;

class WebPushNotificationsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    protected function subscription(string $endpoint = 'https://fcm.googleapis.com/fcm/send/abc123'): array
    {
        return ['endpoint' => $endpoint, 'expirationTime' => null, 'keys' => ['p256dh' => 'BPublicKey', 'auth' => 'AuthToken']];
    }

    public function test_notification_settings_page_is_displayed(): void
    {
        config(['webpush.vapid.public_key' => 'BTestPublicKey']);

        $this->actingAs(User::factory()->create())
            ->get(route('notifications.edit'))
            ->assertOk()
            ->assertSee('Enable on this device');
    }

    public function test_a_browser_can_be_subscribed_and_unsubscribed(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Notifications::class)
            ->call('subscribe', $this->subscription())
            ->assertHasNoErrors();

        $subscription = $user->pushSubscriptions()->sole();
        $this->assertSame('BPublicKey', $subscription->public_key);
        $this->assertSame('AuthToken', $subscription->auth_token);

        Livewire::actingAs($user)->test(Notifications::class)
            ->call('unsubscribe', $this->subscription()['endpoint']);

        $this->assertSame(0, $user->pushSubscriptions()->count());
    }

    public function test_subscriptions_must_use_an_https_endpoint(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Notifications::class)
            ->call('subscribe', $this->subscription('http://insecure.example/push'))
            ->assertHasErrors('endpoint');

        $this->assertSame(0, $user->pushSubscriptions()->count());
    }

    public function test_users_cannot_remove_someone_elses_subscription(): void
    {
        $owner = User::factory()->create();
        $owner->updatePushSubscription($this->subscription()['endpoint'], 'key', 'token');

        Livewire::actingAs(User::factory()->create())->test(Notifications::class)
            ->call('unsubscribe', $this->subscription()['endpoint'])
            ->call('unsubscribeAll');

        $this->assertSame(1, $owner->pushSubscriptions()->count());
    }

    public function test_turning_off_all_devices_removes_every_subscription(): void
    {
        $user = User::factory()->create();
        $user->updatePushSubscription('https://push.example/one', 'key', 'token');
        $user->updatePushSubscription('https://push.example/two', 'key', 'token');

        Livewire::actingAs($user)->test(Notifications::class)
            ->assertSee('Enabled on 2 devices.')
            ->call('unsubscribeAll')
            ->assertDispatched('push-subscriptions-cleared');

        $this->assertSame(0, $user->pushSubscriptions()->count());
    }

    public function test_push_is_only_sent_to_users_with_a_subscription(): void
    {
        $ticket = Ticket::factory()->create(['status' => TicketStatus::Resolved]);
        $subscribed = User::factory()->create();
        $subscribed->updatePushSubscription('https://push.example/one', 'key', 'token');

        $notification = new TicketStatusChanged($ticket, TicketStatus::InProgress);

        $this->assertSame(['mail', WebPushChannel::class], $notification->via($subscribed));
        $this->assertSame(['mail'], $notification->via(User::factory()->create()));
    }

    public function test_status_change_push_links_to_the_ticket(): void
    {
        $project = Project::factory()->create();
        $client = User::factory()->client($project->company)->create();
        $ticket = Ticket::factory()->create(['project_id' => $project->id, 'reporter_id' => $client->id, 'status' => TicketStatus::Resolved]);

        $push = (new TicketStatusChanged($ticket, TicketStatus::InProgress))->toWebPush($client)->toArray();

        $this->assertSame("[{$ticket->reference}] Ticket status updated", $push['title']);
        $this->assertStringContainsString(TicketStatus::Resolved->label(), $push['body']);
        $this->assertStringContainsString('Please verify the fix.', $push['body']);
        $this->assertSame(route('tickets.show', $ticket), $push['data']['url']);
        $this->assertSame($ticket->reference, $push['tag']);
    }

    public function test_client_reminder_push_summarises_waiting_tickets(): void
    {
        $project = Project::factory()->create();
        $client = User::factory()->client($project->company)->create();
        $tickets = Ticket::factory()->count(3)->sequence(
            ['status' => TicketStatus::PendingClient],
            ['status' => TicketStatus::PendingClient],
            ['status' => TicketStatus::Resolved],
        )->create(['project_id' => $project->id, 'reporter_id' => $client->id]);

        $push = (new ClientTicketsReminder($tickets))->toWebPush($client)->toArray();

        $this->assertSame('3 tickets are waiting for you', $push['title']);
        $this->assertSame('2 need your reply · 1 resolved, awaiting your confirmation', $push['body']);
        $this->assertSame(route('tickets.index'), $push['data']['url']);
    }
}
