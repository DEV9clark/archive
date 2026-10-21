import { Building2, Folder, Globe2, Landmark, MapPin, Users } from "lucide-react";
import type { Niveau, TypeTerritoire } from "@/types";

export function IconeNiveau({ niveau, type, className = "h-4 w-4" }: { niveau: Niveau; type?: TypeTerritoire | null; className?: string }) {
  if (niveau === "territoire") {
    if (type === "continent") return <Globe2 className={className} />;
    if (type === "nation") return <Landmark className={className} />;
    return <MapPin className={className} />;
  }
  if (niveau === "departement") return <Building2 className={className} />;
  if (niveau === "assemblee") return <Users className={className} />;
  return <Folder className={className} />;
}
