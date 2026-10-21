import { Lock } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import type { Statut } from "@/types";

const TONS = { brouillon: "alerte", valide: "succes", archive: "neutre" } as const;
const LIBELLES = { brouillon: "Brouillon", valide: "Validé", archive: "Archivé" } as const;

export function BadgeStatut({ statut }: { statut: Statut }) {
  return <Badge ton={TONS[statut]}>{LIBELLES[statut]}</Badge>;
}

export function BadgeConfidentiel() {
  return <Badge ton="danger"><Lock className="h-3 w-3" /> Confidentiel</Badge>;
}

export const STATUTS: { valeur: Statut; libelle: string }[] = [
  { valeur: "brouillon", libelle: LIBELLES.brouillon },
  { valeur: "valide", libelle: LIBELLES.valide },
  { valeur: "archive", libelle: LIBELLES.archive },
];
