<?php

use App\Models\JournalAcces;
use App\Models\Utilisateur;

it('connecte un utilisateur et renvoie son profil et ses droits', function () {
    $a = arbre();
    utilisateur('lecteur', $a['almadies'])->update(['email' => 'lecteur@test.local']);

    $reponse = $this->postJson('/api/v1/auth/login', ['email' => 'LECTEUR@test.local', 'password' => 'password'])
        ->assertOk()
        ->assertJsonPath('utilisateur.roles.0', 'lecteur')
        ->assertJsonPath('utilisateur.perimetres_detail.0.fil_ariane.4.nom', 'Almadies');

    expect($reponse->json('utilisateur.permissions'))->toContain('documents.voir')->not->toContain('documents.deposer');

    $this->withToken($reponse->json('token'))->getJson('/api/v1/auth/me')->assertOk();
    expect(JournalAcces::where('action', 'CONNEXION')->count())->toBe(1);
});

it('refuse un mauvais mot de passe et journalise l\'échec', function () {
    $user = utilisateur('lecteur');

    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'faux'])
        ->assertUnprocessable()->assertJsonValidationErrors('email');

    expect(JournalAcces::where('action', 'CONNEXION')->first()->details)->toMatchArray(['succes' => false]);
});

it('refuse un compte désactivé', function () {
    $user = Utilisateur::factory()->inactif()->role('lecteur')->create();

    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])->assertForbidden();
});

it('exige une authentification', function () {
    $this->getJson('/api/v1/territoires')->assertUnauthorized();
});
