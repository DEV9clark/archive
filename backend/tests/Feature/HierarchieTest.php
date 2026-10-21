<?php

use App\Models\Departement;
use App\Models\Dossier;
use App\Models\Territoire;
use App\Models\UtilisateurPerimetre;
use App\Services\HierarchieService;
use Illuminate\Database\QueryException;

it('calcule des chemins ltree sans accents, de la racine au dossier', function () {
    $a = arbre();

    expect($a['senegal']->chemin)->toMatch('/^afrique_[0-9a-f]{8}\.senegal_[0-9a-f]{8}$/')
        ->and($a['senegal']->profondeur)->toBe(2)
        ->and($a['pv']->chemin)->toStartWith($a['almadies']->chemin.'.')
        ->and($a['pv']->getFilArianeSlugs())->toBe(['afrique', 'senegal', 'dakar', 'administration', 'almadies', 'proces_verbaux']);
});

it('propage un renommage à tous les descendants, toutes tables confondues', function () {
    $a = arbre();
    $a['senegal']->update(['nom' => 'République du Sénégal']);

    $nouveau = $a['senegal']->chemin;
    expect($nouveau)->toContain('republique_du_senegal_')
        ->and(Territoire::find($a['dakar']->id)->chemin)->toStartWith($nouveau.'.')
        ->and(Territoire::find($a['dakar']->id)->profondeur)->toBe(3)
        ->and(Departement::find($a['depDakar']->id)->chemin)->toStartWith($nouveau.'.')
        ->and(Dossier::find($a['pv']->id)->chemin)->toStartWith($nouveau.'.');
});

it('déplace un sous-arbre et fait suivre les périmètres', function () {
    $a = arbre();
    $surAssemblee = utilisateur('archiviste', $a['almadies']);
    $surDossier = utilisateur('lecteur', $a['pv']);

    $a['almadies']->update(['departement_id' => $a['depThies']->id]);

    $chemin = fn ($u) => UtilisateurPerimetre::where('utilisateur_id', $u->id)->value('chemin');
    expect(Dossier::find($a['pv']->id)->chemin)->toStartWith($a['depThies']->chemin.'.')
        ->and($chemin($surAssemblee))->toBe($a['almadies']->chemin)
        ->and($chemin($surDossier))->toBe(Dossier::find($a['pv']->id)->chemin);
});

it('refuse de déplacer un nœud sous son propre descendant', function () {
    $a = arbre();
    $a['senegal']->update(['parent_id' => $a['dakar']->id]);
})->throws(QueryException::class, 'descendant');

it('reconstruit le fil d\'Ariane en une requête', function () {
    $a = arbre();
    $fil = app(HierarchieService::class)->filAriane($a['pv']->chemin);

    expect(array_column($fil, 'nom'))->toBe(['Afrique', 'Sénégal', 'Dakar', 'Administration', 'Almadies', 'Procès-verbaux'])
        ->and(array_column($fil, 'niveau'))->toBe(['territoire', 'territoire', 'territoire', 'departement', 'assemblee', 'dossier']);
});

it('impose continent → nation → district via l\'API', function () {
    $a = arbre();
    $admin = utilisateur('admin');

    $this->actingAs($admin)->postJson('/api/v1/territoires', [
        'nom' => 'Rufisque', 'type' => 'nation', 'parent_id' => $a['senegal']->id,
    ])->assertUnprocessable()->assertJsonValidationErrors('type');

    $this->actingAs($admin)->postJson('/api/v1/territoires', [
        'nom' => 'Rufisque', 'type' => 'district', 'parent_id' => $a['senegal']->id,
    ])->assertCreated()->assertJsonPath('niveau', 'territoire');
});

it('refuse de supprimer un nœud non vide puis restaure depuis la corbeille', function () {
    $a = arbre();
    $admin = utilisateur('admin');

    $this->actingAs($admin)->deleteJson("/api/v1/assemblees/{$a['almadies']->id}")->assertStatus(409);

    $this->actingAs($admin)->deleteJson("/api/v1/dossiers/{$a['pv']->id}")->assertNoContent();
    $this->actingAs($admin)->getJson('/api/v1/corbeille')->assertOk()->assertJsonPath('data.0.id', $a['pv']->id);
    $this->actingAs($admin)->postJson("/api/v1/dossiers/{$a['pv']->id}/restaurer")->assertOk();
    expect(Dossier::find($a['pv']->id))->not->toBeNull();
});
