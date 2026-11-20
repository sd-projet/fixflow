#!/bin/sh
# Démarrage en production : base de données, premier admin, puis serveur.
set -e
cd /app

# Render fournit le port d'écoute dans $PORT (10000 par défaut).
export SERVER_NAME=":${PORT:-10000}"

mkdir -p var/uploads var/cache var/log

if [ -n "$(ls -A migrations 2>/dev/null | grep -v '^\.gitignore$')" ]; then
  php bin/console doctrine:migrations:migrate --no-interaction --env=prod
else
  # Pas encore de migrations : synchronisation directe du schéma (acceptable pour une démo).
  php bin/console doctrine:schema:update --force --no-interaction --env=prod
fi

if [ -n "$FIXFLOW_ADMIN_EMAIL" ] && [ -n "$FIXFLOW_ADMIN_PASSWORD" ]; then
  php bin/console app:user:create "$FIXFLOW_ADMIN_EMAIL" "Administrateur" ROLE_ADMIN --password="$FIXFLOW_ADMIN_PASSWORD" --env=prod
fi

if [ "${FIXFLOW_SEED:-0}" = "1" ]; then
  php bin/console app:seed --env=prod
fi

exec "$@"
