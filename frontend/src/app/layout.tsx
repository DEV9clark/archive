import type { Metadata } from "next";
import { Providers } from "@/components/providers/Providers";
import "@/styles/globals.css";

export const metadata: Metadata = {
  title: { default: "Archivage", template: "%s · Archivage" },
  description: "Système d'archivage hiérarchique",
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="fr">
      <body>
        <Providers>{children}</Providers>
      </body>
    </html>
  );
}
