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

class Assemblee extends Model implements Auditable
{
    use HasFactory, HasUuids, SoftDeletes, AuditableTrait, HasCheminHierarchique, BelongsToPerimetre;

    protected $table = 'assemblees';

    protected $fillable = [
        'nom', 'code', 'territoire_id', 'description',
    ];

    public static function colonnesParent(): array
    {
        return ['territoire_id'];
    }

    /** District auquel l'assemblée est rattachée. */
    public function territoire(): BelongsTo
    {
        return $this->belongsTo(Territoire::class);
    }

    public function dossiers(): HasMany
    {
        return $this->hasMany(Dossier::class);
    }
}
