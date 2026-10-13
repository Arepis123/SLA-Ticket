<?php

namespace Tests\Feature;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Livewire\Admin\Reports;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    public function test_only_admins_can_open_reports(): void
    {
        $this->get(route('admin.reports'))->assertRedirect(route('login'));

        foreach ([User::factory()->client()->create(), User::factory()->internal()->create()] as $user) {
            $this->actingAs($user)->get(route('admin.reports'))->assertForbidden();
            $this->actingAs($user)->get(route('dashboard'))->assertDontSee(route('admin.reports'));

            Livewire::actingAs($user)->test(Reports::class)->assertForbidden();
        }

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.reports'))->assertOk()->assertSee('Reports');
        $this->actingAs($admin)->get(route('dashboard'))->assertSee(route('admin.reports'));
    }

    public function test_report_summarises_tickets_opened_in_the_period(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = app(TicketManager::class);

        $met = Ticket::factory()->priority(TicketPriority::Low)->create();
        $manager->changeStatus($met, TicketStatus::Resolved, $admin);

        $breached = Ticket::factory()->priority(TicketPriority::Critical)->create(['created_at' => now()->subDays(3)]);
        $manager->changeStatus($breached, TicketStatus::Resolved, $admin);

        Ticket::factory()->priority(TicketPriority::Low)->create();
        Ticket::factory()->create(['created_at' => now()->subDays(60)]);

        $component = Livewire::actingAs($admin)->test(Reports::class)->assertOk();
        $summary = $component->instance()->summary;

        $this->assertSame(3, $summary['opened']);
        $this->assertSame(2, $summary['resolved']);
        $this->assertSame(1, $summary['open']);
        $this->assertSame(1, $summary['breached']);
        $this->assertSame(50, $summary['compliance']);

        $component = Livewire::actingAs($admin)->test(Reports::class)->set('company', (string) $met->company_id);

        $this->assertSame(1, $component->instance()->summary['opened']);
        $this->assertSame(100, $component->instance()->summary['compliance']);
        $this->assertCount(1, $component->instance()->breakdowns['company']['rows']);

        $component = Livewire::actingAs($admin)->test(Reports::class)->set('range', [
            'start' => now()->subDays(90)->toDateString(),
            'end' => now()->toDateString(),
        ]);

        $this->assertSame(4, $component->instance()->summary['opened']);
    }

    public function test_admin_can_export_the_report_as_csv(): void
    {
        $admin = User::factory()->admin()->create();
        $ticket = Ticket::factory()->create(['title' => '=HYPERLINK("http://evil.test")']);
        $old = Ticket::factory()->create(['created_at' => now()->subDays(60)]);

        $component = Livewire::actingAs($admin)->test(Reports::class)
            ->call('export')
            ->assertFileDownloaded();

        $csv = base64_decode($component->effects['download']['content']);

        $this->assertStringContainsString($ticket->reference, $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString($old->reference, $csv);
    }

    public function test_admin_can_export_the_report_as_pdf(): void
    {
        $admin = User::factory()->admin()->create();
        $ticket = Ticket::factory()->create();

        $this->assertFileExists(public_path(config('sla.company.logo')));

        $this->actingAs($admin)->view('pdf.report', [
            'companyName' => config('sla.company.name'),
            'logo' => null,
            'period' => [now()->subDays(29), now()],
            'client' => null,
            'system' => null,
            'summary' => Livewire::test(Reports::class)->instance()->summary,
            'breakdowns' => Livewire::test(Reports::class)->instance()->breakdowns,
            'tickets' => Ticket::query()->with(['company', 'project', 'assignee'])->get(),
            'generatedBy' => $admin->name,
        ])->assertSee('Enigmatic Solutions')->assertSee($ticket->reference);

        $component = Livewire::actingAs($admin)->test(Reports::class)
            ->call('exportPdf')
            ->assertFileDownloaded();

        $this->assertStringEndsWith('.pdf', $component->effects['download']['name']);
        $this->assertStringStartsWith('%PDF', base64_decode($component->effects['download']['content']));

        Livewire::actingAs(User::factory()->internal()->create())->test(Reports::class)->assertForbidden();
    }
}
