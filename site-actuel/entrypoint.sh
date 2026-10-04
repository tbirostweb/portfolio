#!/bin/sh
set -e

# Port d'écoute dynamique (non privilégié, numérique uniquement)
LISTEN_PORT="${PORT:-8080}"
case "$LISTEN_PORT" in
  ''|*[!0-9]*) echo "PORT invalide (numérique attendu)" >&2; exit 1 ;;
esac
if [ "$LISTEN_PORT" -lt 1024 ] || [ "$LISTEN_PORT" -gt 65535 ]; then
  echo "PORT invalide (1024-65535 attendu)" >&2
  exit 1
fi
export APP_LISTEN_PORT="$LISTEN_PORT"

# Répertoires d'exécution Apache (dans /tmp : accessibles en non-root)
mkdir -p "$APACHE_RUN_DIR" "$APACHE_LOCK_DIR" "$APACHE_LOG_DIR"

echo "Apache écoute sur le port ${LISTEN_PORT}"
# Purge indépendante des visites et de l'utilisation du formulaire.
(while :; do php /var/www/html/purge-contact.php >/dev/null 2>&1; sleep 3600; done) &
exec "$@"
