"use client";

import { create } from "zustand";
import { persist } from "zustand/middleware";
import type { Profil } from "@/types";

interface EtatAuth {
  token: string | null;
  expiresAt: string | null;
  utilisateur: Profil | null;
  ouvrirSession: (token: string, expiresAt: string, utilisateur: Profil) => void;
  majProfil: (utilisateur: Profil) => void;
  fermerSession: () => void;
}

/** Session persistée dans localStorage (token Bearer Sanctum). */
export const useAuth = create<EtatAuth>()(
  persist(
    (set) => ({
      token: null,
      expiresAt: null,
      utilisateur: null,
      ouvrirSession: (token, expiresAt, utilisateur) => set({ token, expiresAt, utilisateur }),
      majProfil: (utilisateur) => set({ utilisateur }),
      fermerSession: () => set({ token: null, expiresAt: null, utilisateur: null }),
    }),
    { name: "archivage-session" },
  ),
);

/** Vrai si l'utilisateur courant détient la permission (l'admin global les a toutes). */
export function usePeut(permission: string): boolean {
  return useAuth((s) => !!s.utilisateur && (s.utilisateur.admin_global || s.utilisateur.permissions.includes(permission)));
}
