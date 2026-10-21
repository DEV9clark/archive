<?php

use App\Models\Document;

it('compte le contenu du périmètre strict, mois vides compris', function () {
    $a = arbre();
    Document::factory()->count(2)->create(['dossier_id' => $a['pv']->id, 'taille_octets' => 100, 'statut' => 'valide']);
    Document::factory()->create(['dossier_id' => $a['courrier']->id]);
    $archiviste = utilisateur('archiviste', $a['dakar']);

    $reponse = $this->actingAs($archiviste)->getJson('/api/v1/statistiques')->assertOk();

    expect($reponse->json('totaux'))->toMatchArray([
        'territoires' => 1, 'departements' => 1, 'assemblees' => 1, 'dossiers' => 1,
        'documents' => 2, 'taille_octets' => 200,
    ])
        ->and($reponse->json('par_statut.valide'))->toBe(2)
        ->and($reponse->json('depots_par_mois'))->toHaveCount(12)
        ->and($reponse->json('depots_par_mois.11.total'))->toBe(2);
});
