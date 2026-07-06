import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  allowedDevOrigins: ['*'],
  // output: 'export', // Commented out to allow dynamic routing for order tracking IDs
  images: {
    unoptimized: true,
  },
};

export default nextConfig;
