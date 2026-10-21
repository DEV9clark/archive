<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Périmètre d'un utilisateur : liste de chemins ltree.
 * Un utilisateur sans entrée voit tout (admin global).
 * Un utilisateur avec N entrées voit l'union des sous-arbres.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('utilisateur_perimetres', function (Blueprint $t) {
            $t->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $t->uuid('utilisateur_id');
            $t->string('nom', 255);
            $t->string('chemin'); // ltree — typage appliqué via raw ci-dessous
            $t->boolean('lecture_seule')->default(false);
            $t->timestampsTz();

            $t->foreign('utilisateur_id')->references('id')->on('utilisateurs')->cascadeOnDelete();
            $t->index('utilisateur_id');
        });

        \Illuminate\Support\Facades\DB::statement('ALTER TABLE utilisateur_perimetres ALTER COLUMN chemin TYPE ltree USING chemin::ltree');
        \Illuminate\Support\Facades\DB::statement('CREATE INDEX utilisateur_perimetres_chemin_gist ON utilisateur_perimetres USING gist(chemin)');
    }

    public function down(): void
    {
        Schema::dropIfExists('utilisateur_perimetres');
    }
};
