<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Journal d'accès en ajout seul (trigger PostgreSQL, cf. migration 000010). */
class JournalAcces extends Model
{
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = null;

    protected $table = 'journal_acces';

    protected $fillable = [
        'utilisateur_id', 'action', 'entite', 'entite_id', 'chemin',
        'adresse_ip', 'user_agent', 'details',
    ];

    protected $casts = [
        'details' => 'array',
        'created_at' => 'datetime',
    ];

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class);
    }

    public static function actions(): array
    {
        return ['CONNEXION', 'DECONNEXION', 'LECTURE', 'TELECHARGEMENT', 'UPLOAD', 'SUPPRESSION', 'RESTAURATION', 'MODIFICATION', 'CREATION', 'VERIFICATION'];
    }
}
