#!/bin/sh
set -e

# ==============================================================================
# ENTRYPOINT: Inicialización automatizada Zero-Touch para Savepoint (Laravel)
# ==============================================================================

# Si el comando principal es php-fpm (contenedor 'app'), ejecutamos las tareas
# de ciclo de vida e inicialización. Si es otro comando (como el worker 'queue'),
# omitimos estas tareas para evitar condiciones de carrera.
if [ "$1" = "php-fpm" ]; then
    echo "[Savepoint] Iniciando comprobaciones de entorno..."

    # 1. Asegurar existencia de .env
    if [ ! -f .env ]; then
        echo "[Savepoint] Creando .env desde .env.example..."
        cp .env.example .env
    fi

    # 2. Instalar dependencias de Composer si vendor no existe
    if [ ! -d vendor ] || [ ! -f vendor/autoload.php ]; then
        echo "[Savepoint] Instalando dependencias de Composer..."
        composer install --no-interaction --prefer-dist --optimize-autoloader
    fi

    # 3. Generar clave de aplicación si no está definida
    if ! grep -q '^APP_KEY=base64:' .env 2>/dev/null; then
        echo "[Savepoint] Generando APP_KEY en .env..."
        php artisan key:generate --force --no-interaction
    fi

    # 4. Comprobar y ejecutar migraciones pendientes de base de datos
    echo "[Savepoint] Comprobando migraciones de base de datos..."
    php artisan migrate --force --no-interaction

    # 5. Sembrar catálogo y usuario inicial SOLO si la tabla de usuarios está vacía
    echo "[Savepoint] Verificando datos iniciales..."
    php -r "require 'vendor/autoload.php'; (require_once 'bootstrap/app.php')->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); if (\App\Models\User::query()->doesntExist()) { exit(0); } exit(1);" >/dev/null 2>&1 && {
        echo "[Savepoint] Base de datos limpia detectada. Ejecutando seeders iniciales..."
        php artisan db:seed --force --no-interaction
    } || true

    # 6. Enlazar almacenamiento público si no existe
    if [ ! -L public/storage ]; then
        echo "[Savepoint] Creando symlink para public/storage..."
        php artisan storage:link --no-interaction
    fi

    # 7. Compilar assets de frontend si no están compilados
    if [ ! -f public/build/manifest.json ]; then
        echo "[Savepoint] Compilando assets de frontend con Vite..."
        npm install --ignore-scripts
        npm run build
    fi

    # 8. Ajustar permisos para que PHP-FPM (www-data) pueda escribir en storage y cache
    echo "[Savepoint] Ajustando permisos de storage y bootstrap/cache..."
    chown -R www-data:www-data storage bootstrap/cache
    find storage bootstrap/cache -type d -exec chmod 775 {} +
    find storage bootstrap/cache -type f -exec chmod 664 {} +

    echo "[Savepoint] Inicialización completada con éxito. Listo para servir peticiones."
fi

exec "$@"
