"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { Archive, BarChart3, FolderTree, ScrollText, Search, ShieldCheck, Trash2, Users } from "lucide-react";
import { usePeut } from "@/stores/auth";
import { cn } from "@/lib/utils";

export function Sidebar() {
  const chemin = usePathname();
  const liens = [
    { href: "/explorateur", libelle: "Explorateur", icone: FolderTree, visible: usePeut("hierarchie.voir") },
    { href: "/recherche", libelle: "Recherche", icone: Search, visible: usePeut("documents.voir") },
    { href: "/stats", libelle: "Statistiques", icone: BarChart3, visible: usePeut("stats.voir") },
    { href: "/corbeille", libelle: "Corbeille", icone: Trash2, visible: usePeut("corbeille.restaurer") },
  ];
  const admin = [
    { href: "/admin/utilisateurs", libelle: "Utilisateurs", icone: Users, visible: usePeut("utilisateurs.gerer") },
    { href: "/admin/roles", libelle: "Rôles", icone: ShieldCheck, visible: usePeut("utilisateurs.gerer") },
    { href: "/admin/journal", libelle: "Journal d'accès", icone: ScrollText, visible: usePeut("journal.voir") },
  ];

  const rendu = (l: (typeof liens)[number]) =>
    l.visible && (
      <Link
        key={l.href}
        href={l.href}
        className={cn(
          "flex items-center gap-3 rounded-md px-3 py-2 text-sm text-slate-300 hover:bg-white/10 hover:text-white",
          chemin.startsWith(l.href) && "bg-white/10 text-white",
        )}
      >
        <l.icone className="h-4 w-4" /> {l.libelle}
      </Link>
    );

  return (
    <aside className="hidden w-60 shrink-0 flex-col bg-slate-900 px-3 py-5 md:flex">
      <Link href="/explorateur" className="mb-8 flex items-center gap-2 px-3 font-semibold text-white">
        <Archive className="h-5 w-5 text-accent" /> Archivage
      </Link>
      <nav className="space-y-1">{liens.map(rendu)}</nav>
      {admin.some((l) => l.visible) && (
        <>
          <div className="mt-8 mb-2 px-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Administration</div>
          <nav className="space-y-1">{admin.map(rendu)}</nav>
        </>
      )}
    </aside>
  );
}
