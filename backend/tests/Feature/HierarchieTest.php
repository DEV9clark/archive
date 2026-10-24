<?php

use App\Models\Assemblee;
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
        ->and($a['pv']->getFilArianeSlugs())->toBe(['afrique', 'senegal', 'dakar', 'almadies', 'proces_verbaux']);
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

    $a['almadies']->update(['territoire_id' => $a['thies']->id]);

    $chemin = fn ($u) => UtilisateurPerimetre::where('utilisateur_id', $u->id)->value('chemin');
    expect(Dossier::find($a['pv']->id)->chemin)->toStartWith($a['thies']->chemin.'.')
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

    expect(array_column($fil, 'nom'))->toBe(['Afrique', 'Sénégal', 'Dakar', 'Almadies', 'Procès-verbaux'])
        ->and(array_column($fil, 'niveau'))->toBe(['territoire', 'territoire', 'territoire', 'assemblee', 'dossier']);
});

it('range dossiers et sous-dossiers dans le département d\'un continent ou d\'une nation', function () {
    $a = arbre();
    $admin = utilisateur('admin');
    $secretariat = Departement::create(['nom' => 'Secrétariat', 'territoire_id' => $a['afrique']->id]);

    $dossier = $this->actingAs($admin)->postJson('/api/v1/dossiers', [
        'nom' => 'Rapports', 'departement_id' => $secretariat->id,
    ])->assertCreated()->assertJsonPath('assemblee_id', null)->json();

    $this->actingAs($admin)->postJson('/api/v1/dossiers', [
        'nom' => '2026', 'parent_dossier_id' => $dossier['id'],
    ])->assertCreated()->assertJsonPath('departement_id', $secretariat->id);

    $fil = app(HierarchieService::class)->filAriane(Dossier::where('nom', '2026')->value('chemin'));
    expect(array_column($fil, 'niveau'))->toBe(['territoire', 'departement', 'dossier', 'dossier']);

    $enfants = $this->actingAs($admin)->getJson("/api/v1/arborescence?niveau=departement&id={$secretariat->id}")->json('data');
    expect(array_column($enfants, 'nom'))->toBe(['Rapports']);
});

it('exige un seul conteneur pour un dossier', function () {
    $a = arbre();
    $admin = utilisateur('admin');

    $this->actingAs($admin)->postJson('/api/v1/dossiers', ['nom' => 'X'])
        ->assertUnprocessable()->assertJsonValidationErrors('departement_id');
    $this->actingAs($admin)->postJson('/api/v1/dossiers', [
        'nom' => 'X', 'departement_id' => $a['depDakar']->id, 'assemblee_id' => $a['almadies']->id,
    ])->assertUnprocessable()->assertJsonValidationErrors('departement_id');
});

it('n\'accepte d\'assemblée que dans un district', function () {
    $a = arbre();
    $admin = utilisateur('admin');

    $this->actingAs($admin)->postJson('/api/v1/assemblees', ['nom' => 'AG', 'territoire_id' => $a['senegal']->id])
        ->assertUnprocessable()->assertJsonValidationErrors('territoire_id');
    $this->actingAs($admin)->postJson('/api/v1/assemblees', ['nom' => 'AG', 'territoire_id' => $a['dakar']->id])
        ->assertCreated();

    Assemblee::create(['nom' => 'Hors district', 'territoire_id' => $a['afrique']->id]);
})->throws(QueryException::class, 'district');

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
