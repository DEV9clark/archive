"use client";

import { useRouter } from "next/navigation";
import { LogOut } from "lucide-react";
import { useQueryClient } from "@tanstack/react-query";
import { useAuth } from "@/stores/auth";
import { api } from "@/lib/api";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { BarreRecherche } from "@/components/recherche/BarreRecherche";

export function Entete() {
  const router = useRouter();
  const client = useQueryClient();
  const utilisateur = useAuth((s) => s.utilisateur);
  const fermerSession = useAuth((s) => s.fermerSession);

  async function deconnexion() {
    await api.post("/auth/logout").catch(() => {});
    fermerSession();
    client.clear();
    router.replace("/login");
  }

  const perimetre = utilisateur?.admin_global
    ? "Mondial"
    : utilisateur?.perimetres_detail.map((p) => p.fil_ariane?.at(-1)?.nom ?? p.chemin).join(", ") || "Aucun périmètre";

  return (
    <header className="flex h-14 items-center gap-4 border-b border-slate-200 bg-white px-6">
      <BarreRecherche />
      <div className="ml-auto flex items-center gap-3 text-sm">
        <div className="text-right leading-tight">
          <div className="font-medium">{utilisateur?.nom_complet}</div>
          <div className="text-xs text-slate-500">{perimetre}</div>
        </div>
        {utilisateur?.roles?.[0] && <Badge ton="info">{utilisateur.roles[0]}</Badge>}
        <Button variante="fantome" taille="icone" onClick={deconnexion} aria-label="Se déconnecter" title="Se déconnecter">
          <LogOut className="h-4 w-4" />
        </Button>
      </div>
    </header>
  );
}
