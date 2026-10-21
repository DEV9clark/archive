export function PageHeader({ titre, sousTitre, actions }: { titre: string; sousTitre?: React.ReactNode; actions?: React.ReactNode }) {
  return (
    <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
      <div>
        <h1 className="text-2xl font-semibold text-slate-900">{titre}</h1>
        {sousTitre && <div className="mt-1 text-sm text-slate-500">{sousTitre}</div>}
      </div>
      {actions && <div className="flex items-center gap-2">{actions}</div>}
    </div>
  );
}
