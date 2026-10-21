"use client";

import { use, useState } from "react";
import { useRouter } from "next/navigation";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Download, Eye, Pencil, ShieldCheck, ShieldAlert, Trash2 } from "lucide-react";
import { toast } from "sonner";
import { api, ErreurApi } from "@/lib/api";
import { formatDate, formatTaille } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Dialog } from "@/components/ui/dialog";
import { Champ, Input, Select, Textarea } from "@/components/ui/input";
import { Etat } from "@/components/shared/Etat";
import { FilAriane } from "@/components/shared/FilAriane";
import { PageHeader } from "@/components/shared/PageHeader";
import { BadgeConfidentiel, BadgeStatut, STATUTS } from "@/components/documents/BadgeStatut";
import type { Audit, Document, DocumentDetail } from "@/types";

const APERCU = /^(application\/pdf|image\/|text\/plain)/;

export default function PageDocument({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const router = useRouter();
  const client = useQueryClient();
  const [edition, setEdition] = useState(false);

  const detail = useQuery({ queryKey: ["document", id], queryFn: () => api.get<DocumentDetail>(`/documents/${id}`) });
  const historique = useQuery({
    queryKey: ["document", id, "historique"],
    queryFn: () => api.get<{ data: Audit[] }>(`/documents/${id}/historique`).then((r) => r.data),
  });
  const integrite = useMutation({
    mutationFn: () => api.get<{ present: boolean; integre: boolean; checksum_calcule: string | null }>(`/documents/${id}/integrite`),
  });
  const suppression = useMutation({
    mutationFn: () => api.delete(`/documents/${id}`),
    onSuccess: () => {
      client.invalidateQueries({ queryKey: ["documents"] });
      toast.success("Document placé dans la corbeille.");
      router.push(`/dossiers/${detail.data!.data.dossier_id}`);
    },
    onError: (e) => toast.error(e instanceof ErreurApi ? e.message : "Suppression impossible."),
  });

  const doc = detail.data?.data;
  const droits = detail.data?.droits;
  const fichier = (enLigne: boolean) =>
    api.fichier(`/documents/${id}/telecharger`, doc!.nom_original, enLigne).catch((e) =>
      toast.error(e instanceof ErreurApi ? e.message : "Téléchargement impossible."),
    );

  return (
    <Etat chargement={detail.isLoading} erreur={detail.error}>
      {doc && droits && (
        <div className="space-y-6">
          <PageHeader
            titre={doc.titre}
            sousTitre={<FilAriane etapes={detail.data!.fil_ariane} />}
            actions={
              <>
                {droits.telecharger && APERCU.test(doc.mime_type) && (
                  <Button variante="secondaire" taille="sm" onClick={() => fichier(true)}><Eye className="h-4 w-4" /> Aperçu</Button>
                )}
                {droits.telecharger && (
                  <Button taille="sm" onClick={() => fichier(false)}><Download className="h-4 w-4" /> Télécharger</Button>
                )}
                {droits.modifier && (
                  <Button variante="secondaire" taille="sm" onClick={() => setEdition(true)}><Pencil className="h-4 w-4" /> Modifier</Button>
                )}
                {droits.supprimer && (
                  <Button variante="danger" taille="sm" onClick={() => confirm("Mettre ce document à la corbeille ?") && suppression.mutate()}>
                    <Trash2 className="h-4 w-4" />
                  </Button>
                )}
              </>
            }
          />

          <div className="grid gap-6 lg:grid-cols-[2fr_1fr]">
            <Card>
              <CardHeader><CardTitle>Fiche</CardTitle></CardHeader>
              <CardContent>
                <dl className="grid grid-cols-[10rem_1fr] gap-y-3 text-sm">
                  <dt className="text-slate-500">Statut</dt>
                  <dd className="flex gap-1"><BadgeStatut statut={doc.statut} />{doc.confidentiel && <BadgeConfidentiel />}</dd>
                  <dt className="text-slate-500">Description</dt>
                  <dd className="whitespace-pre-line">{doc.description || "—"}</dd>
                  <dt className="text-slate-500">Fichier d&apos;origine</dt>
                  <dd>{doc.nom_original}</dd>
                  <dt className="text-slate-500">Type</dt>
                  <dd>{doc.mime_type}</dd>
                  <dt className="text-slate-500">Taille</dt>
                  <dd>{formatTaille(doc.taille_octets)}</dd>
                  <dt className="text-slate-500">Version</dt>
                  <dd>{doc.version}</dd>
                  <dt className="text-slate-500">Déposé</dt>
                  <dd>{formatDate(doc.created_at, true)} par {doc.auteur?.nom_complet ?? "—"}</dd>
                  <dt className="text-slate-500">Empreinte SHA-256</dt>
                  <dd className="font-mono text-xs break-all">{doc.checksum_sha256}</dd>
                </dl>
              </CardContent>
            </Card>

            <div className="space-y-6">
              <Card>
                <CardHeader><CardTitle>Intégrité</CardTitle></CardHeader>
                <CardContent className="space-y-3 text-sm">
                  <p className="text-slate-500">Recalcule l&apos;empreinte du fichier stocké et la compare à celle enregistrée au dépôt.</p>
                  <Button variante="secondaire" taille="sm" disabled={integrite.isPending} onClick={() => integrite.mutate()}>
                    <ShieldCheck className="h-4 w-4" /> Vérifier
                  </Button>
                  {integrite.data && (
                    integrite.data.integre ? (
                      <p className="flex items-center gap-2 text-emerald-700"><ShieldCheck className="h-4 w-4" /> Fichier intègre.</p>
                    ) : (
                      <p className="flex items-center gap-2 text-red-700">
                        <ShieldAlert className="h-4 w-4" />
                        {integrite.data.present ? "Le fichier a été altéré !" : "Fichier introuvable sur le stockage !"}
                      </p>
                    )
                  )}
                </CardContent>
              </Card>

              <Card>
                <CardHeader><CardTitle>Historique</CardTitle></CardHeader>
                <Etat chargement={historique.isLoading} erreur={historique.error} vide={historique.data?.length === 0 && "Aucune modification."}>
                  <ol className="space-y-3 px-5 py-4 text-sm">
                    {historique.data?.map((a) => (
                      <li key={a.id} className="border-l-2 border-slate-200 pl-3">
                        <div className="font-medium">{a.evenement === "created" ? "Création" : a.evenement === "updated" ? "Modification" : a.evenement}</div>
                        <div className="text-xs text-slate-500">{formatDate(a.created_at, true)} · {a.utilisateur?.nom_complet ?? "système"}</div>
                        {a.evenement === "updated" && a.nouvelles && (
                          <div className="mt-1 text-xs text-slate-600">
                            {Object.entries(a.nouvelles).map(([k, v]) => (
                              <div key={k}>{k} : <s className="text-slate-400">{String(a.anciennes?.[k] ?? "")}</s> → {String(v)}</div>
                            ))}
                          </div>
                        )}
                      </li>
                    ))}
                  </ol>
                </Etat>
              </Card>
            </div>
          </div>

          <Dialog ouvert={edition} onFermer={() => setEdition(false)} titre="Modifier les métadonnées">
            <EditionDocument document={doc} onTermine={() => setEdition(false)} />
          </Dialog>
        </div>
      )}
    </Etat>
  );
}

function EditionDocument({ document, onTermine }: { document: Document; onTermine: () => void }) {
  const client = useQueryClient();
  const [v, setV] = useState({ titre: document.titre, description: document.description ?? "", statut: document.statut });
  const maj = useMutation({
    mutationFn: () => api.put(`/documents/${document.id}`, v),
    onSuccess: () => {
      client.invalidateQueries({ queryKey: ["document", document.id] });
      toast.success("Métadonnées enregistrées.");
      onTermine();
    },
    onError: (e) => toast.error(e instanceof ErreurApi ? e.detail : "Échec."),
  });

  return (
    <form className="space-y-4" onSubmit={(e) => { e.preventDefault(); maj.mutate(); }}>
      <Champ label="Titre"><Input required maxLength={255} value={v.titre} onChange={(e) => setV({ ...v, titre: e.target.value })} /></Champ>
      <Champ label="Description"><Textarea value={v.description} onChange={(e) => setV({ ...v, description: e.target.value })} /></Champ>
      <Champ label="Statut">
        <Select value={v.statut} onChange={(e) => setV({ ...v, statut: e.target.value as Document["statut"] })}>
          {STATUTS.map((s) => <option key={s.valeur} value={s.valeur}>{s.libelle}</option>)}
        </Select>
      </Champ>
      <div className="flex justify-end"><Button type="submit" disabled={maj.isPending}>Enregistrer</Button></div>
    </form>
  );
}
