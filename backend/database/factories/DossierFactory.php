<?php

namespace Database\Factories;

use App\Models\Assemblee;
use App\Models\Dossier;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Dossier> */
class DossierFactory extends Factory
{
    protected $model = Dossier::class;

    public function definition(): array
    {
        return [
            'nom'          => 'Dossier '.fake()->unique()->word(),
            'assemblee_id' => Assemblee::factory(),
        ];
    }

    public function sous(Dossier $parent): static
    {
        return $this->state(['assemblee_id' => $parent->assemblee_id, 'parent_dossier_id' => $parent->id]);
    }
}
