<flux:card>
    <flux:accordion transition>
        <flux:accordion.item :heading="__('1. Signing in and your account')" expanded>
            <flux:text>{{ __('Your account is created for you by the administrator. There is no public sign-up.') }}</flux:text>
            <x-manual.steps bullets>
                <li>{{ __('Sign in with the email and password you were given, or use the link emailed to you to set your own password.') }}</li>
                <li>{{ __('Forgot your password? Click "Forgot your password?" on the sign-in page to get a reset link by email.') }}</li>
                <li>{{ __('Click your name at the bottom of the sidebar and choose Settings to change your name, email or password, switch on two-factor authentication, or change between light and dark mode.') }}</li>
                <li>{{ __('Under Settings → Security you can also add a passkey, which lets you sign in with your fingerprint, face or device PIN instead of typing your password.') }}</li>
                <li>{{ __('Under Settings → Notifications you can switch on browser popups for ticket updates (see "Emails and notifications" below).') }}</li>
            </x-manual.steps>
        </flux:accordion.item>

        <flux:accordion.item :heading="__('2. Your dashboard')">
            <flux:text>{{ __('The dashboard is the first page after you sign in. It shows:') }}</flux:text>
            <x-manual.steps bullets>
                <li>{{ __('An orange notice when the support team is waiting for your reply.') }}</li>
                <li>{{ __('A green notice when a fix is ready for you to check.') }}</li>
                <li>{{ __('Counts of your open tickets, tickets waiting for your reply, and tickets fixed in the last 30 days.') }}</li>
                <li>{{ __('Your most recently updated tickets. Click a ticket title to open it.') }}</li>
            </x-manual.steps>
        </flux:accordion.item>

        <flux:accordion.item :heading="__('3. Reporting a bug')">
            <x-manual.steps>
                <li>{{ __('Click "Report a Bug" in the sidebar.') }}</li>
                <li>{{ __('Choose which system has the problem. You only see the systems built for your company.') }}</li>
                <li>{{ __('Write a short summary, for example "Invoice PDF shows the wrong total".') }}</li>
                <li>{{ __('Pick the type of issue and the environment (Production is the live system your staff use every day).') }}</li>
                <li>{{ __('Choose how severe it is. This sets how quickly the team must respond and fix it (see the table below).') }}</li>
                <li>{{ __('Describe what happened and what you expected instead. Include any error message you saw.') }}</li>
                <li>{{ __('Optional but very helpful: list the steps to reproduce the problem, and attach screenshots or files (up to 5 files, 10 MB each).') }}</li>
                <li>{{ __('Click "Submit ticket". You get a reference number such as :example.', ['example' => config('sla.reference_prefix').'-'.now()->format('Y').'-00012']) }}</li>
            </x-manual.steps>

            <flux:heading class="mt-4 mb-2">{{ __('Choosing the right severity') }}</flux:heading>
            @include('livewire.manual.partials.sla-targets')
            <flux:text size="sm" class="mt-2">{{ __('Please choose honestly. The support team may adjust the priority after reviewing your report.') }}</flux:text>
            <flux:text size="sm" class="mt-2">{{ __('You can report a bug at any time. For a severity counted in business hours, a report sent at night, at the weekend or on a public holiday starts counting when our office next opens.') }}</flux:text>
        </flux:accordion.item>

        <flux:accordion.item :heading="__('4. Following your tickets')">
            <flux:text>{{ __('"My Tickets" lists the tickets you reported. If you are your company\'s client admin, the page is called "Company Tickets" and lists every ticket reported by anyone in your company, with the reporter\'s name. Use the search box and the filters at the top to narrow the list, and click a column heading to sort. To keep a set of filters, click "Save view" and give it a name.') }}</flux:text>
            <flux:text class="mt-2">{{ __('If someone else already reported the same problem, our team may merge your ticket into theirs. You will get an email, your ticket closes with a link to the one we are working on, and that ticket appears in your list too. You will receive its updates and can reply on it.') }}</flux:text>

            <flux:heading class="mt-4 mb-2">{{ __('Ticket statuses') }}</flux:heading>
            @include('livewire.manual.partials.statuses')

            <flux:heading class="mt-4 mb-2">{{ __('The Target / SLA badge') }}</flux:heading>
            <flux:text>{{ __('Every ticket has a deadline based on its severity. The badge tells you whether we are meeting it.') }}</flux:text>
            @include('livewire.manual.partials.sla-states')
        </flux:accordion.item>

        <flux:accordion.item :heading="__('5. Replying and adding files')">
            <x-manual.steps>
                <li>{{ __('Open the ticket and scroll to the reply box under the Activity timeline.') }}</li>
                <li>{{ __('Type your message. To attach files, drop them on "Attach files" or click it to browse.') }}</li>
                <li>{{ __('Click "Send reply", or press Ctrl + Enter (Cmd + Enter on a Mac).') }}</li>
            </x-manual.steps>
            <flux:text>{{ __('The Activity timeline shows every reply and status change in order, so you can always see what has happened so far.') }}</flux:text>
        </flux:accordion.item>

        <flux:accordion.item :heading="__('6. Seeing what the team has done')">
            <flux:text>{{ __('An open ticket has two tabs:') }}</flux:text>
            <x-manual.steps bullets>
                <li><flux:text inline variant="strong">{{ __('Activity') }}</flux:text> — {{ __('the conversation. Every reply and status change, in order. This is the tab you start on.') }}</li>
                <li><flux:text inline variant="strong">{{ __('Work Log') }}</flux:text> — {{ __('the record the support team keeps of what they investigated and fixed, sometimes with screenshots or files attached.') }}</li>
            </x-manual.steps>
            <flux:text>{{ __('A green number on the "Work Log" tab means the team has added entries you have not read yet. Click the tab to read them and the number disappears. You are emailed about new entries as well, so you will not miss them while you are looking at the Activity tab.') }}</flux:text>
            <flux:text class="mt-2">{{ __('Only the support team can write in the work log. The ticket page refreshes itself every minute, so new replies and entries appear without you reloading.') }}</flux:text>
        </flux:accordion.item>

        <flux:accordion.item :heading="__('7. When the team needs more information')">
            <flux:text>{{ __('If the team cannot continue without your input, the ticket changes to "Pending Client" and you receive an email.') }}</flux:text>
            <x-manual.steps bullets>
                <li>{{ __('The deadline clock is paused while the ticket waits for you, so the fix may take longer the later you reply.') }}</li>
                <li>{{ __('Open the ticket and reply with the requested information.') }}</li>
                <li>{{ __('As soon as you reply, the ticket goes back to the team automatically and the clock starts again.') }}</li>
            </x-manual.steps>
        </flux:accordion.item>

        <flux:accordion.item :heading="__('8. Confirming a fix, or reopening')">
            <flux:text>{{ __('When the team marks a ticket as "Resolved" you receive an email and a green box appears at the top of the ticket. Please test the fix, then:') }}</flux:text>
            <x-manual.steps bullets>
                <li><flux:text inline variant="strong">{{ __('It works:') }}</flux:text> {{ __('click "Confirm fix". The ticket is closed.') }}</li>
                <li><flux:text inline variant="strong">{{ __('It is still broken:') }}</flux:text> {{ __('write what is still wrong in the reply box, then click "Still not fixed". The ticket is reopened and the team is notified.') }}</li>
            </x-manual.steps>
            @if (config('sla.auto_close_days') > 0)
                <flux:callout icon="clock" class="mt-3">
                    <flux:callout.text>{{ trans_choice('{1} If you do nothing for 1 day, the ticket is closed automatically.|[2,*] If you do nothing for :count days, the ticket is closed automatically.', config('sla.auto_close_days')) }}</flux:callout.text>
                </flux:callout>
            @endif
            <flux:text class="mt-3">{{ __('A closed ticket cannot be replied to. If the problem comes back later, please report a new bug and mention the old reference number.') }}</flux:text>
        </flux:accordion.item>

        <flux:accordion.item :heading="__('9. Emails and notifications')">
            <flux:heading class="mb-2">{{ __('Emails you will receive') }}</flux:heading>
            <x-manual.steps bullets>
                <li>{{ __('When the status of your ticket changes (for example to In Progress, Pending Client or Resolved).') }}</li>
                <li>{{ __('When the support team replies to your ticket.') }}</li>
                <li>{{ __('When the support team adds a work log entry to a ticket you reported.') }}</li>
                <li>{{ __('When your ticket is merged into another report of the same problem. After that, you receive the emails above for that ticket instead.') }}</li>
            </x-manual.steps>
            <flux:text>{{ __('Each email has a button that takes you straight to the ticket.') }}</flux:text>

            @if (filled($reminderTimes = config('sla.reminders.times')))
                <flux:heading class="mt-4 mb-2">{{ __('Daily reminders') }}</flux:heading>
                <flux:text>{{ __('At :times each day, if any of your tickets are waiting on you, you receive one summary email listing them:', ['times' => \Illuminate\Support\Arr::join($reminderTimes, ', ', ' '.__('and').' ')]) }}</flux:text>
                <x-manual.steps bullets>
                    <li>{{ __('Tickets in "Pending Client", where the team needs your reply to continue.') }}</li>
                    <li>{{ __('Tickets in "Resolved", where the team is waiting for you to confirm the fix or reopen it.') }}</li>
                </x-manual.steps>
                <flux:text>{{ __('A ticket only starts appearing the day after it changed to Pending Client or Resolved, because you were already emailed about that change. After that it is included at most once a day. Client admins receive one summary for the whole company. Once you reply, or confirm or reopen the fix, the reminders for that ticket stop.') }}</flux:text>
            @endif

            @include('livewire.manual.partials.browser-notifications')
        </flux:accordion.item>
    </flux:accordion>
</flux:card>
