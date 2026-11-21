## Why

El contrato pide una base de datos con "usuarios registrados, lavanderías, repartidores, pedidos, pagos e historial
completo" (`NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3`), que nunca guarde los números de tarjeta (`:20`) y que
permita crecer a "plazas adicionales de la República Mexicana sin necesidad de reescritura del sistema ni de rediseño
de la base de datos" (`NSV-2026-001-v8.pdf:4:Cláusula I`). El ADR-0016 fijó el modelo que lo cumple, y Arthur lo
aprobó el 2026-11-20 (C-193). Este cambio convierte sus reglas comunes en requisitos que una prueba de esquema
verifica, para que cada uno de los demás cambios cree sus tablas sobre las mismas reglas. Va con `base-tecnica`, antes
de `registro-e-inicio-de-sesion`, que crea las primeras tablas.

## What Changes

- Las reglas comunes del modelo se vuelven requisitos verificables (ADR-0016, `vault/arquitectura/modelo-de-datos.md`):
  - **Nombres:** tablas y columnas en español, `snake_case` y minúsculas. Todo identificador empieza con `id_`. La
    llave primaria es `id_<tabla>`; la foránea, el nombre de la llave a la que apunta; el público, `id_publico`; los
    de la pasarela, `id_pasarela_<cosa>` (C-190, C-194).
  - **Plaza:** toda tabla de operación lleva `id_plazas`, con llave compuesta en las hijas (Cláusula I, p. 4).
  - **Tipos:** dinero en `numeric(12,2)`, horas en `timestamptz` y mapas en `geography`.
  - **Integridad:** llaves foráneas con índice y `ON DELETE RESTRICT`.
  - **Historial completo:** pedidos, pagos y eventos no se borran (p. 20).
  - **Datos de tarjeta:** ninguna columna los guarda (p. 20).
  - **Pagos:** ningún nombre de tabla ni de columna nombra a un proveedor (C-192, DD-079).
- Se crea una prueba de esquema que recorre la base después de migrar y falla si una tabla rompe esas reglas. Corre
  en la integración continua desde que existe la primera tabla.
- Se fija cómo se escribe una migración y un modelo de Eloquent con estos nombres: `$table`, `$primaryKey`, las
  llaves de cada relación y el ULID público.

**Qué NO cambia:**

- No se crea ninguna tabla del negocio. Cada cambio crea las suyas con su migración; la tabla de quién crea qué está
  en `vault/arquitectura/modelo-de-datos.md`, columna "Nace en".
- No se integra ninguna pasarela: Conekta, la del contrato, entra con el cambio `pago`; el posible cambio de pasarela
  es DD-079.
- Las tablas del framework conservan su nombre (`migrations`, `jobs`, `cache`, `sessions`,
  `personal_access_tokens`): la prueba de esquema las excluye.

**Fuera del contrato:**

- Nada. Las reglas sostienen lo que el contrato pide en las pp. 4 y 20; no agregan funciones.

## Capabilities

### New Capabilities

- `modelo-de-datos`: las reglas comunes que toda tabla del negocio cumple (nombres, plaza, tipos, integridad,
  historial, datos de tarjeta y pasarela sin proveedor), verificadas por una prueba de esquema.

### Modified Capabilities

- Ninguna. No hay specs aceptadas todavía en `openspec/specs/`.

## Impact

- **`servidor/`:** una prueba de esquema en `tests/Feature/Modelo/`, que lee `information_schema` y los catálogos de
  PostgreSQL, y una guía corta para escribir migraciones y modelos con estos nombres.
- **Los demás cambios:** sus migraciones deben pasar esta prueba. Su `design.md` nombra las tablas que crean y su
  `tasks.md`, la migración.
- **ADRs:** ADR-0016 (el modelo), ADR-0004 (PostgreSQL con PostGIS) y ADR-0003 (Laravel).
- **Integración continua:** la prueba corre en el trabajo `servidor` de `ci.yml`.
