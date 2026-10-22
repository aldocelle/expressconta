# Alcance de la primera versión

## Implementado

- SPA responsive con diez rutas de trabajo y acceso de cliente.
- Autenticación, recuperación por broker y configuración de transporte de correo.
- Estudios, empresas, responsables y asignaciones.
- Alta, edición y archivo de empresas.
- Trabajos manuales y mensuales recurrentes desde acuerdos/plantillas.
- Checklist, transición de etapas y motivos.
- Solicitudes, cargas privadas, versiones y revisión.
- Tareas y agenda agrupada por fecha.
- Honorarios, cargos, pagos parciales y saldos.
- Informes borrador, aprobación/publicación y registro de descarga.
- Consultas y notas internas filtradas en backend.
- Avisos internos y auditoría.
- Setup sin demo, seed explícito, documentación y pruebas.

## Límites actuales

- No hay libro contable, F29, emisión de facturas tributarias, remuneraciones calculadas ni conciliación bancaria.
- No hay conexiones a SII, TGR, DT, Previred, WhatsApp, pasarelas o IA/RAG.
- Los recordatorios son borradores, sin envío automático.
- El calendario es una agenda agrupada por fecha; no implementa grilla mensual ni arrastrar y soltar.
- El frontend consume listas completas de trabajos/documentos del alcance autorizado; para carteras grandes corresponde agregar paginación en esos módulos. Empresas sí tiene paginación.
- Contacto principal y servicio de referencia viven en la ficha; el catálogo avanzado de contactos/servicios, dependencias de tareas y recurrencias de tareas individuales quedan pendientes.
- Los ajustes/reversas de pagos y distribución entre cargos requieren un módulo posterior; los pagos registrados no se borran ni editan desde la interfaz.
- Los informes se versionan creando una nueva entrega del mismo trabajo, sin edición de archivos publicados.
- No hay importación/exportación CSV ni administración de almacenamiento S3 en esta entrega.
- No hay MFA, antivirus/OCR, políticas de retención automáticas, monitoreo ni backup gestionado.
- La verificación en este entorno utiliza SQLite y navegador local; MySQL debe verificarse con CI o servidor real antes de uso productivo.

Estas limitaciones se mantienen explícitas en lugar de presentar funciones simuladas como reales.
