/**
 * Browser push: the toggle on Settings → Notifications and the card offering
 * it after sign-in. The server keeps one subscription per browser; these ask
 * the browser for one and hand it to the Livewire component they sit in
 * (App\Concerns\ManagesPushSubscriptions).
 */
const SNOOZE_KEY = 'push-prompt-snoozed-until';
const SNOOZE_DAYS = 14;

document.addEventListener('alpine:init', () => {
    window.Alpine.data('pushToggle', (vapidKey) => ({
        // unsupported | ios-install | insecure | denied | off | on
        state: 'off',
        busy: false,

        async init() {
            if (!window.isSecureContext) {
                this.state = 'insecure';
            } else if (!pushSupported()) {
                // iOS only offers push to sites added to the home screen.
                this.state = /iPad|iPhone|iPod/.test(navigator.userAgent) ? 'ios-install' : 'unsupported';
            } else if (Notification.permission === 'denied') {
                this.state = 'denied';
            } else {
                const subscription = await currentSubscription();
                const known = subscription && (await this.$wire.isSubscribed(subscription.endpoint));
                this.state = known ? 'on' : 'off';
            }
        },

        async enable() {
            this.busy = true;

            try {
                this.state = await subscribeToPush(this.$wire, vapidKey);
            } finally {
                this.busy = false;
            }
        },

        async disable() {
            this.busy = true;

            try {
                const subscription = await currentSubscription();

                if (subscription) {
                    await this.$wire.unsubscribe(subscription.endpoint);
                    await subscription.unsubscribe();
                }

                this.cleared();
            } finally {
                this.busy = false;
            }
        },

        // Switched off on purpose, so the sign-in card should not offer it straight back.
        cleared() {
            snooze();

            if (this.state === 'on') {
                this.state = 'off';
            }
        },
    }));

    // The server only renders this for users with no device switched on; here it
    // also stays away where the browser blocks notifications or was snoozed.
    window.Alpine.data('pushPrompt', (vapidKey) => ({
        show: false,
        busy: false,

        init() {
            this.show = window.isSecureContext && pushSupported() && Notification.permission !== 'denied' && !snoozed();
        },

        async enable() {
            this.busy = true;

            try {
                await subscribeToPush(this.$wire, vapidKey);
            } finally {
                this.busy = false;
                this.show = false;
            }
        },

        later() {
            snooze();
            this.show = false;
        },
    }));
});

function pushSupported() {
    return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
}

function snooze() {
    try {
        localStorage.setItem(SNOOZE_KEY, String(Date.now() + SNOOZE_DAYS * 24 * 60 * 60 * 1000));
    } catch {
        // Storage blocked: the card simply comes back on the next page.
    }
}

function snoozed() {
    try {
        return Number(localStorage.getItem(SNOOZE_KEY) ?? 0) > Date.now();
    } catch {
        return false;
    }
}

async function currentSubscription() {
    const registration = await navigator.serviceWorker.getRegistration('/');

    return registration ? registration.pushManager.getSubscription() : null;
}

/**
 * Ask for permission, subscribe this browser and save it on the server.
 * Returns the resulting state for the settings toggle: 'on', 'off' or 'denied'.
 */
async function subscribeToPush($wire, vapidKey) {
    if ((await Notification.requestPermission()) !== 'granted') {
        return Notification.permission === 'denied' ? 'denied' : 'off';
    }

    const registration = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
    await navigator.serviceWorker.ready;

    const subscription =
        (await registration.pushManager.getSubscription()) ??
        (await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(vapidKey),
        }));

    const encoding = (PushManager.supportedContentEncodings ?? ['aesgcm']).includes('aes128gcm') ? 'aes128gcm' : 'aesgcm';

    await $wire.subscribe(subscription.toJSON(), encoding);

    return 'on';
}

function urlBase64ToUint8Array(base64) {
    const padded = (base64 + '='.repeat((4 - (base64.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');

    return Uint8Array.from(atob(padded), (char) => char.charCodeAt(0));
}
