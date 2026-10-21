<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\ClientTicketsReminder;
use App\Services\TicketManager;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ClientRemindersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        config(['sla.reminders.times' => ['08:00', '14:00'], 'sla.timezone' => 'Asia/Kuala_Lumpur']);
    }

    public function test_clients_get_their_own_waiting_tickets_and_client_admins_their_companys(): void
    {
        $project = Project::factory()->create();
        $reporter = User::factory()->client($project->company)->create();
        $colleague = User::factory()->client($project->company)->create();
        $clientAdmin = User::factory()->clientAdmin($project->company)->create();
        $inactiveAdmin = User::factory()->clientAdmin($project->company)->inactive()->create();

        $pending = Ticket::factory()->create(['project_id' => $project->id, 'reporter_id' => $reporter->id, 'status' => TicketStatus::PendingClient]);
        $resolved = Ticket::factory()->create(['project_id' => $project->id, 'reporter_id' => $colleague->id, 'status' => TicketStatus::Resolved]);
        Ticket::factory()->create(['project_id' => $project->id, 'reporter_id' => $reporter->id, 'status' => TicketStatus::InProgress]);
        $otherCompany = Ticket::factory()->create(['status' => TicketStatus::PendingClient]);

        $this->assertSame(4, app(TicketManager::class)->sendClientReminders());

        $ticketsFor = fn (User $user) => Notification::sent($user, ClientTicketsReminder::class)->sole()->tickets->pluck('id')->sort()->values()->all();

        $this->assertSame([$pending->id], $ticketsFor($reporter));
        $this->assertSame([$resolved->id], $ticketsFor($colleague));
        $this->assertSame([$pending->id, $resolved->id], $ticketsFor($clientAdmin));
        $this->assertSame([$otherCompany->id], $ticketsFor($otherCompany->reporter));
        Notification::assertNotSentTo($inactiveAdmin, ClientTicketsReminder::class);

        $mail = Notification::sent($clientAdmin, ClientTicketsReminder::class)->sole()->toMail($clientAdmin);
        $this->assertStringContainsString($pending->reference, implode("\n", $mail->introLines));
        $this->assertStringContainsString($resolved->reference, implode("\n", $mail->introLines));
    }

    public function test_reminder_slots_follow_kuala_lumpur_time(): void
    {
        $manager = app(TicketManager::class);
        $at = fn (string $time) => $manager->dueReminderSlot(CarbonImmutable::parse($time, 'Asia/Kuala_Lumpur'))?->format('Y-m-d H:i');

        $this->assertNull($at('2026-10-21 07:59'));
        $this->assertSame('2026-10-21 08:00', $at('2026-10-21 08:00'));
        $this->assertSame('2026-10-21 08:00', $at('2026-10-21 09:30')); // a missed tick still sends
        $this->assertNull($at('2026-10-21 10:01'));                     // too late; wait for 14:00
        $this->assertSame('2026-10-21 14:00', $at('2026-10-21 14:05'));

        // 08:00 in Kuala Lumpur is 00:00 UTC.
        $this->assertSame('2026-10-21 08:00', $manager->dueReminderSlot(CarbonImmutable::parse('2026-10-21 00:00', 'UTC'))?->format('Y-m-d H:i'));
    }

    public function test_due_command_sends_each_slot_once(): void
    {
        $ticket = Ticket::factory()->create(['status' => TicketStatus::PendingClient]);

        $this->travelTo(CarbonImmutable::parse('2026-10-21 07:00', 'Asia/Kuala_Lumpur'));
        $this->artisan('tickets:remind --due')->assertSuccessful();
        Notification::assertNothingSent();

        $this->travelTo(CarbonImmutable::parse('2026-10-21 08:00', 'Asia/Kuala_Lumpur'));
        $this->artisan('tickets:remind --due')->assertSuccessful();
        $this->travelTo(CarbonImmutable::parse('2026-10-21 08:01', 'Asia/Kuala_Lumpur'));
        $this->artisan('tickets:remind --due')->assertSuccessful();
        Notification::assertSentToTimes($ticket->reporter, ClientTicketsReminder::class, 1);

        $this->travelTo(CarbonImmutable::parse('2026-10-21 14:00', 'Asia/Kuala_Lumpur'));
        $this->artisan('tickets:remind --due')->assertSuccessful();
        Notification::assertSentToTimes($ticket->reporter, ClientTicketsReminder::class, 2);
    }
}
