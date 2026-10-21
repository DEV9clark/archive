<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Documents archivés. Le fichier vit sur le disque d'archive (`cle_stockage`),
 * la base garde l'empreinte SHA-256 pour prouver l'intégrité.
 *
 * `recherche` : tsvector généré (français + f_unaccent), pondéré
 *   A = titre, B = description, C = nom du fichier, D = texte extrait.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $t) {
            $t->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $t->string('titre', 255);
            $t->string('nom_original', 255);
            $t->text('description')->nullable();
            $t->string('cle_stockage', 512)->unique();
            $t->string('mime_type', 128);
            $t->string('extension', 16)->nullable();
            $t->unsignedBigInteger('taille_octets');
            $t->char('checksum_sha256', 64);
            $t->unsignedInteger('version')->default(1);
            $t->string('statut', 16)->default('brouillon');
            $t->boolean('confidentiel')->default(false);
            $t->text('contenu_texte')->nullable(); // extraction PDF / OCR (Phase 9)
            $t->uuid('dossier_id');
            $t->uuid('cree_par')->nullable();
            $t->timestampsTz();
            $t->softDeletesTz();

            $t->foreign('dossier_id')->references('id')->on('dossiers')->restrictOnDelete();
            $t->foreign('cree_par')->references('id')->on('utilisateurs')->restrictOnDelete();
            $t->index(['dossier_id', 'created_at']);
            $t->index('checksum_sha256');
        });

        DB::statement("ALTER TABLE documents
            ADD CONSTRAINT documents_statut_check
            CHECK (statut IN ('brouillon','valide','archive'))");

        DB::statement(<<<'SQL'
            ALTER TABLE documents ADD COLUMN recherche tsvector
            GENERATED ALWAYS AS (
                setweight(to_tsvector('french', f_unaccent(coalesce(titre, ''))), 'A') ||
                setweight(to_tsvector('french', f_unaccent(coalesce(description, ''))), 'B') ||
                setweight(to_tsvector('french', f_unaccent(coalesce(nom_original, ''))), 'C') ||
                setweight(to_tsvector('french', f_unaccent(coalesce(contenu_texte, ''))), 'D')
            ) STORED
        SQL);

        DB::statement('CREATE INDEX documents_recherche_gin ON documents USING gin(recherche)');
        DB::statement('CREATE INDEX documents_titre_trgm ON documents USING gin(f_unaccent(lower(titre)) gin_trgm_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
