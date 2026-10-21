import { FormulaireConnexion } from "@/components/auth/FormulaireConnexion";

export const metadata = { title: "Connexion" };

export default function PageConnexion() {
  return (
    <main className="flex min-h-screen items-center justify-center bg-gradient-to-br from-primaire to-slate-900 p-4">
      <FormulaireConnexion />
    </main>
  );
}
