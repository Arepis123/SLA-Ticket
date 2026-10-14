<flux:card>
    <flux:accordion transition>
        <flux:accordion.item :heading="__('1. Your role')" expanded>
            <flux:text>{{ __('As a member of the support team you can see and work on tickets from every client. You cannot open the Administration pages (Clients, Projects, Users, SLA Policies); those are for administrators only.') }}</flux:text>
            <flux:text class="mt-2">{{ __('Click your name at the bottom of the sidebar and choose Settings to manage your profile, password, two-factor authentication, passkeys and appearance. A passkey lets you sign in with your fingerprint, face or device PIN instead of typing a password; add one under Settings → Security.') }}</flux:text>
        </flux:accordion.item>

        <flux:accordion.item :heading="__('2. Reading the SLA dashboard')">
            <x-manual.steps bullets>
                <li><flux:text inline variant="strong">{{ __('Open tickets') }}</flux:text> — {{ __('everything not yet resolved, with how many have no assignee.') }}</li>
                <li><flux:text inline variant="strong">{{ __('SLA breached') }}</flux:text> — {{ __('open tickets that have missed a deadline. Deal with these first.') }}</li>
                <li><flux:text inline variant="strong">{{ __('At risk') }}</flux:text> — {{ __('open tickets that have used :pct% or more of their time.', ['pct' => config('sla.at_risk_threshold')]) }}</li>
                <li><flux:text inline variant="strong">{{ __('SLA compliance (30d)') }}</flux:text> — {{ __('the share of tickets resolved in the last 30 days that were fixed on time.') }}</li>
                <li><flux:text inline variant="strong">{{ __('Needs attention') }}</flux:text> — {{ __('breached tickets first, then the ones closest to their deadline.') }}</li>
            </x-manual.steps>
            <flux:text>{{ __('Click any number to open the matching ticket list. The dashboard refreshes itself every minute.') }}</flux:text>
        </flux:accordion.item>

        <flux:accordion.item :heading="__('3. Finding tickets')">
            <flux:text>{{ __('The Tickets page lists all tickets. Useful filters:') }}</flux:text>
            <x-manual.steps bullets>
                <li>{{ __('Assignee → "Assigned to me" for your own queue, or "Unassigned" to find tickets nobody has picked up.') }}</li>
                <li>{{ __('Status → "All unresolved" to hide finished tickets.') }}</li>
                <li>{{ __('SLA → "Breached" or "At risk" to see what is urgent.') }}</li>
                <li>{{ __('Click the SLA column heading to sort by deadline.') }}</li>
            </x-manual.steps>
        </flux:accordion.item>

        <flux:accordion.item :heading="__('4. Working on a ticket')">
            <x-manual.steps>
                <li>{{ __('Open the ticket. If it has no assignee, click "Assign to me" at the top right.') }}</li>
                <li>{{ __('Read the description, steps to reproduce and attachments.') }}</li>
                <li>{{ __('Use the Manage panel on the right to change Status, Priority or Assignee. Changes are saved the moment you pick a value.') }}</li>
                <li>{{ __('Keep the client informed with replies as you make progress.') }}</li>
                <li>{{ __('When the fix is delivered, set the status to "Resolved". The client is asked to confirm.') }}</li>
            </x-manual.steps>

            <flux:heading class="mt-4 mb-2">{{ __('Statuses and what they do to the clock') }}</flux:heading>
            @include('livewire.manual.partials.statuses')
            <flux:text size="sm" class="mt-2">{{ __('"Reopened" is set by the client when they reject a fix. You cannot choose it yourself.') }}</flux:text>
        </flux:accordion.item>

        <flux:accordion.item :heading="__('5. Replies and internal notes')">
            <x-manual.steps bullets>
                <li><flux:text inline variant="strong">{{ __('Reply') }}</flux:text> — {{ __('visible to the client, who is also emailed.') }}</li>
                <li><flux:text inline variant="strong">{{ __('Internal note') }}</flux:text> — {{ __('switch on "Internal note" before sending. Only the support team can see it, shown with an amber background. Use it for root-cause notes and handovers.') }}</li>
                <li><flux:text inline variant="strong">{{ __('Reply and change status together') }}</flux:text> — {{ __('use the "Keep status" dropdown in the reply box, for example to ask a question and set "Pending Client" in one step.') }}</li>
            </x-manual.steps>
            <flux:text>{{ __('Send with the button or Ctrl + Enter (Cmd + Enter on a Mac). Files attached to an internal note are hidden from the client too.') }}</flux:text>
        </flux:accordion.item>

        <flux:accordion.item :heading="__('6. The work log')">
            <flux:text>{{ __('Every ticket has a "Work Log" tab next to "Activity". Use it to record what you actually did: what you investigated, what you found, and what you changed.') }}</flux:text>
            <x-manual.steps bullets>
                <li>{{ __('Only the support team can write entries, but the client can read all of them. Anything you do not want the client to see belongs in an internal note instead.') }}</li>
                <li>{{ __('The editor handles headings, bold, italic, lists, quotes and links, so longer notes stay readable.') }}</li>
                <li>{{ __('The person who reported the ticket is emailed each time you add an entry.') }}</li>
                <li>{{ __('Entries cannot be edited or deleted afterwards, so the log stays a reliable record of the work.') }}</li>
            </x-manual.steps>
            <flux:text>{{ __('In short: the work log is the technical record of the fix, replies are for talking to the client, and internal notes are for anything private to the team.') }}</flux:text>
        </flux:accordion.item>

        <flux:accordion.item :heading="__('7. How the SLA clock works')">
            <flux:text>{{ __('Each priority has two targets, counted 24 hours a day, 7 days a week from the moment the ticket is opened:') }}</flux:text>
            <div class="mt-2">
                @include('livewire.manual.partials.sla-targets')
            </div>
            <x-manual.steps bullets class="mt-3">
                <li><flux:text inline variant="strong">{{ __('First response') }}</flux:text> — {{ __('is recorded when a team member sends a reply to the client or changes the status. An internal note does not count.') }}</li>
                <li><flux:text inline variant="strong">{{ __('Pausing') }}</flux:text> — {{ __('the clock stops while a ticket is Pending Client, Resolved or Closed. When work resumes, the deadlines move back by the time it was paused.') }}</li>
                <li><flux:text inline variant="strong">{{ __('Changing priority') }}</flux:text> — {{ __('recalculates both deadlines from the original opening time using the new priority.') }}</li>
                <li><flux:text inline variant="strong">{{ __('Checks') }}</flux:text> — {{ __('the system re-checks every few minutes and emails the assignee and the administrators when a ticket becomes at risk or breaches. An open ticket page also refreshes itself every minute, so new replies appear without reloading.') }}</li>
            </x-manual.steps>

            <flux:heading class="mt-4 mb-2">{{ __('SLA badges') }}</flux:heading>
            @include('livewire.manual.partials.sla-states')
        </flux:accordion.item>

        <flux:accordion.item :heading="__('8. Who opens tickets')">
            <flux:text>{{ __('Only clients can report a bug, so every ticket comes from the client\'s own account. If a client contacts you by phone or email instead, ask them to sign in and use "Report a Bug". This keeps the report, the deadline and the conversation in one place.') }}</flux:text>
        </flux:accordion.item>

        <flux:accordion.item :heading="__('9. Emails you will receive')">
            <x-manual.steps bullets>
                <li>{{ __('When a ticket is assigned to you.') }}</li>
                <li>{{ __('When a client replies to, confirms or reopens a ticket assigned to you.') }}</li>
                <li>{{ __('When a colleague adds an internal note to a ticket assigned to you.') }}</li>
                <li>{{ __('When a ticket assigned to you becomes at risk or breaches its SLA.') }}</li>
            </x-manual.steps>
        </flux:accordion.item>
    </flux:accordion>
</flux:card>
