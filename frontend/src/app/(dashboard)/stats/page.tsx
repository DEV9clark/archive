"use client";

import { useQuery } from "@tanstack/react-query";
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from "recharts";
import { Building2, FileText, Folder, HardDrive, MapPin, Users } from "lucide-react";
import { api } from "@/lib/api";
import { formatTaille } from "@/lib/utils";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Table, Td, Th } from "@/components/ui/table";
import { Etat } from "@/components/shared/Etat";
import { PageHeader } from "@/components/shared/PageHeader";
import { StatCard } from "@/components/shared/StatCard";
import { BadgeStatut } from "@/components/documents/BadgeStatut";
import type { Statistiques, Statut } from "@/types";

const MOIS = ["janv.", "févr.", "mars", "avr.", "mai", "juin", "juil.", "août", "sept.", "oct.", "nov.", "déc."];

export default function PageStatistiques() {
  const { data, isLoading, error } = useQuery({ queryKey: ["statistiques"], queryFn: () => api.get<Statistiques>("/statistiques") });
  const nombre = (n?: number) => (n ?? 0).toLocaleString("fr-FR");

  return (
    <div className="space-y-6">
      <PageHeader titre="Statistiques" sousTitre="Calculées sur votre périmètre." />
      <Etat chargement={isLoading} erreur={error}>
        {data && (
          <>
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
              <StatCard libelle="Territoires" valeur={nombre(data.totaux.territoires)} icone={MapPin} />
              <StatCard libelle="Départements" valeur={nombre(data.totaux.departements)} icone={Building2} />
              <StatCard libelle="Assemblées" valeur={nombre(data.totaux.assemblees)} icone={Users} />
              <StatCard libelle="Dossiers" valeur={nombre(data.totaux.dossiers)} icone={Folder} />
              <StatCard libelle="Documents" valeur={nombre(data.totaux.documents)} icone={FileText} />
              <StatCard libelle="Volume" valeur={formatTaille(data.totaux.taille_octets ?? 0)} icone={HardDrive} />
            </div>

            <div className="grid gap-6 lg:grid-cols-[2fr_1fr]">
              <Card>
                <CardHeader><CardTitle>Dépôts sur 12 mois</CardTitle></CardHeader>
                <CardContent className="h-72">
                  <ResponsiveContainer width="100%" height="100%">
                    <BarChart data={data.depots_par_mois.map((m) => ({ ...m, libelle: MOIS[Number(m.mois.slice(5)) - 1] }))}>
                      <CartesianGrid vertical={false} stroke="#e2e8f0" />
                      <XAxis dataKey="libelle" tickLine={false} axisLine={false} fontSize={12} />
                      <YAxis allowDecimals={false} tickLine={false} axisLine={false} fontSize={12} width={32} />
                      <Tooltip formatter={(v) => [v, "Documents"]} labelFormatter={(_, p) => p?.[0]?.payload.mois} />
                      <Bar dataKey="total" fill="#1f4e79" radius={[4, 4, 0, 0]} />
                    </BarChart>
                  </ResponsiveContainer>
                </CardContent>
              </Card>

              <Card>
                <CardHeader><CardTitle>Par statut</CardTitle></CardHeader>
                <CardContent className="space-y-3">
                  {(["brouillon", "valide", "archive"] as Statut[]).map((s) => {
                    const n = data.par_statut[s] ?? 0;
                    const part = data.totaux.documents ? (n / data.totaux.documents) * 100 : 0;
                    return (
                      <div key={s} className="space-y-1">
                        <div className="flex justify-between text-sm"><BadgeStatut statut={s} /><span className="tabular-nums">{nombre(n)}</span></div>
                        <div className="h-2 rounded-full bg-slate-100"><div className="h-2 rounded-full bg-primaire" style={{ width: `${part}%` }} /></div>
                      </div>
                    );
                  })}
                </CardContent>
              </Card>
            </div>

            <Card>
              <CardHeader><CardTitle>Types de fichiers</CardTitle></CardHeader>
              <Etat vide={!data.par_type.length && "Aucun document."}>
                <Table>
                  <thead><tr><Th>Type MIME</Th><Th className="text-right">Documents</Th><Th className="text-right">Volume</Th></tr></thead>
                  <tbody>
                    {data.par_type.map((t) => (
                      <tr key={t.mime_type}>
                        <Td className="font-mono text-xs">{t.mime_type}</Td>
                        <Td className="text-right tabular-nums">{nombre(t.total)}</Td>
                        <Td className="text-right tabular-nums">{formatTaille(Number(t.taille_octets))}</Td>
                      </tr>
                    ))}
                  </tbody>
                </Table>
              </Etat>
            </Card>
          </>
        )}
      </Etat>
    </div>
  );
}
