const apiUrl = new URL(process.env.API_URL || "http://127.0.0.1:8000");
const imageProtocol = apiUrl.protocol.replace(":", "");
const imageHosts = [...new Set([apiUrl.hostname, "localhost", "127.0.0.1"])];

/** @type {import('next').NextConfig} */
const nextConfig = {
  cacheComponents: true,
  poweredByHeader: false,
  compress: true,
  async headers() {
    return [
      {
        source: "/:path*",
        headers: [
          { key: "X-Content-Type-Options", value: "nosniff" },
          { key: "X-Frame-Options", value: "SAMEORIGIN" },
          { key: "Referrer-Policy", value: "strict-origin-when-cross-origin" },
          {
            key: "Permissions-Policy",
            value: "camera=(), microphone=(), geolocation=(), browsing-topics=()",
          },
        ],
      },
      {
        // Feeds and sitemaps change slowly; let browsers cache them briefly.
        source: "/:file(feed.xml|sitemap.xml|robots.txt)",
        headers: [{ key: "Cache-Control", value: "public, max-age=300, s-maxage=900" }],
      },
    ];
  },
  images: {
    remotePatterns: imageHosts.map((hostname) => ({
      protocol: imageProtocol,
      hostname,
      port: apiUrl.port || undefined,
      pathname: "/storage/**",
    })),
  },
  partialPrefetching: true,
  turbopack: {
    rules: {
      "*.css": {
        loaders: ["@tailwindcss/turbopack"],
        as: "*.css",
      },
    },
  },
  // Cross-origin dev requests from the cloud preview host must be allowed
  // explicitly; the value is supplied per environment rather than hardcoded.
  allowedDevOrigins: process.env.NEXT_DEV_ALLOWED_ORIGIN
    ? [process.env.NEXT_DEV_ALLOWED_ORIGIN]
    : [],
};

export default nextConfig;
