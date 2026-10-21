<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tables spatie/laravel-permission, adaptées aux clés UUID des utilisateurs
 * (`model_id` en uuid). Pas d'équipes : le périmètre est porté par ltree.
 */
return new class extends Migration {
    public function up(): void
    {
        $tables = config('permission.table_names');

        Schema::create($tables['permissions'], function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('name');
            $t->string('guard_name');
            $t->string('description')->nullable();
            $t->timestamps();
            $t->unique(['name', 'guard_name']);
        });

        Schema::create($tables['roles'], function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('name');
            $t->string('guard_name');
            $t->string('description')->nullable();
            $t->timestamps();
            $t->unique(['name', 'guard_name']);
        });

        Schema::create($tables['model_has_permissions'], function (Blueprint $t) use ($tables) {
            $t->unsignedBigInteger('permission_id');
            $t->string('model_type');
            $t->uuid('model_id');
            $t->index(['model_id', 'model_type'], 'model_has_permissions_model_id_model_type_index');
            $t->foreign('permission_id')->references('id')->on($tables['permissions'])->cascadeOnDelete();
            $t->primary(['permission_id', 'model_id', 'model_type'], 'model_has_permissions_permission_model_type_primary');
        });

        Schema::create($tables['model_has_roles'], function (Blueprint $t) use ($tables) {
            $t->unsignedBigInteger('role_id');
            $t->string('model_type');
            $t->uuid('model_id');
            $t->index(['model_id', 'model_type'], 'model_has_roles_model_id_model_type_index');
            $t->foreign('role_id')->references('id')->on($tables['roles'])->cascadeOnDelete();
            $t->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_role_model_type_primary');
        });

        Schema::create($tables['role_has_permissions'], function (Blueprint $t) use ($tables) {
            $t->unsignedBigInteger('permission_id');
            $t->unsignedBigInteger('role_id');
            $t->foreign('permission_id')->references('id')->on($tables['permissions'])->cascadeOnDelete();
            $t->foreign('role_id')->references('id')->on($tables['roles'])->cascadeOnDelete();
            $t->primary(['permission_id', 'role_id'], 'role_has_permissions_permission_id_role_id_primary');
        });
    }

    public function down(): void
    {
        $tables = config('permission.table_names');

        Schema::dropIfExists($tables['role_has_permissions']);
        Schema::dropIfExists($tables['model_has_roles']);
        Schema::dropIfExists($tables['model_has_permissions']);
        Schema::dropIfExists($tables['roles']);
        Schema::dropIfExists($tables['permissions']);
    }
};
