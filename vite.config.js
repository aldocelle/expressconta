import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
export default defineConfig({plugins:[laravel({input:['resources/js/main.ts'],refresh:true}),vue()],server:{host:'0.0.0.0'}});
