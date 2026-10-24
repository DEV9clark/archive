<?php

namespace App\Models;

use App\Models\Concerns\BelongsToPerimetre;
use App\Models\Concerns\HasCheminHierarchique;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/** Rangé dans un département OU une assemblée (exactement un des deux). */
class Dossier extends Model implements Auditable
{
    use HasFactory, HasUuids, SoftDeletes, AuditableTrait, HasCheminHierarchique, BelongsToPerimetre;

    protected $table = 'dossiers';

    protected $fillable = [
        'nom', 'reference', 'description',
        'departement_id', 'assemblee_id', 'parent_dossier_id',
    ];

    public static function colonnesParent(): array
    {
        return ['departement_id', 'assemblee_id', 'parent_dossier_id'];
    }

    public function departement(): BelongsTo
    {
        return $this->belongsTo(Departement::class);
    }

    public function assemblee(): BelongsTo
    {
        return $this->belongsTo(Assemblee::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, 'parent_dossier_id');
    }

    public function enfants(): HasMany
    {
        return $this->hasMany(static::class, 'parent_dossier_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
