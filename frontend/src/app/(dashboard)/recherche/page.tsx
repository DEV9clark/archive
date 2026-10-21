"use client";

import { Suspense, useState } from "react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { useQuery } from "@tanstack/react-query";
import { api } from "@/lib/api";
import { LIBELLES_NIVEAU, lienNoeud } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Card, CardHeader, CardTitle } from "@/components/ui/card";
import { Input, Select } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Etat } from "@/components/shared/Etat";
import { PageHeader } from "@/components/shared/PageHeader";
import { IconeNiveau } from "@/components/explorateur/IconeNiveau";
import { STATUTS } from "@/components/documents/BadgeStatut";
import { TableDocuments } from "@/components/documents/TableDocuments";
import type { ResultatRecherche } from "@/types";

const CHAMPS = ["q", "statut", "mime", "du", "au", "niveau", "id"] as const;

function Recherche() {
  const router = useRouter();
  const params = useSearchParams();
  const actifs = Object.fromEntries(CHAMPS.map((c) => [c, params.get(c) ?? ""])) as Record<(typeof CHAMPS)[number], string>;
  const [saisie, setSaisie] = useState(actifs);

  const resultats = useQuery({
    queryKey: ["recherche", actifs],
    queryFn: () => api.get<ResultatRecherche>("/recherche", actifs),
    enabled: actifs.q.length >= 2,
  });

  function lancer(e: React.FormEvent) {
    e.preventDefault();
    const q = new URLSearchParams(Object.entries(saisie).filter(([, v]) => v));
    router.push(`/recherche?${q}`);
  }

  return (
    <div className="space-y-6">
      <PageHeader
        titre="Recherche"
        sousTitre="Insensible aux accents et tolérante aux fautes de frappe."
      />

      <Card>
        <form onSubmit={lancer} className="flex flex-wrap items-end gap-3 p-4">
          <Input className="min-w-64 flex-1" placeholder="Mots-clés…" value={saisie.q} onChange={(e) => setSaisie({ ...saisie, q: e.target.value })} />
          <Select className="w-36" value={saisie.statut} onChange={(e) => setSaisie({ ...saisie, statut: e.target.value })}>
            <option value="">Tous statuts</option>
            {STATUTS.map((s) => <option key={s.valeur} value={s.valeur}>{s.libelle}</option>)}
          </Select>
          <Select className="w-36" value={saisie.mime} onChange={(e) => setSaisie({ ...saisie, mime: e.target.value })}>
            <option value="">Tous types</option>
            <option value="application/pdf">PDF</option>
            <option value="image/">Images</option>
            <option value="text/">Texte</option>
            <option value="application/vnd">Bureautique</option>
          </Select>
          <label className="text-xs text-slate-500">Du<Input type="date" value={saisie.du} onChange={(e) => setSaisie({ ...saisie, du: e.target.value })} /></label>
          <label className="text-xs text-slate-500">Au<Input type="date" value={saisie.au} onChange={(e) => setSaisie({ ...saisie, au: e.target.value })} /></label>
          <Button type="submit" disabled={saisie.q.trim().length < 2}>Rechercher</Button>
        </form>
        {actifs.niveau && (
          <div className="flex items-center gap-2 border-t border-slate-100 px-4 py-2 text-sm text-slate-600">
            Restreint à un {LIBELLES_NIVEAU[actifs.niveau as keyof typeof LIBELLES_NIVEAU]?.toLowerCase()}.
            <button className="text-primaire hover:underline" onClick={() => setSaisie({ ...saisie, niveau: "", id: "" })}>
              Retirer au prochain lancement
            </button>
          </div>
        )}
      </Card>

      {actifs.q.length >= 2 && (
        <Etat chargement={resultats.isLoading} erreur={resultats.error}>
          {resultats.data && (
            <div className="grid gap-6 lg:grid-cols-[1fr_320px]">
              <Card>
                <CardHeader><CardTitle>Documents ({resultats.data.documents.length})</CardTitle></CardHeader>
                <Etat vide={!resultats.data.documents.length && "Aucun document trouvé."}>
                  <TableDocuments documents={resultats.data.documents} avecDossier />
                </Etat>
              </Card>
              <Card className="h-fit">
                <CardHeader><CardTitle>Lieux et dossiers ({resultats.data.noeuds.length})</CardTitle></CardHeader>
                <Etat vide={!resultats.data.noeuds.length && "Aucun résultat."}>
                  <ul className="divide-y divide-slate-100">
                    {resultats.data.noeuds.map((n) => (
                      <li key={`${n.niveau}-${n.id}`}>
                        <Link href={lienNoeud(n.niveau, n.id)} className="block px-5 py-3 hover:bg-slate-50">
                          <div className="flex items-center gap-2 font-medium">
                            <IconeNiveau niveau={n.niveau} type={n.type} /> {n.nom}
                            <Badge className="ml-auto">{n.type ?? LIBELLES_NIVEAU[n.niveau]}</Badge>
                          </div>
                          <div className="mt-0.5 truncate text-xs text-slate-500">
                            {n.fil_ariane.slice(0, -1).map((e) => e.nom).join(" › ")}
                          </div>
                        </Link>
                      </li>
                    ))}
                  </ul>
                </Etat>
              </Card>
            </div>
          )}
        </Etat>
      )}
    </div>
  );
}

export default function PageRecherche() {
  return (
    <Suspense>
      <Recherche />
    </Suspense>
  );
}
