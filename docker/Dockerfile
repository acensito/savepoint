FROM php:8.4-fpm-alpine

# 1. Instalar dependencias del sistema y librerías para extensiones PHP
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
    icu-dev

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

WORKDIR /app

# 7. Copiar y configurar entrypoint de inicialización automática
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
