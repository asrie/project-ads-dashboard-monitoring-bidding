import { defineConfig } from 'vite';
import { svelte } from '@sveltejs/vite-plugin-svelte';

// SPA dashboard frontend. Talks to the Laravel API at VITE_API_BASE_URL.
export default defineConfig({
  plugins: [svelte()],
  server: {
    port: 5173,
    host: true,
  },
});
