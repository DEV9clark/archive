<?php

namespace App\Services;

use App\Models\JournalAcces;
use App\Models\Utilisateur;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/** Écrit dans le journal d'accès probant (`journal_acces`). */
class JournalService
{
    public function __construct(
        private readonly Request $request,
        private readonly HierarchieService $hierarchie,
    ) {}

    public function tracer(string $action, ?Model $entite = null, array $details = [], ?Utilisateur $user = null): JournalAcces
    {
        $user ??= $this->request->user();

        return JournalAcces::create([
            'utilisateur_id' => $user?->getKey(),
            'action'         => $action,
            'entite'         => $entite ? $this->hierarchie->niveauDe($entite) : null,
            'entite_id'      => $entite?->getKey(),
            'chemin'         => $entite ? $this->hierarchie->cheminDe($entite) : null,
            'adresse_ip'     => $this->request->ip(),
            'user_agent'     => mb_substr((string) $this->request->userAgent(), 0, 512) ?: null,
            'details'        => $details ?: null,
        ]);
    }
}
