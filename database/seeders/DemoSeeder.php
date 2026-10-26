<?php

namespace Database\Seeders;

use App\Enums\ResolutionType;
use App\Enums\TicketCategory;
use App\Enums\TicketEnvironment;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Project;
use App\Models\User;
use App\Services\TicketManager;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Notification;

/**
 * Sample clients, staff and tickets with realistic SLA histories.
 */
class DemoSeeder extends Seeder
{
    /** @var array<int, array{0: string, 1: string}> */
    protected array $issues = [
        ['Login page returns 500 error', 'Users get "Server Error" after submitting the login form since this morning.'],
        ['Report export to Excel is empty', 'The monthly report downloads but the spreadsheet has headers only.'],
        ['Dashboard takes 30s to load', 'Main dashboard is extremely slow during office hours.'],
        ['Wrong total in invoice PDF', 'Invoice total does not include the 8% service tax.'],
        ['Password reset email not received', 'Several staff reported they never receive the reset email.'],
        ['Date picker shows wrong timezone', 'Selected dates are saved one day earlier than chosen.'],
        ['Upload fails for files above 2MB', 'Uploading a 3MB PDF shows "Something went wrong".'],
        ['Duplicate records after sync', 'Each nightly sync creates duplicate customer records.'],
        ['Mobile layout broken on iPhone', 'Buttons overlap the form on Safari iOS.'],
        ['Search returns no results for names with apostrophe', "Searching for O'Brien returns nothing."],
        ['Notification emails sent twice', 'Clients receive two copies of every notification.'],
        ['Cannot approve leave request', 'Approve button does nothing for managers.'],
        ['Stock quantity goes negative', 'Inventory shows -5 units after cancelling an order.'],
        ['Session expires too quickly', 'Users are logged out after 5 minutes of inactivity.'],
        ['Chart labels overlapping', 'Monthly chart x-axis labels overlap when there are 12 months.'],
        ['Payment gateway callback timeout', 'Some payments are marked failed although the bank charged the customer.'],
        ['Access denied for new role', 'Users with the Supervisor role cannot open the reports module.'],
        ['Typo on the receipt footer', 'The footer reads "Thank yuo".'],
    ];

    /**
     * Rich-text engineer notes written while a fix is being investigated.
     *
     * @var array<int, string>
     */
    protected array $investigationLogs = [
        '<p>Reproduced on staging using a copy of the client\'s data.</p><ul><li>Only happens when the session has been idle for more than 15 minutes</li><li>Stack trace points at the <strong>null check</strong> that was removed in last week\'s release</li></ul>',
        '<p><strong>Root cause found.</strong> The query is missing an index, so it falls back to a full table scan once the table passes ~50k rows.</p><p>Next step: add the index behind a migration and re-measure.</p>',
        '<p>Pulled the production logs for the reported time window.</p><ol><li>Request arrives and is authenticated</li><li>Downstream API call times out after 30s</li><li>Our handler swallows the exception and returns an empty payload</li></ol><p>The timeout is the real problem; the empty response is just the symptom.</p>',
        '<p>Not reproducible on Chrome or Firefox, only on <strong>Safari</strong>. Narrowing it down to a CSS issue rather than application logic.</p>',
        '<p>Checked with the client to confirm the expected behaviour before changing anything:</p><blockquote>The total should include service tax, the same as the on-screen summary.</blockquote><p>Confirmed. The on-screen calculation is correct and the export is the one that needs fixing.</p>',
        '<p>Added temporary logging around the affected code path so we can capture the exact payload the next time it happens.</p><p><em>Waiting for the next occurrence before making any change.</em></p>',
    ];

    /**
     * Rich-text engineer notes written when the fix ships.
     *
     * @var array<int, string>
     */
    protected array $fixLogs = [
        '<p><strong>Fix deployed to production.</strong></p><ul><li>Restored the missing validation check</li><li>Added a regression test covering the idle-session case</li></ul><p>Monitoring error rates for the next 24 hours.</p>',
        '<p>Deployed the index migration during the maintenance window.</p><p>Page load went from <strong>~30s</strong> down to <strong>under 1s</strong> on the same dataset.</p>',
        '<p>Patched the export so it reuses the same calculation as the on-screen summary instead of duplicating the logic. That removes the chance of the two drifting apart again.</p>',
        '<p>Fix released. Changes made:</p><ol><li>Raised the downstream timeout and added one retry</li><li>Surfaced a proper error instead of an empty response when the call does fail</li></ol>',
        '<p>Corrected the text and deployed. Also swept the rest of the templates for the same typo &mdash; <strong>two more</strong> were found and fixed.</p>',
    ];

    public function run(TicketManager $manager): void
    {
        Notification::fake();

        $staff = collect([
            ['Daniel Lee', 'daniel@example.com'],
            ['Nurul Aina', 'nurul@example.com'],
        ])->map(fn (array $user) => User::query()->firstOrCreate(['email' => $user[1]], [
            'name' => $user[0],
            'password' => 'password',
            'role' => UserRole::Internal,
            'email_verified_at' => now(),
        ]));

        $admin = User::query()->where('role', UserRole::Admin)->first();
        $staff->push($admin);

        $clients = collect([
            ['Acme Logistics Sdn Bhd', 'client@acme.test', 'Ahmad Faiz', ['Fleet Tracking Portal', 'Warehouse Inventory']],
            ['Bright Clinic Group', 'client@brightclinic.test', 'Dr. Mei Ling', ['Patient Booking App']],
            ['Nova Retail', 'client@novaretail.test', 'Kumar Raj', ['E-Commerce Store', 'POS Back Office']],
        ])->map(function (array $data) {
            $company = Company::query()->firstOrCreate(['name' => $data[0]], [
                'email' => $data[1],
                'contact_person' => $data[2],
                'phone' => fake()->phoneNumber(),
            ]);

            foreach ($data[3] as $projectName) {
                Project::query()->firstOrCreate(['company_id' => $company->id, 'name' => $projectName], [
                    'code' => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $projectName), 0, 4)),
                    'description' => "{$projectName} developed for {$company->name}.",
                ]);
            }

