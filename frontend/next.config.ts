import type { NextConfig } from "next";

const backend = process.env.BACKEND_URL ?? "http://127.0.0.1:8000";

const nextConfig: NextConfig = {
  async rewrites() {
    if (process.env.NODE_ENV !== "development") {
      return [];
    }
    return [
      { source: "/api/:path*", destination: `${backend}/api/:path*` },
      { source: "/sanctum/:path*", destination: `${backend}/sanctum/:path*` },
      { source: "/docs/:path*", destination: `${backend}/docs/:path*` },
    ];
  },
};

export default nextConfig;
