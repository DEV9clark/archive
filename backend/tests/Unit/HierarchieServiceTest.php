<?php

use App\Services\HierarchieService;

it('teste l\'inclusion de chemins ltree sans faux positif de préfixe', function (string $parent, string $enfant, bool $attendu) {
    expect(HierarchieService::contient($parent, $enfant))->toBe($attendu);
})->with([
    ['a.b', 'a.b', true],
    ['a.b', 'a.b.c', true],
    ['a.b', 'a.bc', false],
    ['a.b.c', 'a.b', false],
]);
