FROM php:8.4-fpm-alpine

# Instalar paquetes necesarios
RUN apk add --no-cache \
    postgresql-dev \
    $PHPIZE_DEPS \
    git \
    curl \
    zip \
    unzip

# Compilar extensiones PHP
RUN docker-php-ext-install pdo_pgsql bcmath

# Instalar Redis
RUN pecl install redis && docker-php-ext-enable redis

# Crear directorios de Laravel
RUN mkdir -p /app/storage /app/bootstrap/cache && \
    chmod -R 775 /app/storage /app/bootstrap/cache

WORKDIR /app

EXPOSE 9000

CMD ["php-fpm"]