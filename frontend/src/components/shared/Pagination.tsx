import { Button } from "@/components/ui/button";

export function Pagination({ page, derniere, onPage }: { page: number; derniere: number; onPage: (p: number) => void }) {
  if (derniere <= 1) return null;
  return (
    <div className="flex items-center justify-end gap-2 px-4 py-3 text-sm text-slate-600">
      <Button variante="secondaire" taille="sm" disabled={page <= 1} onClick={() => onPage(page - 1)}>Précédent</Button>
      <span>Page {page} / {derniere}</span>
      <Button variante="secondaire" taille="sm" disabled={page >= derniere} onClick={() => onPage(page + 1)}>Suivant</Button>
    </div>
  );
}
