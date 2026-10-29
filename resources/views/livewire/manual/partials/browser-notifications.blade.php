<flux:heading class="mt-4 mb-2">{{ __('Browser notifications') }}</flux:heading>
<flux:text>{{ __('You can also get a popup on your computer or phone for the same updates, even when the app is not open in a tab. It works alongside the emails you have left on, so you can switch it on or off at any time.') }}</flux:text>
<flux:text class="mt-2">{{ __('Until you switch this on, a card in the bottom corner offers it after you sign in. Click "Enable" and then "Allow" when the browser asks. "Not now" hides the card for :days days, and so does turning notifications off in Settings. You can also switch it on yourself at any time:', ['days' => 14]) }}</flux:text>
<x-manual.steps>
    <li>{{ __('Click your name at the bottom of the sidebar, choose Settings, then Notifications.') }}</li>
    <li>{{ __('Click "Enable on this device" and choose "Allow" when the browser asks.') }}</li>
    <li>{{ __('Repeat on each computer or phone where you want popups. Each device is switched on separately.') }}</li>
</x-manual.steps>
<x-manual.steps bullets>
    <li>{{ __('Click a popup to open the ticket. Several updates on the same ticket replace each other instead of piling up.') }}</li>
    <li><flux:text inline variant="strong">{{ __('iPhone and iPad:') }}</flux:text> {{ __('open the app in Safari, tap Share → "Add to Home Screen", then open it from the home screen icon and enable notifications there.') }}</li>
    <li><flux:text inline variant="strong">{{ __('Nothing appears?') }}</flux:text> {{ __('Check that your computer allows notifications from your browser (on a Mac: System Settings → Notifications) and that Do Not Disturb or Focus is off.') }}</li>
    <li><flux:text inline variant="strong">{{ __('Clicked "Block" by mistake?') }}</flux:text> {{ __('Allow notifications for this site in your browser\'s site settings (the icon left of the address bar), then reload the Notifications page.') }}</li>
    <li><flux:text inline variant="strong">{{ __('Shared or lost device?') }}</flux:text> {{ __('Popups belong to the browser, not to your sign-in, so they keep arriving after you sign out. Click "Turn off" on a shared computer before leaving it, or "Turn off on all devices" for one you no longer have.') }}</li>
</x-manual.steps>
