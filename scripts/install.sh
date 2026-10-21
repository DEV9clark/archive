#!/usr/bin/env bash
# =============================================================================
#  Archivage — installation complète (DEV)
#  - Démarre la stack (postgres, redis, minio, app, nginx, worker)
#  - Backend : composer install, key:generate, storage:link, migrate --seed
#  - Frontend : npm install, build
#  - Affiche les accès finaux
# =============================================================================
set -euo pipefail

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null && pwd )"
ROOT_DIR="$( cd "$SCRIPT_DIR/.." && pwd )"
cd "$ROOT_DIR"

# --- 1. .env ----------------------------------------------------------------
if [ ! -f .env ]; then
  cp .env.example .env
  echo "→ .env créé depuis .env.example (édite-le si besoin)"
fi

# --- 2. Démarrage Docker ------------------------------------------------------
echo "→ docker compose up -d --build"
docker compose up -d --build

# Attendre que Postgres soit prêt
echo "→ Attente de Postgres…"
for i in $(seq 1 60); do
  if docker compose exec -T postgres pg_isready -U "${DB_USERNAME:-archivage}" >/dev/null 2>&1; then
    break
  fi
  sleep 2
done

# --- 3. Backend : install ---------------------------------------------------
echo "→ composer install (backend)"
docker compose exec -T app composer install --no-interaction --prefer-dist --no-progress

echo "→ php artisan key:generate (si besoin)"
docker compose exec -T app php artisan key:generate --no-interaction || true

echo "→ php artisan storage:link"
docker compose exec -T app php artisan storage:link || true

echo "→ php artisan migrate --seed"
docker compose exec -T app php artisan migrate --force --seed

# --- 4. Frontend : install + build ------------------------------------------
if [ ! -f frontend/.env.local ]; then
  APP_PORT="$(grep -E '^APP_PORT=' .env | cut -d= -f2)"
  echo "NEXT_PUBLIC_API_URL=http://localhost:${APP_PORT:-8000}/api/v1" > frontend/.env.local
  echo "→ frontend/.env.local créé"
fi

echo "→ npm install (frontend)"
(cd frontend && npm install --no-audit --no-fund)

echo "→ npm run build (frontend)"
(cd frontend && npm run build || echo "⚠ build front en échec — vérifie frontend/.env.local")

# --- 5. Résumé ---------------------------------------------------------------
cat <<EOF

╔══════════════════════════════════════════════════════════════════════╗
║  Archivage — installation terminée                                    ║
╠══════════════════════════════════════════════════════════════════════╣
║  Frontend  :  http://localhost:3000   (npm run dev dans frontend/)   ║
║  API       :  http://localhost:8000/api/v1                         ║
║                                                                      ║
║  Comptes de démo (mot de passe : password)                          ║
║   · admin@archivage.local          (admin global)                  ║
║   · afrique@archivage.local        (admin continental)              ║
║   · senegal@archivage.local        (admin national)                 ║
║   · archiviste@archivage.local     (archiviste district)            ║
║   · lecteur@archivage.local        (lecteur assemblée)              ║
╚══════════════════════════════════════════════════════════════════════╝
EOF
