<?php

namespace Database\Factories;

use App\Models\Utilisateur;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/** @extends Factory<Utilisateur> */
class UtilisateurFactory extends Factory
{
    protected $model = Utilisateur::class;

    public function definition(): array
    {
        return [
            'email'        => fake()->unique()->safeEmail(),
            'nom_complet'  => fake()->name(),
            'mot_de_passe' => 'password',
            'actif'        => true,
        ];
    }

    public function role(string $role): static
    {
        return $this->afterCreating(fn (Utilisateur $u) => $u->assignRole($role));
    }

    /** Ajoute un périmètre sur le nœud donné (n'importe quel niveau). */
    public function perimetre(Model $noeud, bool $lectureSeule = false): static
    {
        return $this->afterCreating(fn (Utilisateur $u) => $u->perimetres()->create([
            'nom'           => $noeud->nom,
            'chemin'        => $noeud->chemin,
            'lecture_seule' => $lectureSeule,
        ]));
    }

    public function inactif(): static
    {
        return $this->state(['actif' => false]);
    }
}
