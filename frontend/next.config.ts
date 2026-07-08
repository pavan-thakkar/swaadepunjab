import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  allowedDevOrigins: ['*'],
  output: 'export',       // Static HTML export for Apache server deployment
  trailingSlash: true,    // Generates /page/index.html — needed for Apache routing
  images: {
    unoptimized: true,
  },
};

export default nextConfig;
