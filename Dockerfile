# Occupy Uranus — bot webhook, Mini-App and its API in one Apache + PHP container.
#
#   docker compose up --build        (see docs/DOCKER.md)
#
# Code and game content are baked into the image. Everything the app writes
# (sessions, leaderboard, logs, file_id cache) goes to /var/lib/uranus, a volume.

FROM php:8.5-apache

# curl and mbstring ship with the official image; APCu backs the per-user rate limit.
RUN pecl install apcu \
    && docker-php-ext-enable apcu \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Honour the project's .htaccess (denies config.php and *.json/*.log, no indexes).
RUN printf '%s\n' \
        'ServerName localhost' \
        '<Directory /var/www/html>' \
        '    AllowOverride All' \
        '</Directory>' \
        > /etc/apache2/conf-available/uranus.conf \
    && a2enconf uranus

ENV URANUS_RUNTIME_DIR=/var/lib/uranus

WORKDIR /var/www/html
COPY --chown=www-data:www-data . .

# config.php creates data/backups on every request if it is missing; make it up front.
RUN mkdir -p data/backups "$URANUS_RUNTIME_DIR" \
    && chown -R www-data:www-data data/backups "$URANUS_RUNTIME_DIR"

VOLUME /var/lib/uranus

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD curl -fsS -o /dev/null http://localhost/uranus_game_bot.php || exit 1
