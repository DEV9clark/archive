<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Journal d'accès à valeur probante : qui a consulté / téléchargé / déposé quoi.
 * Table en ajout seul — un trigger refuse tout UPDATE ou DELETE.
 * `chemin` fige la position de l'entité au moment de l'accès, ce qui permet
 * de filtrer le journal par périmètre même après un déplacement.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('journal_acces', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->uuid('utilisateur_id')->nullable();
            $t->string('action', 32);
            $t->string('entite', 32)->nullable();
            $t->uuid('entite_id')->nullable();
            $t->string('chemin')->nullable(); // ltree
            $t->string('adresse_ip', 45)->nullable();
            $t->string('user_agent', 512)->nullable();
            $t->jsonb('details')->nullable();
            $t->timestampTz('created_at')->useCurrent();

            $t->foreign('utilisateur_id')->references('id')->on('utilisateurs')->restrictOnDelete();
            $t->index(['utilisateur_id', 'created_at']);
            $t->index(['entite', 'entite_id']);
            $t->index(['action', 'created_at']);
        });

        DB::statement('ALTER TABLE journal_acces ALTER COLUMN chemin TYPE ltree USING chemin::ltree');
        DB::statement('CREATE INDEX journal_acces_chemin_gist ON journal_acces USING gist(chemin)');

        DB::statement(<<<'SQL'
        CREATE OR REPLACE FUNCTION trg_journal_acces_immuable() RETURNS trigger AS $fn$
        BEGIN
            RAISE EXCEPTION 'journal_acces est en ajout seul (% refusé)', TG_OP;
        END $fn$ LANGUAGE plpgsql;
        SQL);
        DB::statement('CREATE TRIGGER journal_acces_immuable
            BEFORE UPDATE OR DELETE ON journal_acces
            FOR EACH ROW EXECUTE FUNCTION trg_journal_acces_immuable()');
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_acces');
        DB::statement('DROP FUNCTION IF EXISTS trg_journal_acces_immuable()');
    }
};
