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

class Territoire extends Model implements Auditable
{
    use HasFactory, HasUuids, SoftDeletes, AuditableTrait, HasCheminHierarchique, BelongsToPerimetre;

    public const TYPES = ['continent', 'nation', 'district'];

    protected $table = 'territoires';

    protected $fillable = [
        'nom', 'code', 'type', 'parent_id', 'description',
    ];

    protected $casts = [
        'profondeur' => 'integer',
    ];

    public static function colonnesParent(): array
    {
        return ['parent_id'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, 'parent_id');
    }

    public function enfants(): HasMany
    {
        return $this->hasMany(static::class, 'parent_id');
    }

    public function departements(): HasMany
    {
        return $this->hasMany(Departement::class);
    }

    /** Type attendu pour un enfant direct (continent → nation → district). */
    public function typeEnfant(): ?string
    {
        $i = array_search($this->type, self::TYPES, true);

        return self::TYPES[$i + 1] ?? null;
    }
}
