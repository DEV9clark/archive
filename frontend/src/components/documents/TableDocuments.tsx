import Link from "next/link";
import { FileText } from "lucide-react";
import { Table, Td, Th } from "@/components/ui/table";
import { formatDate, formatTaille } from "@/lib/utils";
import { BadgeConfidentiel, BadgeStatut } from "./BadgeStatut";
import type { Document } from "@/types";

export function TableDocuments({ documents, avecDossier = false }: { documents: Document[]; avecDossier?: boolean }) {
  return (
    <Table>
      <thead>
        <tr>
          <Th>Titre</Th>
          {avecDossier && <Th>Dossier</Th>}
          <Th>Statut</Th>
          <Th className="text-right">Taille</Th>
          <Th>Déposé le</Th>
          <Th>Par</Th>
        </tr>
      </thead>
      <tbody>
        {documents.map((d) => (
          <tr key={d.id} className="hover:bg-slate-50">
            <Td>
              <Link href={`/documents/${d.id}`} className="flex items-center gap-2 font-medium text-primaire hover:underline">
                <FileText className="h-4 w-4 shrink-0" />
                <span className="truncate">{d.titre}</span>
              </Link>
              <div className="pl-6 text-xs text-slate-500">{d.nom_original}</div>
            </Td>
            {avecDossier && (
              <Td>{d.dossier && <Link href={`/dossiers/${d.dossier.id}`} className="hover:underline">{d.dossier.nom}</Link>}</Td>
            )}
            <Td>
              <div className="flex gap-1">
                <BadgeStatut statut={d.statut} />
                {d.confidentiel && <BadgeConfidentiel />}
              </div>
            </Td>
            <Td className="text-right tabular-nums">{formatTaille(d.taille_octets)}</Td>
            <Td>{formatDate(d.created_at)}</Td>
            <Td className="text-slate-500">{d.auteur?.nom_complet ?? "—"}</Td>
          </tr>
        ))}
      </tbody>
    </Table>
  );
}
