## Why

El contrato manda operar en el TERRITORIO INICIAL —todos los municipios del Estado de Durango y los de Torreón y
Matamoros, Coahuila— y diseñar la arquitectura para crecer a plazas adicionales de la República "sin necesidad de
reescritura del sistema ni de rediseño de la base de datos" (`NSV-2026-001-v8.pdf:4:Cláusula I`). Abrir una ciudad
nueva tampoco debe exigir modificar "su programación" (`NSV-2026-001-v8.pdf:37:ANEXO C, Sección C.3`). Va ahora
porque las lavanderías del hito H2 nacen con su plaza, y agregar la plaza después sería el rediseño que el contrato
prohíbe.

## What Changes

- La ficha de la plaza: un área operativa con nombre, zona horaria y estado (activa o no), sin atarla a un estado ni a
  un municipio.
- Las zonas de servicio como polígonos de PostGIS, cada una de su plaza.
- Resolver si un punto cae en una zona de una plaza activa, y en cuál.
- Abrir una plaza es cargar un archivo de datos con un comando: sin migraciones de esquema ni código nuevo.
- La tabla de parámetros de la plaza (`parametros_plaza`), que el modelo de datos pone aquí (ADR-0016) y nace vacía:
  cada regla comercial abierta que cambia por plaza será una fila, con su vigencia, cargada por el cambio que la use.
- Una prueba de esquema que exige la plaza, obligatoria, en toda tabla de operación, sobre la misma lista de tablas
  sin plaza de `modelo-de-datos`.
- La carga del territorio inicial como datos, que espera dos respuestas (ver `design.md`, Open Questions).

**Qué NO cambia**

- El usuario no lleva plaza: la lleva su domicilio (`vault/arquitectura/modelo-multi-plaza.md`).
- No hay pantalla de zonas en el panel: el Módulo D no la enumera (`NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3`).
- La comisión y los precios nacen con plaza en sus propios cambios (`administracion`, `perfil-de-lavanderia`), no
  aquí. El envío y las demás reglas comerciales abiertas serán filas de `parametros_plaza`, que nace aquí vacía; las
  carga y las valida el cambio que las usa (`agenda-de-recoleccion`, `resumen-y-confirmacion`).
- El folio único por plaza (BR-21) va en `resumen-y-confirmacion`.
- Los respaldos de la base van con el hosting, en `publicacion` (DD-070).
- La app (`movil/`) no cambia.

**Fuera del contrato**

- Activar plazas fuera del territorio inicial: es Orden de Cambio (`NSV-2026-001-v8.pdf:4:Cláusula I` y
  `NSV-2026-001-v8.pdf:36:ANEXO C, Sección C.1`). El modelo lo permite; la operación no lo hace.
- Moneda por plaza: `modelo-multi-plaza.md` la propone por una expansión a otros países (C-45), pero la Cláusula I
  habla de "plazas adicionales de la República Mexicana". No se construye. Sin fila.
- Una pantalla para dibujar o editar zonas en el panel: el Módulo D no la tiene. Las zonas se cargan como datos.
  Sin fila.

## Capabilities

### New Capabilities

- `plazas`: la ficha de la plaza, sus zonas de servicio, la resolución de un punto, la apertura de una plaza con datos
  y la regla de que toda fila de operación lleva su plaza.

### Modified Capabilities

Ninguna.

## Impact

- **`servidor/`:** la migración de `plazas`, `zonas` (con índice espacial) y `parametros_plaza`; sus modelos; el
  comando que carga una plaza desde un archivo; la prueba de esquema de la plaza obligatoria; el sembrado de plazas de
  prueba.
- **`movil/`:** sin cambios.
- **Base:** tres tablas nuevas, con las columnas de `vault/arquitectura/modelo-de-datos.md`. PostGIS ya queda
  habilitado en `base-tecnica` (ADR-0004).
- **Dependencias:** ninguna nueva si las consultas espaciales se escriben con las funciones de PostGIS. Un paquete de
  tipos espaciales para Eloquent sería una decisión aparte (ver `design.md`).
- **ADRs:** ADR-0004 (PostgreSQL con PostGIS), ADR-0009 (zonas como polígonos propios), ADR-0012 (datos de prueba
  por entorno) y ADR-0016 (el modelo de datos).
