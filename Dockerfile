FROM php:8.2-apache-bookworm

# System dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    libc-client-dev \
    libkrb5-dev \
    libssl-dev \
    cron \
    git \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-configure imap --with-kerberos --with-imap-ssl \
    && docker-php-ext-install -j$(nproc) \
        mysqli \
        gd \
        intl \
        mbstring \
        zip \
        imap \
        opcache

# PHP production config + tune for ITFlow
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && sed -i \
        -e 's/^upload_max_filesize.*/upload_max_filesize = 64M/' \
        -e 's/^post_max_size.*/post_max_size = 64M/' \
        -e 's/^memory_limit.*/memory_limit = 256M/' \
        -e 's/^max_execution_time.*/max_execution_time = 120/' \
        "$PHP_INI_DIR/php.ini"

# Enable Apache modules
RUN a2enmod rewrite headers

# Apache: allow .htaccess overrides for the app
RUN echo '<Directory /var/www/html>\n\
    Options -Indexes +FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/itflow.conf \
    && a2enconf itflow

WORKDIR /var/www/html

# Copy application (uploads and config.php are excluded via .dockerignore)
COPY --chown=www-data:www-data . .

# Ensure uploads dir exists with correct permissions
RUN mkdir -p uploads && chown -R www-data:www-data uploads && chmod 775 uploads

# Cron job — runs every hour via PHP CLI
RUN printf '0 * * * * www-data /usr/local/bin/php /var/www/html/cron/cron.php >> /var/log/itflow-cron.log 2>&1\n' \
    > /etc/cron.d/itflow \
    && chmod 0644 /etc/cron.d/itflow

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]
