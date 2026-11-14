FROM php:8.4-cli

RUN apt-get update \
 && apt-get install -y --no-install-recommends git unzip libpq-dev libicu-dev libzip-dev \
 && docker-php-ext-install pdo_pgsql intl zip opcache \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_MEMORY_LIMIT=-1 FIXFLOW_ROLE=api PHP_CLI_SERVER_WORKERS=4

WORKDIR /app

# sed : tolère les fins de ligne Windows (CRLF) si le dépôt a été cloné sous Windows.
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/docker-entrypoint.sh && chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
# Serveur de développement. En production : PHP-FPM + Nginx (ou FrankenPHP), APP_ENV=prod.
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public", "public/index.php"]
