<?php

namespace Tests;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Rôles et permissions sont nécessaires à presque tous les tests. */
    protected bool $seed = true;

    protected string $seeder = RolePermissionSeeder::class;
}
