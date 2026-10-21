<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\Dossier;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Document sans fichier réel sur le disque (métadonnées seules).
 * Pour un fichier réel, passer par POST /api/v1/documents.
 *
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        return [
            'titre'           => fake()->sentence(4),
            'nom_original'    => fake()->word().'.pdf',
            'cle_stockage'    => 'archives/'.now()->format('Y/m').'/'.Str::uuid().'.pdf',
            'mime_type'       => 'application/pdf',
            'extension'       => 'pdf',
            'taille_octets'   => fake()->numberBetween(1_000, 5_000_000),
            'checksum_sha256' => hash('sha256', Str::random()),
            'statut'          => 'brouillon',
            'confidentiel'    => false,
            'dossier_id'      => Dossier::factory(),
        ];
    }

    public function confidentiel(): static
    {
        return $this->state(['confidentiel' => true]);
    }
}
