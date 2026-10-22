import axios from 'axios';
export const api=axios.create({baseURL:'/api',withCredentials:true,withXSRFToken:true,headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});
export function errorText(e:unknown):string {if(axios.isAxiosError(e)){const d=e.response?.data;return d?.errors?Object.values(d.errors).flat().join(' '):d?.message||'No se pudo completar la operación. Revisa tu conexión.';}return 'Ocurrió un error inesperado.';}
export async function csrf(){await axios.get('/sanctum/csrf-cookie',{withCredentials:true});}
export const clp=(n:number)=>new Intl.NumberFormat('es-CL',{style:'currency',currency:'CLP',maximumFractionDigits:0}).format(n);
export function date(d:string|null){return d?new Intl.DateTimeFormat('es-CL',{timeZone:'America/Santiago'}).format(new Date(d.length===10?d+'T12:00:00Z':d)):'Sin fecha';}
export const currentPeriod=()=>new Intl.DateTimeFormat('en-CA',{timeZone:'America/Santiago',year:'numeric',month:'2-digit'}).format(new Date());
export const statusLabels:Record<string,string>={not_started:'Por iniciar',waiting:'Esperando antecedentes',preparing:'En preparación',review:'En revisión',changes:'Requiere ajustes',approved:'Aprobado',delivered:'Entregado',cancelled:'Cancelado',pending:'Pendiente',received:'Recibido para revisión',reviewing:'En revisión',accepted:'Aceptado',correction:'Corrección solicitada',not_applicable:'No aplica',paid:'Pagado',overdue:'Vencido',partial:'Pago parcial',in_progress:'En curso',completed:'Completada',open:'Abierta',resolved:'Resuelta'};

export async function companyOptions():Promise<any[]> {const result:any[]=[];let page=1,last=1;do{const data=(await api.get('/companies',{params:{page}})).data;result.push(...data.data);last=data.last_page;page++;}while(page<=last);return result;}
