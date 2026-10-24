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
 * Jeu de démonstration : Afrique → Sénégal / Côte d'Ivoire / Mali → districts.
 * Chaque territoire (continent, nation, district) a ses départements de bureau,
 * chaque district ses assemblées ; dossiers, sous-dossiers et quelques
 * documents texte sous les deux. Plus les 5 comptes de démo du README
 * (mot de passe : `password`).
 */
class AfriqueSeeder extends Seeder
{
    private const NATIONS = [
        'Sénégal'       => ['SN', ['Dakar' => 'DK', 'Thiès' => 'TH', 'Saint-Louis' => 'SL']],
        'Côte d\'Ivoire' => ['CI', ['Abidjan' => 'AB', 'Bouaké' => 'BK']],
        'Mali'          => ['ML', ['Bamako' => 'BM']],
    ];

    /** Départements de bureau, présents à chaque niveau de territoire. */
    private const DEPARTEMENTS = ['Secrétariat', 'Finances', 'Jeunesse'];

    private const ASSEMBLEES = [
        'Dakar'   => ['Almadies', 'Plateau', 'Médina'],
        'Abidjan' => ['Cocody', 'Plateau'],
    ];

    public function run(): void
    {
        $afrique = Territoire::firstOrCreate(['nom' => 'Afrique', 'parent_id' => null], ['type' => 'continent', 'code' => 'AF']);
        $noeuds = ['afrique' => $afrique];
        $this->departements($afrique);

        foreach (self::NATIONS as $nation => [$code, $districts]) {
            $n = Territoire::firstOrCreate(['nom' => $nation, 'parent_id' => $afrique->id], ['type' => 'nation', 'code' => $code]);
            $noeuds[$nation] = $n;
            $this->departements($n);

            foreach ($districts as $district => $codeDistrict) {
                $d = Territoire::firstOrCreate(['nom' => $district, 'parent_id' => $n->id], ['type' => 'district', 'code' => $codeDistrict]);
                $noeuds[$district] = $d;
                $this->departements($d);

                foreach (self::ASSEMBLEES[$district] ?? ['Centre'] as $assemblee) {
                    $a = Assemblee::firstOrCreate(['nom' => $assemblee, 'territoire_id' => $d->id]);
                    $noeuds["{$district}/{$assemblee}"] = $a;
                    $this->dossiers($a, "{$assemblee} ({$district})");
                }
            }
        }

        $this->comptes($noeuds);
    }

    /** Départements de bureau d'un territoire ; seul le secrétariat reçoit des dossiers de démo. */
    private function departements(Territoire $territoire): void
    {
        foreach (self::DEPARTEMENTS as $departement) {
            $dep = Departement::firstOrCreate(['nom' => $departement, 'territoire_id' => $territoire->id]);

            if ($departement === 'Secrétariat') {
                $this->dossiers($dep, "{$departement} — {$territoire->nom}");
            }
        }
    }

    /** Dossiers types sous un département ou une assemblée, avec un sous-dossier. */
    private function dossiers(Departement|Assemblee $conteneur, string $libelle): void
    {
        $cle = [
            'departement_id' => $conteneur instanceof Departement ? $conteneur->id : null,
            'assemblee_id'   => $conteneur instanceof Assemblee ? $conteneur->id : null,
        ];

        $pv = Dossier::firstOrCreate(
            $cle + ['nom' => 'Procès-verbaux', 'parent_dossier_id' => null],
            ['reference' => 'PV-'.now()->year],
        );
        $courrier = Dossier::firstOrCreate($cle + ['nom' => 'Correspondances', 'parent_dossier_id' => null]);
        $entrant = Dossier::firstOrCreate($cle + ['nom' => 'Courrier entrant', 'parent_dossier_id' => $courrier->id]);

        if ($pv->documents()->exists()) {
            return;
        }

        $this->document($pv, "PV de réunion — {$libelle}",
            "Procès-verbal de la réunion ordinaire : {$libelle}.\n"
            ."Ordre du jour : budget annuel, rapport d'activité, élection du bureau.\n", 'valide');
        $this->document($pv, "Rapport financier — {$libelle}",
            "poste;montant\ncotisations;1250000\ndons;430000\ndépenses;980000\n", 'archive', 'csv');
        $this->document($entrant, "Lettre reçue — {$libelle}",
            "Objet : mise à disposition d'une salle pour les réunions mensuelles.\n", 'brouillon');
        $this->document($entrant, "Note interne confidentielle — {$libelle}",
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
