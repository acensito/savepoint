#!/bin/sh
set -e

# ---------------------------------------------------------------------------
# 1. El contenedor arranca como root (ver Dockerfile: sin USER). Si /app (el
#    repo montado desde el host) no pertenece ya al usuario "developer", lo
#    corregimos aquí — así el contenedor se autocorrige sin depender de que
#    el UID del host coincida con el $uid con el que se construyó la imagen
#    (p. ej. un servidor donde el repo se clonó como root: sin esto,
#    "developer" no podía ni crear vendor/, y git rechazaba el repo entero
#    por "dubious ownership"). Si /app YA es de "developer" (el caso normal
#    tras el primer arranque), el chown no tiene nada que hacer y esto es
#    prácticamente gratis.
# ---------------------------------------------------------------------------
if [ "$(id -u)" = "0" ]; then
    chown -R developer:developer /app
fi

# ---------------------------------------------------------------------------
# 2. Preparar la app (composer install, .env, migraciones...) como
#    "developer", nunca como root — ver setup.sh.
# ---------------------------------------------------------------------------
su-exec developer /usr/local/bin/setup.sh

# ---------------------------------------------------------------------------
# 3. Arrancar el proceso principal del contenedor. El MAESTRO de php-fpm se
#    deja arrancar como root a propósito: así lo espera esta imagen base
#    (su error_log apunta a /proc/self/fd/2, que un maestro no-root no
#    puede reabrir) y son sus WORKERS —los que de verdad atienden
#    peticiones, nunca el maestro— los que bajan a "developer" solos, por
#    la directiva "user"/"group" de php-fpm.d/www.conf (ver Dockerfile).
#    Para cualquier otro comando (p. ej. "queue:work" del contenedor
#    "queue", un proceso normal sin ese mecanismo propio de privilegios)
#    bajamos aquí mismo con su-exec.
# ---------------------------------------------------------------------------
if [ "$1" = "php-fpm" ]; then
    exec "$@"
else
    exec su-exec developer "$@"
fi
