<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Active les extensions Postgres clés et crée le wrapper IMMUTABLE
 * `f_unaccent(text)` indispensable pour les colonnes GENERATED ALWAYS AS
 * qui contiennent unaccent(text) — Postgres refuse les expressions STABLE
 * dans une colonne générée STORED.
 */
return new class extends Migration {
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pgcrypto');
        DB::statement('CREATE EXTENSION IF NOT EXISTS ltree');
        DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent');
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        // f_unaccent : wrapper IMMUTABLE (unaccent() lui-même est STABLE)
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION f_unaccent(text)
            RETURNS text
            LANGUAGE sql IMMUTABLE PARALLEL SAFE
            AS $$
                SELECT public.unaccent('public.unaccent'::regdictionary, $1)
            $$
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP FUNCTION IF EXISTS f_unaccent(text)');
        // On ne retire pas les extensions : d'autres schémas peuvent en dépendre.
    }
};
