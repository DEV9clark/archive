import { forwardRef, type ButtonHTMLAttributes } from "react";
import { cn } from "@/lib/utils";

const VARIANTES = {
  primaire: "bg-primaire text-white hover:bg-primaire/90",
  secondaire: "border border-slate-300 bg-white text-slate-800 hover:bg-slate-50",
  danger: "bg-red-600 text-white hover:bg-red-700",
  fantome: "text-slate-700 hover:bg-slate-100",
} as const;

const TAILLES = { sm: "h-8 px-3 text-sm", md: "h-10 px-4 text-sm", icone: "h-8 w-8" } as const;

export interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variante?: keyof typeof VARIANTES;
  taille?: keyof typeof TAILLES;
}

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(
  ({ className, variante = "primaire", taille = "md", type = "button", ...props }, ref) => (
    <button
      ref={ref}
      type={type}
      className={cn(
        "inline-flex items-center justify-center gap-2 rounded-md font-medium transition-colors",
        "focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primaire",
        "disabled:pointer-events-none disabled:opacity-50",
        VARIANTES[variante],
        TAILLES[taille],
        className,
      )}
      {...props}
    />
  ),
);
Button.displayName = "Button";
