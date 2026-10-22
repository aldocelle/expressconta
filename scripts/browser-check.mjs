import {chromium} from 'playwright';
import {spawn} from 'node:child_process';
import {mkdirSync} from 'node:fs';
import setup from '../tests/e2e/setup.mjs';
import {e2eEnv} from '../tests/e2e/environment.mjs';
console.log('Preparing isolated browser database');setup();
const server=spawn('php',['artisan','serve','--host=127.0.0.1','--port=8000','--no-reload'],{env:e2eEnv,stdio:['ignore','pipe','pipe']});
server.stdout.on('data',x=>process.stdout.write(x));server.stderr.on('data',x=>process.stderr.write(x));
let browser;
try{
 for(let i=0;i<80;i++){try{const r=await fetch('http://127.0.0.1:8000/up');if(r.ok)break;}catch{}if(i===79)throw new Error('PHP did not become ready');await new Promise(r=>setTimeout(r,150));}
 console.log('Launching Chromium');browser=await chromium.launch({headless:true,args:['--no-sandbox']});const page=await browser.newPage({viewport:{width:1440,height:1000}});const errors=[];page.on('pageerror',e=>errors.push(e.message));
 page.on('response',r=>{if(r.status()>=400)console.log('HTTP',r.status(),r.url())});page.on('console',m=>{if(m.type()==='error')console.log('CONSOLE',m.text())});console.log('Opening login');await page.goto('http://127.0.0.1:8000/login',{waitUntil:'domcontentloaded'});console.log('Login body: '+(await page.locator('body').innerText()).slice(0,300));await page.getByLabel('Correo electrónico').fill('admin@expressconta.test');await page.getByLabel('Contraseña',{exact:true}).fill(e2eEnv.DEMO_PASSWORD);await page.getByRole('button',{name:'Entrar al estudio'}).click();await page.getByRole('heading',{name:'Tu estudio, bajo control.'}).waitFor();
 mkdirSync('test-results',{recursive:true});await page.screenshot({path:'test-results/resumen-desktop.png',fullPage:true});console.log('Dashboard loaded');
 for(const path of ['companies','works','documents','tasks','billing','reports','conversations','settings']){await page.goto('http://127.0.0.1:8000/'+path);await page.locator('h1').waitFor();await page.waitForTimeout(250);if(await page.getByRole('alert').count())throw new Error(path+': '+await page.getByRole('alert').allTextContents());console.log('Verified '+path);}
 await page.getByRole('button',{name:'Cerrar sesión'}).click();await page.getByRole('heading',{name:'Un nuevo día, todo claro.'}).waitFor();await page.getByLabel('Correo electrónico').fill('cliente@expressconta.test');await page.getByLabel('Contraseña',{exact:true}).fill(e2eEnv.DEMO_PASSWORD);await page.getByRole('button',{name:'Entrar al estudio'}).click();await page.getByRole('heading',{name:'Hola, Francisca.'}).waitFor();await page.setViewportSize({width:390,height:844});await page.screenshot({path:'test-results/portal-mobile.png',fullPage:true});if(await page.evaluate(()=>document.documentElement.scrollWidth>window.innerWidth))throw new Error('Mobile page overflows');if(errors.length)throw new Error(errors.join('\n'));console.log('Browser check passed: desktop navigation, login/logout and mobile client portal');
}finally{await browser?.close();server.kill('SIGTERM');}
