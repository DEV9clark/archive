"use client";

import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { X } from "lucide-react";
import { toast } from "sonner";
import { api, ErreurApi } from "@/lib/api";
import { LIBELLES_NIVEAU } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Champ, Input, Select } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import type { Niveau, ResultatRecherche, Role, Utilisateur } from "@/types";

interface PerimetreSaisi {
  niveau: Niveau;
  id: string;
  nom: string;
  lecture_seule: boolean;
}

/** Création / modification d'un compte : identité, rôle, périmètres. */
export function FormulaireUtilisateur({ utilisateur, onTermine }: { utilisateur?: Utilisateur; onTermine: () => void }) {
  const client = useQueryClient();
  const roles = useQuery({ queryKey: ["roles"], queryFn: () => api.get<{ data: Role[] }>("/admin/roles").then((r) => r.data) });
  const [v, setV] = useState({
    email: utilisateur?.email ?? "",
    nom_complet: utilisateur?.nom_complet ?? "",
    mot_de_passe: "",
    role: utilisateur?.roles?.[0] ?? "lecteur",
    actif: utilisateur?.actif ?? true,
  });
  const [perimetres, setPerimetres] = useState<PerimetreSaisi[]>(
    (utilisateur?.perimetres ?? [])
      .filter((p) => p.noeud)
      .map((p) => ({ ...p.noeud!, nom: p.nom ?? p.chemin, lecture_seule: p.lecture_seule })),
  );
  const [recherche, setRecherche] = useState("");
  const [erreurs, setErreurs] = useState<Record<string, string[]>>({});

  const candidats = useQuery({
    queryKey: ["recherche-noeuds", recherche],
    queryFn: () => api.get<ResultatRecherche>("/recherche", { q: recherche }).then((r) => r.noeuds),
    enabled: recherche.trim().length >= 2,
  });

  const enregistrement = useMutation({
    mutationFn: () => {
      const corps = {
        ...v,
        mot_de_passe: v.mot_de_passe || undefined,
        perimetres: perimetres.map(({ niveau, id, lecture_seule }) => ({ niveau, id, lecture_seule })),
      };
      return utilisateur ? api.put(`/admin/utilisateurs/${utilisateur.id}`, corps) : api.post("/admin/utilisateurs", corps);
    },
    onSuccess: () => {
      client.invalidateQueries({ queryKey: ["utilisateurs"] });
      toast.success(utilisateur ? "Compte mis à jour." : "Compte créé.");
      onTermine();
    },
    onError: (e) => {
      if (e instanceof ErreurApi) {
        setErreurs(e.erreurs);
        toast.error(e.detail);
      }
    },
  });

  const erreurPerimetres = Object.entries(erreurs).find(([k]) => k.startsWith("perimetres"))?.[1][0];

  return (
    <form className="space-y-4" onSubmit={(e) => { e.preventDefault(); enregistrement.mutate(); }}>
      <div className="grid grid-cols-2 gap-4">
        <Champ label="Nom complet" erreur={erreurs.nom_complet?.[0]}>
          <Input required value={v.nom_complet} onChange={(e) => setV({ ...v, nom_complet: e.target.value })} />
        </Champ>
        <Champ label="E-mail" erreur={erreurs.email?.[0]}>
          <Input type="email" required value={v.email} onChange={(e) => setV({ ...v, email: e.target.value })} />
        </Champ>
        <Champ label={utilisateur ? "Nouveau mot de passe (optionnel)" : "Mot de passe"} erreur={erreurs.mot_de_passe?.[0]}>
          <Input type="password" autoComplete="new-password" required={!utilisateur} value={v.mot_de_passe} onChange={(e) => setV({ ...v, mot_de_passe: e.target.value })} />
        </Champ>
        <Champ label="Rôle" erreur={erreurs.role?.[0]}>
          <Select value={v.role} onChange={(e) => setV({ ...v, role: e.target.value })}>
            {roles.data?.filter((r) => r.attribuable).map((r) => <option key={r.nom} value={r.nom}>{r.nom}</option>)}
          </Select>
        </Champ>
      </div>

      <div className="space-y-2">
        <span className="text-sm font-medium text-slate-700">Périmètres</span>
        {perimetres.length === 0 && v.role !== "admin" && (
          <p className="text-xs text-amber-700">Sans périmètre, ce compte ne verra aucune archive.</p>
        )}
        <ul className="space-y-1">
          {perimetres.map((p, i) => (
            <li key={p.id} className="flex items-center gap-2 rounded-md bg-slate-50 px-3 py-1.5 text-sm">
              <Badge>{LIBELLES_NIVEAU[p.niveau]}</Badge>
              <span className="flex-1">{p.nom}</span>
              <label className="flex items-center gap-1 text-xs text-slate-600">
                <input
                  type="checkbox"
                  checked={p.lecture_seule}
                  onChange={(e) => setPerimetres(perimetres.map((x, j) => (j === i ? { ...x, lecture_seule: e.target.checked } : x)))}
                />
                lecture seule
              </label>
              <button type="button" onClick={() => setPerimetres(perimetres.filter((_, j) => j !== i))} aria-label="Retirer">
                <X className="h-4 w-4 text-slate-400" />
              </button>
            </li>
          ))}
        </ul>
        <Input placeholder="Ajouter : chercher un territoire, une assemblée…" value={recherche} onChange={(e) => setRecherche(e.target.value)} />
        {candidats.data && recherche.length >= 2 && (
          <ul className="max-h-40 overflow-y-auto rounded-md border border-slate-200 text-sm">
            {candidats.data.filter((c) => !perimetres.some((p) => p.id === c.id)).map((c) => (
              <li key={c.id}>
                <button
                  type="button"
                  className="w-full px-3 py-1.5 text-left hover:bg-slate-50"
                  onClick={() => {
                    setPerimetres([...perimetres, { niveau: c.niveau, id: c.id, nom: c.nom, lecture_seule: false }]);
                    setRecherche("");
                  }}
                >
                  {c.nom} <span className="text-xs text-slate-500">— {c.fil_ariane.slice(0, -1).map((e) => e.nom).join(" › ")}</span>
                </button>
              </li>
            ))}
          </ul>
        )}
        {erreurPerimetres && <p className="text-xs text-red-600">{erreurPerimetres}</p>}
      </div>

      {utilisateur && (
        <label className="flex items-center gap-2 text-sm">
          <input type="checkbox" checked={v.actif} onChange={(e) => setV({ ...v, actif: e.target.checked })} /> Compte actif
        </label>
      )}

      <div className="flex justify-end">
        <Button type="submit" disabled={enregistrement.isPending}>{utilisateur ? "Enregistrer" : "Créer le compte"}</Button>
      </div>
    </form>
  );
}
