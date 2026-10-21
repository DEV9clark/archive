<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UtilisateurPerimetre extends Model
{
    use HasUuids;

    protected $table = 'utilisateur_perimetres';

    protected $fillable = [
        'utilisateur_id',
        'nom',
        'chemin',
        'lecture_seule',
    ];

    protected $casts = [
        'lecture_seule' => 'boolean',
    ];

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class);
    }
}
