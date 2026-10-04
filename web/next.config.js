/** @type {import('next').NextConfig} */
module.exports = {
  reactStrictMode: true,
  // Keep production builds from overwriting a running dev server's generated files.
  distDir: process.env.NODE_ENV === 'production' ? '.next-build' : '.next',
  async rewrites() {
    return [{ source: '/api/:path*', destination: `${process.env.API_ORIGIN || 'http://127.0.0.1:8001'}/api/:path*` }];
  },
};
