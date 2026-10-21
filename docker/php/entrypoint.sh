#!/bin/sh
set -e
cd /var/www/backend

# Premier lancement : installation vendor si manquant
if [ ! -d "vendor" ]; then
  echo "[entrypoint] vendor absent — composer install"
  composer install --no-interaction --prefer-dist --no-progress --no-scripts || true
fi

# Génère la clé APP_KEY si manquante (Laravel)
if [ ! -f ".env" ]; then
  cp .env.example .env
fi
if ! grep -q "^APP_KEY=base64:" .env; then
  php artisan key:generate --no-interaction || true
fi

# Optimisations dev (pas de cache config pour itérer vite)
php artisan storage:link >/dev/null 2>&1 || true

exec "$@"
