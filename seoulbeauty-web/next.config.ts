import type { NextConfig } from "next";
import path from "node:path";

// NestJS API (seoulbeauty-api). Server-side fetches hit it directly; the browser goes
// through the same-origin /api/* rewrite so no CORS and no exposed port.
const API_BASE = process.env.API_BASE || "http://127.0.0.1:3110";
// Mounted under https://seoulbeauty.app/next/ by Apache (the PHP site owns "/").
const BASE_PATH = process.env.NEXT_BASE_PATH ?? "/next";

const nextConfig: NextConfig = {
  basePath: BASE_PATH,
  cacheComponents: true,
  partialPrefetching: true,
  poweredByHeader: false,
  // This app lives inside the hms-saas monorepo (another package-lock.json above): pin the root.
  turbopack: { root: path.resolve(__dirname) },
  outputFileTracingRoot: path.resolve(__dirname),
  // Multipart uploads (community 4x5MB, review 4x5MB) pass through the /api rewrite; the default proxy body cap is 10MB.
  experimental: { proxyClientMaxBodySize: "25mb" },
  async rewrites() {
    return [{ source: "/api/:path*", destination: `${API_BASE}/:path*` }];
  },
};

export default nextConfig;
