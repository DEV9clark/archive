# =============================================================================
#  Archivage — Système d'Archivage Hiérarchique
#  ----------------------------------------------------------------------------
#  Stack : Laravel 13 (PHP 8.4) · PostgreSQL 16 · Next.js 16 / React 19
#          Sanctum · spatie/laravel-permission ^8 · owen-it/laravel-auditing ^14
# =============================================================================

## Pré-requis

| Binaire | Version cible | Vérification |
|---|---|---|
| Docker Desktop | ≥ 4.x (Compose v2) | `docker compose version` |
| Node | ≥ 20.x | `node --version` |
| Git | tout | `git --version` |

> Le conteneur PHP 8.4 fournit PHP, Composer et toutes les extensions.

## Démarrage en un seul script

```bash
git clone <repo> archivage && cd archivage
cp .env.example .env
# Édite .env si tu veux changer APP_NAME, ports, credentials…
./scripts/install.sh
```

Le script `scripts/install.sh` :
1. build & up des conteneurs (`php-fpm`, `nginx`, `postgres:16`, `minio`, `worker`)
2. `composer install` dans le conteneur
3. `npm install` + `npm run build` côté front
4. `php artisan migrate --seed` (Crée extensions, schéma, jeux de données Afrique)
5. `php artisan storage:link`
6. Crée un utilisateur **admin@archivage.local / password** (imprimé à la fin)

## URLs

| Service | URL | Identifiants par défaut |
|---|---|---|
| Frontend (Next.js) | http://localhost:3000 | — |
| API (Nginx → PHP-FPM) | http://localhost:8000 | — |
| MinIO Console | http://localhost:9001 | minio / minio12345 |
| Postgres | localhost:5432 | archivage / archivage |

## Comptes de démo (créés par `AfriqueSeeder`)

| Email | Mot de passe | Rôle | Périmètre |
|---|---|---|---|
| admin@archivage.local | password | `admin` | mondial |
| afrique@archivage.local | password | `admin-continental` | continent Afrique |
| senegal@archivage.local | password | `admin-national` | nation Sénégal |
| archiviste@archivage.local | password | `archiviste` | district Dakar |
| lecteur@archivage.local | password | `lecteur` | assemblée Almadies |

Rôles disponibles (du plus large au plus restreint) : `admin`, `admin-continental`, `admin-national`, `admin-district`, `archiviste`, `lecteur`. Un admin délégué ne gère que des comptes de rang inférieur, sur des sous-périmètres du sien.

## Architecture

```
Archivage/
├── backend/                  Laravel 13 (PHP 8.4-fpm-alpine)
│   ├── app/Models/           Eloquent + Auditable + SoftDeletes + Perimetre
│   ├── app/Http/Controllers/Api/V1   CRUD + Arborescence + Recherche + Journal + Stats + Corbeille + Admin
│   ├── app/Policies/         permission spatie × périmètre ltree
│   ├── app/Services/         HierarchieService (ltree), StockageService, JournalService
│   ├── database/migrations   extensions pgcrypto/ltree/unaccent/pg_trgm + tables
│   ├── database/seeders      RolePermissionSeeder + AfriqueSeeder
│   └── tests/                Pest
├── frontend/                 Next.js 16 (App Router)
│   ├── src/app/              (auth)/login · (dashboard)/* (route groups)
│   ├── src/components/ui     shadcn-like (button, card, dialog, table…)
│   └── src/components/shared DataTable, PageHeader, StatCard, Sidebar…
├── docker/                   Dockerfile php:8.4-fpm-alpine + nginx
└── docker-compose.yml        postgres:16 + minio + app + worker
```

## Modèle de données

```
territoires (continent → nation → district) → departements → assemblees → dossiers (sous-dossiers libres) → documents
```

Chaque niveau porte un `chemin` **`ltree`** matériel (`afrique_1a2b3c4d.senegal_5e6f7a8b.dakar_…` : slug sans accents + 8 hex de l'UUID), calculé **uniquement par trigger PostgreSQL** (`trg_hierarchie_set_chemin`). Un renommage ou un déplacement réécrit le préfixe de tous les descendants, toutes tables confondues, ainsi que les périmètres utilisateurs concernés (`trg_hierarchie_cascade`). Un déplacement sous son propre descendant est refusé. Effets :

- **Fil d'Ariane** : 1 requête SQL (`SELECT … WHERE chemin @> target`)
- **Périmètre utilisateur** : 1 requête (`EXISTS (...) WHERE target.chemin <@ perimetre.chemin`). Les ancêtres du périmètre restent visibles pour la navigation, sans droit d'écriture ; un périmètre peut être en lecture seule. Un compte non-admin sans périmètre ne voit rien.
- **Audit** : `owen-it/laravel-auditing` enregistre toute modification ; un `journal_acces` dédié trace les consultations et téléchargements (valeur probante).

## Stockage des fichiers

- v1 : **disque local** (`backend/storage/app/archives/AAAA/MM/{uuid}.{ext}`).
- Bascule vers **MinIO/S3** : `FILESYSTEM_DISK=s3` dans `.env` (Phase 8).
- Chaque document stocke : `cle_stockage`, `mime_type`, `taille_octets`, **`checksum_sha256`** (intégrité probante), `version`, `statut`, `confidentiel`.

## Recherche

`tsvector` généré (français + `unaccent`) + index GIN + `pg_trgm` sur le nom → recherche insensible aux accents et tolérante aux fautes (`senegal` ⇒ *Sénégal* ; `Sengal` ⇒ *Sénégal*).

## Sécurité

- Auth par **Sanctum** (tokens Bearer)
- **RBAC** hiérarchique : 6 rôles × périmètre `ltree` (un utilisateur d'un district ne voit pas un autre district)
- `RESTRICT` sur toutes les FK + **soft delete** (`deleted_at`) : impossible d'effacer par accident tout un fonds
- Journalisation d'accès (connexions réussies et échouées, lectures, téléchargements, dépôts, modifications) dans `journal_acces`, **en ajout seul** (un trigger refuse UPDATE/DELETE) ; chaque entrée fige le `chemin` de l'élément
- Vérification d'intégrité à la demande (`GET /documents/{id}/integrite` recalcule le SHA-256)
- Documents confidentiels visibles seulement avec `documents.confidentiel`
- Anti force brute : 5 tentatives de connexion / minute par email + IP
- Headers de sécurité (`SecureHeaders` via middleware)

## Commandes utiles

```bash
# Backend
docker compose exec app bash
docker compose exec app php artisan migrate:fresh --seed
docker compose exec app php artisan test     # base archivage_test (créée par docker/postgres/init.sql)
docker compose exec app ./vendor/bin/pint --test

# Frontend
cd frontend && npm run dev          # dev server :3000
cd frontend && npm run build        # build prod
cd frontend && npm run lint && npm test

# Sauvegarde
./scripts/backup.sh                 # dump PG + tar des archives → ./backups/
./scripts/restore.sh ./backups/AAAA-MM-DD.tar.gz
```

## Roadmap (Phase 9+)

- PHP 8.4 → **8.5** (1 ligne de Dockerfile)
- Extraction texte PDF (`pypdf`-style via `smalot/pdfparser` PHP)
- OCR Tesseract (`thiagoalves/php-tesseract`)
- Notifications
- Politique de rétention / purge légale
- Export (CSV, ZIP d'un dossier)
- Recherche sémantique (intégration plus tard d'un micro-service Python)

## Licence

MIT © Archivage contributors.
# archive
