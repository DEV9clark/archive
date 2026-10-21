"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { Archive, Loader2 } from "lucide-react";
import { api, ErreurApi } from "@/lib/api";
import { useAuth } from "@/stores/auth";
import { Button } from "@/components/ui/button";
import { Champ, Input } from "@/components/ui/input";
import type { Profil } from "@/types";

export function FormulaireConnexion() {
  const router = useRouter();
  const ouvrirSession = useAuth((s) => s.ouvrirSession);
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [erreur, setErreur] = useState<string | null>(null);
  const [envoi, setEnvoi] = useState(false);

  async function soumettre(e: React.FormEvent) {
    e.preventDefault();
    setEnvoi(true);
    setErreur(null);
    try {
      const r = await api.post<{ token: string; expires_at: string; utilisateur: Profil }>("/auth/login", {
        email,
        password,
        appareil: navigator.userAgent.slice(0, 100),
      });
      ouvrirSession(r.token, r.expires_at, r.utilisateur);
      router.replace("/explorateur");
    } catch (err) {
      setErreur(err instanceof ErreurApi ? err.detail : "Serveur injoignable.");
    } finally {
      setEnvoi(false);
    }
  }

  return (
    <form onSubmit={soumettre} className="w-full max-w-sm space-y-5 rounded-xl bg-white p-8 shadow-2xl">
      <div className="flex flex-col items-center gap-2 text-center">
        <div className="rounded-full bg-primaire-clair p-3 text-primaire">
          <Archive className="h-6 w-6" />
        </div>
        <h1 className="text-xl font-semibold">Archivage</h1>
        <p className="text-sm text-slate-500">Connectez-vous pour accéder aux archives</p>
      </div>

      <Champ label="Adresse e-mail">
        <Input type="email" autoComplete="username" required value={email} onChange={(e) => setEmail(e.target.value)} />
      </Champ>
      <Champ label="Mot de passe">
        <Input type="password" autoComplete="current-password" required value={password} onChange={(e) => setPassword(e.target.value)} />
      </Champ>

      {erreur && <p role="alert" className="rounded-md bg-red-50 p-3 text-sm text-red-700">{erreur}</p>}

      <Button type="submit" className="w-full" disabled={envoi}>
        {envoi && <Loader2 className="h-4 w-4 animate-spin" />} Se connecter
      </Button>
    </form>
  );
}
