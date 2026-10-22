import {defineConfig} from 'vite';
import vue from '@vitejs/plugin-vue';
export default defineConfig({plugins:[vue()],build:{outDir:'.vercel/output/static',emptyOutDir:true},publicDir:false});
