## Context

- F-T14 sale del A.5: "Descarga de reportes en formato CSV y Excel para cualquier rango de fechas"
  (`NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5`). Lo acredita el criterio A44-2.
- La exportación de Filament trae CSV y XLSX de fábrica, corre en colas con lotes de trabajos y deja la protección
  contra fórmulas inyectadas para activarla a mano (ADR-0003).
- Las cifras las calculan `indicadores` y `administracion`. Este cambio no calcula nada: descarga lo que ya muestra el
  panel.

## Goals / Non-Goals

**Goals:**

- Los 14 reportes en los dos formatos, con las mismas cifras que el panel.
- Archivos que se abren bien en Excel y que no ejecutan nada.
- Rangos grandes sin bloquear el panel.

**Non-Goals:**

- Otros formatos, el envío por correo o programado, y descargar desde la app.
- Columnas o cifras que el panel no muestra.

## Decisions

No hay decisión técnica nueva. Se aplica el ADR-0003:

- **Un exportador de Filament por reporte,** que lee la misma consulta del indicador o del reporte financiero. No hay
  una segunda fuente de cifras.
- **Excel es XLSX,** el formato que Filament trae de fábrica.
- **La descarga corre en cola** y avisa con una notificación del panel. Que Filament 5 solo deje descargar el
  archivo a quien lo pidió es grado E, `SIN VERIFICAR`: lo comprueba el test de exportacion-E11.
- **La protección contra fórmulas** se activa en la escritura del CSV y del XLSX, solo en las celdas de texto.

**Lecturas del contrato que hizo el especificador.** Arthur las confirma al aprobar:

1. **"Reportes"** son los 11 indicadores del A.5 y los tres reportes financieros del Módulo D #6.
2. **"CSV y Excel"** del A.5 manda sobre "CSV o Excel" del A.4.4: se construyen los dos.
3. **"Cualquier rango de fechas"** no tiene tope de duración.

## Tablas y migración

No crea tablas del negocio ni migración propia (`vault/arquitectura/modelo-de-datos.md`, ADR-0016). Cada archivo sale de
la consulta de `indicadores` o de `administracion`, que leen las tablas que nombra el `design.md` de cada uno. De las
que crean esos dos cambios, entran a un archivo `actividad_usuarios` (`indicadores`) y `comisiones` (`administracion`).
La descarga de la conciliación espera `movimientos_pasarela`, que nace con DD-112.

La exportación usa además tablas del framework:

- `job_batches`, para la cola y los lotes. Ya está en la lista de excepciones de `servidor/config/modelo.php`
  (`modelo-de-datos`, decisión 2).
- `exports`, de Filament, y `notifications`, de Laravel, para el aviso de archivo listo. **No están en esa lista.**
  Sus migraciones de fábrica usan `id`, `user_id` y `notifiable_id`, que la prueba de esquema rechaza (modelo-E01 y
  modelo-E02), y `exports` apunta a `users`, que aquí se llama `usuarios` (grado E, `SIN VERIFICAR` en Filament 5).
  Ver Open Questions.

## Risks / Trade-offs

- [El hosting no da un proceso de colas (DD-070)] → en local corre con el proceso de colas de Laravel; antes del
  entorno de prueba, el hosting elegido debe darlo.
- [Un archivo pasa del límite de filas de Excel, 1,048,576 por hoja (E, `SIN VERIFICAR`)] → con el volumen de
  arranque no ocurre: los reportes son agregados por periodo. Si ocurre, se decide cómo partir el archivo.
- [Indicadores y reportes bloqueados] → su descarga espera al escenario que los libera; no se exporta una cifra que
  el panel todavía no tiene.
- [El escape de fórmulas convierte un número negativo en texto] → el escape solo va en celdas de texto
  (exportacion-E09).

## Open Questions

1. **Cómo se logra que Excel lea bien los acentos del CSV.** Recomendación de Claude: UTF-8 con marca de orden de
   bytes (BOM). Lo decide Arthur.
2. **En qué disco se guardan los archivos generados y cuánto tiempo.** El ADR-0006 es para fotos y evidencias. Lo
   decide Arthur; si cambia el almacén, necesita ADR.
3. **`exports` y `notifications` frente a la prueba de esquema.** Hay que agregarlas a la lista de tablas del
   framework en `servidor/config/modelo.php`, y que `exports` apunte a `usuarios.id_usuarios`. La lista es de
   `modelo-de-datos`; lo decide Arthur allí, antes de la tarea 1.1, porque toda exportación de Filament escribe en
   `exports`.
