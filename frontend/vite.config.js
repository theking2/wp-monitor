import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

// Built output is served by the PHP webserver from htdocs/assets/app, alongside the API
// (see ../htdocs/.htaccess) — base/outDir below must match that path.
export default defineConfig({
  plugins: [vue(), tailwindcss()],
  base: '/assets/app/',
  build: {
    outDir: '../htdocs/assets/app',
    emptyOutDir: true,
  },
  server: {
    proxy: {
      '/api': 'http://localhost:9080',
      '/assets/fonts': 'http://localhost:9080',
    },
  },
})
