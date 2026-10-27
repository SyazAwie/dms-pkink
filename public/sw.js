/*
 * Service worker DMS PKINK: notifikasi peranti (Web Push).
 * Notifikasi dipaparkan oleh sistem pengendalian walaupun tab/pelayar ditutup;
 * klik notifikasi membuka (atau memfokus) halaman berkaitan.
 */

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

self.addEventListener('push', (event) => {
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        data = { title: 'DMS PKINK', body: event.data ? event.data.text() : '' };
    }

    const tajuk = data.title || 'DMS PKINK';
    event.waitUntil(self.registration.showNotification(tajuk, {
        body: data.body || '',
        icon: data.icon || '/ikon/dms-192.png',
        badge: data.badge || '/ikon/dms-192.png',
        tag: data.tag || undefined,
        renotify: !!data.tag,
        lang: 'ms',
        data: { url: (data.data && data.data.url) || '/notifikasi' },
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const sasaran = new URL(event.notification.data.url || '/notifikasi', self.location.origin).href;

    event.waitUntil((async () => {
        const tetingkap = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        for (const klien of tetingkap) {
            if (new URL(klien.url).origin === self.location.origin && 'focus' in klien) {
                await klien.focus();
                return klien.navigate ? klien.navigate(sasaran) : undefined;
            }
        }
        return self.clients.openWindow(sasaran);
    })());
});
