import type { LucideIcon } from "lucide-react";
import { Card } from "@/components/ui/card";

export function StatCard({ libelle, valeur, icone: Icone }: { libelle: string; valeur: string | number; icone: LucideIcon }) {
  return (
    <Card className="flex items-center gap-4 p-4">
      <div className="rounded-md bg-primaire-clair p-2 text-primaire">
        <Icone className="h-5 w-5" />
      </div>
      <div>
        <div className="text-2xl font-semibold tabular-nums">{valeur}</div>
        <div className="text-sm text-slate-500">{libelle}</div>
      </div>
    </Card>
  );
}
