<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Assemblee;
use App\Models\Departement;
use App\Models\Dossier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Un dossier est rangé dans un département OU une assemblée, éventuellement
 * sous un dossier parent du même conteneur (sous-dossiers à profondeur libre).
 */
class DossierController extends NoeudController
{
    protected string $modele = Dossier::class;

    protected array $enfants = ['enfants', 'documents'];

    protected array $filtres = ['departement_id', 'assemblee_id', 'parent_dossier_id'];

    protected function regles(Request $request, ?Model $noeud): array
    {
        if ($request->filled('parent_dossier_id')) {
            // Sous-dossier : le conteneur est hérité du parent
            $parent = Dossier::find($request->input('parent_dossier_id'));
            if ($parent) {
                $request->merge($parent->only(['departement_id', 'assemblee_id']));
            }
        } elseif ($request->filled('departement_id') && ! $request->filled('assemblee_id')) {
            $request->merge(['assemblee_id' => null]);
        } elseif ($request->filled('assemblee_id') && ! $request->filled('departement_id')) {
            $request->merge(['departement_id' => null]);
        }

        $requis = $noeud ? 'sometimes' : 'required';

        return [
            'nom'               => [$requis, 'string', 'max:255'],
            'reference'         => ['nullable', 'string', 'max:64'],
            'description'       => ['nullable', 'string', 'max:1000'],
            'departement_id'    => [
                $noeud ? 'nullable' : 'required_without:assemblee_id', 'nullable', 'uuid', 'prohibits:assemblee_id',
                Rule::exists('departements', 'id')->whereNull('deleted_at'),
            ],
            'assemblee_id'      => [
                'nullable', 'uuid',
                Rule::exists('assemblees', 'id')->whereNull('deleted_at'),
            ],
            'parent_dossier_id' => [
                'nullable', 'uuid',
                Rule::exists('dossiers', 'id')->whereNull('deleted_at'),
                Rule::notIn(array_filter([$noeud?->getKey()])),
            ],
        ];
    }

    protected function parentDe(array $donnees): ?Model
    {
        return match (true) {
            ! empty($donnees['parent_dossier_id']) => Dossier::find($donnees['parent_dossier_id']),
            ! empty($donnees['assemblee_id'])      => Assemblee::find($donnees['assemblee_id']),
            default                                => Departement::find($donnees['departement_id'] ?? null),
        };
    }

    protected function verifier(array $donnees, ?Model $parent, ?Model $noeud): void
    {
        $rattachement = $donnees + ($noeud?->only(['departement_id', 'assemblee_id']) ?? []);

        if (empty($rattachement['departement_id']) === empty($rattachement['assemblee_id'])) {
            throw ValidationException::withMessages([
                'departement_id' => 'Un dossier doit être rangé soit dans un département, soit dans une assemblée.',
            ]);
        }
    }
}
