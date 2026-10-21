<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Mise à jour des métadonnées (le fichier lui-même est immuable). */
class UpdateDocumentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'titre'        => ['sometimes', 'string', 'max:255'],
            'description'  => ['nullable', 'string', 'max:5000'],
            'statut'       => ['sometimes', Rule::in(Document::STATUTS)],
            'confidentiel' => ['sometimes', 'boolean'],
            'dossier_id'   => ['sometimes', 'uuid', Rule::exists('dossiers', 'id')->whereNull('deleted_at')],
        ];
    }
}
