import { defineConfig } from 'vite';

export default defineConfig({
  build: {
    outDir: 'assets/dist',
    rollupOptions: {
      input: 'assets/css/app.css',
    },
  },
});
