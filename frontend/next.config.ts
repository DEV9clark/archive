import type { NextConfig } from "next";

// Le navigateur appelle /api/v1 sur l'origine du front ; Next relaie vers nginx.
// Une seule URL à exposer (ngrok) et plus besoin de CORS.
const API_INTERNE = process.env.API_INTERNE_URL ?? "http://localhost:8010";

const nextConfig: NextConfig = {
  output: "standalone",
  poweredByHeader: false,
  allowedDevOrigins: ["*.ngrok-free.dev", "*.ngrok-free.app", "*.ngrok.app", "*.ngrok.dev"],
  experimental: {
    // Dépôt de documents : aligné sur client_max_body_size de nginx
    proxyClientMaxBodySize: "220mb",
  },
  async rewrites() {
    return [{ source: "/api/:chemin*", destination: `${API_INTERNE}/api/:chemin*` }];
  },
};

export default nextConfig;
