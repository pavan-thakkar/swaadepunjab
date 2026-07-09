import type { NextConfig } from "next";

const isProd = process.env.NODE_ENV === 'production';

const nextConfig: NextConfig = {
  allowedDevOrigins: ['*'],
  // Static export only for production builds (deploy to Apache).
  // Dev server (npm run dev) runs normally without this.
  ...(isProd ? { output: 'export', trailingSlash: true } : {}),
  images: {
    unoptimized: true,
  },
};

export default nextConfig;

