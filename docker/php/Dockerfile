# ============================================================
# Bank Jatim SNAP API Tester — PHP 8.3 + Apache (production)
# ============================================================
FROM php:8.3-apache

# --- Sistem & ekstensi PHP ---
# Image resmi PHP 8.3 meng-compile cURL (--with-curl) dan OpenSSL (--with-openssl)
# secara bawaan; keduanya diverifikasi pada langkah berikutnya.
# pdo_mysql diinstal di atas mysqlnd yang sudah tersedia.
RUN docker-php-ext-install pdo_mysql

# Verifikasi ekstensi wajib tersedia (build gagal bila ada yang hilang).
RUN php -m | grep -qE '^(curl|openssl|pdo_mysql)$' || (php -m; exit 1)

# --- Apache ---
RUN a2enmod rewrite headers

# Document root menunjuk ke public/ (lihat vhost.conf).
COPY docker/apache/vhost.conf /etc/apache2/sites-available/000-default.conf

# --- Konfigurasi PHP production ---
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && { \
        echo 'date.timezone = Asia/Jakarta'; \
        echo 'display_errors = Off'; \
        echo 'log_errors = On'; \
        echo 'error_log = /var/www/html/storage/logs/php-error.log'; \
        echo 'expose_php = Off'; \
        echo 'session.cookie_httponly = 1'; \
    } > /usr/local/etc/php/conf.d/99-app.ini

WORKDIR /var/www/html

# Source code aplikasi. Credential dan .env TIDAK disalin ke image
# (mount melalui docker-compose sebagai file read-only).
COPY . /var/www/html

RUN mkdir -p /var/www/html/storage/logs /var/www/html/storage/keys \
    && chown -R www-data:www-data /var/www/html/storage

EXPOSE 80
