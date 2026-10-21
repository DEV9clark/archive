"use client";

import { useState } from "react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { api, ErreurApi } from "@/lib/api";
import { LIBELLES_NIVEAU, RESSOURCE } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Champ, Input, Textarea } from "@/components/ui/input";
import type { Niveau, Noeud, TypeTerritoire } from "@/types";

export interface CibleCreation {
  niveau: Niveau;
  type?: TypeTerritoire;
  /** Clé étrangère et valeur du parent, ex. { territoire_id: "…" } */
  parent: Record<string, string>;
}

/** Création (cible fournie) ou modification (noeud fourni) d'un nœud de la hiérarchie. */
export function FormulaireNoeud({ cible, noeud, onTermine }: { cible?: CibleCreation; noeud?: Noeud; onTermine: (n: Noeud) => void }) {
  const client = useQueryClient();
  const niveau = noeud?.niveau ?? cible!.niveau;
  const [valeurs, setValeurs] = useState({
    nom: noeud?.nom ?? "",
    code: noeud?.code ?? "",
    reference: noeud?.reference ?? "",
    description: noeud?.description ?? "",
  });
  const [erreurs, setErreurs] = useState<Record<string, string[]>>({});

  const mutation = useMutation({
    mutationFn: () => {
      const corps: Record<string, unknown> = { nom: valeurs.nom, description: valeurs.description || null };
      if (niveau === "dossier") corps.reference = valeurs.reference || null;
      else corps.code = valeurs.code || null;

      return noeud
        ? api.put<Noeud>(`/${RESSOURCE[niveau]}/${noeud.id}`, corps)
        : api.post<Noeud>(`/${RESSOURCE[niveau]}`, { ...corps, ...cible!.parent, ...(cible!.type ? { type: cible!.type } : {}) });
    },
    onSuccess: (n) => {
      client.invalidateQueries({ queryKey: ["arborescence"] });
      client.invalidateQueries({ queryKey: ["noeud"] });
      toast.success(noeud ? "Modifications enregistrées." : `${LIBELLES_NIVEAU[niveau]} créé.`);
      onTermine(n);
    },
    onError: (e) => {
      if (e instanceof ErreurApi) {
        setErreurs(e.erreurs);
        if (!Object.keys(e.erreurs).length) toast.error(e.message);
      }
    },
  });

  const maj = (champ: keyof typeof valeurs) => (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) =>
    setValeurs((v) => ({ ...v, [champ]: e.target.value }));

  return (
    <form
      className="space-y-4"
      onSubmit={(e) => {
        e.preventDefault();
        mutation.mutate();
      }}
    >
      <Champ label="Nom" erreur={erreurs.nom?.[0] ?? erreurs.type?.[0]}>
        <Input required autoFocus maxLength={255} value={valeurs.nom} onChange={maj("nom")} />
      </Champ>
      {niveau === "dossier" ? (
        <Champ label="Référence" erreur={erreurs.reference?.[0]}>
          <Input maxLength={64} value={valeurs.reference} onChange={maj("reference")} />
        </Champ>
      ) : (
        <Champ label="Code" erreur={erreurs.code?.[0]}>
          <Input maxLength={32} value={valeurs.code} onChange={maj("code")} />
        </Champ>
      )}
      <Champ label="Description" erreur={erreurs.description?.[0]}>
        <Textarea maxLength={niveau === "dossier" ? 1000 : 500} value={valeurs.description} onChange={maj("description")} />
      </Champ>
      <div className="flex justify-end">
        <Button type="submit" disabled={mutation.isPending}>{noeud ? "Enregistrer" : "Créer"}</Button>
      </div>
    </form>
  );
}
