<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\NewTicketComment;
use App\Notifications\SlaAlert;
use App\Services\Push\FcmClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MobilePushTest extends TestCase
{
    use RefreshDatabase;

    protected const SEND_URL = 'https://fcm.googleapis.com/v1/projects/sla-monitor-test/messages:send';

    protected function setUp(): void
    {
        parent::setUp();

        // A service account file with a cached access token, so no call goes to Google.
        $path = tempnam(sys_get_temp_dir(), 'fcm');
        file_put_contents($path, json_encode(['project_id' => 'sla-monitor-test']));
        $this->app->instance(FcmClient::class, new FcmClient($path));
        Cache::put('fcm-access-token', 'test-access-token', 60);
    }

    /**
     * An admin signed in on a phone through the API, with that phone registered.
     */
    protected function adminWithPhone(string $deviceToken = 'device-1'): User
    {
        $admin = User::factory()->admin()->create();
        $plain = $admin->createToken('Pixel', ['monitor'])->plainTextToken;

        // Each call signs in afresh, as a separate phone would.
        $this->app['auth']->forgetGuards();
        $this->withToken($plain)
            ->postJson(route('api.devices.store'), ['token' => $deviceToken, 'platform' => 'android'])
            ->assertNoContent();

        return $admin;
    }

    public function test_a_phone_is_registered_against_the_sign_in_and_forgotten_on_sign_out(): void
    {
        $admin = $this->adminWithPhone();
        $device = $admin->deviceTokens()->sole();

        $this->assertSame('android', $device->platform);
        $this->assertSame($admin->tokens()->sole()->id, $device->personal_access_token_id);

        // Signing out deletes the API token, and the device goes with it.
        $admin->tokens()->delete();

        $this->assertSame(0, DeviceToken::query()->count());
    }

    public function test_a_phone_moves_to_whoever_signed_in_on_it_last(): void
    {
        $first = $this->adminWithPhone('shared-phone');
        $second = $this->adminWithPhone('shared-phone');

        $this->assertSame(0, $first->deviceTokens()->count());
        $this->assertSame(1, $second->deviceTokens()->count());
    }

    public function test_an_sla_alert_reaches_the_phone_and_opens_the_ticket(): void
    {
        Http::fake([self::SEND_URL => Http::response(['name' => 'projects/x/messages/1'])]);
        $admin = $this->adminWithPhone();
        $ticket = Ticket::factory()->create(['created_at' => now()->subDays(3)]);

        $admin->notify(new SlaAlert($ticket, 'resolution_breached'));

        Http::assertSent(function (Request $request) use ($ticket) {
            return $request->url() === self::SEND_URL
                && $request->hasHeader('Authorization', 'Bearer test-access-token')
                && $request['message']['token'] === 'device-1'
                && $request['message']['notification']['title'] === "[{$ticket->reference}] SLA breached: resolution overdue"
                && $request['message']['data'] === ['reference' => $ticket->reference, 'type' => 'SlaAlert']
                && $request['message']['android']['notification']['tag'] === $ticket->reference;
        });
    }

    public function test_comments_do_not_buzz_the_phone(): void
    {
        Http::fake();
        $admin = $this->adminWithPhone();
        $ticket = Ticket::factory()->create();
        $comment = $ticket->comments()->create(['user_id' => $admin->id, 'body' => 'Looking into it.', 'is_internal' => false]);

        $admin->notify(new NewTicketComment($comment));

        Http::assertNothingSent();
    }

    public function test_an_uninstalled_app_is_forgotten(): void
    {
        Http::fake([self::SEND_URL => Http::response(['error' => [
            'code' => 404,
            'message' => 'Requested entity was not found.',
            'details' => [['@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError', 'errorCode' => 'UNREGISTERED']],
        ]], 404)]);
        $admin = $this->adminWithPhone();

        $admin->notify(new SlaAlert(Ticket::factory()->create(), 'at_risk'));

        $this->assertSame(0, $admin->deviceTokens()->count());
    }

    public function test_without_firebase_set_up_nothing_is_sent(): void
    {
        Http::fake();
        $this->app->instance(FcmClient::class, new FcmClient('/no/such/file.json'));
        $admin = $this->adminWithPhone();

        $admin->notify(new SlaAlert(Ticket::factory()->create(), 'at_risk'));

        Http::assertNothingSent();
        $this->assertSame(1, $admin->deviceTokens()->count());
    }
}
