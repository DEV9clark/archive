<?php

use App\Models\Assemblee;
use App\Models\Departement;
use App\Models\Dossier;
use App\Models\Territoire;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * Petite hiérarchie commune :
 *   Afrique → Sénégal → Dakar → Administration (département)
 *                             → Almadies (assemblée) → Procès-verbaux
 *                     → Thiès → Administration (département)
 *                             → Centre (assemblée)   → Courrier
 *
 * @return array<string, \Illuminate\Database\Eloquent\Model>
 */
function arbre(): array
{
    $afrique = Territoire::create(['nom' => 'Afrique', 'type' => 'continent']);
    $senegal = Territoire::create(['nom' => 'Sénégal', 'type' => 'nation', 'parent_id' => $afrique->id]);
    $dakar = Territoire::create(['nom' => 'Dakar', 'type' => 'district', 'parent_id' => $senegal->id]);
    $thies = Territoire::create(['nom' => 'Thiès', 'type' => 'district', 'parent_id' => $senegal->id]);

    $depDakar = Departement::create(['nom' => 'Administration', 'territoire_id' => $dakar->id]);
    $depThies = Departement::create(['nom' => 'Administration', 'territoire_id' => $thies->id]);
    $almadies = Assemblee::create(['nom' => 'Almadies', 'territoire_id' => $dakar->id]);
    $centre = Assemblee::create(['nom' => 'Centre', 'territoire_id' => $thies->id]);
    $pv = Dossier::create(['nom' => 'Procès-verbaux', 'assemblee_id' => $almadies->id]);
    $courrier = Dossier::create(['nom' => 'Courrier', 'assemblee_id' => $centre->id]);

    return compact('afrique', 'senegal', 'dakar', 'thies', 'depDakar', 'depThies', 'almadies', 'centre', 'pv', 'courrier');
}

function utilisateur(string $role, ?\Illuminate\Database\Eloquent\Model $perimetre = null, bool $lectureSeule = false): Utilisateur
{
    $factory = Utilisateur::factory()->role($role);

    return ($perimetre ? $factory->perimetre($perimetre, $lectureSeule) : $factory)->create();
}