            return User::query()->firstOrCreate(['email' => $data[1]], [
                'name' => $data[2],
                'password' => 'password',
                'role' => UserRole::Client,
                'company_id' => $company->id,
                'email_verified_at' => now(),
            ]);
        });

        $now = CarbonImmutable::now();

        foreach (range(1, 28) as $i) {
            $client = $clients->random();
            $project = Project::query()->where('company_id', $client->company_id)->inRandomOrder()->first();
            $issue = $this->issues[array_rand($this->issues)];
            $priority = fake()->randomElement([
                TicketPriority::Critical, TicketPriority::High, TicketPriority::High,
                TicketPriority::Medium, TicketPriority::Medium, TicketPriority::Medium, TicketPriority::Low,
            ]);

            // The last tickets are recent and stop part-way so there is live work to monitor:
            // 0 = still open, 1 = acknowledged, 2 = in progress, 3 = waiting on client, 4 = run to completion.
            $live = $i > 18;
            $stage = $live ? random_int(0, 3) : 4;
            $at = $live
                ? $now->subMinutes(random_int(20, 4 * 24 * 60))
                : $now->subMinutes(random_int(5 * 24 * 60, 45 * 24 * 60));
            $step = function (int $minutes) use (&$at, $now): bool {
                $at = $at->addMinutes($minutes);

                if ($at->greaterThan($now)) {
                    return false;
                }

                CarbonImmutable::setTestNow($at);

                return true;
            };

            CarbonImmutable::setTestNow($at);

            $ticket = $manager->create($client, [
                'project_id' => $project->id,
                'title' => $issue[0],
                'description' => $issue[1],
                'steps_to_reproduce' => "1. Log in as a normal user\n2. Go to the affected page\n3. Observe the problem",
                'environment' => fake()->randomElement([TicketEnvironment::Production, TicketEnvironment::Production, TicketEnvironment::Staging]),
                'category' => fake()->randomElement(TicketCategory::cases()),
                'priority' => $priority,
            ]);

            $assignee = $staff->random();
            $speed = fake()->randomElement([0.3, 0.6, 1.0, 1.6]); // < 1 = faster than SLA

            $targets = $priority->defaultTargets();

            if ($stage < 1 || ! $step(random_int(5, (int) ($targets['response'] * $speed) + 5))) {
                continue;
            }
            $manager->assign($ticket, $assignee, $admin);
            $manager->changeStatus($ticket, TicketStatus::Acknowledged, $assignee);

            if ($stage < 2 || ! $step(random_int(10, 120))) {
                continue;
            }
            $manager->changeStatus($ticket, TicketStatus::InProgress, $assignee);
            $manager->comment($ticket, $assignee, 'Thanks for reporting. We are able to reproduce the issue and are working on a fix.');

            if ($step(random_int(15, 180))) {
                $manager->logWork($ticket, $assignee, fake()->randomElement($this->investigationLogs));

                // Longer-running tickets pick up a second note from whoever else looked at it.
                if (fake()->boolean(35) && $step(random_int(60, 8 * 60))) {
                    $manager->logWork($ticket, $staff->random(), fake()->randomElement($this->investigationLogs));
                }
            }

            if ($stage === 3) {
                if ($step(random_int(30, 240))) {
                    $manager->comment($ticket, $assignee, 'Could you share a screenshot and the exact time the error happened?');
                    $manager->changeStatus($ticket, TicketStatus::PendingClient, $assignee);
                }

                continue;
            }

            if ($stage < 4) {
                continue;
            }

            if (fake()->boolean(25)) {
                if (! $step(random_int(30, 240))) {
                    continue;
                }
                $manager->comment($ticket, $assignee, 'Could you share a screenshot and the exact time the error happened?');
                $manager->changeStatus($ticket, TicketStatus::PendingClient, $assignee);

                if (! $step(random_int(60, 24 * 60))) {
                    continue;
                }
                $manager->comment($ticket, $client, 'Sure, it happened around 10:15am. Screenshot attached in the original report.');
            }

            if (! $step((int) ($targets['resolution'] * $speed))) {
                continue;
            }
            $manager->logWork($ticket, $assignee, fake()->randomElement($this->fixLogs));
            $manager->comment($ticket, $assignee, 'A fix has been deployed to production. Please verify on your side.');
            $manager->changeStatus($ticket, TicketStatus::Resolved, $assignee, fake()->randomElement([ResolutionType::CodeChange, ResolutionType::DataFix]));

            if (fake()->boolean(60) && $step(random_int(60, 48 * 60))) {
                $manager->changeStatus($ticket, TicketStatus::Closed, $client);
            }
        }

        CarbonImmutable::setTestNow();

        $manager->checkSla();
    }
}
