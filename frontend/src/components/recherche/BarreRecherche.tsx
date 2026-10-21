"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { Search } from "lucide-react";

export function BarreRecherche() {
  const router = useRouter();
  const [q, setQ] = useState("");

  return (
    <form
      role="search"
      className="relative w-full max-w-md"
      onSubmit={(e) => {
        e.preventDefault();
        if (q.trim().length >= 2) router.push(`/recherche?q=${encodeURIComponent(q.trim())}`);
      }}
    >
      <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
      <input
        value={q}
        onChange={(e) => setQ(e.target.value)}
        placeholder="Rechercher un document, un lieu, un dossier…"
        className="h-9 w-full rounded-md border border-slate-200 bg-slate-50 pr-3 pl-9 text-sm focus:border-primaire focus:bg-white focus:outline-none"
      />
    </form>
  );
}
