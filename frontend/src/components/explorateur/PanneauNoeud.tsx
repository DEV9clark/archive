"use client";

import { useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Pencil, Plus, Trash2 } from "lucide-react";
import { toast } from "sonner";
import { api, ErreurApi } from "@/lib/api";
import { useEnfants } from "@/hooks/useArborescence";
import { usePeut, useAuth } from "@/stores/auth";
import { LIBELLES_NIVEAU, RESSOURCE, formatDate, lienNoeud } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Dialog } from "@/components/ui/dialog";
import { Badge } from "@/components/ui/badge";
import { Etat } from "@/components/shared/Etat";
import { FilAriane } from "@/components/shared/FilAriane";
import { PageHeader } from "@/components/shared/PageHeader";
import { FormulaireNoeud, type CibleCreation } from "./FormulaireNoeud";
import { IconeNiveau } from "./IconeNiveau";
import type { Niveau, Noeud, TypeTerritoire } from "@/types";

/** Enfants créables sous un nœud donné. */
function ciblesCreation(noeud: Noeud | null): (CibleCreation & { libelle: string })[] {
  if (!noeud) return [{ niveau: "territoire", type: "continent", parent: {}, libelle: "Continent" }];
  switch (noeud.niveau) {
    case "territoire": {
      const sous: TypeTerritoire | null = noeud.type === "continent" ? "nation" : noeud.type === "nation" ? "district" : null;
      return [
        ...(sous ? [{ niveau: "territoire" as const, type: sous, parent: { parent_id: noeud.id }, libelle: sous === "nation" ? "Nation" : "District" }] : []),
        { niveau: "departement", parent: { territoire_id: noeud.id }, libelle: "Département" },
        ...(noeud.type === "district" ? [{ niveau: "assemblee" as const, parent: { territoire_id: noeud.id }, libelle: "Assemblée" }] : []),
      ];
    }
    case "departement":
      return [{ niveau: "dossier", parent: { departement_id: noeud.id }, libelle: "Dossier" }];
    case "assemblee":
      return [{ niveau: "dossier", parent: { assemblee_id: noeud.id }, libelle: "Dossier" }];
    default:
      return [{ niveau: "dossier", parent: { parent_dossier_id: noeud.id }, libelle: "Sous-dossier" }];
  }
}

export function PanneauNoeud({ niveau, id }: { niveau?: Niveau; id?: string }) {
  const router = useRouter();
  const client = useQueryClient();
  const adminGlobal = useAuth((s) => s.utilisateur?.admin_global);
  const peutGererHierarchie = usePeut("hierarchie.gerer");
  const peutGererDossiers = usePeut("dossiers.gerer");
  const [creation, setCreation] = useState<CibleCreation | null>(null);
  const [edition, setEdition] = useState(false);

  const detail = useQuery({
    queryKey: ["noeud", niveau, id],
    queryFn: () => api.get<Noeud>(`/${RESSOURCE[niveau!]}/${id}`),
    enabled: !!niveau && !!id,
  });
  const enfants = useEnfants(niveau, id);
  const noeud = detail.data ?? null;

  const suppression = useMutation({
    mutationFn: () => api.delete(`/${RESSOURCE[niveau!]}/${id}`),
    onSuccess: () => {
      client.invalidateQueries({ queryKey: ["arborescence"] });
      toast.success("Élément placé dans la corbeille.");
      const parent = noeud?.fil_ariane?.at(-2);
      router.push(parent ? lienNoeud(parent.niveau, parent.id) : "/explorateur");
    },
    onError: (e) => toast.error(e instanceof ErreurApi ? e.message : "Suppression impossible."),
  });

  // Écriture : le serveur tranche (droits.modifier) ; sans nœud, seul l'admin global crée un continent
  const peutCreer = noeud
    ? !!noeud.droits?.modifier && (noeud.niveau === "territoire" ? peutGererHierarchie : peutGererDossiers)
    : !!adminGlobal;

  return (
    <div className="space-y-4">
      <Etat chargement={detail.isLoading} erreur={detail.error}>
        <PageHeader
          titre={noeud?.nom ?? "Explorateur"}
          sousTitre={noeud ? <FilAriane etapes={noeud.fil_ariane?.slice(0, -1) ?? []} /> : "Parcourez la hiérarchie des archives."}
          actions={
            <>
              {peutCreer && ciblesCreation(noeud).map((c) => (
                <Button key={c.libelle} variante="secondaire" taille="sm" onClick={() => setCreation(c)}>
                  <Plus className="h-4 w-4" /> {c.libelle}
                </Button>
              ))}
              {noeud?.droits?.modifier && (
                <Button variante="secondaire" taille="sm" onClick={() => setEdition(true)}>
                  <Pencil className="h-4 w-4" /> Renommer
                </Button>
              )}
              {noeud?.droits?.supprimer && (
                <Button
                  variante="danger"
                  taille="sm"
                  disabled={suppression.isPending}
                  onClick={() => confirm(`Mettre « ${noeud.nom} » à la corbeille ?`) && suppression.mutate()}
                >
                  <Trash2 className="h-4 w-4" />
                </Button>
              )}
            </>
          }
        />

        {noeud && (
          <div className="flex flex-wrap gap-2 text-sm text-slate-500">
            <Badge ton="info">{noeud.type ?? LIBELLES_NIVEAU[noeud.niveau]}</Badge>
            {noeud.code && <Badge>Code {noeud.code}</Badge>}
            <span>Créé le {formatDate(noeud.created_at)}</span>
            {noeud.description && <p className="w-full text-slate-700">{noeud.description}</p>}
          </div>
        )}
      </Etat>

      <Card>
        <CardHeader><CardTitle>Contenu</CardTitle></CardHeader>
        <Etat chargement={enfants.isLoading} erreur={enfants.error} vide={enfants.data?.length === 0 && "Aucun élément."}>
          <ul className="divide-y divide-slate-100">
            {enfants.data?.map((n) => (
              <li key={`${n.niveau}-${n.id}`}>
                <Link href={lienNoeud(n.niveau, n.id)} className="flex items-center gap-3 px-5 py-3 hover:bg-slate-50">
                  <span className="text-primaire"><IconeNiveau niveau={n.niveau} type={n.type} /></span>
                  <span className={n.accessible ? "font-medium" : "text-slate-400"}>{n.nom}</span>
                  <Badge className="ml-auto">{n.type ?? LIBELLES_NIVEAU[n.niveau]}</Badge>
                  <span className="w-28 text-right text-xs text-slate-500">
                    {Object.entries(n.comptes).map(([k, v]) => `${v} ${k}`).join(" · ")}
                  </span>
                </Link>
              </li>
            ))}
          </ul>
        </Etat>
      </Card>

      <Dialog ouvert={!!creation} onFermer={() => setCreation(null)} titre={`Nouveau : ${creation?.type ?? LIBELLES_NIVEAU[creation?.niveau ?? "dossier"]}`}>
        {creation && (
          <FormulaireNoeud
            cible={creation}
            onTermine={(n) => {
              setCreation(null);
              router.push(lienNoeud(creation.niveau, n.id));
            }}
          />
        )}
      </Dialog>
      <Dialog ouvert={edition} onFermer={() => setEdition(false)} titre="Modifier">
        {noeud && <FormulaireNoeud noeud={noeud} onTermine={() => setEdition(false)} />}
      </Dialog>
    </div>
  );
}
