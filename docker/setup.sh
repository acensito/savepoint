#!/bin/sh
# Preparación de la app, ejecutada como el usuario sin privilegios (ver
# entrypoint.sh, que llama a este script vía su-exec). No arranca ningún
# proceso, solo deja /app listo antes de que entrypoint.sh lance php-fpm o
# el comando que toque.
set -e

cd /app

# ---------------------------------------------------------------------------
# 1. Si /app está vacío (primer arranque, volumen recién montado), instalamos
#    Laravel desde cero. Como composer create-project no puede instalar en un
#    directorio no vacío, instalamos en una carpeta temporal y movemos el
#    contenido.
# ---------------------------------------------------------------------------
if [ ! -f "composer.json" ]; then
    echo "📦 No hay proyecto Laravel en el repo. Instalando Laravel..."
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
# 3. Crear .env si no existe todavía. Es el mismo .env que usa Docker
#    Compose (ver docker-compose.yml): no hay un .env de Laravel aparte que
#    sincronizar, así que lo que se ponga aquí es directamente lo que
#    Laravel usa.
# ---------------------------------------------------------------------------
if [ ! -f ".env" ]; then
    echo "⚙️  Creando .env a partir de .env.example..."
    cp .env.example .env
fi

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
