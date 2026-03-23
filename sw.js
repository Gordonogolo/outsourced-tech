// sw.js - Service Worker for Push Notifications

const CACHE_NAME = 'outsourced-v1';
const OFFLINE_URL = 'http://192.168.1.143/outsourced/public/offline.html';

// Install event
self.addEventListener('install', (event) => {
    console.log('Service Worker installing.');
    
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll([
                'http://192.168.1.143/outsourced/',
                'http://192.168.1.143/outsourced/public/',
                'http://192.168.1.143/outsourced/assets/css/style.css',
                'http://192.168.1.143/outsourced/assets/js/app.js',
            ]);
        })
    );
    
    self.skipWaiting();
});

// Activate event
self.addEventListener('activate', (event) => {
    console.log('Service Worker activating.');
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheName !== CACHE_NAME) {
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
    self.clients.claim();
});

// Push notification received
self.addEventListener('push', (event) => {
    console.log('Push notification received:', event);
    
    let data = {
        title: 'Outsourced Technologies',
        body: 'You have a new notification',
        icon: '/outsourced/assets/images/logo.png',
        badge: '/outsourced/assets/images/logo.png',
    };
    
    if (event.data) {
        try {
            data = event.data.json();
        } catch (e) {
            data.body = event.data.text();
        }
    }
    
    const options = {
        body: data.body,
        icon: data.icon || '/outsourced/assets/images/logo.png',
        badge: data.badge || '/outsourced/assets/images/logo.png',
        image: data.image || null,
        tag: data.tag || 'default',
        data: data.data || { url: data.url || '/' },
        requireInteraction: data.requireInteraction || false,
        actions: data.actions || [
            { action: 'view', title: 'View' },
            { action: 'dismiss', title: 'Dismiss' }
        ],
        vibrate: [200, 100, 200],
    };
    
    event.waitUntil(
        self.registration.showNotification(data.title, options)
    );
});

// Notification click handler
self.addEventListener('notificationclick', (event) => {
    console.log('Notification click:', event);
    
    event.notification.close();
    
    if (event.action === 'dismiss') {
        return;
    }
    
    const urlToOpen = event.notification.data?.url || '/outsourced/';
    
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
            // Check if there is already a window open
            for (const client of windowClients) {
                if (client.url.includes(self.location.origin) && 'focus' in client) {
                    client.navigate(urlToOpen);
                    return client.focus();
                }
            }
            
            // Open new window if none exists
            if (clients.openWindow) {
                return clients.openWindow(urlToOpen);
            }
        })
    );
});

// Fetch event for offline support
self.addEventListener('fetch', (event) => {
    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request).catch(() => {
                return caches.match(OFFLINE_URL);
            })
        );
    }
});
