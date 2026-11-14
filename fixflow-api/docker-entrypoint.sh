#!/bin/sh
# Point d'entrée des conteneurs PHP.
#  - FIXFLOW_ROLE=api    : installe le projet si besoin, prépare la base, puis lance le serveur
#  - FIXFLOW_ROLE=worker : attend que l'API soit prête, puis exécute la commande (consumer, alertes…)
set -e
cd /app

if [ "${FIXFLOW_ROLE:-api}" = "worker" ]; then
  until [ -f var/.ready ]; do echo "[fixflow] en attente de l'API…"; sleep 3; done
  exec "$@"
fi

mkdir -p var
rm -f var/.ready

if [ ! -f bin/console ] || [ ! -f vendor/autoload_runtime.php ]; then
  echo "[fixflow] première installation (Symfony + dépendances), cela peut prendre quelques minutes…"
  sh ./bootstrap.sh
else
  composer install --no-interaction
fi

echo "[fixflow] préparation de la base de données"
if [ -n "$(ls -A migrations 2>/dev/null | grep -v '^\.gitignore$')" ]; then
  php bin/console doctrine:migrations:migrate --no-interaction
else
  # Pas encore de migrations : en développement on synchronise directement le schéma.
  php bin/console doctrine:schema:update --force --no-interaction
fi

if [ "${FIXFLOW_SEED:-0}" = "1" ]; then
  php bin/console app:seed
fi

touch var/.ready
echo "[fixflow] API prête sur http://localhost:8000 (documentation : /api/docs)"
exec "$@"
