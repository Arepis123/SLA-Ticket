<?php

namespace Tests\Feature;

use App\Enums\SlaState;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Livewire\Admin\BusinessHours;
use App\Livewire\Admin\SlaPolicies;
use App\Models\BusinessHour;
use App\Models\Holiday;
use App\Models\Project;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketManager;
use App\Support\BusinessCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The office week seeded by the migration is Monday to Friday, 09:00 to 18:00. Times
 * below are Kuala Lumpur time (UTC+8) while the app itself runs in UTC.
 */
class BusinessHoursTest extends TestCase
{
    use RefreshDatabase;

    protected const TZ = 'Asia/Kuala_Lumpur';

    protected TicketManager $manager;

    protected User $admin;

    protected User $client;

    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        config(['sla.timezone' => self::TZ]);

        $this->manager = app(TicketManager::class);
        $this->admin = User::factory()->admin()->create();
        $this->project = Project::factory()->create();
        $this->client = User::factory()->client($this->project->company)->create();

        // High: 4h response, 24h resolution, business hours only. Critical stays 24/7.
        SlaPolicy::updateOrCreate(['priority' => TicketPriority::High], [
            'response_minutes' => 4 * 60,
            'resolution_minutes' => 24 * 60,
            'business_hours' => true,
        ]);
    }

    protected function at(string $localTime): CarbonImmutable
    {
        return CarbonImmutable::parse($localTime, self::TZ);
    }

    protected function travelToLocal(string $localTime): void
    {
        $this->travelTo($this->at($localTime));
    }

    protected function openTicket(TicketPriority $priority = TicketPriority::High): Ticket
    {
        return $this->manager->create($this->client, [
            'project_id' => $this->project->id,
            'title' => 'Payroll export fails',
            'description' => 'Export button returns an error',
            'priority' => $priority,
            'category' => 'bug',
            'environment' => 'production',
        ]);
    }

    public function test_adding_business_minutes_skips_nights_and_weekends(): void
    {
        $calendar = app(BusinessCalendar::class);

        // Friday 17:00 + 4h = 1h on Friday, then 3h from Monday 09:00.
        $due = $calendar->addMinutes($this->at('2026-10-16 17:00'), 4 * 60);
        $this->assertTrue($due->equalTo($this->at('2026-10-19 12:00')));
        $this->assertSame('UTC', $due->timezoneName, 'Results come back in the app timezone.');

        // Before opening, the clock starts at 09:00.
        $this->assertTrue($calendar->addMinutes($this->at('2026-10-19 07:00'), 60)->equalTo($this->at('2026-10-19 10:00')));

        // Exactly filling a day ends at closing time rather than rolling over.
        $this->assertTrue($calendar->addMinutes($this->at('2026-10-19 09:00'), 9 * 60)->equalTo($this->at('2026-10-19 18:00')));
    }

    public function test_holidays_are_skipped(): void
    {
        Holiday::create(['date' => '2026-10-19', 'name' => 'Deepavali (observed)']);

        $calendar = app(BusinessCalendar::class);

        $this->assertTrue($calendar->addMinutes($this->at('2026-10-16 17:00'), 4 * 60)->equalTo($this->at('2026-10-20 12:00')));
        $this->assertTrue($calendar->isHoliday($this->at('2026-10-19 12:00')));
        $this->assertFalse($calendar->isOpenAt($this->at('2026-10-19 12:00')));
    }

    public function test_counting_business_time_between_two_moments(): void
    {
        $calendar = app(BusinessCalendar::class);

        // Friday 17:00-18:00 plus Monday 09:00-10:00.
        $this->assertSame(7200, $calendar->secondsBetween($this->at('2026-10-16 17:00'), $this->at('2026-10-19 10:00')));
        $this->assertSame(0, $calendar->secondsBetween($this->at('2026-10-17 10:00'), $this->at('2026-10-18 20:00')), 'Weekend');
        $this->assertSame(0, $calendar->secondsBetween($this->at('2026-10-19 10:00'), $this->at('2026-10-19 09:00')), 'Backwards');
    }

    public function test_without_opening_hours_the_calendar_runs_around_the_clock(): void
    {
        BusinessHour::query()->delete();

        $calendar = app(BusinessCalendar::class);

        $this->assertFalse($calendar->hasHours());
        $this->assertTrue($calendar->addMinutes($this->at('2026-10-17 10:00'), 60)->equalTo($this->at('2026-10-17 11:00')));
        $this->assertFalse(SlaPolicy::usesBusinessHours(TicketPriority::High), 'A priority cannot stall on an empty week.');
    }

    public function test_business_hours_ticket_gets_due_dates_in_working_time(): void
    {
        $this->travelToLocal('2026-10-16 17:00');

        $ticket = $this->openTicket(TicketPriority::High);

        $this->assertTrue($ticket->usesBusinessHours());
        $this->assertTrue($ticket->response_due_at->equalTo($this->at('2026-10-19 12:00')));
        // 24h of business time: Fri 1h, Mon 9h, Tue 9h, Wed 5h.
        $this->assertTrue($ticket->resolution_due_at->equalTo($this->at('2026-10-21 14:00')));
    }

    public function test_round_the_clock_priorities_are_unaffected(): void
    {
        $this->travelToLocal('2026-10-16 17:00');

        $ticket = $this->openTicket(TicketPriority::Critical); // 1h response, 8h resolution

        $this->assertFalse($ticket->usesBusinessHours());
        $this->assertTrue($ticket->response_due_at->equalTo($this->at('2026-10-16 18:00')));
        $this->assertTrue($ticket->resolution_due_at->equalTo($this->at('2026-10-17 01:00')));
    }

    public function test_the_weekend_does_not_use_up_the_window(): void
    {
        $this->travelToLocal('2026-10-16 17:00');
        $ticket = $this->openTicket(TicketPriority::High);

        // 63 wall-clock hours later, but only one business hour has passed.
        $this->travelToLocal('2026-10-19 08:00');
        $ticket->refresh();

        $this->assertEqualsWithDelta(4.2, $ticket->resolutionProgress(), 0.1);
        $this->assertSame(SlaState::OnTrack, $ticket->slaState());
        $this->assertTrue($ticket->isOutsideBusinessHours());
        $this->assertSame(0, array_sum($this->manager->checkSla()));
    }

    public function test_pausing_credits_only_the_business_time_spent_waiting(): void
    {
        $this->travelToLocal('2026-10-16 10:00');
        $ticket = $this->openTicket(TicketPriority::High);

        // Waiting on the client from Friday 16:00 until Monday 11:00: 2h on Friday and 2h on Monday.
        $this->travelToLocal('2026-10-16 16:00');
        $this->manager->changeStatus($ticket, TicketStatus::PendingClient, $this->admin);

        $this->travelToLocal('2026-10-19 11:00');
        $this->manager->comment($ticket->fresh(), $this->client, 'Here is the file');

        $ticket->refresh();
        $this->assertSame(TicketStatus::InProgress, $ticket->status);
        $this->assertSame(4 * 60, $ticket->paused_business_minutes);
        $this->assertSame(67 * 60, $ticket->paused_minutes);
        // 24h + 4h paused = 28 business hours from Friday 10:00: Fri 8h, Mon 9h, Tue 9h, Wed 2h.
        $this->assertTrue($ticket->resolution_due_at->equalTo($this->at('2026-10-21 11:00')));
    }

    public function test_changing_priority_switches_to_the_matching_pause_counter(): void
    {
        $this->travelToLocal('2026-10-16 17:00');
        $ticket = $this->openTicket(TicketPriority::Critical);

        // Paused across the weekend: 64h on the wall clock, 1h of business time.
        $this->travelToLocal('2026-10-16 17:30');
        $this->manager->changeStatus($ticket, TicketStatus::PendingClient, $this->admin);
        $this->travelToLocal('2026-10-19 09:30');
        $this->manager->changeStatus($ticket->fresh(), TicketStatus::InProgress, $this->admin);

        $ticket->refresh();
        $this->assertSame(64 * 60, $ticket->paused_minutes);
        $this->assertSame(60, $ticket->paused_business_minutes);

        $this->manager->changePriority($ticket, TicketPriority::High, $this->admin);

        // 24h + 1h paused of business time from Friday 17:00: Fri 1h, Mon 9h, Tue 9h, Wed 6h.
        $this->assertTrue($ticket->fresh()->resolution_due_at->equalTo($this->at('2026-10-21 15:00')));
    }

    public function test_holidays_and_hours_are_picked_up_without_a_stale_cache(): void
    {
        $calendar = app(BusinessCalendar::class);
        $this->assertTrue($calendar->isOpenAt($this->at('2026-10-19 12:00')));

        Holiday::create(['date' => '2026-10-19', 'name' => 'Deepavali (observed)']);
        $this->assertFalse(app(BusinessCalendar::class)->isOpenAt($this->at('2026-10-19 12:00')));

        BusinessHour::query()->where('weekday', 6)->delete();
        BusinessHour::create(['weekday' => 6, 'opens_at' => '09:00', 'closes_at' => '13:00']);
        $this->assertTrue(app(BusinessCalendar::class)->isOpenAt($this->at('2026-10-17 10:00')));
    }

    public function test_admin_turns_business_hours_on_per_priority(): void
    {
        Livewire::actingAs($this->admin)->test(SlaPolicies::class)
            ->assertSet('businessHours.high', true)
            ->assertSet('businessHours.medium', false)
            ->set('businessHours.medium', true)
            ->set('businessHours.high', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(SlaPolicy::usesBusinessHours(TicketPriority::Medium));
        $this->assertFalse(SlaPolicy::usesBusinessHours(TicketPriority::High));
    }

    public function test_admin_edits_the_week_and_open_tickets_are_re_timed(): void
    {
        $this->travelToLocal('2026-10-16 17:00');
        $ticket = $this->openTicket(TicketPriority::High);
        $this->assertTrue($ticket->response_due_at->equalTo($this->at('2026-10-19 12:00')));

        // Open Saturday mornings: the 3 remaining response hours now fall on Saturday.
        Livewire::actingAs($this->admin)->test(BusinessHours::class)
            ->set('days.6.open', true)
            ->set('days.6.opens_at', '09:00')
            ->set('days.6.closes_at', '13:00')
            ->call('saveHours')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('business_hours', ['weekday' => 6]);
        $this->assertTrue($ticket->fresh()->response_due_at->equalTo($this->at('2026-10-17 12:00')));
    }

    public function test_closing_time_must_follow_opening_time(): void
    {
        Livewire::actingAs($this->admin)->test(BusinessHours::class)
            ->set('days.1.opens_at', '18:00')
            ->set('days.1.closes_at', '09:00')
            ->call('saveHours')
            ->assertHasErrors(['days.1.closes_at' => 'after']);
    }

    public function test_admin_adds_and_removes_holidays(): void
    {
        $this->travelToLocal('2026-10-16 17:00');
        $ticket = $this->openTicket(TicketPriority::High);

        $page = Livewire::actingAs($this->admin)->test(BusinessHours::class)
            ->set('holidayDate', '2026-10-19')
            ->set('holidayName', 'Deepavali (observed)')
            ->call('addHoliday')
            ->assertHasNoErrors()
            ->assertSee('Deepavali (observed)');

        $this->assertTrue($ticket->fresh()->response_due_at->equalTo($this->at('2026-10-20 12:00')));

        $page->set('holidayDate', '2026-10-19')->set('holidayName', 'Again')->call('addHoliday')->assertHasErrors('holidayDate');

        $page->call('deleteHoliday', Holiday::query()->sole()->id);

        $this->assertDatabaseCount('holidays', 0);
        $this->assertTrue($ticket->fresh()->response_due_at->equalTo($this->at('2026-10-19 12:00')));
    }

    public function test_breached_tickets_keep_their_deadline_when_the_calendar_changes(): void
    {
        $this->travelToLocal('2026-10-16 17:00');
        $ticket = $this->openTicket(TicketPriority::High);
        $ticket->forceFill(['resolution_breached_at' => now()])->save();
        $due = $ticket->resolution_due_at;

        Holiday::create(['date' => '2026-10-19', 'name' => 'Deepavali (observed)']);
        $this->assertSame(0, $this->manager->recalculateOpenDueDates());
        $this->assertTrue($ticket->fresh()->resolution_due_at->equalTo($due));
    }

    public function test_only_admins_reach_the_business_hours_page(): void
    {
        $this->actingAs($this->admin)->get(route('admin.business-hours'))->assertOk()->assertSee('Weekly schedule');
        $this->actingAs(User::factory()->internal()->create())->get(route('admin.business-hours'))->assertForbidden();
        $this->actingAs($this->client)->get(route('admin.business-hours'))->assertForbidden();
    }

    public function test_the_week_is_described_in_words_for_the_manual(): void
    {
        BusinessHour::create(['weekday' => 6, 'opens_at' => '09:00', 'closes_at' => '13:00']);

        $this->assertSame('Mon–Fri 09:00–18:00, Sat 09:00–13:00', app(BusinessCalendar::class)->describe());

        $this->actingAs($this->client)->get(route('manual'))
            ->assertOk()
            ->assertSee('Business hours are Mon–Fri 09:00–18:00, Sat 09:00–13:00 (Asia/Kuala_Lumpur time)', false);
    }
}
