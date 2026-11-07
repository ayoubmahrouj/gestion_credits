#!/bin/sh
set -e

cd /var/www

if [ -z "$APP_KEY" ]; then
  echo "ERREUR : APP_KEY est vide. Renseignez backend/.env (voir DEPLOIEMENT.md, étape 4)." >&2
  exit 1
fi

mkdir -p \
  storage/framework/cache \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs \
  storage/app/backups \
  storage/app/exports \
  bootstrap/cache

php artisan package:discover --ansi

FRESH=0

if [ ! -f database/database.sqlite ]; then
  touch database/database.sqlite
  FRESH=1
fi

php artisan migrate --force --no-interaction

if [ "$FRESH" = "1" ]; then
  php artisan db:seed --force --no-interaction
fi

if [ "$APP_ENV" = "production" ]; then
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
fi

chown -R www-data:www-data storage bootstrap/cache database

exec "$@"
