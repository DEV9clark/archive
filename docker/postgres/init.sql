-- =============================================================================
-- Archivage — extensions et objets partagés au premier boot de Postgres 16
-- Crée aussi le wrapper IMMUTABLE f_unaccent nécessaire à la migration des
-- colonnes tsvector générées. Cf. migration 000001_create_extensions.
-- =============================================================================

-- Ces extensions sont aussi créées idempotemment par la migration 000001 ;
-- ce script garantit qu'elles existent avant même les migrations artisan.
CREATE EXTENSION IF NOT EXISTS pgcrypto;
CREATE EXTENSION IF NOT EXISTS ltree;
CREATE EXTENSION IF NOT EXISTS unaccent;
CREATE EXTENSION IF NOT EXISTS pg_trgm;

-- unaccent(text) est STABLE — on l'enveloppe dans une fonction IMMUTABLE
-- pour pouvoir l'utiliser dans une colonne GENERATED ALWAYS AS ... STORED
-- (qui exige une expression IMMUTABLE). C'est le pattern standard Postgres.
CREATE OR REPLACE FUNCTION f_unaccent(text)
RETURNS text
LANGUAGE sql IMMUTABLE PARALLEL SAFE
AS $$
  SELECT public.unaccent('public.unaccent'::regdictionary, $1)
$$;

-- Base dédiée aux tests (phpunit.xml → DB_DATABASE=archivage_test).
-- Les extensions y sont créées par la migration 000000.
CREATE DATABASE archivage_test;
