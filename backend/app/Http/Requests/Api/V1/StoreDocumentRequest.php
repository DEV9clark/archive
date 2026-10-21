<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Dépôt d'un fichier dans un dossier (multipart/form-data). */
class StoreDocumentRequest extends FormRequest
{
    public function rules(): array
    {
        $config = config('filesystems.archive');

        return [
            'fichier'      => [
                'required', 'file',
                'max:'.($config['max_mb'] * 1024),
                'mimetypes:'.implode(',', $config['mimes']),
            ],
            'dossier_id'   => ['required', 'uuid', Rule::exists('dossiers', 'id')->whereNull('deleted_at')],
            'titre'        => ['nullable', 'string', 'max:255'],
            'description'  => ['nullable', 'string', 'max:5000'],
            'statut'       => ['nullable', Rule::in(Document::STATUTS)],
            'confidentiel' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'fichier.mimetypes' => 'Ce type de fichier n\'est pas accepté.',
            'fichier.max'       => 'Le fichier dépasse la taille maximale autorisée.',
        ];
    }
}
