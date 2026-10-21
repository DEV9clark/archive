<?php

use App\Models\Document;

it('limite un archiviste de district à son sous-arbre', function () {
    $a = arbre();
    $archiviste = utilisateur('archiviste', $a['dakar']);

    $this->actingAs($archiviste)->getJson("/api/v1/dossiers/{$a['pv']->id}")->assertOk();
    $this->actingAs($archiviste)->getJson("/api/v1/dossiers/{$a['courrier']->id}")->assertForbidden();
    $this->actingAs($archiviste)->getJson("/api/v1/territoires/{$a['thies']->id}")->assertForbidden();

    $ids = collect($this->actingAs($archiviste)->getJson('/api/v1/dossiers')->json('data'))->pluck('id');
    expect($ids)->toContain($a['pv']->id)->not->toContain($a['courrier']->id);
});

it('montre les ancêtres pour la navigation, sans droit d\'écriture', function () {
    $a = arbre();
    $archiviste = utilisateur('archiviste', $a['dakar']);

    $this->actingAs($archiviste)->getJson("/api/v1/territoires/{$a['senegal']->id}")
        ->assertOk()->assertJsonPath('droits.modifier', false);

    $racines = $this->actingAs($archiviste)->getJson('/api/v1/arborescence')->assertOk()->json('data');
    expect($racines)->toHaveCount(1)->and($racines[0]['accessible'])->toBeFalse();

    $enfants = collect($this->actingAs($archiviste)
        ->getJson("/api/v1/arborescence?niveau=territoire&id={$a['senegal']->id}")->json('data'))->pluck('nom');
    expect($enfants->all())->toBe(['Dakar']);
});

it('interdit à un lecteur de créer ou modifier', function () {
    $a = arbre();
    $lecteur = utilisateur('lecteur', $a['almadies']);

    $this->actingAs($lecteur)->postJson('/api/v1/dossiers', ['nom' => 'X', 'assemblee_id' => $a['almadies']->id])->assertForbidden();
    $this->actingAs($lecteur)->putJson("/api/v1/dossiers/{$a['pv']->id}", ['nom' => 'Y'])->assertForbidden();
    $this->actingAs($lecteur)->getJson("/api/v1/dossiers/{$a['pv']->id}")->assertOk();
});

it('respecte un périmètre en lecture seule', function () {
    $a = arbre();
    $archiviste = utilisateur('archiviste', $a['dakar'], lectureSeule: true);

    $this->actingAs($archiviste)->getJson("/api/v1/dossiers/{$a['pv']->id}")->assertOk();
    $this->actingAs($archiviste)->postJson('/api/v1/dossiers', ['nom' => 'X', 'assemblee_id' => $a['almadies']->id])->assertForbidden();
});

it('cache les documents hors périmètre et confidentiels', function () {
    $a = arbre();
    $public = Document::factory()->create(['dossier_id' => $a['pv']->id]);
    $secret = Document::factory()->confidentiel()->create(['dossier_id' => $a['pv']->id]);
    $ailleurs = Document::factory()->create(['dossier_id' => $a['courrier']->id]);
    $lecteur = utilisateur('lecteur', $a['almadies']);

    $ids = collect($this->actingAs($lecteur)->getJson('/api/v1/documents')->json('data'))->pluck('id');
    expect($ids->all())->toBe([$public->id]);

    $this->actingAs($lecteur)->getJson("/api/v1/documents/{$secret->id}")->assertForbidden();
    $this->actingAs($lecteur)->getJson("/api/v1/documents/{$ailleurs->id}")->assertForbidden();
});

it('empêche un admin national de gérer un compte hors de son périmètre ou de rang égal', function () {
    $a = arbre();
    $national = utilisateur('admin-national', $a['senegal']);

    $this->actingAs($national)->postJson('/api/v1/admin/utilisateurs', [
        'email' => 'x@test.local', 'nom_complet' => 'X', 'mot_de_passe' => 'secret123',
        'role' => 'admin-national', 'perimetres' => [],
    ])->assertUnprocessable()->assertJsonValidationErrors('role');

    $this->actingAs($national)->postJson('/api/v1/admin/utilisateurs', [
        'email' => 'y@test.local', 'nom_complet' => 'Y', 'mot_de_passe' => 'secret123',
        'role' => 'archiviste', 'perimetres' => [['niveau' => 'territoire', 'id' => $a['afrique']->id]],
    ])->assertUnprocessable()->assertJsonValidationErrors('perimetres.0.id');

    $this->actingAs($national)->postJson('/api/v1/admin/utilisateurs', [
        'email' => 'z@test.local', 'nom_complet' => 'Z', 'mot_de_passe' => 'secret123',
        'role' => 'archiviste', 'perimetres' => [['niveau' => 'territoire', 'id' => $a['dakar']->id]],
    ])->assertCreated()->assertJsonPath('data.roles.0', 'archiviste');
});
