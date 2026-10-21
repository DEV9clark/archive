<?php

namespace App\Models;

use App\Models\Concerns\BelongsToPerimetre;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class Document extends Model implements Auditable
{
    use HasFactory, HasUuids, SoftDeletes, AuditableTrait, BelongsToPerimetre;

    public const STATUTS = ['brouillon', 'valide', 'archive'];

    protected $table = 'documents';

    protected $fillable = [
        'titre', 'nom_original', 'description',
        'cle_stockage', 'mime_type', 'extension',
        'taille_octets', 'checksum_sha256',
        'version', 'statut', 'confidentiel',
        'dossier_id', 'cree_par',
    ];

    /** Colonnes internes jamais exposées par l'API. */
    protected $hidden = ['cle_stockage', 'contenu_texte', 'recherche'];

    /** Le texte extrait peut être volumineux : il n'entre pas dans l'audit. */
    protected $auditExclude = ['contenu_texte', 'recherche'];

    protected $casts = [
        'taille_octets' => 'integer',
        'version'       => 'integer',
        'confidentiel'  => 'boolean',
    ];

    public function dossier(): BelongsTo
    {
        return $this->belongsTo(Dossier::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'cree_par');
    }

    /** Chemin ltree du dossier parent (le document n'a pas de chemin propre). */
    public function chemin(): ?string
    {
        return $this->dossier()->withTrashed()->value('chemin');
    }
}
