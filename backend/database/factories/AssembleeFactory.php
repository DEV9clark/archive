<?php

namespace Database\Factories;

use App\Models\Assemblee;
use App\Models\Territoire;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Assemblee> */
class AssembleeFactory extends Factory
{
    protected $model = Assemblee::class;

    public function definition(): array
    {
        return [
            'nom'           => 'Assemblée '.fake()->unique()->word(),
            'territoire_id' => Territoire::factory()->state(['type' => 'district']),
        ];
    }
}
