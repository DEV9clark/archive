<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dossier : rangé soit dans un département (bureau d'un continent, d'une
 * nation ou d'un district), soit dans une assemblée — jamais les deux.
 * Sous-dossiers à profondeur libre via `parent_dossier_id`.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('dossiers', function (Blueprint $t) {
            $t->uuid('id')->default(DB::raw('gen_random_uuid()'));
            $t->primary('id'); // explicite : doit précéder la FK auto-référencée
            $t->string('nom', 255);
            $t->string('reference', 64)->nullable();
            $t->string('description', 1000)->nullable();
            $t->uuid('departement_id')->nullable();
            $t->uuid('assemblee_id')->nullable();
            $t->uuid('parent_dossier_id')->nullable();
            $t->string('chemin')->nullable(); // ltree, calculé par trigger (000008)
            $t->timestampsTz();
            $t->softDeletesTz();

            $t->foreign('departement_id')->references('id')->on('departements')->restrictOnDelete();
            $t->foreign('assemblee_id')->references('id')->on('assemblees')->restrictOnDelete();
            $t->foreign('parent_dossier_id')->references('id')->on('dossiers')->restrictOnDelete();
            $t->unique(['departement_id', 'assemblee_id', 'parent_dossier_id', 'nom'], 'dossiers_unique')->nullsNotDistinct();
            $t->index('reference');
        });

        DB::statement('ALTER TABLE dossiers
            ADD CONSTRAINT dossiers_rattachement_check
            CHECK (num_nonnulls(departement_id, assemblee_id) = 1)');

        DB::statement('ALTER TABLE dossiers ALTER COLUMN chemin TYPE ltree USING chemin::ltree');
        DB::statement('ALTER TABLE dossiers ALTER COLUMN chemin SET NOT NULL');
        DB::statement('CREATE UNIQUE INDEX dossiers_chemin_unique ON dossiers (chemin)');
        DB::statement('CREATE INDEX dossiers_chemin_gist ON dossiers USING gist(chemin)');
        DB::statement('CREATE INDEX dossiers_nom_trgm ON dossiers USING gin(f_unaccent(lower(nom)) gin_trgm_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('dossiers');
    }
};
