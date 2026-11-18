
# Etapa 1: instalo las dependencias de Composer sin las de desarrollo (PHPUnit no va a produccion).
FROM composer:2 AS dependencias
WORKDIR /app
COPY composer.json composer.lock ./
COPY api/src api/src
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --ignore-platform-reqs

# Etapa 2: PHP 8.2 con Apache, la misma version que XAMPP
FROM php:8.2-apache

# pdo_mysql para la base de datos y mod_rewrite para las rutas limpias de la API (/api/materias)
RUN docker-php-ext-install pdo_mysql \
 && a2enmod rewrite headers \
 && sed -ri 's!AllowOverride None!AllowOverride All!' /etc/apache2/apache2.conf \
 && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
 && printf 'upload_max_filesize=20M\npost_max_size=25M\ndate.timezone=America/Bogota\n' > "$PHP_INI_DIR/conf.d/siult.ini"

#  la misma ruta que en XAMPP (/proyectoGestorEscolar) para no cambiar ninguna URL
WORKDIR /var/www/html/proyectoGestorEscolar
COPY --chown=www-data:www-data . .
COPY --from=dependencias --chown=www-data:www-data /app/vendor ./vendor
RUN mkdir -p config/uploads && chown -R www-data:www-data config/uploads

# La configuracion llega por variables de entorno (12-factor): MYSQLHOST, MYSQLUSER, MYSQLPASSWORD,
# MYSQLDATABASE, MYSQLPORT, REDIS_HOST, FRONTEND_URL. Ver docker-compose.yml y k8s/.
HEALTHCHECK --interval=15s --timeout=3s --start-period=20s --retries=3 \
  CMD curl -fs http://localhost/proyectoGestorEscolar/api/salud > /dev/null || exit 1

EXPOSE 80
