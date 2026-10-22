/**
 * Shows the browser push notifications sent by App\Notifications and opens
 * the linked ticket when one is clicked. The payload is the JSON form of
 * NotificationChannels\WebPush\WebPushMessage.
 */
self.addEventListener('push', (event) => {
    const { title = 'FixWatch', ...options } = event.data ? event.data.json() : {};

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const url = new URL(event.notification.data?.url ?? '/', self.location.origin).href;

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
            const open = windows.find((client) => client.url === url);

            return open ? open.focus() : self.clients.openWindow(url);
        }),
    );
});
