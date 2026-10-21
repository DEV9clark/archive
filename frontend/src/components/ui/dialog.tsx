"use client";

import { useEffect, useRef } from "react";
import { X } from "lucide-react";

/** Boîte de dialogue modale basée sur <dialog> (focus et Échap gérés par le navigateur). */
export function Dialog({
  ouvert,
  onFermer,
  titre,
  children,
}: {
  ouvert: boolean;
  onFermer: () => void;
  titre: string;
  children: React.ReactNode;
}) {
  const ref = useRef<HTMLDialogElement>(null);

  useEffect(() => {
    const d = ref.current;
    if (!d) return;
    if (ouvert && !d.open) d.showModal();
    if (!ouvert && d.open) d.close();
  }, [ouvert]);

  return (
    <dialog
      ref={ref}
      onClose={onFermer}
      className="m-auto w-full max-w-lg rounded-lg bg-white p-0 shadow-xl backdrop:bg-slate-900/40"
    >
      <div className="flex items-center justify-between border-b border-slate-100 px-5 py-3">
        <h2 className="font-semibold">{titre}</h2>
        <button onClick={onFermer} className="rounded p-1 text-slate-500 hover:bg-slate-100" aria-label="Fermer">
          <X className="h-4 w-4" />
        </button>
      </div>
      <div className="px-5 py-4">{ouvert && children}</div>
    </dialog>
  );
}
