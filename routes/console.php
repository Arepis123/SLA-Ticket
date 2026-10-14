<?php

use App\Services\TicketManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('sla:check', function (TicketManager $manager) {
    $stats = $manager->checkSla();

    $this->info(sprintf(
        'Response breached: %d · Resolution breached: %d · At risk: %d · Auto-closed: %d',
        $stats['response_breached'],
        $stats['resolution_breached'],
        $stats['at_risk'],
        $stats['auto_closed'],
    ));
})->purpose('Flag SLA breaches and at-risk tickets, and auto-close stale resolved tickets');

Schedule::command('sla:check')->everyMinute()->withoutOverlapping();

// Notifications are queued, and shared hosting gives us no way to keep a
// `queue:work` daemon alive. Draining the queue from the scheduler instead
// means the single `schedule:run` cron entry is all the server needs.
Schedule::command('queue:work --stop-when-empty --max-time=50')
    ->everyMinute()
    ->withoutOverlapping();
