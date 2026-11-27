/*
 | Donna's service worker. It exists for one thing: showing a notification
 | when she replies and the app isn't open, and bringing the app forward
 | when the notification is tapped. It caches nothing: the app needs the
 | network to be any use, so there is no offline mode to keep in step.
 |
 | Served from the site root so its scope is the whole app.
 */

self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('push', (event) => {
    let message = {};

    try {
        message = event.data ? event.data.json() : {};
    } catch {
        message = { body: event.data ? event.data.text() : '' };
    }

    // iOS requires every push to show a notification, so there is always one to show.
    event.waitUntil(
        self.registration.showNotification(message.title || 'Donna', {
            body: message.body || 'Donna replied.',
            icon: message.icon || '/icon-192.png',
            tag: message.tag,
            data: message.data || {},
        }),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const url = (event.notification.data && event.notification.data.url) || '/';

    event.waitUntil(
        (async () => {
            const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });

            // The app is already open somewhere: bring that forward rather than open a second one.
            for (const client of windows) {
                if ('focus' in client) {
                    return client.focus();
                }
            }

            return self.clients.openWindow(url);
        })(),
    );
});
