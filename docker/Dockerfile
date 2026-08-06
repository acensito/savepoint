FROM php:8.4-fpm-alpine

# Argumentos para el UID/GID (Evita problemas de permisos con los volúmenes)
ARG uid=1000
ARG user=developer

# 1. Instalar dependencias del sistema y librerías para extensiones PHP
# su-exec: para que entrypoint.sh pueda arrancar como root, corregir permisos
# de /app y rebajarse al usuario sin privilegios antes de ejecutar nada de
# la app (ver comentario junto a USER más abajo).
RUN apk add --no-cache \
    postgresql-dev \
    $PHPIZE_DEPS \
    linux-headers \
    git \
    curl \
    zip \
    unzip \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    icu-dev \
    su-exec

# 2. Configurar e instalar extensiones de PHP nativas
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_pgsql bcmath gd intl opcache

# 3. Instalar extensión Redis vía PECL
RUN pecl install redis && docker-php-ext-enable redis

# 4. Configuración base optimizada para OPcache
RUN echo -e "opcache.enable=1\n\
opcache.memory_consumption=128\n\
opcache.interned_strings_buffer=8\n\
opcache.max_accelerated_files=10000\n\
opcache.revalidate_freq=2\n\
opcache.fast_shutdown=1" > /usr/local/etc/php/conf.d/opcache-recommended.ini

# 4b. Subida de ficheros (carátulas de juegos, límite real de 1MB validado en Laravel)
RUN echo -e "upload_max_filesize=2M\n\
post_max_size=4M" > /usr/local/etc/php/conf.d/uploads.ini

# 5. Instalar Composer obteniéndolo de su imagen oficial
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 6. Instalar Node.js/npm (para compilar assets con Vite) desde la imagen oficial de Node
COPY --from=node:22-alpine /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-alpine /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

# 7. Crear usuario no-root (con su propio grupo, mismo nombre) para trabajar
#    localmente sin romper permisos. PHP-FPM más abajo exige explícitamente
#    que ni el usuario ni el grupo de sus workers sean root/0 cuando el
#    maestro arranca como root (ver Dockerfile más abajo y entrypoint.sh),
#    así que hace falta un grupo propio, no basta con reutilizar "root".
RUN addgroup -g $uid $user \
    && adduser -G $user -u $uid -h /home/$user -D $user \
    && mkdir -p /home/$user/.composer /home/$user/.npm \
    && chown -R $user:$user /home/$user

# 8. Preparar el directorio de trabajo de Laravel
RUN mkdir -p /app/storage /app/bootstrap/cache \
    && chown -R $user:$user /app

# 8b. Los WORKERS de PHP-FPM (los que de verdad atienden peticiones) bajan
#     de privilegios a $user en vez del www-data por defecto de la imagen,
#     para que coincidan con quien es dueño de /app (ver entrypoint.sh).
RUN sed -i \
    -e "s/^user = .*/user = $user/" \
    -e "s/^group = .*/group = $user/" \
    /usr/local/etc/php-fpm.d/www.conf

# 9. Scripts de arranque: setup.sh instala/actualiza dependencias y ejecuta
#    migraciones (como $user); entrypoint.sh corrige permisos, delega en
#    setup.sh y lanza el proceso principal.
COPY entrypoint.sh setup.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/entrypoint.sh /usr/local/bin/setup.sh

WORKDIR /app

# Sin USER aquí a propósito: el contenedor arranca como root para que
# entrypoint.sh pueda corregir la propiedad de /app (el volumen montado en
# tiempo de ejecución puede pertenecer a cualquier UID del host, no solo al
# $uid con el que se construyó esta imagen — p. ej. un servidor donde el
# repo se clonó como root). setup.sh (composer install, migraciones...) y
# el comando "queue:work" del contenedor "queue" corren como $user vía
# su-exec (ver entrypoint.sh); el MAESTRO de php-fpm se deja arrancar como
# root a propósito —así lo espera esta imagen base, cuyo error_log apunta a
# /proc/self/fd/2, algo que un maestro no-root no puede reabrir— y son sus
# WORKERS (los que de verdad atienden peticiones, nunca el maestro) los que
# bajan a $user solos, por la directiva "user"/"group" de php-fpm.d/www.conf
# de más arriba.
EXPOSE 9000
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]