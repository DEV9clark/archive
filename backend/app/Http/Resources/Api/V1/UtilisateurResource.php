<?php

namespace App\Http\Resources\Api\V1;

use App\Services\HierarchieService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Utilisateur */
class UtilisateurResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'email'                 => $this->email,
            'nom_complet'           => $this->nom_complet,
            'actif'                 => $this->actif,
            'derniere_connexion_at' => $this->derniere_connexion_at,
            'roles'                 => $this->whenLoaded('roles', fn () => $this->getRoleNames()),
            'perimetres'            => $this->whenLoaded('perimetres', fn () => $this->perimetres->map(fn ($p) => [
                'id'            => $p->id,
                'nom'           => $p->nom,
                'chemin'        => $p->chemin,
                'lecture_seule' => $p->lecture_seule,
                'noeud'         => app(HierarchieService::class)->noeudParChemin($p->chemin),
            ])),
            'created_at'            => $this->created_at,
        ];
    }
}
