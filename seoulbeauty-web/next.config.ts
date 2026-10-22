import type { NextConfig } from "next";
import path from "node:path";

// NestJS API (seoulbeauty-api). Server-side fetches hit it directly; the browser goes
// through the same-origin /api/* rewrite so no CORS and no exposed port.
const API_BASE = process.env.API_BASE || "http://127.0.0.1:3110";

const nextConfig: NextConfig = {
  cacheComponents: true,
  partialPrefetching: true,
  poweredByHeader: false,
  // This app lives inside the hms-saas monorepo (another package-lock.json above): pin the root.
  turbopack: { root: path.resolve(__dirname) },
  outputFileTracingRoot: path.resolve(__dirname),
  async rewrites() {
    return [{ source: "/api/:path*", destination: `${API_BASE}/:path*` }];
  },
};

export default nextConfig;
