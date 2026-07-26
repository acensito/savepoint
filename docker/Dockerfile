FROM php:8.4-fpm-alpine

# Argumentos para el UID/GID (Evita problemas de permisos con los volúmenes)
ARG uid=1000
ARG user=developer

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

# 5. Instalar Composer obteniéndolo de su imagen oficial
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 6. Crear usuario no-root para trabajar localmente sin romper permisos
RUN adduser -G root -u $uid -h /home/$user -D $user \
    && mkdir -p /home/$user/.composer \
    && chown -R $user:root /home/$user

# 7. Preparar el directorio de trabajo de Laravel
RUN mkdir -p /app/storage /app/bootstrap/cache \
    && chown -R $user:root /app

WORKDIR /app
USER $user

EXPOSE 9000
CMD ["php-fpm"]