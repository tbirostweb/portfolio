# --- Étape 1 : build des assets frontend (CSS) ---
# Images épinglées version + digest (mise à jour volontaire et vérifiée).
FROM node:20.20.2-alpine@sha256:fb4cd12c85ee03686f6af5362a0b0d56d50c58a04632e6c0fb8363f609372293 AS builder

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund --silent

COPY src/ ./src/
COPY tailwind.config.js ./
RUN ./node_modules/.bin/tailwindcss -i ./src/input.css -o ./src/output.css --minify


# --- Étape 2 : dépendances PHP (Composer n'existe pas dans l'image finale) ---
FROM composer:2.2.25@sha256:e27f788e74d2233af99d51b8ef9a086ab6158890d63102e6696db165e0d36403 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction --ignore-platform-reqs


# --- Étape 3 : image finale PHP + Apache, exécutée en non-root (www-data) ---
FROM php:8.2.34-apache@sha256:a5ca3797b19e8ba69905233f79fae6db191d2477f8e6d5194f2c05f01e22e104

# Config PHP de production + limites de requête
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && printf 'variables_order = EGPCS\npost_max_size = 32K\nupload_max_filesize = 32K\nmax_file_uploads = 0\n' > "$PHP_INI_DIR/conf.d/zz-app.ini"

# Apache préparé au build : port non privilégié (variable APP_LISTEN_PORT, définie par
# l'entrypoint), pid/verrous dans /tmp, logs vers stdout/stderr, pas d'index de répertoire.
RUN a2enmod rewrite headers \
    && printf 'ServerName localhost\nServerTokens Prod\nServerSignature Off\n' > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername \
    && printf 'Listen ${APP_LISTEN_PORT}\n' > /etc/apache2/ports.conf \
    && printf '<VirtualHost *:${APP_LISTEN_PORT}>\n    DocumentRoot /var/www/html\n    ErrorLog /proc/self/fd/2\n    CustomLog /proc/self/fd/1 combined\n    <Directory /var/www/html>\n        Options -Indexes +FollowSymLinks\n        AllowOverride All\n        Require all granted\n    </Directory>\n</VirtualHost>\n' > /etc/apache2/sites-available/000-default.conf

ENV APACHE_RUN_USER=www-data \
    APACHE_RUN_GROUP=www-data \
    APACHE_PID_FILE=/tmp/apache2.pid \
    APACHE_RUN_DIR=/tmp/apache2 \
    APACHE_LOCK_DIR=/tmp/apache2-lock \
    APACHE_LOG_DIR=/tmp/apache2-log

WORKDIR /var/www/html

# Code et dépendances : propriété root, lecture seule pour l'utilisateur d'exécution
COPY --chown=root:root --from=vendor /app/vendor ./vendor
COPY --chown=root:root src/ ./
COPY --chown=root:root --from=builder /app/src/output.css ./output.css
COPY --chown=root:root entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod 0755 /usr/local/bin/entrypoint.sh \
    && find /var/www/html -type d -exec chmod 0555 {} + \
    && find /var/www/html -type f -exec chmod 0444 {} +

USER www-data

# Port d'écoute : PORT (Dokploy) sinon 8080 — à renseigner côté domaine Dokploy.
ENV PORT=8080
EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD php -r '$p=getenv("PORT")?:"8080"; exit(@file_get_contents("http://127.0.0.1:".$p."/health.php")==="ok"?0:1);'

ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
