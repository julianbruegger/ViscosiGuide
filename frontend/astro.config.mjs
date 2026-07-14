// @ts-check
import { defineConfig } from 'astro/config';

// Static build: HTML/CSS/JS are generated at build time and uploaded to HostPoint.
// The PHP API is served from the same origin under /api, so no runtime Node server
// is needed. Leaflet is bundled from npm (no third-party CDN) to keep the CSP tight.
export default defineConfig({
  output: 'static',
  trailingSlash: 'ignore',
  build: {
    assets: 'assets',
    // Emit /login.html instead of /login/index.html so a single Apache rewrite
    // (add .html) gives clean extensionless URLs on HostPoint.
    format: 'file',
  },
  devToolbar: { enabled: false },
});
