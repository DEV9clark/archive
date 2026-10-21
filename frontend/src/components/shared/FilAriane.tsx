import Link from "next/link";
import { ChevronRight } from "lucide-react";
import { lienNoeud } from "@/lib/utils";
import type { EtapeAriane } from "@/types";

export function FilAriane({ etapes }: { etapes: EtapeAriane[] }) {
  return (
    <nav aria-label="Fil d'Ariane" className="flex flex-wrap items-center gap-1 text-sm text-slate-500">
      <Link href="/explorateur" className="hover:text-primaire">Racine</Link>
      {etapes.map((e) => (
        <span key={e.id} className="flex items-center gap-1">
          <ChevronRight className="h-3.5 w-3.5" />
          <Link href={lienNoeud(e.niveau, e.id)} className="hover:text-primaire">{e.nom}</Link>
        </span>
      ))}
    </nav>
  );
}
