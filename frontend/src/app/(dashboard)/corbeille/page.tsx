"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { RotateCcw } from "lucide-react";
import { toast } from "sonner";
import { api, ErreurApi } from "@/lib/api";
import { LIBELLES_NIVEAU, RESSOURCE, formatDate } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Table, Td, Th } from "@/components/ui/table";
import { Etat } from "@/components/shared/Etat";
import { PageHeader } from "@/components/shared/PageHeader";
import type { ElementCorbeille } from "@/types";

export default function PageCorbeille() {
  const client = useQueryClient();
  const { data, isLoading, error } = useQuery({
    queryKey: ["corbeille"],
    queryFn: () => api.get<{ data: ElementCorbeille[] }>("/corbeille").then((r) => r.data),
  });

  const restauration = useMutation({
    mutationFn: (e: ElementCorbeille) =>
      api.post(`/${e.niveau === "document" ? "documents" : RESSOURCE[e.niveau]}/${e.id}/restaurer`),
    onSuccess: () => {
      client.invalidateQueries();
      toast.success("Élément restauré.");
    },
    onError: (e) => toast.error(e instanceof ErreurApi ? e.message : "Restauration impossible."),
  });

  return (
    <div className="space-y-6">
      <PageHeader
        titre="Corbeille"
        sousTitre="Les éléments supprimés restent conservés ; les fichiers ne sont jamais effacés du stockage."
      />
      <Card>
        <Etat chargement={isLoading} erreur={error} vide={data?.length === 0 && "La corbeille est vide."}>
          <Table>
            <thead><tr><Th>Élément</Th><Th>Niveau</Th><Th>Supprimé le</Th><Th /></tr></thead>
            <tbody>
              {data?.map((e) => (
                <tr key={`${e.niveau}-${e.id}`}>
                  <Td className="font-medium">{e.nom}</Td>
                  <Td><Badge>{LIBELLES_NIVEAU[e.niveau]}</Badge></Td>
                  <Td>{formatDate(e.deleted_at, true)}</Td>
                  <Td className="text-right">
                    <Button variante="secondaire" taille="sm" disabled={restauration.isPending} onClick={() => restauration.mutate(e)}>
                      <RotateCcw className="h-4 w-4" /> Restaurer
                    </Button>
                  </Td>
                </tr>
              ))}
            </tbody>
          </Table>
        </Etat>
      </Card>
    </div>
  );
}
