"use client";

import { use, useState } from "react";
import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import { Folder, Upload } from "lucide-react";
import { api } from "@/lib/api";
import { usePeut } from "@/stores/auth";
import { Button } from "@/components/ui/button";
import { Card, CardHeader, CardTitle } from "@/components/ui/card";
import { Dialog } from "@/components/ui/dialog";
import { Input, Select } from "@/components/ui/input";
import { Etat } from "@/components/shared/Etat";
import { Pagination } from "@/components/shared/Pagination";
import { PanneauNoeud } from "@/components/explorateur/PanneauNoeud";
import { DepotDocument } from "@/components/documents/DepotDocument";
import { STATUTS } from "@/components/documents/BadgeStatut";
import { TableDocuments } from "@/components/documents/TableDocuments";
import type { Document, Noeud, Pagine } from "@/types";

export default function PageDossier({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const peutDeposer = usePeut("documents.deposer");
  const [depot, setDepot] = useState(false);
  const [filtres, setFiltres] = useState({ q: "", statut: "", page: 1 });

  const dossier = useQuery({ queryKey: ["noeud", "dossier", id], queryFn: () => api.get<Noeud>(`/dossiers/${id}`) });
  const documents = useQuery({
    queryKey: ["documents", id, filtres],
    queryFn: () => api.get<Pagine<Document>>("/documents", { dossier_id: id, ...filtres }),
  });

  return (
    <div className="space-y-6">
      <PanneauNoeud niveau="dossier" id={id} />

      <Card>
        <CardHeader className="flex flex-wrap items-center gap-3">
          <CardTitle className="mr-auto">Documents</CardTitle>
          <Input
            placeholder="Filtrer…"
            className="h-8 w-48"
            value={filtres.q}
            onChange={(e) => setFiltres({ ...filtres, q: e.target.value, page: 1 })}
          />
          <Select className="h-8 w-36" value={filtres.statut} onChange={(e) => setFiltres({ ...filtres, statut: e.target.value, page: 1 })}>
            <option value="">Tous statuts</option>
            {STATUTS.map((s) => <option key={s.valeur} value={s.valeur}>{s.libelle}</option>)}
          </Select>
          {peutDeposer && dossier.data?.droits?.modifier && (
            <Button taille="sm" onClick={() => setDepot(true)}>
              <Upload className="h-4 w-4" /> Déposer
            </Button>
          )}
        </CardHeader>
        <Etat chargement={documents.isLoading} erreur={documents.error} vide={documents.data?.data.length === 0 && "Aucun document dans ce dossier."}>
          {documents.data && (
            <>
              <TableDocuments documents={documents.data.data} />
              <Pagination
                page={documents.data.meta?.current_page ?? 1}
                derniere={documents.data.meta?.last_page ?? 1}
                onPage={(page) => setFiltres({ ...filtres, page })}
              />
            </>
          )}
        </Etat>
      </Card>

      {dossier.data?.fil_ariane && (
        <p className="flex items-center gap-2 text-xs text-slate-400">
          <Folder className="h-3.5 w-3.5" />
          <Link href={`/recherche?niveau=dossier&id=${id}`} className="hover:underline">Rechercher dans ce dossier</Link>
        </p>
      )}

      <Dialog ouvert={depot} onFermer={() => setDepot(false)} titre="Déposer des documents">
        <DepotDocument dossierId={id} onTermine={() => setDepot(false)} />
      </Dialog>
    </div>
  );
}
