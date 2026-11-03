'use strict';

self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  event.waitUntil((async () => {
    const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    const appWindow = windows.find((client) => client.url.startsWith(self.registration.scope));

    if (appWindow) {
      await appWindow.focus();
      return;
    }

    await self.clients.openWindow(new URL('worktrack/calendar', self.registration.scope).href);
  })());
});
