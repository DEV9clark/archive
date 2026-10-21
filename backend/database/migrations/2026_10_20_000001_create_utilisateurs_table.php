<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('utilisateurs', function (Blueprint $t) {
            $t->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $t->string('email')->unique();
            $t->string('nom_complet', 255);
            $t->string('mot_de_passe', 255); // hashed
            $t->boolean('actif')->default(true);
            $t->timestamp('email_verified_at')->nullable();
            $t->timestamp('derniere_connexion_at')->nullable();
            $t->rememberToken();
            $t->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('utilisateurs');
    }
};
