/**
 * Browser push: the toggle on Settings → Notifications and the card offering
 * it after sign-in. The server keeps one subscription per browser; these ask
 * the browser for one and hand it to the Livewire component they sit in
 * (App\Concerns\ManagesPushSubscriptions).
 */
const SNOOZE_KEY = 'push-prompt-snoozed-until';
const SNOOZE_DAYS = 14;

const SEEN_TICK_MS = 500;
const SEEN_DWELL_MS = 1500;
const SEEN_FLUSH_MS = 3000;

document.addEventListener('alpine:init', () => {
    // Set while a guided tour is on screen, so the push card waits instead of covering it.
    window.Alpine.store('tour', { open: false });

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

    // Ticket page: entries the server marked with data-seen count as seen once
    // they stay on screen (half of the entry, or half the screen for a tall one)
    // for SEEN_DWELL_MS while this tab is in front. Entries in a hidden tab panel
    // have no size, so they never count. Seen keys go up in batches; the server
    // drops the marker from them on its next render.
    window.Alpine.data('entrySeen', () => {
        const dwell = new Map();
        const queued = new Set();
        const sent = new Set();
        let ticker;
        let flusher;

        return {
            init() {
                ticker = setInterval(() => this.tick(), SEEN_TICK_MS);
                flusher = setInterval(() => this.flush(), SEEN_FLUSH_MS);
            },

            destroy() {
                clearInterval(ticker);
                clearInterval(flusher);
            },

            tick() {
                if (document.visibilityState !== 'visible' || !document.hasFocus()) {
                    return;
                }

                this.$el.querySelectorAll('[data-seen]').forEach((el) => {
                    const key = el.dataset.seen;

                    if (sent.has(key) || queued.has(key)) {
                        return;
                    }

                    if (!onScreen(el)) {
                        dwell.delete(key);

                        return;
                    }

                    const time = (dwell.get(key) ?? 0) + SEEN_TICK_MS;

                    if (time >= SEEN_DWELL_MS) {
                        dwell.delete(key);
                        queued.add(key);
                    } else {
                        dwell.set(key, time);
                    }
                });
            },

            flush() {
                if (queued.size === 0) {
                    return;
                }

                const keys = [...queued];
                queued.clear();
                keys.forEach((key) => sent.add(key));

                this.$wire.markEntriesSeen(keys);
            },
        };
    });

    // Guided tour (resources/views/components/tour.blade.php). Each step highlights
    // the first element on screen named in its `target` (a data-tour attribute)
    // and puts the card beside it. A step with no target, or none on screen
    // (sidebar links on a phone, say), shows the card in the middle instead.
    window.Alpine.data('tour', (steps) => ({
        steps,
        index: 0,
        open: false,
        // Placed once, so later moves animate instead of sliding in from the corner.
        ready: false,
        spot: null,
        card: { top: 0, left: 0 },

        get step() {
            return this.steps[this.index];
        },

        get last() {
            return this.index === this.steps.length - 1;
        },

        init() {
            this.place = this.place.bind(this);
            window.addEventListener('resize', this.place);
            window.addEventListener('scroll', this.place, true);

            this.open = true;
            window.Alpine.store('tour').open = true;
            this.go(0);
        },

        destroy() {
            window.Alpine.store('tour').open = false;
            window.removeEventListener('resize', this.place);
            window.removeEventListener('scroll', this.place, true);
        },

        next() {
            this.last ? this.finish() : this.go(this.index + 1);
        },

        back() {
            if (this.index > 0) {
                this.go(this.index - 1);
            }
        },

        go(index) {
            this.index = index;
            this.element()?.scrollIntoView({ block: 'center', behavior: 'smooth' });

            this.$nextTick(() => {
                this.place();
                this.$refs.next.focus();
                this.$nextTick(() => (this.ready = true));
            });
        },

        finish() {
            this.open = false;
            window.Alpine.store('tour').open = false;
            this.$wire.finishTour();

            // Drop ?tour=1 (from "Take the tour" in the manual) so a reload does not replay it.
            const url = new URL(window.location.href);

            if (url.searchParams.has('tour')) {
                url.searchParams.delete('tour');
                history.replaceState(history.state, '', url);
            }
        },

        element() {
            return (
                [this.step.target ?? []]
                    .flat()
                    .map((name) => document.querySelector(`[data-tour="${name}"]`))
                    .find((el) => {
                        const rect = el?.getBoundingClientRect();

                        // Hidden, or parked off screen like the sidebar on a phone.
                        return rect && rect.width > 0 && rect.right > 0 && rect.left < window.innerWidth;
                    }) ?? null
            );
        },

        place() {
            if (!this.open) {
                return;
            }

            const el = this.element();
            const { offsetWidth: width, offsetHeight: height } = this.$refs.card;
            const { innerWidth: vw, innerHeight: vh } = window;
            const margin = 16;
            const clamp = (value, room) => Math.max(margin, Math.min(value, room - margin));

            if (!el) {
                this.spot = null;
                this.card = { top: (vh - height) / 2, left: (vw - width) / 2 };

                return;
            }

            const rect = el.getBoundingClientRect();
            const pad = 6;
            const gap = pad + 12;

            this.spot = { top: rect.top - pad, left: rect.left - pad, width: rect.width + pad * 2, height: rect.height + pad * 2 };

            // Beside a tall element, line up with the middle of the part on screen.
            const middle = (Math.max(rect.top, 0) + Math.min(rect.bottom, vh) - height) / 2;

            const sides = {
                bottom: { top: rect.bottom + gap, left: rect.left, fits: rect.bottom + gap + height <= vh - margin },
                top: { top: rect.top - gap - height, left: rect.left, fits: rect.top - gap - height >= margin },
                right: { top: middle, left: rect.right + gap, fits: rect.right + gap + width <= vw - margin },
                left: { top: middle, left: rect.left - gap - width, fits: rect.left - gap - width >= margin },
            };
            // Where nothing fits, stay on the side the step asked for and only overlap as far as the screen forces.
            const side =
                [this.step.side, 'bottom', 'top', 'right'].map((name) => sides[name]).find((option) => option?.fits) ??
                sides[this.step.side] ??
                sides.bottom;

            this.card = { top: clamp(side.top, vh - height), left: clamp(side.left, vw - width) };
        },
    }));
});

function pushSupported() {
    return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
}

/**
 * At least half of the element is in the viewport, or it fills half the
 * viewport (a long entry can never be half visible on a small screen).
 */
function onScreen(el) {
    const rect = el.getBoundingClientRect();
    const height = window.innerHeight;
    const visible = Math.min(rect.bottom, height) - Math.max(rect.top, 0);

    return rect.height > 0 && visible > 0 && (visible >= rect.height / 2 || visible >= height / 2);
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
