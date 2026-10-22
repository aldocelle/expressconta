import {execFileSync} from 'node:child_process';
import {mkdirSync,writeFileSync} from 'node:fs';
const raw=process.env.BACKEND_ORIGIN?.trim();
let origin;
if(raw){const url=new URL(raw);if(url.protocol!=='https:'||url.username||url.password||url.pathname!=='/'||url.search||url.hash)throw new Error('BACKEND_ORIGIN debe ser un origen HTTPS sin credenciales, ruta, query o fragmento.');origin=url.origin;if(process.env.VERCEL_URL&&url.host===process.env.VERCEL_URL)throw new Error('BACKEND_ORIGIN no puede apuntar a este frontend.');}
execFileSync(process.execPath,['node_modules/vue-tsc/bin/vue-tsc.js','--noEmit'],{stdio:'inherit'});
execFileSync(process.execPath,['node_modules/vite/bin/vite.js','build','--config','vite.vercel.config.js'],{stdio:'inherit',env:{...process.env,VITE_STANDALONE:'true',VITE_BACKEND_CONFIGURED:origin?'true':'false'}});
mkdirSync('.vercel/output',{recursive:true});
const routes=[{src:'/(.*)',headers:{'X-Content-Type-Options':'nosniff','Referrer-Policy':'strict-origin-when-cross-origin','X-Frame-Options':'DENY'},continue:true}];
if(origin){routes.push({src:'/api/(.*)',dest:origin+'/api/$1'},{src:'/sanctum/(.*)',dest:origin+'/sanctum/$1'});}else{writeFileSync('.vercel/output/static/service-unavailable.json',JSON.stringify({message:'El estudio todavía no está conectado. Contacta al administrador para habilitar el acceso.'}));routes.push({src:'/(?:api|sanctum)(?:/.*)?',dest:'/service-unavailable.json',status:503,headers:{'Content-Type':'application/json','Cache-Control':'no-store'}});console.warn('Frontend preparado sin backend: el acceso mostrará servicio pendiente de conexión.');}
routes.push({handle:'filesystem'},{src:'/.*',dest:'/index.html'});
writeFileSync('.vercel/output/config.json',JSON.stringify({version:3,routes},null,2));
