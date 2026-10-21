"use client";

import { useQuery } from "@tanstack/react-query";
import { Check } from "lucide-react";
import { api } from "@/lib/api";
import { Card } from "@/components/ui/card";
import { Table, Td, Th } from "@/components/ui/table";
import { Etat } from "@/components/shared/Etat";
import { PageHeader } from "@/components/shared/PageHeader";
import type { Role } from "@/types";

export default function PageRoles() {
  const { data, isLoading, error } = useQuery({
    queryKey: ["roles"],
    queryFn: () => api.get<{ data: Role[]; permissions: { name: string; description: string | null }[] }>("/admin/roles"),
  });

  return (
    <div className="space-y-6">
      <PageHeader titre="Rôles et permissions" sousTitre="Le rôle définit ce qu'un compte peut faire ; son périmètre définit où." />
      <Card>
        <Etat chargement={isLoading} erreur={error}>
          {data && (
            <Table>
              <thead>
                <tr>
                  <Th>Permission</Th>
                  {data.data.map((r) => (
                    <Th key={r.nom} className="text-center">
                      {r.nom}
                      <div className="text-xs font-normal">{r.utilisateurs} compte(s)</div>
                    </Th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {data.permissions.map((p) => (
                  <tr key={p.name}>
                    <Td>
                      <div className="font-mono text-xs">{p.name}</div>
                      <div className="text-xs text-slate-500">{p.description}</div>
                    </Td>
                    {data.data.map((r) => (
                      <Td key={r.nom} className="text-center">
                        {r.permissions.includes(p.name) && <Check className="mx-auto h-4 w-4 text-emerald-600" aria-label="oui" />}
                      </Td>
                    ))}
                  </tr>
                ))}
              </tbody>
            </Table>
          )}
        </Etat>
      </Card>
    </div>
  );
}
