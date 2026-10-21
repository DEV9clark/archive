<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Assemblee;
use App\Models\Dossier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Un dossier est rangé dans une assemblée, éventuellement sous un dossier
 * parent de la même assemblée (sous-dossiers à profondeur libre).
 */
class DossierController extends NoeudController
{
    protected string $modele = Dossier::class;

    protected array $enfants = ['enfants', 'documents'];

    protected array $filtres = ['assemblee_id', 'parent_dossier_id'];

    protected function regles(Request $request, ?Model $noeud): array
    {
        // Sous-dossier : l'assemblée est déduite du parent
        if ($request->filled('parent_dossier_id') && ! $request->filled('assemblee_id')) {
            $request->merge([
                'assemblee_id' => Dossier::whereKey($request->input('parent_dossier_id'))->value('assemblee_id'),
            ]);
        }

        $requis = $noeud ? 'sometimes' : 'required';

        return [
            'nom'               => [$requis, 'string', 'max:255'],
            'reference'         => ['nullable', 'string', 'max:64'],
            'description'       => ['nullable', 'string', 'max:1000'],
            'assemblee_id'      => [$requis, 'uuid', Rule::exists('assemblees', 'id')->whereNull('deleted_at')],
            'parent_dossier_id' => [
                'nullable', 'uuid',
                Rule::exists('dossiers', 'id')->whereNull('deleted_at'),
                Rule::notIn(array_filter([$noeud?->getKey()])),
            ],
        ];
    }

    protected function parentDe(array $donnees): ?Model
    {
        return ! empty($donnees['parent_dossier_id'])
            ? Dossier::find($donnees['parent_dossier_id'])
            : Assemblee::find($donnees['assemblee_id'] ?? null);
    }
}
