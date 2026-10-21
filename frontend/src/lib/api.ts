import { useAuth } from "@/stores/auth";

export const API_URL = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000/api/v1";

export class ErreurApi extends Error {
  constructor(
    public status: number,
    message: string,
    public erreurs: Record<string, string[]> = {},
  ) {
    super(message);
  }

  /** Premier message de validation, ou le message général. */
  get detail(): string {
    return Object.values(this.erreurs)[0]?.[0] ?? this.message;
  }
}

type Params = Record<string, string | number | boolean | null | undefined>;

export function avecParams(chemin: string, params?: Params): string {
  if (!params) return chemin;
  const q = new URLSearchParams();
  for (const [k, v] of Object.entries(params)) {
    if (v !== undefined && v !== null && v !== "") q.set(k, String(v));
  }
  const s = q.toString();
  return s ? `${chemin}?${s}` : chemin;
}

async function requete(chemin: string, init: RequestInit = {}): Promise<Response> {
  const token = useAuth.getState().token;
  const headers = new Headers(init.headers);
  headers.set("Accept", "application/json");
  if (token) headers.set("Authorization", `Bearer ${token}`);
  if (init.body && !(init.body instanceof FormData)) headers.set("Content-Type", "application/json");

  const reponse = await fetch(`${API_URL}${chemin}`, { ...init, headers });

  if (reponse.status === 401 && token) {
    useAuth.getState().fermerSession();
  }
  if (!reponse.ok) {
    const corps = await reponse.json().catch(() => ({}));
    throw new ErreurApi(reponse.status, corps.message ?? `Erreur ${reponse.status}`, corps.errors);
  }
  return reponse;
}

export const api = {
  async get<T>(chemin: string, params?: Params): Promise<T> {
    return (await requete(avecParams(chemin, params))).json();
  },
  async post<T>(chemin: string, corps?: unknown): Promise<T> {
    const r = await requete(chemin, {
      method: "POST",
      body: corps instanceof FormData ? corps : JSON.stringify(corps ?? {}),
    });
    return r.status === 204 ? (undefined as T) : r.json();
  },
  async put<T>(chemin: string, corps: unknown): Promise<T> {
    return (await requete(chemin, { method: "PUT", body: JSON.stringify(corps) })).json();
  },
  async delete(chemin: string): Promise<void> {
    await requete(chemin, { method: "DELETE" });
  },
  /** Récupère un fichier protégé (en-tête Bearer) et le propose au téléchargement ou l'ouvre. */
  async fichier(chemin: string, nom: string, enLigne = false): Promise<void> {
    const blob = await (await requete(avecParams(chemin, { inline: enLigne ? 1 : undefined }))).blob();
    const url = URL.createObjectURL(blob);
    if (enLigne) {
      window.open(url, "_blank", "noopener");
    } else {
      const a = document.createElement("a");
      a.href = url;
      a.download = nom;
      a.click();
    }
    setTimeout(() => URL.revokeObjectURL(url), 60_000);
  },
};
