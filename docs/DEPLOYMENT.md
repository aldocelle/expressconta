# Despliegue y operación

## Hosting compartido

Comprobar PHP/extensiones, Composer/SSH, MySQL, cron, HTTPS, tamaño de subida y posibilidad de elegir el document root. No se presupone que cualquier plan Hostinger permita todos esos recursos.

1. Subir el código a una carpeta privada de la cuenta. Mantener `.env`, `vendor`, `storage`, `database` y `config` fuera del directorio público.
2. Configurar document root para apuntar a `expressconta/public`.
3. Ejecutar `composer install --no-dev --prefer-dist --optimize-autoloader` usando el lockfile.
4. Compilar con `npm ci && npm run build` localmente/CI y subir `public/build`. Node no se necesita en el servidor.
5. Crear `.env` del servidor con credenciales propias, `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://…`, `SESSION_SECURE_COOKIE=true`, `LOG_CHANNEL=single` y `MAIL_MAILER` correctamente configurado.
6. Generar la clave **solo para la instalación inicial** con `php artisan key:generate`. Conservarla en despliegues y respaldos; no regenerarla en cada versión.
7. Dar permisos de escritura a `storage` y `bootstrap/cache` al usuario PHP, sin usar 777.
8. Ejecutar `php artisan migrate --force`, `php artisan config:cache`, `php artisan route:cache` y `php artisan view:cache`.
9. Crear el primer estudio con `php artisan expressconta:setup "Mi Estudio" correo@dominio.cl`.
10. Verificar login/logout, recuperación por correo, una carga privada y el aislamiento entre dos cuentas antes de incorporar clientes reales.

Si el proveedor fija `public_html`, mantener la aplicación privada fuera de esa carpeta y copiar **solo el contenido de `public/`** allí. Ajustar en ese `index.php` las rutas absolutas a `vendor/autoload.php`, `bootstrap/app.php` y mantenimiento. Configurar también el public path de Laravel para que Vite encuentre `public_html/build/manifest.json`. Esta adaptación depende del layout de la cuenta y debe probarse en ella. No copiar toda la aplicación bajo `public_html`.

## Cron

Usar la ruta PHP real que entregue el proveedor:

```cron
* * * * * cd /ruta/privada/expressconta && /ruta/php artisan schedule:run >> /ruta/privada/expressconta/storage/logs/scheduler.log 2>&1
* * * * * cd /ruta/privada/expressconta && /usr/bin/flock -n /ruta/privada/expressconta/storage/queue.lock /ruta/php artisan queue:work database --stop-when-empty --max-time=50 --tries=3 >> /ruta/privada/expressconta/storage/logs/queue.log 2>&1
```

El scheduler genera los períodos a las 06:00 de Santiago. Los avisos internos actuales son síncronos; la cola queda disponible para trabajos externos posteriores. Confirmar disponibilidad de `flock`; si no existe, usar el mecanismo de exclusión del hosting. No lanzar workers persistentes donde el plan no los soporte.

## Docker local

`docker compose up --build` crea un servicio PHP de desarrollo y MySQL, con un volumen para MySQL. Copiar primero `.env.example` a `.env`, generar una clave y configurar `DB_HOST=db`, `DB_DATABASE=expressconta`, `DB_USERNAME=expressconta` y una contraseña local propia. Composer y los activos se construyen dentro de la imagen. Después ejecutar las migraciones y el setup con `docker compose exec app php artisan …`.

El Dockerfile/Compose son un entorno local, no una configuración de producción.

## Respaldos

Respaldar como un conjunto:

- Dump consistente de MySQL/MariaDB.
- `storage/app/private`, conservando las rutas.
- `.env` y APP_KEY en un almacén seguro fuera del repositorio.
- Versión del código y lockfiles.

Evitar pasar contraseñas en argumentos de comandos. Usar un archivo de opciones de MySQL con permisos restringidos o el mecanismo de secretos del proveedor. Definir retención, cifrado y una copia fuera del hosting.

Restaurar primero en un entorno aislado: instalar la versión de código, restaurar base y archivos, recuperar APP_KEY/configuración, limpiar cachés y comprobar conteos, usuarios y descarga de documentos. Comprobar nuevamente permisos tras restaurar. El repositorio documenta el procedimiento; no administra automáticamente backups.

## Actualizaciones

Respaldar antes de migrar. Preservar `.env` y documentos, usar mantenimiento si la migración lo requiere y evitar sobrescribir storage con la carpeta vacía del despliegue. Mantener registro de migraciones. `migrate:rollback` no sustituye un respaldo: ciertas migraciones pueden eliminar información.

## Vercel: interfaz y proxy al backend PHP

La configuración `vercel.json` ejecuta `npm run build:vercel`. Produce una SPA estática y rutas mediante Build Output API v3. No instala Composer ni intenta ejecutar Laravel en Vercel.

1. Desplegar Laravel/MySQL/archivos privados en un servidor PHP con HTTPS.
2. Conectar este repositorio a Vercel, con raíz en el repositorio y preset Other. La configuración del repo define la compilación.
3. Definir `BACKEND_ORIGIN=https://backend.dominio.cl` en las variables de Vercel y volver a desplegar. Debe ser solo un origen, sin rutas ni credenciales. No es una clave secreta.
4. Las rutas `/api/*` y `/sanctum/*` se reescriben al backend, manteniendo al navegador bajo el dominio de Vercel. La SPA conserva sus rutas propias.
5. En Laravel: `SESSION_DOMAIN=null`, `SESSION_SECURE_COOKIE=true`. No fijar cookies al dominio del backend. Configurar `FRONTEND_URL=https://frontend.vercel.app` para los enlaces de recuperación de contraseña.
6. Probar de extremo a extremo login, CSRF, cookies, carga, descarga y recuperación en los dominios reales. Respetar los límites de tamaño y duración del proxy de Vercel/hosting.

Si falta `BACKEND_ORIGIN`, se puede publicar la interfaz, pero mostrará **servicio pendiente de conexión**. No acepta ingresos, simula clientes ni guarda datos. Las rutas de API devuelven 503. No confundir ese despliegue con el sistema operativo.

La alternativa PHP serverless requiere una adaptación distinta: runtime comunitario, almacenamiento externo, sesiones compartidas, base de datos remota y planificación de tareas. Este repositorio conserva el backend PHP con disco privado persistente y no lo presenta como compatible sin cambios con ese entorno.
