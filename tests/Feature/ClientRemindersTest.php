<?php

namespace Tests\Feature;

use App\Enums\ResolutionType;
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

        // 2026-10-24 and 25 are a Saturday and Sunday.
        $this->assertNull($at('2026-10-24 08:00'));
        $this->assertNull($at('2026-10-25 14:00'));
        $this->assertSame('2026-10-26 08:00', $at('2026-10-26 08:00'));

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

        // Already reminded about this status today, so the afternoon run skips it.
        $this->travelTo(CarbonImmutable::parse('2026-10-21 14:00', 'Asia/Kuala_Lumpur'));
        $this->artisan('tickets:remind --due')->assertSuccessful();
        Notification::assertSentToTimes($ticket->reporter, ClientTicketsReminder::class, 1);

        $this->travelTo(CarbonImmutable::parse('2026-10-22 08:00', 'Asia/Kuala_Lumpur'));
        $this->artisan('tickets:remind --due')->assertSuccessful();
        Notification::assertSentToTimes($ticket->reporter, ClientTicketsReminder::class, 2);
    }

    public function test_a_ticket_is_reminded_again_the_same_day_only_after_its_status_changes(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-21 08:00', 'Asia/Kuala_Lumpur'));
        $manager = app(TicketManager::class);
        $pending = Ticket::factory()->create(['status' => TicketStatus::PendingClient]);
        $resolved = Ticket::factory()->create(['status' => TicketStatus::Resolved]);

        $this->assertSame(2, $manager->sendClientReminders());
        $this->assertSame(TicketStatus::PendingClient, $pending->fresh()->client_reminded_status);

        // The client replied and the team then resolved it before the afternoon run.
        $pending->update(['status' => TicketStatus::Resolved]);
        $this->travelTo(CarbonImmutable::parse('2026-10-21 14:00', 'Asia/Kuala_Lumpur'));

        $this->assertSame(1, $manager->sendClientReminders());
        Notification::assertSentToTimes($pending->reporter, ClientTicketsReminder::class, 2);
        Notification::assertSentToTimes($resolved->reporter, ClientTicketsReminder::class, 1);
        $this->assertSame(TicketStatus::Resolved, $pending->fresh()->client_reminded_status);
    }

    public function test_a_status_change_email_counts_as_that_days_reminder(): void
    {
        $manager = app(TicketManager::class);
        $ticket = Ticket::factory()->create(['status' => TicketStatus::InProgress]);
        $staff = User::factory()->internal()->create();

        $this->travelTo(CarbonImmutable::parse('2026-10-21 13:57', 'Asia/Kuala_Lumpur'));
        $manager->changeStatus($ticket, TicketStatus::Resolved, $staff, ResolutionType::CodeChange);

        $this->travelTo(CarbonImmutable::parse('2026-10-21 14:00', 'Asia/Kuala_Lumpur'));
        $this->assertSame(0, $manager->sendClientReminders());

        $this->travelTo(CarbonImmutable::parse('2026-10-22 08:00', 'Asia/Kuala_Lumpur'));
        $this->assertSame(1, $manager->sendClientReminders());
        Notification::assertSentToTimes($ticket->reporter, ClientTicketsReminder::class, 1);
    }

    public function test_the_once_a_day_limit_follows_the_kuala_lumpur_day_not_utc(): void
    {
        $manager = app(TicketManager::class);
        Ticket::factory()->create(['status' => TicketStatus::Resolved]);

        // 01:00 and 07:00 in Kuala Lumpur are the same local day but straddle midnight UTC.
        $this->travelTo(CarbonImmutable::parse('2026-10-22 01:00', 'Asia/Kuala_Lumpur'));
        $this->assertSame(1, $manager->sendClientReminders());

        $this->travelTo(CarbonImmutable::parse('2026-10-22 07:00', 'Asia/Kuala_Lumpur'));
        $this->assertSame(0, $manager->sendClientReminders());

        $this->travelTo(CarbonImmutable::parse('2026-10-23 00:30', 'Asia/Kuala_Lumpur'));
        $this->assertSame(1, $manager->sendClientReminders());
    }
}
