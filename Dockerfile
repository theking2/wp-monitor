# syntax=docker/dockerfile:1

# Source image (update php version when needed)
# 8.4+ required: webklex/php-imap's dependency chain (illuminate/symfony components) needs PHP >= 8.4.1
FROM php:8.4.22-apache

# Composer binary (used to install app dependencies, see htdocs/composer.json)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Let htdocs/.htaccess route all non-file requests to the front controller (index.php)
COPY apache-vhost.conf /etc/apache2/conf-available/wp-monitor.conf

# PHP ini path for upload config
ARG UPLOADS_INI="/usr/local/etc/php/conf.d/uploads.ini"

# Enable mod_rewrite, install dependencies, PHP extensions, and xdebug in one layer
# Clean up apt cache to reduce image size
RUN a2enmod rewrite \
    && a2enconf wp-monitor \
    && apt-get update \
    && apt-get install -y --no-install-recommends libsqlite3-dev libcurl4-openssl-dev \
    && docker-php-ext-install pdo_sqlite curl \
    && docker-php-ext-enable pdo_sqlite curl \
    && printf "upload_max_filesize = 128M\npost_max_size = 128M\n" > "${UPLOADS_INI}" \
    && pecl install xdebug-3.4.1 \
    && docker-php-ext-enable xdebug \
    && apt-mark manual libcurl4 libsqlite3-0 \
    && apt-get purge -y --auto-remove libsqlite3-dev libcurl4-openssl-dev \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

USER www-data
