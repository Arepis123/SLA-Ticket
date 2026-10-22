<flux:callout icon="information-circle" class="mb-4">
    <flux:callout.text>{{ __('Administrators can do everything in the Support team guide as well, with one difference: your Tickets page shows every ticket rather than opening on your own queue, and the "Assigned to me" filter is only offered to the support team. This guide covers the extra Administration pages.') }}</flux:callout.text>
</flux:callout>

<flux:card>
    <flux:accordion transition>
        <flux:accordion.item :heading="__('1. Setting up a new client')" expanded>
            <flux:text>{{ __('Do these in order, because each step depends on the one before:') }}</flux:text>
            <x-manual.steps>
                <li><flux:text inline variant="strong">{{ __('Clients') }}</flux:text> — {{ __('add the company.') }}</li>
                <li><flux:text inline variant="strong">{{ __('Projects') }}</flux:text> — {{ __('add each system you built for that company.') }}</li>
                <li><flux:text inline variant="strong">{{ __('Users') }}</flux:text> — {{ __('create a Client account for each person at the company who will report bugs.') }}</li>
            </x-manual.steps>
            <flux:text>{{ __('A client user can only report bugs against their own company\'s systems, and only sees the tickets they reported (plus any ticket one of theirs was merged into) unless you make them a client admin.') }}</flux:text>
        </flux:accordion.item>

        <flux:accordion.item :heading="__('2. Clients')">
            <x-manual.steps bullets>
                <li>{{ __('Click "Add client" and enter the company name and contact details.') }}</li>
                <li>{{ __('Use the "…" menu on a row to edit it or jump to its systems. The Systems and Users counts are links too.') }}</li>
                <li>{{ __('Switch a client to Inactive to stop new tickets: its systems disappear from the bug report form. Existing tickets are kept.') }}</li>
                <li>{{ __('Delete is only allowed for a client that has no tickets. Otherwise deactivate it.') }}</li>
            </x-manual.steps>
        </flux:accordion.item>

        <flux:accordion.item :heading="__('3. Projects (systems)')">
            <x-manual.steps bullets>
                <li>{{ __('Click "Add system", choose the client, and enter the system name. Code and URL are optional.') }}</li>
                <li>{{ __('Inactive systems are hidden from the bug report form but keep their ticket history.') }}</li>
                <li>{{ __('The "Open / total tickets" number links to that system\'s tickets.') }}</li>
                <li>{{ __('A system with tickets cannot be deleted; deactivate it instead.') }}</li>
            </x-manual.steps>
        </flux:accordion.item>

        <flux:accordion.item :heading="__('4. Users and roles')">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Role') }}</flux:table.column>
                    <flux:table.column>{{ __('Who it is for') }}</flux:table.column>
                    <flux:table.column>{{ __('What they can do') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    <flux:table.row>
                        <flux:table.cell><flux:badge size="sm" :color="\App\Enums\UserRole::Admin->color()">{{ \App\Enums\UserRole::Admin->label() }}</flux:badge></flux:table.cell>
                        <flux:table.cell class="whitespace-normal">{{ __('You, the system owner') }}</flux:table.cell>
                        <flux:table.cell class="whitespace-normal">{{ __('Everything, including the Administration pages.') }}</flux:table.cell>
                    </flux:table.row>
                    <flux:table.row>
                        <flux:table.cell><flux:badge size="sm" :color="\App\Enums\UserRole::Internal->color()">{{ \App\Enums\UserRole::Internal->label() }}</flux:badge></flux:table.cell>
                        <flux:table.cell class="whitespace-normal">{{ __('Your developers and support staff') }}</flux:table.cell>
                        <flux:table.cell class="whitespace-normal">{{ __('See and work on all tickets from all clients. No Administration pages.') }}</flux:table.cell>
                    </flux:table.row>
                    <flux:table.row>
                        <flux:table.cell><flux:badge size="sm" :color="\App\Enums\UserRole::Client->color()">{{ \App\Enums\UserRole::Client->label() }}</flux:badge></flux:table.cell>
                        <flux:table.cell class="whitespace-normal">{{ __('People at a client company') }}</flux:table.cell>
                        <flux:table.cell class="whitespace-normal">{{ __('Report bugs and follow the tickets they reported. A client admin sees and can reply to every ticket of their company. Cannot see internal notes.') }}</flux:table.cell>
                    </flux:table.row>
                </flux:table.rows>
            </flux:table>

            <x-manual.steps bullets class="mt-3">
                <li>{{ __('Click "Add user". A Client user must be linked to a client company.') }}</li>
                <li>{{ __('Switch on "Client admin" for the person at a client company who should see every ticket of that company. Other client users only see the tickets they reported themselves, plus any ticket one of their reports was merged into.') }}</li>
                <li>{{ __('Either type a password for them, or tick "Email the user a link to set their own password".') }}</li>
                <li>{{ __('"Send password reset link" in the "…" menu emails a reset link at any time.') }}</li>
                <li>{{ __('"Deactivate" blocks sign-in and signs the user out on their next click. Their tickets and replies are kept.') }}</li>
                <li>{{ __('You cannot deactivate yourself or remove your own Admin role, so you cannot lock yourself out.') }}</li>
            </x-manual.steps>
        </flux:accordion.item>

        <flux:accordion.item :heading="__('5. SLA Policies')">
            <flux:text>{{ __('SLA Policies sets, for each priority, how many hours the team has to send a first response and to resolve the ticket. Current values:') }}</flux:text>
            <div class="mt-2">
                @include('livewire.manual.partials.sla-targets')
            </div>
            <x-manual.steps bullets class="mt-3">
                <li>{{ __('Enter hours (decimals allowed, for example 0.5 for 30 minutes) and click "Save policies".') }}</li>
                <li>{{ __('The resolution target must be at least as long as the response target.') }}</li>
                <li>{{ __('New targets apply to tickets opened afterwards, and to a ticket whose priority is changed. Tickets that are already open keep their current deadlines. The same goes for switching a priority\'s Clock.') }}</li>
                <li>{{ __('Each priority\'s "Clock" is either 24/7 or Business hours. A business-hours clock only runs during the opening hours set under Administration → Business Hours, and never on holidays. A common setup is Critical on 24/7 and everything else on business hours.') }}</li>
                <li>{{ __('Either clock pauses while a ticket is Pending Client, Resolved or Closed.') }}</li>
            </x-manual.steps>

            <flux:heading class="mt-4 mb-2">{{ __('Business hours and holidays') }}</flux:heading>
            <x-manual.steps bullets>
                <li>{{ __('Open Administration → Business Hours. Switch each day on or off and set its opening and closing time, then click "Save hours".') }}</li>
                <li>{{ __('Add public holidays with a date and a name. The office counts as closed all day.') }}</li>
                <li>{{ __('Saving the hours or adding or removing a holiday recalculates the deadlines of open tickets straight away. Tickets that already breached keep their deadline.') }}</li>
                <li>{{ __('Times are in the timezone shown at the top of the page (SLA_TIMEZONE, below). Check it before you rely on the hours.') }}</li>
                <li>{{ __('Clients can still report bugs at any time. A ticket on a business-hours priority that arrives at night, at the weekend or on a holiday starts counting when the office next opens; one on a 24/7 priority starts counting straight away.') }}</li>
                <li>{{ __('Clients choose the severity themselves, so a 24/7 priority is the faster one to pick out of hours. Lower it on the ticket if it was chosen too high; the deadlines are then recalculated on that priority\'s clock.') }}</li>
            </x-manual.steps>
        </flux:accordion.item>

        <flux:accordion.item :heading="__('6. Monitoring and alerts')">
            <flux:text>{{ __('The SLA dashboard is your overview; see "Reading the SLA dashboard" in the Support team guide. In addition, every administrator is emailed when:') }}</flux:text>
            <x-manual.steps bullets>
                <li>{{ __('A client submits a new ticket.') }}</li>
                <li>{{ __('A client replies to, confirms or reopens any ticket.') }}</li>
                <li>{{ __('Any ticket becomes at risk (:pct% of its time used) or breaches its first-response or resolution target.', ['pct' => config('sla.at_risk_threshold')]) }}</li>
            </x-manual.steps>
            <flux:text>{{ __('Each of these also arrives as a browser popup if you switch that on under Settings → Notifications; see "Emails and notifications" in the Support team guide. Clients can switch it on for themselves the same way.') }}</flux:text>
            <flux:text class="mt-2">{{ __('Each ticket\'s Activity timeline keeps a permanent record of every status, priority and assignee change and every SLA event. The Work Log tab is a second permanent record, written by the support team and readable by the client; see "The work log" in the Support team guide.') }}</flux:text>
        </flux:accordion.item>

        <flux:accordion.item :heading="__('7. Reports')">
            <flux:text>{{ __('Reports shows SLA performance for the tickets opened in a period you choose. Only administrators can open it.') }}</flux:text>
            <x-manual.steps bullets>
                <li>{{ __('Pick a date range (or a preset such as "Last 30 Days"), and optionally one client or system.') }}</li>
                <li>{{ __('The cards show tickets opened, resolved and breached, SLA compliance, and average first-response and resolution times.') }}</li>
                <li>{{ __('The Breakdown tabs split the same figures by client, system, priority, assignee and category.') }}</li>
                <li>{{ __('"Export" downloads the chosen filters either as a PDF report with the company logo in the header, or as a CSV with one row per ticket, ready for Excel.') }}</li>
            </x-manual.steps>
            <flux:text>{{ __('A ticket counts in the period it was opened in, even if it was resolved later. Duplicates merged into another ticket are left out, both from the figures and from the CSV export.') }}</flux:text>
        </flux:accordion.item>

        <flux:accordion.item :heading="__('8. Keeping the system running')">
            <flux:text>{{ __('Three jobs have to run in the background, otherwise deadlines are never checked and no email or browser notification is ever sent:') }}</flux:text>
            <x-manual.steps bullets>
                <li><flux:text inline variant="strong">{{ __('SLA check') }}</flux:text> — {{ __('flags at-risk and breached tickets, and auto-closes resolved ones.') }}</li>
                <li><flux:text inline variant="strong">{{ __('Client reminders') }}</flux:text> — {{ __('at the times set in SLA_REMINDER_TIMES, emails each client a summary of their tickets waiting on them (Pending Client, or Resolved and not yet confirmed). A ticket is included at most once a day, starting the day after it changed status, since the status email already told the client that day.') }}</li>
                <li><flux:text inline variant="strong">{{ __('Queue worker') }}</flux:text> — {{ __('sends the emails and browser notifications that the rest of the system queues up. Notifications go out the next time it runs, so run it every minute if you want them to feel instant.') }}</li>
            </x-manual.steps>
            <flux:text>{{ __('There are two ways to run them, depending on what the hosting allows:') }}</flux:text>
            <x-manual.steps>
                <li>{{ __('If the server can run commands on a schedule, one cron entry calling "artisan schedule:run" every minute does both.') }}</li>
                <li>{{ __('On hosting that cannot do that (such as a Plesk subscription without command tasks), schedule the address /cron.php instead, with its token, every one to two minutes. It runs exactly the same three jobs.') }}</li>
            </x-manual.steps>
            <flux:callout icon="exclamation-triangle" class="mt-3">
                <flux:callout.text>{{ __('Whichever method is used, if it stops running the system looks healthy but goes quiet: no SLA badges change, no tickets auto-close, and no email or browser notification arrives. If people stop receiving email, check this first.') }}</flux:callout.text>
            </flux:callout>
            <flux:text class="mt-2">{{ __('These settings live in the server\'s .env file:') }}</flux:text>
            <flux:table class="mt-2">
                <flux:table.columns>
                    <flux:table.column>{{ __('Setting') }}</flux:table.column>
                    <flux:table.column>{{ __('What it controls') }}</flux:table.column>
                    <flux:table.column>{{ __('Current value') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    <flux:table.row>
                        <flux:table.cell class="font-mono">SLA_AT_RISK_THRESHOLD</flux:table.cell>
                        <flux:table.cell class="whitespace-normal">{{ __('Percentage of time used before a ticket is flagged "at risk".') }}</flux:table.cell>
                        <flux:table.cell variant="strong">{{ config('sla.at_risk_threshold') }}%</flux:table.cell>
                    </flux:table.row>
                    <flux:table.row>
                        <flux:table.cell class="font-mono">SLA_AUTO_CLOSE_DAYS</flux:table.cell>
                        <flux:table.cell class="whitespace-normal">{{ __('Days before an unconfirmed resolved ticket is closed. 0 turns this off.') }}</flux:table.cell>
                        <flux:table.cell variant="strong">{{ config('sla.auto_close_days') }}</flux:table.cell>
                    </flux:table.row>
                    <flux:table.row>
                        <flux:table.cell class="font-mono">SLA_TIMEZONE</flux:table.cell>
                        <flux:table.cell class="whitespace-normal">{{ __('Timezone the business hours and holidays are in, for example Asia/Kuala_Lumpur. Empty uses the app timezone.') }}</flux:table.cell>
                        <flux:table.cell variant="strong">{{ app(\App\Support\BusinessCalendar::class)->timezone() }}</flux:table.cell>
                    </flux:table.row>
                    <flux:table.row>
                        <flux:table.cell class="font-mono">SLA_REMINDER_TIMES</flux:table.cell>
                        <flux:table.cell class="whitespace-normal">{{ __('Times of day (24-hour, comma separated, in SLA_TIMEZONE) when clients are sent their reminder summary. Empty turns reminders off.') }}</flux:table.cell>
                        <flux:table.cell variant="strong">{{ implode(', ', config('sla.reminders.times')) ?: __('Off') }}</flux:table.cell>
                    </flux:table.row>
                    <flux:table.row>
                        <flux:table.cell class="font-mono">SLA_REFERENCE_PREFIX</flux:table.cell>
                        <flux:table.cell class="whitespace-normal">{{ __('Prefix of new ticket reference numbers.') }}</flux:table.cell>
                        <flux:table.cell variant="strong">{{ config('sla.reference_prefix') }}</flux:table.cell>
                    </flux:table.row>
                    <flux:table.row>
                        <flux:table.cell class="font-mono">MAIL_MAILER</flux:table.cell>
                        <flux:table.cell class="whitespace-normal">{{ __('How emails are sent. "log" only writes them to the log file and delivers nothing.') }}</flux:table.cell>
                        <flux:table.cell variant="strong">{{ config('mail.default') }}</flux:table.cell>
                    </flux:table.row>
                    <flux:table.row>
                        <flux:table.cell class="font-mono">SLA_CRON_TOKEN</flux:table.cell>
                        <flux:table.cell class="whitespace-normal">{{ __('Secret that lets the scheduled task reach /cron.php. Empty means that address is switched off.') }}</flux:table.cell>
                        <flux:table.cell variant="strong">{{ config('sla.cron_token') ? __('Set') : __('Not set') }}</flux:table.cell>
                    </flux:table.row>
                    <flux:table.row>
                        <flux:table.cell class="font-mono">VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY</flux:table.cell>
                        <flux:table.cell class="whitespace-normal">{{ __('Keys that sign browser notifications. Create them once with "php artisan webpush:vapid" and never change them: every device that was switched on stops receiving popups if they do. Empty means browser notifications are off. They also need the site to be served over https.') }}</flux:table.cell>
                        <flux:table.cell variant="strong">{{ config('webpush.vapid.public_key') && config('webpush.vapid.private_key') ? __('Set') : __('Not set') }}</flux:table.cell>
                    </flux:table.row>
                </flux:table.rows>
            </flux:table>
        </flux:accordion.item>
    </flux:accordion>
</flux:card>
