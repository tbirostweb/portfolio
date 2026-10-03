#!/bin/sh
set -e

# Configuration du port dynamique pour Apache
LISTEN_PORT=${PORT:-80}

echo "Configuration d'Apache pour écouter sur le port ${LISTEN_PORT}"

# Modification du port dans ports.conf
sed -i "s/Listen 80/Listen ${LISTEN_PORT}/g" /etc/apache2/ports.conf

# Modification du port dans le VirtualHost
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${LISTEN_PORT}>/g" /etc/apache2/sites-available/*.conf

# Lancement d'Apache
exec "$@"