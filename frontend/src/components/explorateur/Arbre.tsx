"use client";

import { useState } from "react";
import Link from "next/link";
import { ChevronRight, Loader2 } from "lucide-react";
import { useEnfants } from "@/hooks/useArborescence";
import { cn, lienNoeud } from "@/lib/utils";
import { IconeNiveau } from "./IconeNiveau";
import type { NoeudArbre } from "@/types";

/** Arbre de navigation chargé à la demande, niveau par niveau. */
export function Arbre({ selection }: { selection?: string }) {
  const { data, isLoading } = useEnfants();

  if (isLoading) return <Loader2 className="m-4 h-4 w-4 animate-spin text-slate-400" />;
  if (!data?.length) return <p className="p-4 text-sm text-slate-500">Aucun élément dans votre périmètre.</p>;

  return (
    <ul role="tree" className="text-sm">
      {data.map((n) => <Branche key={n.id} noeud={n} selection={selection} profondeur={0} />)}
    </ul>
  );
}

function Branche({ noeud, selection, profondeur }: { noeud: NoeudArbre; selection?: string; profondeur: number }) {
  const [ouvert, setOuvert] = useState(profondeur < 1);
  const { data, isLoading } = useEnfants(noeud.niveau, noeud.id, ouvert && noeud.a_enfants);

  return (
    <li role="treeitem" aria-selected={selection === noeud.id} aria-expanded={noeud.a_enfants ? ouvert : undefined}>
      <div
        className={cn(
          "flex items-center gap-1 rounded-md py-1 pr-2 hover:bg-slate-100",
          selection === noeud.id && "bg-primaire-clair text-primaire",
        )}
        style={{ paddingLeft: profondeur * 14 + 4 }}
      >
        <button
          onClick={() => setOuvert(!ouvert)}
          className={cn("rounded p-0.5 text-slate-400", !noeud.a_enfants && "invisible")}
          aria-label={ouvert ? "Replier" : "Déplier"}
        >
          <ChevronRight className={cn("h-3.5 w-3.5 transition-transform", ouvert && "rotate-90")} />
        </button>
        <Link
          href={lienNoeud(noeud.niveau, noeud.id)}
          className={cn("flex min-w-0 flex-1 items-center gap-2", !noeud.accessible && "text-slate-400")}
          title={noeud.accessible ? noeud.nom : `${noeud.nom} (hors périmètre — navigation seule)`}
        >
          <IconeNiveau niveau={noeud.niveau} type={noeud.type} className="h-4 w-4 shrink-0" />
          <span className="truncate">{noeud.nom}</span>
        </Link>
        {isLoading && <Loader2 className="h-3 w-3 animate-spin text-slate-400" />}
      </div>
      {ouvert && data && data.length > 0 && (
        <ul role="group">
          {data.map((n) => <Branche key={`${n.niveau}-${n.id}`} noeud={n} selection={selection} profondeur={profondeur + 1} />)}
        </ul>
      )}
    </li>
  );
}
