import { clsx, type ClassValue } from "clsx";
import { twMerge } from "tailwind-merge";
import type { Niveau } from "@/types";

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}

export function formatTaille(octets: number): string {
  if (octets < 1024) return `${octets} o`;
  const unites = ["Ko", "Mo", "Go", "To"];
  let v = octets / 1024;
  let i = 0;
  while (v >= 1024 && i < unites.length - 1) {
    v /= 1024;
    i++;
  }
  return `${v.toLocaleString("fr-FR", { maximumFractionDigits: 1 })} ${unites[i]}`;
}

export function formatDate(iso: string | null | undefined, avecHeure = false): string {
  if (!iso) return "—";
  return new Date(iso).toLocaleString("fr-FR", {
    dateStyle: "medium",
    ...(avecHeure ? { timeStyle: "short" } : {}),
  });
}

export const LIBELLES_NIVEAU: Record<Niveau | "document", string> = {
  territoire: "Territoire",
  departement: "Département",
  assemblee: "Assemblée",
  dossier: "Dossier",
  document: "Document",
};

/** Segment d'URL API pour un niveau ("assemblee" → "assemblees"). */
export const RESSOURCE: Record<Niveau, string> = {
  territoire: "territoires",
  departement: "departements",
  assemblee: "assemblees",
  dossier: "dossiers",
};

/** Lien de l'interface vers un nœud : dossiers ont leur page, le reste passe par l'explorateur. */
export function lienNoeud(niveau: Niveau, id: string): string {
  return niveau === "dossier" ? `/dossiers/${id}` : `/explorateur?niveau=${niveau}&id=${id}`;
}
