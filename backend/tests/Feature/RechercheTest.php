<?php

use App\Models\Document;

it('trouve les nœuds sans accents et malgré une faute de frappe', function () {
    $a = arbre();
    $admin = utilisateur('admin');

    foreach (['senegal', 'Sengal', 'SÉNÉGAL'] as $q) {
        $noms = collect($this->actingAs($admin)->getJson('/api/v1/recherche?q='.urlencode($q))->assertOk()->json('noeuds'))->pluck('nom');
        expect($noms)->toContain('Sénégal');
    }
});

it('cherche dans le texte des documents, insensible aux accents', function () {
    $a = arbre();
    $admin = utilisateur('admin');
    $doc = Document::factory()->create([
        'dossier_id'  => $a['pv']->id,
        'titre'       => 'Compte rendu',
        'description' => 'Élection du bureau de l\'assemblée générale',
    ]);

    $ids = collect($this->actingAs($admin)->getJson('/api/v1/recherche?q=election+assemblee')->json('documents'))->pluck('id');
    expect($ids)->toContain($doc->id);
});

it('ne renvoie que les résultats du périmètre', function () {
    $a = arbre();
    Document::factory()->create(['dossier_id' => $a['courrier']->id, 'titre' => 'Budget de Thiès']);
    $lecteur = utilisateur('lecteur', $a['almadies']);

    $reponse = $this->actingAs($lecteur)->getJson('/api/v1/recherche?q=budget')->assertOk();
    expect($reponse->json('documents'))->toBeEmpty();
});
