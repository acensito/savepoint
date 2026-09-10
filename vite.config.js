import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import { readFileSync, writeFileSync } from 'node:fs';
import { createHash } from 'node:crypto';

/**
 * Reescribe el sufijo de versión de STATIC_CACHE_NAME en public/sw.js con un
 * hash del manifest de Vite recién generado (issue #186, auditoría de
 * rendimiento del 2026-09-10): antes era un 'v1' fijo a mano en el propio
 * fichero, así que un despliegue nunca invalidaba de verdad la caché de
 * assets del anterior salvo que alguien se acordara de subir el número.
 * public/sw.js no pasa por el pipeline de assets de Vite (tiene que servirse
 * literalmente en /sw.js, no con hash, para registrarse con ese scope) — se
 * reescribe aquí, en el propio fichero fuente, en vez de generarlo dentro de
 * public/build.
 */
function swVersionPlugin() {
    return {
        name: 'savepoint-sw-version',
        apply: 'build',
        closeBundle() {
            const manifest = readFileSync('public/build/manifest.json', 'utf-8');
            const version = createHash('sha1').update(manifest).digest('hex').slice(0, 10);

            const swPath = 'public/sw.js';
            const sw = readFileSync(swPath, 'utf-8');
            const updated = sw.replace(
                /const STATIC_CACHE_NAME = 'savepoint-static-[^']+';/,
                `const STATIC_CACHE_NAME = 'savepoint-static-${version}';`,
            );

            writeFileSync(swPath, updated);
        },
    };
}

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
        swVersionPlugin(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
