## Why

El A.5 cierra con una obligación: "Descarga de reportes en formato CSV y Excel para cualquier rango de fechas" (`NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5`). Su criterio de aceptación es "Reportes exportables generados correctamente en formato CSV o Excel" (`NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.4`). Los reportes financieros del Módulo D también son reportes: "Ingresos por periodo, comisiones y conciliación con Conekta" (`NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3`, Módulo D #6).

## What Changes

- **Descarga en CSV y en Excel (XLSX)** de cada reporte: los 11 indicadores del A.5 y los tres reportes financieros (ingresos, comisiones y conciliación con la pasarela: Conekta mientras el cliente no firme el cambio, DD-079). Se construyen los dos formatos: el A.5 dice "CSV y Excel", aunque el A.4.4 acepte "CSV o Excel".
- **Cualquier rango de fechas,** con los dos extremos incluidos y sin tope de duración.
- **Archivos que se abren bien:** el CSV conserva acentos y eñes en Excel, y ningún texto se escribe como fórmula (ADR-0003).
- **Generación en segundo plano,** con aviso en el panel cuando el archivo está listo. Solo lo descarga quien lo pidió.
- El archivo trae las mismas cifras que el panel, porque sale de la misma consulta del indicador o del reporte.

**Qué NO cambia:**

- Los indicadores (`indicadores`) y los reportes financieros (`administracion`): aquí solo se descargan, y ninguna cifra se calcula de otra forma.
- La descarga de un indicador o reporte que tiene escenarios con `SIN RESPUESTA` espera a que ese escenario se libere.

**Fuera del contrato** (posibles Órdenes de Cambio, `NSV-2026-001-v8.pdf:8:Cláusula IV`). Nadie ha pedido nada fuera del A.5 para la descarga. No se construyen, porque el contrato no los pide: otros formatos, como PDF; el envío de reportes por correo o programado; ni descargas desde la app.

## Capabilities

### New Capabilities

- `exportacion`: la descarga en CSV y en Excel de los reportes del panel, para cualquier rango de fechas.

### Modified Capabilities

Ninguna.

## Impact

- **`servidor/`:** un exportador de Filament por reporte, que lee la misma consulta que el panel; el formulario de rango; la escritura segura del CSV y del XLSX; la cola y el aviso de archivo listo. Tests en `servidor/tests/Feature/Exportacion/`.
- **`movil/`:** nada.
- **Base de datos:** no crea tablas del negocio. Lee lo que leen `indicadores` y `administracion`. Usa las tablas del framework que pide la exportación de Filament (`exports` y `job_batches`) y las notificaciones del panel (`notifications`); `exports` y `notifications` todavía no están en la lista de excepciones de la prueba de esquema de `modelo-de-datos` (ver `design.md`, sección "Tablas y migración").
- **Almacenamiento:** los archivos generados, mientras se descargan. En qué disco viven es una pregunta abierta de
  `design.md`; el ADR-0006 es para fotos y evidencias.
- **Hosting:** hace falta un proceso de colas corriendo en el entorno de prueba y en el de producción (DD-070).
- **Dependencias:** ninguna fuera de los ADR: la exportación de Filament, con CSV y XLSX (ADR-0003).
- **Cambios de los que depende:** `indicadores` y `administracion`.
- **ADRs:** ADR-0003 y ADR-0016 (modelo de datos); el hosting, DD-070.
