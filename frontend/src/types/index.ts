// Types miroirs des réponses de l'API Laravel (/api/v1).

export type Niveau = "territoire" | "departement" | "assemblee" | "dossier";
export type TypeTerritoire = "continent" | "nation" | "district";
export type Statut = "brouillon" | "valide" | "archive";

export interface EtapeAriane {
  id: string;
  nom: string;
  niveau: Niveau;
  type: TypeTerritoire | null;
}

export interface Perimetre {
  id?: string;
  nom?: string;
  chemin: string;
  lecture_seule: boolean;
  fil_ariane?: EtapeAriane[];
  noeud?: { niveau: Niveau; id: string } | null;
}

export interface Utilisateur {
  id: string;
  email: string;
  nom_complet: string;
  actif: boolean;
  derniere_connexion_at: string | null;
  roles?: string[];
  perimetres?: Perimetre[];
  created_at: string;
}

export interface Profil extends Utilisateur {
  permissions: string[];
  admin_global: boolean;
  perimetres_detail: Perimetre[];
}

export interface NoeudArbre {
  id: string;
  nom: string;
  niveau: Niveau;
  type: TypeTerritoire | null;
  comptes: Record<string, number>;
  a_enfants: boolean;
  accessible: boolean;
}

export interface Noeud {
  id: string;
  nom: string;
  niveau: Niveau;
  code?: string | null;
  reference?: string | null;
  description?: string | null;
  type?: TypeTerritoire;
  parent_id?: string | null;
  territoire_id?: string;
  departement_id?: string;
  assemblee_id?: string;
  parent_dossier_id?: string | null;
  chemin: string;
  created_at: string;
  updated_at: string;
  fil_ariane?: EtapeAriane[];
  droits?: { modifier: boolean; supprimer: boolean };
  [compte: `${string}_count`]: number | undefined;
}

export interface Document {
  id: string;
  titre: string;
  nom_original: string;
  description: string | null;
  mime_type: string;
  extension: string | null;
  taille_octets: number;
  checksum_sha256: string;
  version: number;
  statut: Statut;
  confidentiel: boolean;
  dossier_id: string;
  dossier?: { id: string; nom: string };
  auteur?: { id: string; nom_complet: string } | null;
  rang?: number;
  created_at: string;
  updated_at: string;
  deleted_at: string | null;
}

export interface DocumentDetail {
  data: Document;
  fil_ariane: EtapeAriane[];
  droits: { telecharger: boolean; modifier: boolean; supprimer: boolean };
}

export interface Audit {
  id: number;
  evenement: string;
  anciennes: Record<string, unknown> | null;
  nouvelles: Record<string, unknown> | null;
  utilisateur: { id: string; nom_complet: string } | null;
  created_at: string;
}

export interface EntreeJournal {
  id: number;
  action: string;
  entite: string | null;
  entite_id: string | null;
  adresse_ip: string | null;
  user_agent: string | null;
  details: Record<string, unknown> | null;
  created_at: string;
  utilisateur: { id: string; nom_complet: string; email: string } | null;
}

export interface Pagine<T> {
  data: T[];
  current_page?: number;
  last_page?: number;
  total?: number;
  meta?: { current_page: number; last_page: number; total: number };
}

export interface Role {
  nom: string;
  description: string | null;
  rang: number;
  attribuable: boolean;
  utilisateurs: number;
  permissions: string[];
}

export interface Statistiques {
  totaux: Record<string, number>;
  par_statut: Partial<Record<Statut, number>>;
  par_type: { mime_type: string; total: number; taille_octets: number }[];
  depots_par_mois: { mois: string; total: number }[];
}

export interface ResultatRecherche {
  documents: Document[];
  noeuds: (EtapeAriane & { rang: number; fil_ariane: EtapeAriane[] })[];
}

export interface ElementCorbeille {
  niveau: Niveau | "document";
  id: string;
  nom: string;
  deleted_at: string;
}
