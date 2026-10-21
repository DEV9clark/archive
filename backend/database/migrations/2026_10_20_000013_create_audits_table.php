<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** owen-it/laravel-auditing — historique des modifications (clés UUID). */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('audits', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('user_type')->nullable();
            $t->uuid('user_id')->nullable();
            $t->string('event');
            $t->uuidMorphs('auditable');
            $t->jsonb('old_values')->nullable();
            $t->jsonb('new_values')->nullable();
            $t->text('url')->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->string('user_agent', 1023)->nullable();
            $t->string('tags')->nullable();
            $t->timestampsTz();

            $t->index(['user_id', 'user_type']);
            $t->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audits');
    }
};
