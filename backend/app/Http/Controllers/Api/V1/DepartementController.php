<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Departement;
use App\Models\Territoire;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartementController extends NoeudController
{
    protected string $modele = Departement::class;

    protected array $enfants = ['dossiers'];

    protected array $filtres = ['territoire_id'];

    protected function regles(Request $request, ?Model $noeud): array
    {
        $requis = $noeud ? 'sometimes' : 'required';

        return [
            'nom'           => [$requis, 'string', 'max:255'],
            'code'          => ['nullable', 'string', 'max:32'],
            'territoire_id' => [$requis, 'uuid', Rule::exists('territoires', 'id')->whereNull('deleted_at')],
            'description'   => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function parentDe(array $donnees): ?Model
    {
        return Territoire::find($donnees['territoire_id'] ?? null);
    }
}
