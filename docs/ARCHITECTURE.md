# Arquitectura y reglas

## Aplicación

Laravel entrega una SPA Vue desde `resources/views/app.blade.php`. Vite compila sus activos a `public/build`; Vue Router resuelve navegación. Las rutas JSON se ubican bajo `/api`, dentro del grupo `web`: sesión, cookies y CSRF están activos. Sanctum autentica mediante el guard web, sin exponer tokens en localStorage. La recuperación de contraseña usa el broker de Laravel. El middleware de Sanctum invalida sesiones cuando cambia el hash de contraseña.

## Aislamiento

El usuario selecciona únicamente un estudio donde tiene membresía. El ID activo reside en la sesión. `StudyContext` valida esa membresía en cada request y limita las consultas a las empresas autorizadas. Los administradores acceden al estudio completo; contadores, revisores y clientes necesitan asignación a empresas. No se acepta el estudio del payload para crear registros.

La autorización explícita complementa las Policies. No hay una API genérica para actualizar todos los campos. La asignación de usuarios valida pertenencia al estudio y conserva al menos un administrador. Un usuario puede pertenecer a varios estudios y las asignaciones se modifican solo dentro del estudio activo.

## Persistencia

- `studies`, `users`, `study_memberships`, `company_memberships`: identidades y permisos.
- `companies`: empresas, contacto principal y referencia del servicio.
- `work_templates`, `engagements`, `work_items`, `tasks`: plantillas, acuerdos y trabajo.
- `document_requests`, `file_versions`, `document_reviews`: solicitudes, archivos e historial.
- `charges`, `payments`: cargos y pagos asignados a un cargo concreto.
- `reports`, `deliveries`: informes y eventos de publicación/descarga.
- `conversations`, `messages`: comunicación y notas internas.
- `notifications`, `audit_events`: avisos y trazabilidad.

Un acuerdo de servicio incluye el honorario recurrente. Los pagos se asignan a un cargo: todavía no hay distribución de una transferencia entre varios cargos ni contabilidad general.

## Flujos

Trabajos: por iniciar → esperando antecedentes → preparación → revisión → aprobación → entrega. Hay transiciones de ajustes, cancelación y reapertura con motivos y permisos. La preparación y etapas posteriores requieren resolver los antecedentes obligatorios. Revisión y aprobación exigen completar el checklist. La entrega requiere un informe aprobado y publicado.

Solicitudes: pendiente → recibido → en revisión → aceptado/corrección. “No aplica” necesita un administrador o revisor y motivo. Se conserva la revisión de cada archivo. La fecha vencida es una condición derivada, no reemplaza el estado de revisión. El servidor mantiene `version` en trabajos y solicitudes: una decisión sobre una versión antigua devuelve 409.

Los cambios financieros y de flujo utilizan transacciones y bloqueos de fila en MySQL. Los cargos recurrentes y trabajos tienen unicidad por acuerdo/período. Los pagos se deduplican por cargo/referencia; repetir una referencia con otros datos devuelve 409. Se impiden sobrepagos.

## Archivos e informes

Los archivos se guardan fuera de `public`, en `storage/app/private`. `FileVersion.path` no se serializa. La descarga valida usuario, estudio y empresa. Los informes del cliente requieren publicación y aprobación. Las descargas se sirven como adjuntos, con `nosniff` y caché privada deshabilitada. No se debe publicar un symlink al disco privado.

Cada nueva carga conserva las versiones anteriores de la solicitud. Cada nueva versión de informe se crea como otro informe del mismo trabajo, manteniendo las entregas previas. No hay OCR, antivirus ni validación semántica automática del contenido en esta versión.

## Tiempo y dinero

Los instantes se almacenan en UTC y se muestran en America/Santiago. Los períodos son `YYYY-MM`; las fechas internas no llevan hora. CLP se almacena como enteros. Los honorarios están separados de obligaciones tributarias.

## Notificaciones

Los eventos de carga, corrección y publicación generan avisos internos de base de datos. Su listado vuelve a comprobar empresa autorizada, incluso si una asignación fue revocada. Los recordatorios documentales son borradores: no se envían por WhatsApp o correo. No se almacenan claves de SII, ClaveÚnica ni bancos.
