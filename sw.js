const BASE = '/Vehicle%20Service%20%26%20Repair%20Center';

self.addEventListener('install', () => {
  self.skipWaiting();
});

self.addEventListener('activate', () => {
  return self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  event.respondWith(fetch(event.request));
});

self.addEventListener('push', (event) => {
  let data = {};
  if (event.data) {
    try {
      data = event.data.json();
    } catch (e) {
      data = {
        title: 'New Notification',
        body: event.data.text(),
      };
    }
  }

  const title = data.title || 'Lumina AutoWorks';
  const options = {
    body: data.body || '',
    icon: data.icon || BASE + '/icons/icon-192x192.png',
    badge: data.badge || BASE + '/icons/icon-192x192.png',
    data: data.data || {},
    requireInteraction: data.requireInteraction !== false,
    vibrate: [200, 100, 200],
    tag: 'vehicle-checkin',
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  const link = event.notification.data.link || 'index.html';

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
      for (const client of clientList) {
        if (client.url.includes(link) && 'focus' in client) {
          return client.focus();
        }
      }
      if (clients.openWindow) {
        return clients.openWindow(link);
      }
    })
  );
});
