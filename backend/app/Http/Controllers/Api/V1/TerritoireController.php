<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Territoire;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TerritoireController extends NoeudController
{
    protected string $modele = Territoire::class;

    protected array $enfants = ['enfants', 'departements', 'assemblees'];

    protected array $filtres = ['parent_id', 'type'];

    protected function regles(Request $request, ?Model $noeud): array
    {
        $requis = $noeud ? 'sometimes' : 'required';

        return [
            'nom'         => [$requis, 'string', 'max:255'],
            'code'        => ['nullable', 'string', 'max:32'],
            'type'        => [$requis, Rule::in(Territoire::TYPES)],
            'parent_id'   => ['nullable', 'uuid', Rule::exists('territoires', 'id')->whereNull('deleted_at')],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function parentDe(array $donnees): ?Model
    {
        return isset($donnees['parent_id']) ? Territoire::find($donnees['parent_id']) : null;
    }

    /** Continent à la racine, puis nation, puis district. */
    protected function verifier(array $donnees, ?Model $parent, ?Model $noeud): void
    {
        $type = $donnees['type'] ?? $noeud?->type;
        $attendu = $parent ? $parent->typeEnfant() : 'continent';

        if ($type !== $attendu) {
            throw ValidationException::withMessages([
                'type' => $attendu
                    ? "À cet endroit, le territoire doit être de type « {$attendu} »."
                    : 'Un district ne peut pas contenir de sous-territoire.',
            ]);
        }
    }
}
