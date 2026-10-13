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
