<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Permission\Traits\HasRoles;

class Utilisateur extends Authenticatable implements Auditable
{
    /** @use HasFactory<\Database\Factories\UtilisateurFactory> */
    use HasFactory, HasUuids, Notifiable, HasApiTokens, HasRoles, AuditableTrait;

    /**
     * Rôles du plus large au plus restreint. Un administrateur ne peut
     * attribuer que des rôles de rang strictement inférieur au sien.
     */
    public const RANGS_ROLES = [
        'admin'             => 100,
        'admin-continental' => 80,
        'admin-national'    => 60,
        'admin-district'    => 40,
        'archiviste'        => 20,
        'lecteur'           => 10,
    ];

    protected $table = 'utilisateurs';

    protected $fillable = [
        'email', 'mot_de_passe', 'nom_complet',
        'actif', 'derniere_connexion_at',
    ];

    protected $hidden = ['mot_de_passe', 'remember_token'];

    protected $auditExclude = ['mot_de_passe', 'remember_token'];

    /** Rôles et permissions sont tous déclarés sur le guard `web`. */
    protected string $guard_name = 'web';

    protected function casts(): array
    {
        return [
            'mot_de_passe'          => 'hashed',
            'actif'                 => 'boolean',
            'derniere_connexion_at' => 'datetime',
            'email_verified_at'     => 'datetime',
        ];
    }

    protected function email(): Attribute
    {
        return Attribute::set(fn (string $value) => mb_strtolower(trim($value)));
    }

    public function getAuthPasswordName(): string
    {
        return 'mot_de_passe';
    }

    public function perimetres(): HasMany
    {
        return $this->hasMany(UtilisateurPerimetre::class);
    }

    public function estAdminGlobal(): bool
    {
        return $this->hasRole('admin');
    }

    /** Rang du rôle le plus élevé de l'utilisateur (0 sans rôle). */
    public function rang(): int
    {
        return $this->getRoleNames()
            ->map(fn (string $r) => self::RANGS_ROLES[$r] ?? 0)
            ->max() ?? 0;
    }

    /**
     * Liste des chemins ltree dont l'utilisateur est responsable.
     * Vide pour un non-admin : l'utilisateur ne voit rien.
     *
     * @return array<int, array{chemin: string, lecture_seule: bool}>
     */
    public function perimetresChemins(): array
    {
        return $this->perimetres
            ->map(fn (UtilisateurPerimetre $p) => [
                'chemin'        => (string) $p->chemin,
                'lecture_seule' => (bool) $p->lecture_seule,
            ])
            ->all();
    }
}
