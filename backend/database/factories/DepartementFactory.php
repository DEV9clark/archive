<?php

namespace Database\Factories;

use App\Models\Departement;
use App\Models\Territoire;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Departement> */
class DepartementFactory extends Factory
{
    protected $model = Departement::class;

    public function definition(): array
    {
        return [
            'nom'           => 'Département '.fake()->unique()->word(),
            'territoire_id' => Territoire::factory(),
        ];
    }
}
