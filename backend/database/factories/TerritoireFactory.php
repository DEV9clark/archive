<?php

namespace Database\Factories;

use App\Models\Territoire;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Territoire> */
class TerritoireFactory extends Factory
{
    protected $model = Territoire::class;

    public function definition(): array
    {
        return [
            'nom'  => fake()->unique()->city(),
            'type' => 'continent',
        ];
    }

    public function sous(Territoire $parent): static
    {
        return $this->state(['parent_id' => $parent->id, 'type' => $parent->typeEnfant()]);
    }
}
