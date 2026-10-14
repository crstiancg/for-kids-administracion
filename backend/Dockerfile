# API Laravel para producción (Dokploy).
#
# serversideup/php: nginx + PHP-FPM listos para Laravel. Con AUTORUN_ENABLED
# (variable de entorno en Dokploy) al arrancar corre storage:link, las
# migraciones y las cachés de config/rutas/vistas.
FROM serversideup/php:8.4-fpm-nginx

USER root
# pdo_pgsql: la base es Postgres. gd (con WebP): fotos de productos
# (Intervention Image). exif: orientar fotos de celular. intl/bcmath: Laravel.
RUN install-php-extensions pdo_pgsql gd exif intl bcmath

USER www-data
WORKDIR /var/www/html

# Primero sólo composer: si no cambian las dependencias, esta capa se
# reutiliza y el build tarda segundos.
COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader --prefer-dist

COPY --chown=www-data:www-data . .
RUN composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi

# serversideup escucha en 8080 (sin root).
EXPOSE 8080
