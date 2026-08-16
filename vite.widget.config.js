import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// Separate, standalone build for the embeddable consent widget
// (01-scope-and-non-goals.md: "capture API + embeddable widget" is one
// MVP item, not two). This deliberately does NOT go through
// laravel-vite-plugin/vite.config.js: that pipeline produces
// content-hashed, manifest-keyed assets loaded via Laravel's own
// @vite() Blade directive, which only works for pages this application
// itself renders. A third-party site embedding this widget has no
// Blade template and no manifest — it needs one stable, predictable URL
// it can hardcode in a <script src> tag. Building in Vite's library mode
// to a fixed filename (public/widget.js, IIFE format, no hash) is what
// makes that possible; laravel-vite-plugin has no library-mode option.
export default defineConfig({
  plugins: [vue()],
  // Library mode (below) skips Vite's usual app-build assumptions,
  // including replacing process.env.NODE_ENV — Vue's bundler-targeted
  // build checks that at runtime, so without this define it throws
  // "process is not defined" in a real browser and never mounts.
  define: {
    'process.env.NODE_ENV': JSON.stringify('production'),
  },
  // This build's own outDir IS public/ (see below) — publicDir is Vite's
  // separate "copy verbatim" directory and would otherwise default to the
  // same folder, which Vite warns about even though it's intentional here.
  publicDir: false,
  build: {
    outDir: 'public',
    emptyOutDir: false,
    lib: {
      entry: 'resources/js/widget/main.js',
      name: 'PrivacyForgeConsentWidget',
      formats: ['iife'],
      fileName: () => 'widget.js',
    },
  },
})
