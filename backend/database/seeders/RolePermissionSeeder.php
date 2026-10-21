<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * 6 rôles × 13 permissions. Le rôle donne le « quoi », le périmètre ltree
 * de l'utilisateur donne le « où ». Idempotent.
 */
class RolePermissionSeeder extends Seeder
{
    public const PERMISSIONS = [
        'hierarchie.voir'        => 'Parcourir territoires, départements, assemblées et dossiers',
        'hierarchie.gerer'       => 'Créer, renommer, déplacer, supprimer territoires, départements et assemblées',
        'dossiers.gerer'         => 'Créer, renommer, déplacer, supprimer des dossiers',
        'documents.voir'         => 'Consulter les fiches documents',
        'documents.telecharger'  => 'Télécharger les fichiers',
        'documents.deposer'      => 'Déposer des documents',
        'documents.modifier'     => 'Modifier les métadonnées d\'un document',
        'documents.supprimer'    => 'Mettre un document à la corbeille',
        'documents.confidentiel' => 'Voir et classer les documents confidentiels',
        'corbeille.restaurer'    => 'Restaurer des éléments supprimés',
        'journal.voir'           => 'Consulter le journal d\'accès',
        'stats.voir'             => 'Voir les statistiques',
        'utilisateurs.gerer'     => 'Gérer les comptes de rang inférieur dans son périmètre',
    ];

    public const ROLES = [
        'admin'             => ['Administrateur global', '*'],
        'admin-continental' => ['Administrateur d\'un continent', '*'],
        'admin-national'    => ['Administrateur d\'une nation', '*'],
        'admin-district'    => ['Administrateur d\'un district', '*'],
        'archiviste'        => ['Archiviste : dépose et organise les documents', [
            'hierarchie.voir', 'dossiers.gerer',
            'documents.voir', 'documents.telecharger', 'documents.deposer',
            'documents.modifier', 'documents.supprimer', 'documents.confidentiel',
            'corbeille.restaurer', 'stats.voir',
        ]],
        'lecteur'           => ['Lecteur : consultation seule', [
            'hierarchie.voir', 'documents.voir', 'documents.telecharger', 'stats.voir',
        ]],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $nom => $description) {
            Permission::updateOrCreate(['name' => $nom, 'guard_name' => 'web'], ['description' => $description]);
        }

        foreach (self::ROLES as $nom => [$description, $permissions]) {
            $role = Role::updateOrCreate(['name' => $nom, 'guard_name' => 'web'], ['description' => $description]);
            $role->syncPermissions($permissions === '*' ? array_keys(self::PERMISSIONS) : $permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
