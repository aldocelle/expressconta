import {test,expect} from '@playwright/test';
import type {Page} from '@playwright/test';
async function login(page:Page,profile='contador'){
 await page.goto('/login');
 await expect(page.getByText('contador@expressconta.demo',{exact:true})).toBeVisible();
 await expect(page.getByText('cliente@expressconta.demo',{exact:true})).toBeVisible();
 await expect(page.getByText('Demo2026!',{exact:true})).toHaveCount(2);
 await page.getByRole('button',{name:`Entrar como ${profile}`,exact:true}).click();
 await expect(page.getByRole('heading',{level:1})).toBeVisible();
}
const dialog=(page:Page)=>page.getByRole('dialog');
test('credenciales visibles, acceso rápido y navegación sin backend',async({page})=>{
 const errors:string[]=[],backend:string[]=[];
 page.on('pageerror',e=>errors.push(e.message));
 page.on('request',r=>{if(/\/(api|sanctum)\//.test(r.url()))backend.push(r.url());});
 await login(page);
 await expect(page.getByRole('heading',{name:'Tu estudio, bajo control.'})).toBeVisible();
 await page.screenshot({path:'test-results/demo-contador.png',fullPage:true});
 for(const [path,title] of [['/companies','Empresas'],['/works','Trabajos por período'],['/documents','Antecedentes y documentos'],['/tasks','Agenda y tareas'],['/billing','Honorarios'],['/reports','Informes y entregas'],['/conversations','Consultas'],['/settings','Configuración']]){
  await page.goto(path);await expect(page.getByRole('heading',{level:1,name:title,exact:true})).toBeVisible();await expect(page.getByRole('alert')).toHaveCount(0);
 }
 await page.goto('/works');await page.getByRole('button',{name:'Ver detalle'}).first().click();await expect(dialog(page)).toBeVisible();
 expect(errors).toEqual([]);expect(backend).toEqual([]);
});
test('cliente entrega ejemplo, contador lo acepta y persiste al recargar',async({page})=>{
 await login(page,'cliente');await page.goto('/companies');await expect(page.locator('tbody tr')).toHaveCount(2);
 await page.goto('/conversations');await page.getByRole('button',{name:/Corrección de cartola bancaria/}).click();await expect(page.getByText(/Nota interna de ejemplo/)).toHaveCount(0);
 await page.goto('/documents');await page.getByRole('button',{name:'Ver solicitud'}).first().click();await dialog(page).getByRole('button',{name:'Simular envío con archivo de ejemplo'}).click();await expect(dialog(page)).toHaveCount(0);await expect(page.locator('.document-row').first().getByText('Recibido para revisión',{exact:true})).toBeVisible();
 await page.getByLabel('Cambiar perfil demo').selectOption('admin');await expect(page.getByRole('heading',{name:'Tu estudio, bajo control.'})).toBeVisible();
 await page.goto('/documents');await page.getByRole('button',{name:'Revisar',exact:true}).first().click();await expect(dialog(page).getByText('cartola-de-ejemplo.txt')).toBeVisible();await dialog(page).getByRole('button',{name:'Guardar revisión'}).click();await expect(dialog(page)).toHaveCount(0);
 await page.reload();await expect(page.locator('.document-row').first().getByText('Aceptado',{exact:true})).toBeVisible();
 await page.getByLabel('Cambiar perfil demo').selectOption('client');await page.goto('/documents');await expect(page.locator('.document-row').first().getByText('Aceptado',{exact:true})).toBeVisible();
});
test('tareas, pagos parciales, recarga y reinicio de datos',async({page})=>{
 await login(page);await page.goto('/tasks');await page.getByRole('button',{name:'Nueva tarea'}).click();await dialog(page).getByLabel('Empresa',{exact:true}).selectOption('1');await dialog(page).getByLabel('Tarea',{exact:true}).fill('Revisión de prueba con amigos');await dialog(page).getByRole('button',{name:'Crear tarea'}).click();await expect(dialog(page)).toHaveCount(0);await page.reload();await expect(page.getByText('Revisión de prueba con amigos',{exact:true})).toBeVisible();
 const row=page.locator('.list-row').filter({hasText:'Revisión de prueba con amigos'});await row.getByRole('button',{name:'Completar tarea'}).click();await expect(row.getByText('Completada',{exact:true})).toBeVisible();
 await page.goto('/billing');const charge=page.locator('tbody tr').filter({hasText:'Café Puerto'});await charge.getByRole('button',{name:'Registrar pago'}).click();await dialog(page).getByLabel('Monto CLP').fill('50000');await dialog(page).getByRole('button',{name:'Registrar pago',exact:true}).click();await expect(dialog(page)).toHaveCount(0);await page.reload();await expect(page.locator('tbody tr').filter({hasText:'Café Puerto'}).getByText('$100.000',{exact:true})).toBeVisible();
 await page.getByRole('button',{name:'Reiniciar demo',exact:true}).click();await dialog(page).getByRole('button',{name:'Sí, reiniciar demo'}).click();await expect(page.getByRole('button',{name:'Entrar como contador'})).toBeVisible();await page.getByRole('button',{name:'Entrar como contador'}).click();await page.goto('/tasks');await expect(page.getByText('Revisión de prueba con amigos')).toHaveCount(0);await page.goto('/billing');await expect(page.locator('tbody tr').filter({hasText:'Café Puerto'}).getByText('$150.000',{exact:true})).toHaveCount(2);
});
test('ciclo de aprobación, informe y entrega',async({page})=>{
 await login(page);await page.goto('/works');let row=page.locator('tbody tr').filter({hasText:'Taller Pacífico'});await row.getByRole('button',{name:'Ver detalle'}).click();await dialog(page).getByLabel('Siguiente etapa').selectOption('approved');await dialog(page).getByRole('button',{name:'Actualizar etapa'}).click();await expect(dialog(page)).toHaveCount(0);await expect(row.getByText('Aprobado',{exact:true})).toBeVisible();
 await page.goto('/reports');await page.getByRole('button',{name:'Nuevo informe'}).click();await dialog(page).getByLabel('Trabajo',{exact:true}).selectOption('4');await dialog(page).getByLabel('Título',{exact:true}).fill('Informe para amigos');await dialog(page).getByRole('button',{name:'Usar informe de ejemplo'}).click();await dialog(page).getByRole('button',{name:'Guardar borrador'}).click();await expect(dialog(page)).toHaveCount(0);await page.locator('.document-row').filter({hasText:'Informe para amigos'}).getByRole('button',{name:'Aprobar y publicar'}).click();await expect(page.locator('.document-row').filter({hasText:'Informe para amigos'}).getByText('Publicado',{exact:true})).toBeVisible();
 await page.goto('/works');row=page.locator('tbody tr').filter({hasText:'Taller Pacífico'});await row.getByRole('button',{name:'Ver detalle'}).click();await dialog(page).getByLabel('Siguiente etapa').selectOption('delivered');await dialog(page).getByRole('button',{name:'Actualizar etapa'}).click();await expect(dialog(page)).toHaveCount(0);await expect(row.getByText('Entregado',{exact:true})).toBeVisible();
});
test('móvil, aislamiento por navegador y descarga ficticia',async({page,browser})=>{
 await page.setViewportSize({width:390,height:844});await page.goto('/login');await page.screenshot({path:'test-results/demo-login-mobile.png',fullPage:true});await login(page,'cliente');
 await page.goto('/reports');const download=page.waitForEvent('download');await page.getByRole('link',{name:'Descargar',exact:true}).first().click();expect((await download).suggestedFilename()).toBe('expressconta-documento-de-ejemplo.txt');
 await page.goto('/');await page.screenshot({path:'test-results/demo-cliente-mobile.png',fullPage:true});expect(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth)).toBe(true);
 const another=await browser.newContext();const anotherPage=await another.newPage();await anotherPage.goto(new URL('/login',page.url()).href);await expect(anotherPage.getByRole('button',{name:'Entrar como contador'})).toBeVisible();await another.close();
});
