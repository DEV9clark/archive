<?php

use App\Models\JournalAcces;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('est en ajout seul', function () {
    JournalAcces::create(['action' => 'LECTURE']);

    DB::table('journal_acces')->update(['action' => 'CONNEXION']);
})->throws(QueryException::class, 'ajout seul');

it('ne montre à un admin délégué que son périmètre', function () {
    $a = arbre();
    JournalAcces::create(['action' => 'LECTURE', 'entite' => 'dossier', 'entite_id' => $a['pv']->id, 'chemin' => $a['pv']->chemin]);
    JournalAcces::create(['action' => 'LECTURE', 'entite' => 'dossier', 'entite_id' => $a['courrier']->id, 'chemin' => $a['courrier']->chemin]);
    $adminDistrict = utilisateur('admin-district', $a['dakar']);

    $ids = collect($this->actingAs($adminDistrict)->getJson('/api/v1/journal')->assertOk()->json('data'))->pluck('entite_id');
    expect($ids->all())->toBe([$a['pv']->id]);

    $this->actingAs(utilisateur('lecteur', $a['almadies']))->getJson('/api/v1/journal')->assertForbidden();
});
