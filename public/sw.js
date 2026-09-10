// El sufijo de versión lo reescribe vite.config.js al compilar (plugin
// swVersionPlugin, ver ahí) con un hash del manifest de Vite: cada
// despliegue con assets distintos activa una caché con nombre distinto, así
// que activate() de abajo desaloja sola la del despliegue anterior sin
// depender de acordarse de subir un número a mano (issue #186, auditoría de
// rendimiento del 2026-09-10 — antes era un 'v1' fijo que nunca cambiaba,
// así que los assets de compilaciones antiguas nunca se desalojaban).
const STATIC_CACHE_NAME = 'savepoint-static-32b121cb9a';

// Carátulas: a diferencia de /build/*, Storage::put() les da un nombre único
// al subirlas pero nada impide que un día se empiecen a sobrescribir bajo la
// misma URL, así que en vez de cache-first puro se sirve la copia en caché
// al momento (si hay) y de fondo se pide una fresca para la próxima visita
// (stale-while-revalidate). No lleva el sufijo de versión de arriba a
// propósito: desplegar código nuevo no invalida carátulas ya subidas.
const COVERS_CACHE_NAME = 'savepoint-covers-v1';

// Techo aproximado, no una LRU exacta (la Cache API no guarda cuándo se leyó
// cada entrada por última vez): al superarlo se descartan las que llevan más
// tiempo AÑADIDAS, vía el orden de cache.keys(). Sin límite, una colección
// de 1000+ juegos (ver README) con carátula propia haría crecer esta caché
// sin freno.
const COVERS_CACHE_LIMIT = 300;

const KNOWN_CACHE_NAMES = [STATIC_CACHE_NAME, COVERS_CACHE_NAME];

self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => !KNOWN_CACHE_NAMES.includes(key)).map((key) => caches.delete(key))))
            .then(() => self.clients.claim())
    );
});

async function trimCoversCache() {
    const cache = await caches.open(COVERS_CACHE_NAME);
    const keys = await cache.keys();
    const excess = keys.length - COVERS_CACHE_LIMIT;

    if (excess > 0) {
        await Promise.all(keys.slice(0, excess).map((key) => cache.delete(key)));
    }
}

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') return;

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) return;

    // Assets versionados de Vite: llevan hash de contenido en el nombre, así
    // que un cambio de fichero es siempre una URL nueva y cache-first es
    // seguro. El resto de peticiones (HTML, API) van siempre a red: son
    // páginas autenticadas con CSRF token embebido por el servidor, así que
    // cachearlas serviría formularios con token caducado.
    if (url.pathname.startsWith('/build/')) {
        event.respondWith(
            caches.match(request).then((cached) => {
                if (cached) return cached;

                return fetch(request).then((response) => {
                    // Sin este guard, un 404/5xx (build a medio desplegar, red
                    // rara) se quedaba cacheado para siempre bajo esa URL: la
                    // próxima visita nunca volvía a intentar la red, ni
                    // siquiera cuando el fichero ya existiera de verdad.
                    if (response.ok) {
                        caches.open(STATIC_CACHE_NAME).then((cache) => cache.put(request, response.clone()));
                    }
                    return response;
                });
            })
        );
        return;
    }

    if (url.pathname.startsWith('/storage/covers/')) {
        event.respondWith(
            caches.open(COVERS_CACHE_NAME).then(async (cache) => {
                const cached = await cache.match(request);

                const network = fetch(request).then((response) => {
                    if (response.ok) {
                        cache.put(request, response.clone());
                        trimCoversCache();
                    }
                    return response;
                }).catch((error) => {
                    if (cached) return cached;
                    throw error;
                });

                // Con copia en caché: se sirve al momento y network sigue de
                // fondo para refrescarla (la promesa no se cancela por no
                // esperarla aquí). Sin copia: no queda otra que esperar la red.
                return cached || network;
            })
        );
    }
});
