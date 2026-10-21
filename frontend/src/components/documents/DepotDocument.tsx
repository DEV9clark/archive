"use client";

import { useRef, useState } from "react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { UploadCloud } from "lucide-react";
import { toast } from "sonner";
import { api, ErreurApi } from "@/lib/api";
import { usePeut } from "@/stores/auth";
import { cn, formatTaille } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Champ, Input, Select, Textarea } from "@/components/ui/input";
import { STATUTS } from "./BadgeStatut";

/** Dépôt d'un ou plusieurs fichiers dans un dossier (glisser-déposer ou sélection). */
export function DepotDocument({ dossierId, onTermine }: { dossierId: string; onTermine: () => void }) {
  const client = useQueryClient();
  const peutConfidentiel = usePeut("documents.confidentiel");
  const entree = useRef<HTMLInputElement>(null);
  const [fichiers, setFichiers] = useState<File[]>([]);
  const [survol, setSurvol] = useState(false);
  const [meta, setMeta] = useState({ titre: "", description: "", statut: "brouillon", confidentiel: false });

  const depot = useMutation({
    mutationFn: async () => {
      // Dépôts séquentiels : un échec n'annule pas les fichiers déjà archivés
      const echecs: string[] = [];
      for (const fichier of fichiers) {
        const form = new FormData();
        form.append("fichier", fichier);
        form.append("dossier_id", dossierId);
        if (fichiers.length === 1 && meta.titre) form.append("titre", meta.titre);
        if (meta.description) form.append("description", meta.description);
        form.append("statut", meta.statut);
        form.append("confidentiel", meta.confidentiel ? "1" : "0");
        try {
          await api.post("/documents", form);
        } catch (e) {
          echecs.push(`${fichier.name} : ${e instanceof ErreurApi ? e.detail : "échec"}`);
        }
      }
      return echecs;
    },
    onSuccess: (echecs) => {
      client.invalidateQueries({ queryKey: ["documents"] });
      client.invalidateQueries({ queryKey: ["arborescence"] });
      const ok = fichiers.length - echecs.length;
      if (ok) toast.success(`${ok} document(s) archivé(s).`);
      echecs.forEach((m) => toast.error(m));
      if (!echecs.length) onTermine();
    },
  });

  return (
    <form
      className="space-y-4"
      onSubmit={(e) => {
        e.preventDefault();
        depot.mutate();
      }}
    >
      <div
        onClick={() => entree.current?.click()}
        onDragOver={(e) => {
          e.preventDefault();
          setSurvol(true);
        }}
        onDragLeave={() => setSurvol(false)}
        onDrop={(e) => {
          e.preventDefault();
          setSurvol(false);
          setFichiers(Array.from(e.dataTransfer.files));
        }}
        className={cn(
          "flex cursor-pointer flex-col items-center gap-2 rounded-lg border-2 border-dashed border-slate-300 p-6 text-center text-sm text-slate-500",
          survol && "border-primaire bg-primaire-clair",
        )}
      >
        <UploadCloud className="h-8 w-8 text-slate-400" />
        {fichiers.length ? (
          <ul className="text-slate-700">
            {fichiers.map((f) => <li key={f.name}>{f.name} — {formatTaille(f.size)}</li>)}
          </ul>
        ) : (
          <span>Glissez des fichiers ici ou cliquez pour choisir<br />PDF, images, Word, Excel, texte</span>
        )}
        <input ref={entree} type="file" multiple hidden onChange={(e) => setFichiers(Array.from(e.target.files ?? []))} />
      </div>

      {fichiers.length === 1 && (
        <Champ label="Titre (par défaut : nom du fichier)">
          <Input value={meta.titre} maxLength={255} onChange={(e) => setMeta({ ...meta, titre: e.target.value })} />
        </Champ>
      )}
      <Champ label="Description">
        <Textarea value={meta.description} onChange={(e) => setMeta({ ...meta, description: e.target.value })} />
      </Champ>
      <div className="flex items-end gap-4">
        <Champ label="Statut">
          <Select value={meta.statut} onChange={(e) => setMeta({ ...meta, statut: e.target.value })}>
            {STATUTS.map((s) => <option key={s.valeur} value={s.valeur}>{s.libelle}</option>)}
          </Select>
        </Champ>
        {peutConfidentiel && (
          <label className="flex h-10 items-center gap-2 text-sm">
            <input type="checkbox" checked={meta.confidentiel} onChange={(e) => setMeta({ ...meta, confidentiel: e.target.checked })} />
            Confidentiel
          </label>
        )}
      </div>
      <div className="flex justify-end">
        <Button type="submit" disabled={!fichiers.length || depot.isPending}>
          {depot.isPending ? "Envoi…" : "Archiver"}
        </Button>
      </div>
    </form>
  );
}
