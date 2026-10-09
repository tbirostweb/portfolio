# --- Étape 1: Build des assets frontend (CSS) ---
FROM node:20-alpine AS builder

WORKDIR /app

# Copie des fichiers de manifeste
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund --silent

# Copie tous les fichiers nécessaires pour le build Tailwind
COPY src/ ./src/
COPY tailwind.config.js ./
# Compilation du CSS
RUN ./node_modules/.bin/tailwindcss -i ./src/input.css -o ./src/output.css --minify


# --- Étape 2: Image finale PHP + Apache ---
FROM php:8.2-apache

# Activation des modules (réécriture + en-têtes de sécurité)
RUN a2enmod rewrite headers

# Config PHP de production (display_errors off, expose_php off, etc.)
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && echo "variables_order = EGPCS" > "$PHP_INI_DIR/conf.d/zz-app.ini"

# Configuration du ServerName pour éviter les warnings
RUN echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername

# Installation des extensions PHP nécessaires
RUN apt-get update -qq && apt-get install -y -qq --no-install-recommends \
        libzip-dev \
        unzip \
    && docker-php-ext-install zip \
    && rm -rf /var/lib/apt/lists/*

# Installation de Composer
COPY --from=composer:lts /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Installation des dépendances Composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Copie du code source
COPY src/ ./

# Copie du CSS compilé DANS LE BON RÉPERTOIRE
COPY --from=builder /app/src/output.css ./output.css

# Configuration Apache SIMPLIFIÉE
RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html|g' /etc/apache2/sites-available/000-default.conf && \
    sed -i '/<\/VirtualHost>/i \
    <Directory /var/www/html>\n\
        Options Indexes FollowSymLinks\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>' /etc/apache2/sites-available/000-default.conf

# Permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Configuration de l'entrypoint
COPY entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]