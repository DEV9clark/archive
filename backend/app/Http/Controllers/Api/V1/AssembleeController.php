<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Assemblee;
use App\Models\Territoire;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Une assemblée est rattachée directement à un district. */
class AssembleeController extends NoeudController
{
    protected string $modele = Assemblee::class;

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

    protected function verifier(array $donnees, ?Model $parent, ?Model $noeud): void
    {
        if ($parent?->type !== 'district') {
            throw ValidationException::withMessages([
                'territoire_id' => 'Une assemblée ne peut être créée que dans un district.',
            ]);
        }
    }
}
