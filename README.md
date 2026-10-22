# ExpressConta

Gestión de estudios contables en Chile con **Laravel 12, PHP 8.2+, Vue 3, TypeScript y MySQL/MariaDB**. Interfaz verde profundo y marfil, portal de clientes y archivos privados.

## Demo pública para compartir

Abrir **https://expressconta.vercel.app**. El login muestra las credenciales de prueba y permite entrar con un clic:

| Perfil | Correo | Contraseña demo |
|---|---|---|
| Contador | contador@expressconta.demo | Demo2026! |
| Cliente | cliente@expressconta.demo | Demo2026! |

Son perfiles locales ficticios, sin acceso al backend. Los cambios se guardan exclusivamente en el navegador y no se comparten entre personas/dispositivos. La franja de demostración permite cambiar de perfil y reiniciar los datos. Las cargas conservan solo nombres/metadatos: no leen ni envían el contenido. Las descargas producen un documento de ejemplo. No ingresar información o contraseñas reales.

`npm run build:vercel` activa la demo cuando falta `BACKEND_ORIGIN`. Para una instalación real, definir `BACKEND_ORIGIN` y dejar `DEMO_MODE=false`. La demo no modifica la autenticación ni los datos de Laravel. `DEMO_MODE=false` sin backend muestra el estado de servicio pendiente. Combinar `DEMO_MODE=true` con un backend provoca error de compilación para evitar mezclar ambos modos.

Verificar la demo con `npm run build:vercel` y `npm run test:demo` (Chromium instalado). Puede probarse una URL publicada con `DEMO_BASE_URL=https://… npm run test:demo`; las pruebas solo modifican datos ficticios del navegador.

## Estado

Primera versión funcional para instalación y evaluación: autenticación por sesión, múltiples estudios, permisos por empresa, trabajos, solicitudes, versiones de archivos, revisión documental, tareas, honorarios con pagos parciales, informes publicados, consultas, notificaciones internas y auditoría.

**No está desplegada automáticamente ni certificada para producción.** WhatsApp, pasarelas, SII, extracción documental e IA no están integrados. La recuperación de acceso requiere configurar un transporte de correo real; el modo `log` no envía correos.

## Requisitos

- PHP 8.2 o superior, con PDO MySQL/SQLite, mbstring, XML/DOM, fileinfo, curl, zip y OpenSSL. Usar una versión mantenida y actualizada por el proveedor.
- Composer 2; Node 20.19+ o 22.12+ para compilar (no se requiere Node en producción).
- MySQL 8 o MariaDB compatible. SQLite se admite para pruebas y demostración local.
- HTTPS en producción; cron para generar períodos y procesar colas.

## Instalar

```bash
composer install
npm ci
cp .env.example .env
php artisan key:generate
```

Configurar la base de datos y `APP_URL` en `.env`. Crear la base vacía antes de migrar.

```bash
php artisan migrate
npm run build
php artisan serve
```

Abrir `http://127.0.0.1:8000`. Para desarrollo con recarga, ejecutar `npm run dev` en otra terminal y seguir accediendo desde Laravel.

### Crear el primer estudio real

```bash
php artisan expressconta:setup "Mi Estudio" administrador@miestudio.cl
```

La contraseña se solicita de forma oculta. El comando crea un estudio vacío, su administrador y una plantilla mensual. No carga clientes ficticios. Agregar empresas y accesos desde Configuración.

### Demostración local

Usar una base independiente, `APP_ENV=local` y definir una contraseña propia de al menos 12 caracteres en `DEMO_PASSWORD`. Luego:

```bash
php artisan db:seed --class=DemoSeeder
```

Cuentas demo: `admin@expressconta.test`, `contador@expressconta.test`, `revisor@expressconta.test`, `cliente@expressconta.test`. Todas utilizan el valor elegido de `DEMO_PASSWORD`. No hay una contraseña predeterminada ni se carga el demo en producción. El cliente está asignado a dos de las seis empresas. La carga demo debe ejecutarse una sola vez en una base vacía.

### Alternativa local con SQLite

Crear `database/database.sqlite`; configurar `DB_CONNECTION=sqlite` y `DB_DATABASE` con su ruta absoluta. Luego ejecutar las migraciones. Producción recomendada: MySQL/MariaDB.

## Módulos

| Pantalla | Comportamiento |
|---|---|
| Resumen | Indicadores calculados y trabajo del período |
| Empresas y ficha | Alta, edición, archivo y detalle de trabajos/documentos/cargos |
| Trabajos | Checklist, etapas, motivos y control de versión |
| Documentos | Solicitudes, cargas privadas, versiones y decisiones de revisión |
| Agenda | Tareas por empresa y agrupación por fecha |
| Honorarios | Cargos, pagos parciales, idempotencia por referencia y saldos |
| Informes | Borradores, aprobación/publicación y registro de descargas |
| Consultas | Conversaciones por empresa y notas internas filtradas en servidor |
| Configuración | Estudio, usuarios, asignaciones y servicios recurrentes |
| Notificaciones | Avisos internos por recepción, corrección y publicación |
| Portal del cliente | Solo empresas autorizadas, pendientes y entregas publicadas |

## Verificación

```bash
php artisan test
npm run build
npx playwright install chromium
npm run test:e2e
```

Playwright crea una base **exclusiva de pruebas** en `database/e2e.sqlite`, la reinicia y levanta un servidor local. No utiliza la base configurada en `.env`. Requiere que el puerto 8000 esté libre. Las contraseñas y la clave incluidas en fixtures E2E son públicas y solo sirven para esa base local.

Las pruebas de backend comprueban permisos, aislamiento, documentos privados, transiciones, concurrencia optimista, pagos y recurrencias. Las pruebas de navegador comprueban ingreso, navegación, portal móvil, carga, alta de empresa y protección CSRF. SQLite no reproduce los bloqueos de MySQL: el workflow de CI incluye una ejecución adicional sobre MySQL.

## Recurrencias

Asignar un servicio recurrente en Configuración. Después:

```bash
php artisan expressconta:generate 2026-10
```

Genera un trabajo y un cargo por acuerdo/período. Las reejecuciones no duplican registros. Se omiten empresas archivadas y acuerdos/plantillas inactivos. Las fechas generadas son fechas **internas**, no vencimientos oficiales.

Más detalles: [arquitectura](docs/ARCHITECTURE.md), [despliegue](docs/DEPLOYMENT.md), [alcance y pendientes](docs/STATUS.md).
