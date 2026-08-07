const CACHE_NAME = 'savepoint-static-v1';

self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))))
            .then(() => self.clients.claim())
    );
});

// Solo se cachean los assets versionados de Vite (/build/...): llevan hash de
// contenido en el nombre, así que cache-first es seguro (un cambio de archivo
// es siempre una URL nueva). El resto de peticiones (HTML, API) van siempre a
// red: son páginas autenticadas con CSRF token embebido por el servidor, así
// que cachearlas serviría formularios con token caducado.
self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') return;

    const url = new URL(request.url);

    if (url.origin !== self.location.origin || !url.pathname.startsWith('/build/')) return;

    event.respondWith(
        caches.match(request).then((cached) => {
            if (cached) return cached;

            return fetch(request).then((response) => {
                const clone = response.clone();
                caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
                return response;
            });
        })
    );
});
