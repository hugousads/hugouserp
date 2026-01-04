/**
 * HugousERP Service Worker
 * Provides offline support and caching for the ERP system
 * 
 * Features:
 * - Cache static assets for offline access
 * - Network-first strategy for API calls
 * - Offline fallback pages
 * - Background sync for offline operations
 * - Push notification support
 */

const CACHE_VERSION = 'v1.0.0';
const CACHE_NAME = `hugouserp-${CACHE_VERSION}`;
const API_CACHE_NAME = `hugouserp-api-${CACHE_VERSION}`;

// Assets to cache on install
const STATIC_ASSETS = [
    '/',
    '/offline.html',
    '/favicon.ico',
    '/sounds/notification.mp3',
];

// API endpoints that should be cached with network-first strategy
const CACHEABLE_API_PATTERNS = [
    /\/api\/products/,
    /\/api\/customers/,
    /\/api\/categories/,
    /\/api\/settings/,
];

// Install event - cache static assets
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                console.log('[SW] Caching static assets');
                // Only cache assets that exist and can be fetched
                return Promise.all(
                    STATIC_ASSETS.map(url => {
                        return fetch(url)
                            .then(response => {
                                if (response.ok) {
                                    return cache.put(url, response);
                                }
                                return Promise.resolve();
                            })
                            .catch(() => Promise.resolve());
                    })
                );
            })
            .then(() => self.skipWaiting())
            .catch((error) => {
                console.warn('[SW] Cache install failed:', error);
            })
    );
});

// Activate event - clean up old caches
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((cacheNames) => {
                return Promise.all(
                    cacheNames
                        .filter((name) => name.startsWith('hugouserp-') && name !== CACHE_NAME && name !== API_CACHE_NAME)
                        .map((name) => {
                            console.log('[SW] Deleting old cache:', name);
                            return caches.delete(name);
                        })
                );
            })
            .then(() => self.clients.claim())
    );
});

// Fetch event - handle requests with appropriate caching strategies
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Skip non-GET requests
    if (request.method !== 'GET') {
        return;
    }

    // Skip browser extensions and external resources
    if (!url.origin.includes(self.location.origin)) {
        return;
    }

    // Skip livewire internal requests
    if (url.pathname.includes('/livewire/')) {
        return;
    }

    // API requests - Network first, fallback to cache
    if (url.pathname.startsWith('/api/')) {
        event.respondWith(networkFirstWithCache(request, API_CACHE_NAME));
        return;
    }

    // Static assets - Cache first, fallback to network
    if (isStaticAsset(url.pathname)) {
        event.respondWith(cacheFirstWithNetwork(request, CACHE_NAME));
        return;
    }

    // HTML pages - Network first, fallback to offline page
    if (request.headers.get('accept')?.includes('text/html')) {
        event.respondWith(networkFirstWithOffline(request));
        return;
    }

    // Default - Network with cache fallback
    event.respondWith(networkFirstWithCache(request, CACHE_NAME));
});

/**
 * Check if URL is a static asset
 */
function isStaticAsset(pathname) {
    const staticExtensions = ['.js', '.css', '.png', '.jpg', '.jpeg', '.gif', '.svg', '.woff', '.woff2', '.ttf', '.ico'];
    return staticExtensions.some(ext => pathname.endsWith(ext)) || pathname.startsWith('/build/');
}

/**
 * Cache-first strategy with network fallback
 */
async function cacheFirstWithNetwork(request, cacheName) {
    try {
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            // Update cache in background
            updateCache(request, cacheName);
            return cachedResponse;
        }

        const networkResponse = await fetch(request);
        if (networkResponse.ok) {
            const cache = await caches.open(cacheName);
            cache.put(request, networkResponse.clone());
        }
        return networkResponse;
    } catch (error) {
        console.warn('[SW] Cache-first failed:', error);
        return caches.match('/offline.html');
    }
}

/**
 * Network-first strategy with cache fallback
 */
