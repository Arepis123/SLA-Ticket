<?php

/**
 * Runs the scheduled SLA work on hosts that cannot run `artisan schedule:run`
 * from cron. It calls the same Artisan commands the scheduler would, so there
 * is no second copy of the SLA rules to keep in sync.
 *
 * Point a Plesk scheduled task at it, either way round:
 *
 *   Run a PHP script: /home/<user>/<domain>/public/cron.php
 *   Fetch a URL:      https://<domain>/cron.php?token=<SLA_CRON_TOKEN>
 *
 * Over HTTP it answers only with a matching SLA_CRON_TOKEN, and 404s otherwise
 * so the endpoint stays invisible. With no token configured it refuses every
 * request, which keeps the file inert until deliberately enabled.
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

$overHttp = PHP_SAPI !== 'cli';

require __DIR__.'/../vendor/autoload.php';

/** @var Application $app */
$app = require __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

if ($overHttp) {
    $expected = (string) config('sla.cron_token');
    $provided = (string) ($_GET['token'] ?? $_SERVER['HTTP_X_CRON_TOKEN'] ?? '');

    if ($expected === '' || ! hash_equals($expected, $provided)) {
        http_response_code(404);
        exit;
    }

    header('Content-Type: text/plain; charset=utf-8');

    // Finish the work even if the caller gives up waiting for a response.
    ignore_user_abort(true);
}

@set_time_limit($overHttp ? 120 : 0);

// A slow run must not overlap the next tick, mirroring the scheduler's
// withoutOverlapping(). The lock expires on its own if this script is killed.
$lock = Cache::lock('sla-cron', 300);

if (! $lock->get()) {
    echo "Previous run still in progress; skipping.\n";
    exit;
}

try {
    // Flag breaches and queue reminders first, so any notification raised is queued before the
    // worker below drains the queue.
    $kernel->call('sla:check');
    echo $kernel->output();

    // Sends only at the configured reminder times; a no-op on other ticks.
    $kernel->call('tickets:remind', ['--due' => true]);
    echo $kernel->output();

    // Booting a worker costs a few seconds even against an empty queue, which
    // adds up over 1440 runs a day on shared hosting. Only pay it when there is
    // something to send.
    if (Queue::size() > 0) {
        $kernel->call('queue:work', [
            '--stop-when-empty' => true,
            '--max-time' => $overHttp ? 45 : 50,
        ]);
        echo $kernel->output();
    } else {
        echo "Queue empty; no worker started.\n";
    }

    echo 'Completed at '.now()->toDateTimeString()."\n";
} finally {
    $lock->release();
}
