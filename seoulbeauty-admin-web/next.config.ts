import type { NextConfig } from "next";
import path from "node:path";

// Admin NestJS API (seoulbeauty-admin-api). The browser only ever talks to it through the
// same-origin /api/* rewrite below, so the admin session cookie travels along and no CORS is needed.
const API_BASE = process.env.API_BASE || "http://127.0.0.1:3300";
// Mounted under https://seoulbeauty.app/admin-next/ by Apache.
// An empty value (e.g. a bare `NEXT_BASE_PATH=` line copied from .env.example) falls back to the default too.
const BASE_PATH = (process.env.NEXT_BASE_PATH ?? "").trim() || "/admin-next";

const nextConfig: NextConfig = {
  basePath: BASE_PATH,
  // Client code builds its fetch and asset URLs from this, so it can never drift from basePath.
  env: { NEXT_PUBLIC_BASE_PATH: BASE_PATH },
  poweredByHeader: false,
  // This app lives inside the hms-saas monorepo (another package-lock.json above): pin the root.
  turbopack: { root: path.resolve(__dirname) },
  outputFileTracingRoot: path.resolve(__dirname),
  // The admin shell must never sit in a shared cache: after a redeploy a cached page would point at
  // chunk files that no longer exist. Hashed assets under /_next/static keep their own long-lived headers.
  async headers() {
    return [
      {
        source: "/((?!_next/static|_next/image).*)",
        headers: [{ key: "Cache-Control", value: "private, no-store" }],
      },
    ];
  },
  async rewrites() {
    return [{ source: "/api/:path*", destination: `${API_BASE}/:path*` }];
  },
};

export default nextConfig;
