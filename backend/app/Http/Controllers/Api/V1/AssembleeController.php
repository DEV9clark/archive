<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Assemblee;
use App\Models\Departement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AssembleeController extends NoeudController
{
    protected string $modele = Assemblee::class;

    protected array $enfants = ['dossiers'];

    protected array $filtres = ['departement_id'];

    protected function regles(Request $request, ?Model $noeud): array
    {
        $requis = $noeud ? 'sometimes' : 'required';

        return [
            'nom'            => [$requis, 'string', 'max:255'],
            'code'           => ['nullable', 'string', 'max:32'],
            'departement_id' => [$requis, 'uuid', Rule::exists('departements', 'id')->whereNull('deleted_at')],
            'description'    => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function parentDe(array $donnees): ?Model
    {
        return Departement::find($donnees['departement_id'] ?? null);
    }
}
