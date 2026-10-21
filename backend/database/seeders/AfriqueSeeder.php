<?php

namespace Database\Seeders;

use App\Models\Assemblee;
use App\Models\Departement;
use App\Models\Document;
use App\Models\Dossier;
use App\Models\Territoire;
use App\Models\Utilisateur;
use App\Services\StockageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;

/**
 * Jeu de démonstration : Afrique → Sénégal / Côte d'Ivoire / Mali → districts
 * → départements → assemblées → dossiers → quelques documents texte,
 * plus les 5 comptes de démo du README (mot de passe : `password`).
 */
class AfriqueSeeder extends Seeder
{
    private const NATIONS = [
        'Sénégal'       => ['SN', ['Dakar' => 'DK', 'Thiès' => 'TH', 'Saint-Louis' => 'SL']],
        'Côte d\'Ivoire' => ['CI', ['Abidjan' => 'AB', 'Bouaké' => 'BK']],
        'Mali'          => ['ML', ['Bamako' => 'BM']],
    ];

    private const DEPARTEMENTS = ['Administration', 'Jeunesse', 'Éducation'];

    private const ASSEMBLEES = [
        'Dakar'   => ['Almadies', 'Plateau', 'Médina'],
        'Abidjan' => ['Cocody', 'Plateau'],
    ];

    public function run(): void
    {
        $afrique = Territoire::firstOrCreate(['nom' => 'Afrique', 'parent_id' => null], ['type' => 'continent', 'code' => 'AF']);
        $noeuds = ['afrique' => $afrique];

        foreach (self::NATIONS as $nation => [$code, $districts]) {
            $n = Territoire::firstOrCreate(['nom' => $nation, 'parent_id' => $afrique->id], ['type' => 'nation', 'code' => $code]);
            $noeuds[$nation] = $n;

            foreach ($districts as $district => $codeDistrict) {
                $d = Territoire::firstOrCreate(['nom' => $district, 'parent_id' => $n->id], ['type' => 'district', 'code' => $codeDistrict]);
                $noeuds[$district] = $d;

                foreach (self::DEPARTEMENTS as $departement) {
                    $dep = Departement::firstOrCreate(['nom' => $departement, 'territoire_id' => $d->id]);

                    if ($departement !== 'Administration') {
                        continue;
                    }
                    foreach (self::ASSEMBLEES[$district] ?? ['Centre'] as $assemblee) {
                        $a = Assemblee::firstOrCreate(['nom' => $assemblee, 'departement_id' => $dep->id]);
                        $noeuds["{$district}/{$assemblee}"] = $a;
                        $this->dossiers($a, $district);
                    }
                }
            }
        }

        $this->comptes($noeuds);
    }

    private function dossiers(Assemblee $assemblee, string $district): void
    {
        $pv = Dossier::firstOrCreate(
            ['nom' => 'Procès-verbaux', 'assemblee_id' => $assemblee->id, 'parent_dossier_id' => null],
            ['reference' => 'PV-'.now()->year],
        );
        $courrier = Dossier::firstOrCreate(
            ['nom' => 'Correspondances', 'assemblee_id' => $assemblee->id, 'parent_dossier_id' => null],
        );
        $entrant = Dossier::firstOrCreate(
            ['nom' => 'Courrier entrant', 'assemblee_id' => $assemblee->id, 'parent_dossier_id' => $courrier->id],
        );

        if ($pv->documents()->exists()) {
            return;
        }

        $this->document($pv, "PV de l'assemblée générale — {$assemblee->nom}",
            "Procès-verbal de l'assemblée générale ordinaire tenue à {$assemblee->nom} ({$district}).\n"
            ."Ordre du jour : budget annuel, rapport d'activité, élection du bureau.\n", 'valide');
        $this->document($pv, "Rapport financier — {$assemblee->nom}",
            "poste;montant\ncotisations;1250000\ndons;430000\ndépenses;980000\n", 'archive', 'csv');
        $this->document($entrant, "Lettre de la mairie de {$district}",
            "Objet : mise à disposition d'une salle pour les réunions mensuelles.\n", 'brouillon');
        $this->document($entrant, "Note interne confidentielle — {$assemblee->nom}",
            "Diffusion restreinte : situation disciplinaire d'un membre.\n", 'valide', 'txt', true);
    }

    private function document(Dossier $dossier, string $titre, string $contenu, string $statut, string $ext = 'txt', bool $confidentiel = false): void
    {
        $fichier = UploadedFile::fake()->createWithContent(str($titre)->slug().".{$ext}", $contenu);
        $attributs = app(StockageService::class)->stocker($fichier);

        Document::create($attributs + [
            'titre'        => $titre,
            'description'  => strtok($contenu, "\n"),
            'statut'       => $statut,
            'confidentiel' => $confidentiel,
            'dossier_id'   => $dossier->id,
        ]);
    }

    /** @param array<string, Model> $noeuds */
    private function comptes(array $noeuds): void
    {
        $comptes = [
            ['admin@archivage.local',      'Administrateur Global',   'admin',             null],
            ['afrique@archivage.local',    'Admin Continental Afrique', 'admin-continental', $noeuds['afrique']],
            ['senegal@archivage.local',    'Admin National Sénégal',  'admin-national',    $noeuds['Sénégal']],
            ['archiviste@archivage.local', 'Archiviste Dakar',        'archiviste',        $noeuds['Dakar']],
            ['lecteur@archivage.local',    'Lecteur Almadies',        'lecteur',           $noeuds['Dakar/Almadies']],
        ];

        foreach ($comptes as [$email, $nom, $role, $perimetre]) {
            $user = Utilisateur::firstOrCreate(
                ['email' => $email],
                ['nom_complet' => $nom, 'mot_de_passe' => 'password', 'actif' => true],
            );
            $user->syncRoles([$role]);

            if ($perimetre && ! $user->perimetres()->exists()) {
                $perimetre->refresh();
                $user->perimetres()->create(['nom' => $perimetre->nom, 'chemin' => $perimetre->chemin]);
            }
        }
    }
}
