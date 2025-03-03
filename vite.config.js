import FastGlob from 'fast-glob'
import { resolve } from 'path';
import { defineConfig, loadEnv } from 'vite';

//import tailwindcss from 'tailwindcss'
//import tailwindcssNesting from 'tailwindcss/nesting/index.js'
//import autoprefixer from 'autoprefixer'
//import postcssImport from 'postcss-import';
//import postcssNested from 'postcss-nested';
// import postcssCustomMedia from 'postcss-custom-media';
// import { svelte } from '@sveltejs/vite-plugin-svelte'
import tailwindcss from '@tailwindcss/vite'

const reload = {
  name: 'reload',
  handleHotUpdate({ file, server }) {
    if (!file.includes('temp') && file.endsWith(".php") || file.endsWith(".latte")) {
      server.ws.send({
        type: 'full-reload',
        path: '*',
      });
    }
  }
}

/** @type {import('vite').UserConfig} */
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), 'DEV');

  return {
    plugins: [reload, tailwindcss()],
    css: {
      postcss: {
        plugins: []
      }
    },
    server: {
      port: env.DEV_PORT || 5174,
      strictPort: true,
      watch: {
        ignored: ['!^src', '!**/app/**/*.latte']
      },
      hmr: {
        host: 'localhost',
        port: 5137,
        protocol: 'ws'
      }
    },
    build: {
      manifest: "manifest.json",
      outDir: "public_html/assets/build",
      emptyOutDir: true,
      rollupOptions: {
        input: FastGlob.sync(['./src/js/*.js', './src/css/*.css']).map(entry => resolve(process.cwd(), entry)),
      },
      assetsDir: '',
    }
  }
})