async function networkFirstWithCache(request, cacheName) {
    try {
        const networkResponse = await fetch(request);
        if (networkResponse.ok) {
            const cache = await caches.open(cacheName);
            cache.put(request, networkResponse.clone());
        }
        return networkResponse;
    } catch (error) {
        console.warn('[SW] Network-first falling back to cache:', error);
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // Return offline response for API calls
        if (request.url.includes('/api/')) {
            return new Response(JSON.stringify({
                success: false,
                offline: true,
                message: 'You are currently offline. Data will sync when connection is restored.'
            }), {
                headers: { 'Content-Type': 'application/json' }
            });
        }
        
        throw error;
    }
}

/**
 * Network-first strategy with offline page fallback
 */
async function networkFirstWithOffline(request) {
    try {
        const networkResponse = await fetch(request);
        return networkResponse;
    } catch (error) {
        console.warn('[SW] Network failed, showing offline page');
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        return caches.match('/offline.html');
    }
}

/**
 * Update cache in background
 */
async function updateCache(request, cacheName) {
    try {
        const networkResponse = await fetch(request);
        if (networkResponse.ok) {
            const cache = await caches.open(cacheName);
            cache.put(request, networkResponse);
        }
    } catch (error) {
        // Silently fail - network might be unavailable
    }
}

// Push notification support
self.addEventListener('push', (event) => {
    if (!event.data) return;

    try {
        const data = event.data.json();
        const options = {
            body: data.body || data.message || 'New notification',
            icon: '/favicon.ico',
            badge: '/favicon.ico',
            vibrate: [100, 50, 100],
            data: {
                url: data.url || '/',
                ...data
            },
            actions: data.actions || []
        };

        event.waitUntil(
            self.registration.showNotification(data.title || 'HugousERP', options)
        );
    } catch (error) {
        console.error('[SW] Push notification error:', error);
    }
});

// Notification click handler
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const url = event.notification.data?.url || '/';
    
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then((clientList) => {
                // Focus existing window if open
                for (const client of clientList) {
                    if (client.url.includes(self.location.origin) && 'focus' in client) {
                        client.focus();
                        // Use postMessage for navigation if navigate is not available
                        if ('navigate' in client) {
                            client.navigate(url);
                        } else {
                            client.postMessage({ type: 'NAVIGATE', url: url });
                        }
                        return;
                    }
                }
                // Open new window
                if (clients.openWindow) {
                    return clients.openWindow(url);
                }
            })
    );
});

// Background sync for offline operations
self.addEventListener('sync', (event) => {
    if (event.tag === 'sync-offline-sales') {
        event.waitUntil(syncOfflineSales());
    }
    if (event.tag === 'sync-offline-data') {
        event.waitUntil(syncOfflineData());
    }
});

/**
 * Sync offline sales when connection is restored
 */
async function syncOfflineSales() {
    try {
        // Get offline sales from IndexedDB via message to client
        const clients = await self.clients.matchAll();
        for (const client of clients) {
            client.postMessage({
                type: 'SYNC_OFFLINE_SALES',
                timestamp: Date.now()
            });
        }
    } catch (error) {
        console.error('[SW] Sync offline sales failed:', error);
    }
}

/**
 * Generic sync for offline data
 */
async function syncOfflineData() {
    try {
        const clients = await self.clients.matchAll();
        for (const client of clients) {
            client.postMessage({
                type: 'SYNC_OFFLINE_DATA',
                timestamp: Date.now()
            });
        }
    } catch (error) {
        console.error('[SW] Sync offline data failed:', error);
    }
}

// Message handler for communication with main app
self.addEventListener('message', (event) => {
    const { type, data } = event.data || {};

    switch (type) {
        case 'SKIP_WAITING':
            self.skipWaiting();
            break;
        case 'CACHE_URLS':
            if (Array.isArray(data)) {
                caches.open(CACHE_NAME).then(cache => {
                    cache.addAll(data).catch(() => {});
                });
            }
            break;
        case 'CLEAR_CACHE':
            caches.keys().then(names => {
                names.forEach(name => {
                    if (name.startsWith('hugouserp-')) {
                        caches.delete(name);
                    }
                });
            });
            break;
        default:
            break;
    }
});
