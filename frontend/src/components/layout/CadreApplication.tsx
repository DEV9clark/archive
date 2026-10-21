"use client";

import { useEffect, useSyncExternalStore } from "react";
import { useRouter } from "next/navigation";
import { Loader2 } from "lucide-react";
import { useAuth } from "@/stores/auth";
import { api } from "@/lib/api";
import { Sidebar } from "./Sidebar";
import { Entete } from "./Entete";
import type { Profil } from "@/types";

/** Garde d'authentification + cadre (barre latérale, en-tête) des pages connectées. */
export function CadreApplication({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const token = useAuth((s) => s.token);
  const majProfil = useAuth((s) => s.majProfil);
  // Attendre la relecture de localStorage avant de décider d'une redirection
  const hydrate = useSyncExternalStore(
    (notifier) => useAuth.persist.onFinishHydration(notifier),
    () => useAuth.persist.hasHydrated(),
    () => false,
  );

  useEffect(() => {
    if (!hydrate) return;
    if (!token) {
      router.replace("/login");
      return;
    }
    // Rafraîchit rôles / périmètres (ils ont pu changer côté admin)
    api.get<Profil>("/auth/me").then(majProfil).catch(() => {});
  }, [hydrate, token, router, majProfil]);

  if (!hydrate || !token) {
    return (
      <div className="flex min-h-screen items-center justify-center text-slate-500">
        <Loader2 className="h-5 w-5 animate-spin" />
      </div>
    );
  }

  return (
    <div className="flex min-h-screen">
      <Sidebar />
      <div className="flex min-w-0 flex-1 flex-col">
        <Entete />
        <main className="flex-1 p-6">{children}</main>
      </div>
    </div>
  );
}
