/**
 * Shows the browser push notifications sent by App\Notifications and opens
 * the linked ticket when one is clicked. The payload is the JSON form of
 * NotificationChannels\WebPush\WebPushMessage.
 */

// Take over open tabs straight away, so a new version of this file applies
// without closing every tab, and existing tabs can be navigated on click.
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

self.addEventListener('push', (event) => {
    const { title = 'FixWatch', ...options } = event.data ? event.data.json() : {};

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    // Keep only the path: the link is built from APP_URL, which may not match the
    // address this browser uses (localhost vs 127.0.0.1, www or not), and a
    // notification may only open pages on its own site.
    const link = new URL(event.notification.data?.url ?? '/', self.location.origin);
    const url = new URL(link.pathname + link.search + link.hash, self.location.origin).href;

    event.waitUntil(openTicket(url));
});

async function openTicket(url) {
    const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });

    const exact = windows.find((client) => client.url === url);
    if (exact) {
        return exact.focus();
    }

    // Reuse an open tab of the app rather than piling up new ones.
    const tab = windows.find((client) => 'navigate' in client);
    if (tab) {
        try {
            const focused = await tab.focus();
            return await (focused ?? tab).navigate(url);
        } catch {
            // Not controlled by this worker (opened before it was installed): open a new tab instead.
        }
    }

    return self.clients.openWindow(url);
}
