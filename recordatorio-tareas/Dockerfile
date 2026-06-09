# Imagen de la aplicación "Recordatorio de Tareas".
# PHP 8.2 CLI con las extensiones necesarias (pdo_mysql, sodium, curl).
FROM php:8.2-cli

# Dependencias del sistema y extensiones de PHP.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libsodium-dev libcurl4-openssl-dev unzip \
    && docker-php-ext-install pdo_mysql \
    && docker-php-ext-install sodium \
    && rm -rf /var/lib/apt/lists/*

# Composer (copiado desde la imagen oficial).
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Instalar dependencias primero (mejor cacheo de capas).
COPY composer.json ./
RUN composer install --no-interaction --no-progress --no-dev || true

# Copiar el resto del proyecto.
COPY . .

# Usar la configuración basada en variables de entorno.
ENV RECORDATORIO_CONFIG=/var/www/html/docker/config.docker.php

RUN chmod +x docker/entrypoint.sh

EXPOSE 8000
ENTRYPOINT ["docker/entrypoint.sh"]
