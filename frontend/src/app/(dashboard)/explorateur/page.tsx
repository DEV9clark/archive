"use client";

import { Suspense } from "react";
import { useSearchParams } from "next/navigation";
import { Card } from "@/components/ui/card";
import { Arbre } from "@/components/explorateur/Arbre";
import { PanneauNoeud } from "@/components/explorateur/PanneauNoeud";
import type { Niveau } from "@/types";

function Explorateur() {
  const params = useSearchParams();
  const niveau = (params.get("niveau") as Niveau | null) ?? undefined;
  const id = params.get("id") ?? undefined;

  return (
    <div className="grid gap-6 lg:grid-cols-[280px_1fr]">
      <Card className="h-fit max-h-[calc(100vh-8rem)] overflow-y-auto p-2 lg:sticky lg:top-6">
        <Arbre selection={id} />
      </Card>
      <PanneauNoeud key={`${niveau}-${id}`} niveau={niveau} id={id} />
    </div>
  );
}

export default function PageExplorateur() {
  return (
    <Suspense>
      <Explorateur />
    </Suspense>
  );
}
