"use client";

import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { Pencil, UserPlus } from "lucide-react";
import { api } from "@/lib/api";
import { formatDate } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Dialog } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Table, Td, Th } from "@/components/ui/table";
import { Etat } from "@/components/shared/Etat";
import { PageHeader } from "@/components/shared/PageHeader";
import { FormulaireUtilisateur } from "@/components/admin/FormulaireUtilisateur";
import type { Utilisateur } from "@/types";

export default function PageUtilisateurs() {
  const [q, setQ] = useState("");
  const [edition, setEdition] = useState<Utilisateur | "nouveau" | null>(null);
  const { data, isLoading, error } = useQuery({
    queryKey: ["utilisateurs", q],
    queryFn: () => api.get<{ data: Utilisateur[] }>("/admin/utilisateurs", { q }).then((r) => r.data),
  });

  return (
    <div className="space-y-6">
      <PageHeader
        titre="Utilisateurs"
        sousTitre="Comptes de rang inférieur au vôtre, dans votre périmètre."
        actions={<Button onClick={() => setEdition("nouveau")}><UserPlus className="h-4 w-4" /> Nouveau compte</Button>}
      />
      <Card>
        <div className="p-4"><Input placeholder="Rechercher un nom ou un e-mail…" className="max-w-sm" value={q} onChange={(e) => setQ(e.target.value)} /></div>
        <Etat chargement={isLoading} erreur={error} vide={data?.length === 0 && "Aucun compte."}>
          <Table>
            <thead><tr><Th>Nom</Th><Th>Rôle</Th><Th>Périmètres</Th><Th>Dernière connexion</Th><Th /></tr></thead>
            <tbody>
              {data?.map((u) => (
                <tr key={u.id} className={u.actif ? "" : "opacity-50"}>
                  <Td>
                    <div className="font-medium">{u.nom_complet}</div>
                    <div className="text-xs text-slate-500">{u.email}</div>
                  </Td>
                  <Td>{u.roles?.map((r) => <Badge key={r} ton="info">{r}</Badge>)}{!u.actif && <Badge ton="danger" className="ml-1">inactif</Badge>}</Td>
                  <Td className="text-slate-600">{u.perimetres?.map((p) => p.nom + (p.lecture_seule ? " (lecture)" : "")).join(", ") || "—"}</Td>
                  <Td>{formatDate(u.derniere_connexion_at, true)}</Td>
                  <Td className="text-right">
                    <Button variante="fantome" taille="icone" onClick={() => setEdition(u)} aria-label="Modifier"><Pencil className="h-4 w-4" /></Button>
                  </Td>
                </tr>
              ))}
            </tbody>
          </Table>
        </Etat>
      </Card>
      <Dialog ouvert={!!edition} onFermer={() => setEdition(null)} titre={edition === "nouveau" ? "Nouveau compte" : "Modifier le compte"}>
        {edition && <FormulaireUtilisateur utilisateur={edition === "nouveau" ? undefined : edition} onTermine={() => setEdition(null)} />}
      </Dialog>
    </div>
  );
}
