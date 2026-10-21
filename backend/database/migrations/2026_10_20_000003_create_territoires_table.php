<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hiérarchie géographique auto-référencée : Continent → NATION → DISTRICT.
 * Chaque ligne porte un `chemin` ltree et un `profondeur` maintenus par
 * triggers BEFORE INSERT/UPDATE (impossible à faire côté PHP car renommage
 * et déplacement de nœud sont fréquents). Cf. migration 000004.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('territoires', function (Blueprint $t) {
            $t->uuid('id')->default(DB::raw('gen_random_uuid()'));
            $t->primary('id'); // explicite : doit précéder la FK auto-référencée
            $t->string('nom', 255);
            $t->string('code', 32)->nullable();
            $t->string('type', 16); // continent|nation|district (check via raw)
            $t->uuid('parent_id')->nullable();
            $t->string('description', 500)->nullable();
            $t->string('chemin')->nullable();        // ltree (raw ALTER ci-dessous)
            $t->smallInteger('profondeur')->default(0);
            $t->timestampsTz();
            $t->softDeletesTz();

            $t->foreign('parent_id')->references('id')->on('territoires')->restrictOnDelete();
            // NULLS NOT DISTINCT : deux racines homonymes sont aussi refusées
            $t->unique(['parent_id', 'nom'], 'territoires_parent_nom_unique')->nullsNotDistinct();
            $t->index('type');
        });

        // Type enum via CHECK
        DB::statement("ALTER TABLE territoires
            ADD CONSTRAINT territoires_type_check
            CHECK (type IN ('continent','nation','district'))");

        // Colonne chemin en ltree (calculée par trigger, jamais par PHP)
        DB::statement('ALTER TABLE territoires ALTER COLUMN chemin TYPE ltree USING chemin::ltree');
        DB::statement('ALTER TABLE territoires ALTER COLUMN chemin SET NOT NULL');

        // Indexation ltree + recherche floue sur le nom
        DB::statement('CREATE UNIQUE INDEX territoires_chemin_unique ON territoires (chemin)');
        DB::statement('CREATE INDEX territoires_chemin_gist ON territoires USING gist(chemin)');
        DB::statement('CREATE INDEX territoires_nom_trgm    ON territoires USING gin(f_unaccent(lower(nom)) gin_trgm_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('territoires');
    }
};
