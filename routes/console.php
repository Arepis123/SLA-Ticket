<?php

use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Services\TicketManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
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

// One-off: older uploads kept the uploader's filename, which can leak when and
// on what device a file was made. Only the database label changes; the files
// on disk already have random names.
Artisan::command('attachments:rename {--dry-run : List the new names without saving them}', function () {
    $renamed = 0;

    Ticket::query()->whereHas('attachments')->with(['attachments' => fn ($query) => $query->orderBy('id')])
        ->each(function (Ticket $ticket) use (&$renamed) {
            foreach ($ticket->attachments->values() as $index => $attachment) {
                $name = TicketAttachment::displayName(
                    $ticket,
                    $index + 1,
                    TicketAttachment::extensionFor(pathinfo($attachment->original_name, PATHINFO_EXTENSION), pathinfo($attachment->path, PATHINFO_EXTENSION)),
                );

                if ($name === $attachment->original_name) {
                    continue;
                }

                $this->line("{$attachment->original_name} → {$name}");

                if (! $this->option('dry-run')) {
                    $attachment->update(['original_name' => $name]);
                }

                $renamed++;
            }
        });

    $this->info(($this->option('dry-run') ? 'Would rename' : 'Renamed')." {$renamed} attachment(s).");
})->purpose('Replace uploaders\' original filenames with ticket-based names');

Artisan::command('tickets:remind {--due : Only send if a reminder time (config sla.reminders) has just passed and was not sent yet}', function (TicketManager $manager) {
    if ($this->option('due')) {
        $slot = $manager->dueReminderSlot();

        // Cache::add is atomic, so the scheduler and public/cron.php running in
        // the same minute still send each slot once.
        if (! $slot || ! Cache::add('tickets-remind:'.$slot->format('Y-m-d H:i'), true, now()->addDays(2))) {
            $this->line('No client reminder due.');

            return;
        }
    }

    $this->info('Client reminders sent: '.$manager->sendClientReminders());
})->purpose('Email clients a digest of their tickets pending their reply or awaiting confirmation');

Schedule::command('sla:check')->everyMinute()->withoutOverlapping();
Schedule::command('tickets:remind --due')->everyMinute()->withoutOverlapping();

// Notifications are queued, and shared hosting gives us no way to keep a
// `queue:work` daemon alive. Draining the queue from the scheduler instead
// means the single `schedule:run` cron entry is all the server needs.
Schedule::command('queue:work --stop-when-empty --max-time=50')
    ->everyMinute()
    ->withoutOverlapping();
