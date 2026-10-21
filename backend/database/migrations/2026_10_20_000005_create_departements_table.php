<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('departements', function (Blueprint $t) {
            $t->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $t->string('nom', 255);
            $t->string('code', 32)->nullable();
            $t->uuid('territoire_id');
            $t->string('description', 500)->nullable();
            $t->string('chemin')->nullable(); // ltree, calculé par trigger (000008)
            $t->timestampsTz();
            $t->softDeletesTz();

            $t->foreign('territoire_id')->references('id')->on('territoires')->restrictOnDelete();
            $t->unique(['territoire_id', 'nom'], 'departements_territoire_nom_unique');
        });

        DB::statement('ALTER TABLE departements ALTER COLUMN chemin TYPE ltree USING chemin::ltree');
        DB::statement('ALTER TABLE departements ALTER COLUMN chemin SET NOT NULL');
        DB::statement('CREATE UNIQUE INDEX departements_chemin_unique ON departements (chemin)');
        DB::statement('CREATE INDEX departements_chemin_gist ON departements USING gist(chemin)');
        DB::statement('CREATE INDEX departements_nom_trgm ON departements USING gin(f_unaccent(lower(nom)) gin_trgm_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('departements');
    }
};
