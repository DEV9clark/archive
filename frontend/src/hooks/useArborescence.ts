"use client";

import { useQuery } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type { Niveau, NoeudArbre } from "@/types";

/** Enfants directs d'un nœud (ou racines si `niveau` est absent). */
export function useEnfants(niveau?: Niveau, id?: string, actif = true) {
  return useQuery({
    queryKey: ["arborescence", niveau ?? "racine", id ?? ""],
    queryFn: () => api.get<{ data: NoeudArbre[] }>("/arborescence", { niveau, id }).then((r) => r.data),
    enabled: actif,
  });
}
