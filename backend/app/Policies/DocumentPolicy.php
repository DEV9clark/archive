<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\Dossier;
use App\Models\Utilisateur;
use App\Services\HierarchieService;

class DocumentPolicy
{
    public function __construct(protected HierarchieService $hierarchie) {}

    public function viewAny(Utilisateur $user): bool
    {
        return $user->checkPermissionTo('documents.voir');
    }

    public function voirConfidentiels(Utilisateur $user): bool
    {
        return $user->checkPermissionTo('documents.confidentiel');
    }

    public function view(Utilisateur $user, Document $document): bool
    {
        return $user->checkPermissionTo('documents.voir')
            && (! $document->confidentiel || $user->checkPermissionTo('documents.confidentiel'))
            && $this->hierarchie->peutLire($user, $document->chemin());
    }

    public function download(Utilisateur $user, Document $document): bool
    {
        return $this->view($user, $document) && $user->checkPermissionTo('documents.telecharger');
    }

    public function createIn(Utilisateur $user, Dossier $dossier): bool
    {
        return $user->checkPermissionTo('documents.deposer')
            && $this->hierarchie->peutEcrire($user, $dossier->chemin);
    }

    public function update(Utilisateur $user, Document $document): bool
    {
        return $user->checkPermissionTo('documents.modifier')
            && $this->view($user, $document)
            && $this->hierarchie->peutEcrire($user, $document->chemin());
    }

    public function delete(Utilisateur $user, Document $document): bool
    {
        return $user->checkPermissionTo('documents.supprimer')
            && $this->view($user, $document)
            && $this->hierarchie->peutEcrire($user, $document->chemin());
    }

    public function restore(Utilisateur $user, Document $document): bool
    {
        return $user->checkPermissionTo('corbeille.restaurer')
            && $this->hierarchie->peutEcrire($user, $document->chemin());
    }
}
