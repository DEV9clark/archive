"use client";

import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import Link from "next/link";
import { api } from "@/lib/api";
import { LIBELLES_NIVEAU, formatDate, lienNoeud } from "@/lib/utils";
import { Card } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Input, Select } from "@/components/ui/input";
import { Table, Td, Th } from "@/components/ui/table";
import { Etat } from "@/components/shared/Etat";
import { PageHeader } from "@/components/shared/PageHeader";
import { Pagination } from "@/components/shared/Pagination";
import type { EntreeJournal, Niveau, Pagine } from "@/types";

const TONS: Record<string, "neutre" | "info" | "succes" | "alerte" | "danger"> = {
  CONNEXION: "info", TELECHARGEMENT: "succes", UPLOAD: "succes", SUPPRESSION: "danger", MODIFICATION: "alerte",
};

export default function PageJournal() {
  const [filtres, setFiltres] = useState({ action: "", du: "", au: "", page: 1 });
  const actions = useQuery({ queryKey: ["journal-actions"], queryFn: () => api.get<{ data: string[] }>("/journal/actions").then((r) => r.data) });
  const journal = useQuery({ queryKey: ["journal", filtres], queryFn: () => api.get<Pagine<EntreeJournal>>("/journal", filtres) });

  const lien = (e: EntreeJournal) => {
    if (!e.entite || !e.entite_id) return null;
    const href = e.entite === "document" ? `/documents/${e.entite_id}` : lienNoeud(e.entite as Niveau, e.entite_id);
    return <Link href={href} className="text-primaire hover:underline">{LIBELLES_NIVEAU[e.entite as Niveau] ?? e.entite}</Link>;
  };

  return (
    <div className="space-y-6">
      <PageHeader titre="Journal d'accès" sousTitre="Registre probant en ajout seul : consultations, téléchargements, dépôts, connexions." />
      <Card>
        <div className="flex flex-wrap items-end gap-3 p-4">
          <Select className="w-48" value={filtres.action} onChange={(e) => setFiltres({ ...filtres, action: e.target.value, page: 1 })}>
            <option value="">Toutes les actions</option>
            {actions.data?.map((a) => <option key={a} value={a}>{a}</option>)}
          </Select>
          <label className="text-xs text-slate-500">Du<Input type="date" value={filtres.du} onChange={(e) => setFiltres({ ...filtres, du: e.target.value, page: 1 })} /></label>
          <label className="text-xs text-slate-500">Au<Input type="date" value={filtres.au} onChange={(e) => setFiltres({ ...filtres, au: e.target.value, page: 1 })} /></label>
        </div>
        <Etat chargement={journal.isLoading} erreur={journal.error} vide={journal.data?.data.length === 0 && "Aucune entrée."}>
          {journal.data && (
            <>
              <Table>
                <thead><tr><Th>Date</Th><Th>Utilisateur</Th><Th>Action</Th><Th>Élément</Th><Th>Détails</Th><Th>Adresse IP</Th></tr></thead>
                <tbody>
                  {journal.data.data.map((e) => (
                    <tr key={e.id}>
                      <Td className="whitespace-nowrap">{formatDate(e.created_at, true)}</Td>
                      <Td>{e.utilisateur?.nom_complet ?? <span className="text-slate-400">anonyme</span>}</Td>
                      <Td><Badge ton={e.details?.succes === false ? "danger" : TONS[e.action] ?? "neutre"}>{e.action}</Badge></Td>
                      <Td>{lien(e) ?? "—"}</Td>
                      <Td className="max-w-xs truncate font-mono text-xs text-slate-500">{e.details ? JSON.stringify(e.details) : ""}</Td>
                      <Td className="font-mono text-xs">{e.adresse_ip}</Td>
                    </tr>
                  ))}
                </tbody>
              </Table>
              <Pagination page={journal.data.current_page ?? 1} derniere={journal.data.last_page ?? 1} onPage={(page) => setFiltres({ ...filtres, page })} />
            </>
          )}
        </Etat>
      </Card>
    </div>
  );
}
