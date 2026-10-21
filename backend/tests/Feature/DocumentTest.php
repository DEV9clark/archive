<?php

use App\Models\Document;
use App\Models\JournalAcces;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('local'));

it('dépose un fichier, calcule son empreinte et journalise le dépôt', function () {
    $a = arbre();
    $archiviste = utilisateur('archiviste', $a['dakar']);
    $contenu = "Procès-verbal de séance\n";

    $reponse = $this->actingAs($archiviste)->post('/api/v1/documents', [
        'fichier'    => UploadedFile::fake()->createWithContent('pv.txt', $contenu),
        'dossier_id' => $a['pv']->id,
        'titre'      => 'PV de séance',
    ], ['Accept' => 'application/json'])->assertCreated();

    $document = Document::findOrFail($reponse->json('data.id'));
    expect($document->checksum_sha256)->toBe(hash('sha256', $contenu))
        ->and($document->mime_type)->toBe('text/plain')
        ->and($document->cree_par)->toBe($archiviste->id)
        ->and($reponse->json('data'))->not->toHaveKey('cle_stockage');
    Storage::disk('local')->assertExists($document->cle_stockage);

    expect(JournalAcces::where('action', 'UPLOAD')->where('entite_id', $document->id)->value('chemin'))
        ->toBe($a['pv']->chemin);
});

it('refuse un type de fichier non autorisé', function () {
    $a = arbre();

    $this->actingAs(utilisateur('admin'))->post('/api/v1/documents', [
        'fichier'    => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload'),
        'dossier_id' => $a['pv']->id,
    ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('fichier');
});

it('télécharge, vérifie l\'intégrité et trace chaque accès', function () {
    $a = arbre();
    $archiviste = utilisateur('archiviste', $a['dakar']);
    $id = $this->actingAs($archiviste)->post('/api/v1/documents', [
        'fichier'    => UploadedFile::fake()->createWithContent('note.txt', 'contenu'),
        'dossier_id' => $a['pv']->id,
    ], ['Accept' => 'application/json'])->json('data.id');

    $this->actingAs($archiviste)->get("/api/v1/documents/{$id}/telecharger")
        ->assertOk()->assertDownload('note.txt');
    $this->actingAs($archiviste)->getJson("/api/v1/documents/{$id}/integrite")
        ->assertOk()->assertJson(['present' => true, 'integre' => true]);

    // Altération du fichier sur le disque
    Storage::disk('local')->put(Document::find($id)->cle_stockage, 'falsifié');
    $this->actingAs($archiviste)->getJson("/api/v1/documents/{$id}/integrite")->assertJson(['integre' => false]);

    expect(JournalAcces::where('entite_id', $id)->pluck('action')->all())
        ->toContain('UPLOAD', 'TELECHARGEMENT', 'VERIFICATION');
});

it('met un document à la corbeille sans effacer le fichier, puis le restaure', function () {
    $a = arbre();
    $admin = utilisateur('admin');
    $id = $this->actingAs($admin)->post('/api/v1/documents', [
        'fichier'    => UploadedFile::fake()->createWithContent('a.txt', 'x'),
        'dossier_id' => $a['pv']->id,
    ], ['Accept' => 'application/json'])->json('data.id');

    $this->actingAs($admin)->deleteJson("/api/v1/documents/{$id}")->assertNoContent();
    Storage::disk('local')->assertExists(Document::withTrashed()->find($id)->cle_stockage);
    $this->actingAs($admin)->getJson("/api/v1/documents/{$id}")->assertNotFound();

    $this->actingAs($admin)->postJson("/api/v1/documents/{$id}/restaurer")->assertOk();
    $this->actingAs($admin)->getJson("/api/v1/documents/{$id}")->assertOk();
});

it('garde un historique des modifications de métadonnées', function () {
    $a = arbre();
    $admin = utilisateur('admin');
    $document = Document::factory()->create(['dossier_id' => $a['pv']->id]);

    $this->actingAs($admin)->patchJson("/api/v1/documents/{$document->id}", ['statut' => 'valide'])->assertOk();

    $this->actingAs($admin)->getJson("/api/v1/documents/{$document->id}/historique")
        ->assertOk()->assertJsonPath('data.0.evenement', 'updated')
        ->assertJsonPath('data.0.nouvelles.statut', 'valide');
});
