import type { HTMLAttributes } from "react";
import { cn } from "@/lib/utils";

const TONS = {
  neutre: "bg-slate-100 text-slate-700",
  info: "bg-primaire-clair text-primaire",
  succes: "bg-emerald-50 text-emerald-700",
  alerte: "bg-amber-50 text-amber-800",
  danger: "bg-red-50 text-red-700",
} as const;

export function Badge({ ton = "neutre", className, ...props }: HTMLAttributes<HTMLSpanElement> & { ton?: keyof typeof TONS }) {
  return (
    <span
      className={cn("inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium", TONS[ton], className)}
      {...props}
    />
  );
}
