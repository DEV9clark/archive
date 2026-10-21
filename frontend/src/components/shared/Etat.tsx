import { Loader2 } from "lucide-react";
import { ErreurApi } from "@/lib/api";

/** Rendu uniforme des états chargement / erreur / vide d'une requête. */
export function Etat({ chargement, erreur, vide, children }: {
  chargement?: boolean;
  erreur?: unknown;
  vide?: string | false;
  children?: React.ReactNode;
}) {
  if (chargement) {
    return (
      <div className="flex items-center gap-2 p-6 text-sm text-slate-500">
        <Loader2 className="h-4 w-4 animate-spin" /> Chargement…
      </div>
    );
  }
  if (erreur) {
    const message = erreur instanceof ErreurApi ? erreur.detail : "Une erreur est survenue.";
    return <div className="m-4 rounded-md bg-red-50 p-4 text-sm text-red-700">{message}</div>;
  }
  if (vide) return <div className="p-6 text-center text-sm text-slate-500">{vide}</div>;
  return <>{children}</>;
}
