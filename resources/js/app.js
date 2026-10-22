/**
 * Browser push toggle on Settings → Notifications. The server keeps one
 * subscription per browser; this asks the browser for one, hands it to the
 * Livewire component, and reflects whether this device is the one enabled.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('pushToggle', (vapidKey) => ({
        // unsupported | ios-install | insecure | denied | off | on
        state: 'off',
        busy: false,

        async init() {
            if (!window.isSecureContext) {
                this.state = 'insecure';
            } else if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
                // iOS only offers push to sites added to the home screen.
                this.state = /iPad|iPhone|iPod/.test(navigator.userAgent) ? 'ios-install' : 'unsupported';
            } else if (Notification.permission === 'denied') {
                this.state = 'denied';
            } else {
                const subscription = await this.currentSubscription();
                const known = subscription && (await this.$wire.isSubscribed(subscription.endpoint));
                this.state = known ? 'on' : 'off';
            }
        },

        async currentSubscription() {
            const registration = await navigator.serviceWorker.getRegistration('/');

            return registration ? registration.pushManager.getSubscription() : null;
        },

        async enable() {
            this.busy = true;

            try {
                if ((await Notification.requestPermission()) !== 'granted') {
                    this.state = Notification.permission === 'denied' ? 'denied' : 'off';

                    return;
                }

                const registration = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
                await navigator.serviceWorker.ready;

                const subscription =
                    (await registration.pushManager.getSubscription()) ??
                    (await registration.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: urlBase64ToUint8Array(vapidKey),
                    }));

                const encoding = (PushManager.supportedContentEncodings ?? ['aesgcm']).includes('aes128gcm')
                    ? 'aes128gcm'
                    : 'aesgcm';

                await this.$wire.subscribe(subscription.toJSON(), encoding);
                this.state = 'on';
            } finally {
                this.busy = false;
            }
        },

        async disable() {
            this.busy = true;

            try {
                const subscription = await this.currentSubscription();

                if (subscription) {
                    await this.$wire.unsubscribe(subscription.endpoint);
                    await subscription.unsubscribe();
                }

                this.state = 'off';
            } finally {
                this.busy = false;
            }
        },
    }));
});

function urlBase64ToUint8Array(base64) {
    const padded = (base64 + '='.repeat((4 - (base64.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');

    return Uint8Array.from(atob(padded), (char) => char.charCodeAt(0));
}
