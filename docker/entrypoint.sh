#!/bin/sh
set -e

cd /app

# ---------------------------------------------------------------------------
# 0. Leer el .env de la raíz del proyecto (montado como fichero de solo
#    lectura en /root.env, ver docker-compose.yml) SOLO en este proceso: a
#    propósito no son "environment:" del contenedor, para que no queden
#    fijas para cualquier comando futuro (p.ej. "docker compose exec app
#    php artisan test", que heredaría el entorno del contenedor tal cual
#    esté definido en compose, no lo que este script toque aquí). Opcional:
#    si no existe (repo recién clonado sin "cp .env.example .env" todavía),
#    se usan los valores por defecto de cada sed de abajo.
#    Se pasa por tr -d '\r' antes de fuentearlo porque el fichero se edita
#    normalmente desde Windows (CRLF), que rompe ". archivo" en el sh de
#    Alpine ("línea N: : not found" con cada línea en blanco/comentario).
# ---------------------------------------------------------------------------
if [ -f /root.env ]; then
    tr -d '\r' < /root.env > /tmp/root.env
    set -a
    . /tmp/root.env
    set +a
fi

# ---------------------------------------------------------------------------
# 1. Si /app está vacío (primer arranque, volumen recién montado), instalamos
#    Laravel desde cero. Como composer create-project no puede instalar en un
#    directorio no vacío, instalamos en una carpeta temporal y movemos el
#    contenido.
# ---------------------------------------------------------------------------
if [ ! -f "composer.json" ]; then
    echo "📦 No hay proyecto Laravel en ./backend. Instalando Laravel..."
    composer create-project laravel/laravel tmp_laravel --no-interaction --prefer-dist
    cp -r tmp_laravel/. .
    rm -rf tmp_laravel
fi

# ---------------------------------------------------------------------------
# 2. Instalar/actualizar dependencias PHP (idempotente: si vendor/ ya existe
#    y no hay cambios, es casi instantáneo)
# ---------------------------------------------------------------------------
composer install --no-interaction --prefer-dist --optimize-autoloader

# ---------------------------------------------------------------------------
# 3. Crear .env si no existe todavía
# ---------------------------------------------------------------------------
if [ ! -f ".env" ]; then
    echo "⚙️  Creando .env a partir de .env.example..."
    cp .env.example .env
fi

# ---------------------------------------------------------------------------
# 3b. Sincronizar credenciales de BD/Redis en backend/.env con las leídas en
#     el paso 0 desde el .env de la raíz del proyecto. Así nunca hay que
#     tocar backend/.env a mano ni puede haber desajuste con postgres/redis.
# ---------------------------------------------------------------------------
sed -i "s/^DB_CONNECTION=.*/DB_CONNECTION=pgsql/" .env
sed -i "s/^DB_HOST=.*/DB_HOST=${DB_HOST:-postgres}/" .env
sed -i "s/^DB_PORT=.*/DB_PORT=${DB_PORT:-5432}/" .env
sed -i "s/^DB_DATABASE=.*/DB_DATABASE=${DB_DATABASE:-savepoint}/" .env
sed -i "s/^DB_USERNAME=.*/DB_USERNAME=${DB_USERNAME:-savepoint}/" .env
sed -i "s/^DB_PASSWORD=.*/DB_PASSWORD=${DB_PASSWORD:-secreto123}/" .env
sed -i "s/^REDIS_HOST=.*/REDIS_HOST=${REDIS_HOST:-redis}/" .env

# ---------------------------------------------------------------------------
# 4. Generar APP_KEY solo si falta (evita regenerarla en cada reinicio,
#    lo que invalidaría sesiones/cookies cifradas)
# ---------------------------------------------------------------------------
if ! grep -q "^APP_KEY=base64" .env; then
    echo "🔑 Generando APP_KEY..."
    php artisan key:generate --force
fi

# ---------------------------------------------------------------------------
# 5. Migraciones: SOLO el contenedor "app" las ejecuta (ROLE=web), para que
#    no se disparen a la vez desde "app" y "queue" y choquen entre sí.
#    depends_on + healthcheck de postgres ya garantizan que la BD está lista.
#
#    A PROPÓSITO "migrate" y NUNCA "migrate:fresh": fresh hace DROP de todas
#    las tablas en cada arranque del contenedor, incluidos reinicios sobre
#    una base de datos con datos reales ya cargados (esto llegó a borrar la
#    colección real de un desarrollador). "migrate" en cambio solo aplica
#    migraciones pendientes y jamás borra nada. En una base de datos vacía
#    (primer arranque de verdad) el resultado es el mismo que "fresh": crea
#    todas las tablas desde cero. --seed es seguro de repetir en cada
#    arranque porque DatabaseSeeder usa updateOrCreate en todas partes (no
#    duplica ni pisa datos ajenos al propio seed).
# ---------------------------------------------------------------------------
if [ "$ROLE" = "web" ]; then
    echo "🚀 Ejecutando migraciones..."
    php artisan migrate --seed --force
    php artisan storage:link || true
fi

# ---------------------------------------------------------------------------
# 6. Arrancar el proceso principal del contenedor (php-fpm, o el comando
#    que se le pase, p.ej. queue:work)
# ---------------------------------------------------------------------------
exec "$@"
