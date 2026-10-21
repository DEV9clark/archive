<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Document */
class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'titre'           => $this->titre,
            'nom_original'    => $this->nom_original,
            'description'     => $this->description,
            'mime_type'       => $this->mime_type,
            'extension'       => $this->extension,
            'taille_octets'   => $this->taille_octets,
            'checksum_sha256' => $this->checksum_sha256,
            'version'         => $this->version,
            'statut'          => $this->statut,
            'confidentiel'    => $this->confidentiel,
            'dossier_id'      => $this->dossier_id,
            'dossier'         => $this->whenLoaded('dossier', fn () => [
                'id'  => $this->dossier->id,
                'nom' => $this->dossier->nom,
            ]),
            'auteur'          => $this->whenLoaded('auteur', fn () => $this->auteur ? [
                'id'          => $this->auteur->id,
                'nom_complet' => $this->auteur->nom_complet,
            ] : null),
            'rang'            => $this->whenHas('rang'),
            'extrait'         => $this->whenHas('extrait'),
            'created_at'      => $this->created_at,
            'updated_at'      => $this->updated_at,
            'deleted_at'      => $this->deleted_at,
        ];
    }
}
