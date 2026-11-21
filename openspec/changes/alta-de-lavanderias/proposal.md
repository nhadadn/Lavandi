## Why

El contrato pide que la lavandería se registre en la app con "Nombre, dirección, foto, servicios, precios y horario"
(`NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo B #1`), que edite su información y sus "ventanas de
disponibilidad" (`NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo B #6`), y que la administración tenga "Alta,
baja, suspensión y edición de socios" (`NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #1`). Hasta el hito H5
las lavanderías son de prueba, sembradas en la base; sin este cambio no hay lavanderías reales que reciban pedidos.

## What Changes

- **Registro de la lavandería desde la app** (Módulo B #1): nombre, dirección, foto, cuáles de los tres tipos de
  servicio ofrece con su precio por kilo, y su horario por día de la semana.
- **Plaza de la lavandería:** el servidor geocodifica la dirección una vez y le asigna la plaza de la zona que la
  contiene. Fuera de las zonas del territorio inicial no se registra (`NSV-2026-001-v8.pdf:4:Cláusula I`).
- **Perfil y horarios desde la app** (Módulo B #6): la lavandería edita esa información y define sus ventanas de
  disponibilidad de una hora por día de la semana.
- **Gestión de lavanderías en el panel web** (Módulo D #1, Filament): edición, suspensión y su levantamiento, y baja
  sin borrar el historial. El alta queda especificada y bloqueada por DD-053.
- **Escenarios escritos y bloqueados:** la entrada al registro y el alta (DD-053), el lavado en seco y las reglas del
  precio (DD-010), el cupo por ventana (BR-17), las ventanas fuera del horario (DD-078) y los pedidos en curso de una
  lavandería suspendida o dada de baja (DD-104).

**Qué NO cambia**

- El componente de autorización y la suite contra fugas: viven en `autorizacion-por-rol`. Aquí solo se usan.
- El almacenamiento y el servicio de las fotos (F-T06, ADR-0006): son de `perfil-de-lavanderia`. Aquí solo se suben.
- Las plazas y las zonas de servicio (`plazas-y-zonas`) y la geocodificación de direcciones (`direcciones`).
- El perfil que ve el usuario (`perfil-de-lavanderia`) y la búsqueda de lavanderías cercanas, con la definición de
  "activa" de DD-083 (`lavanderias-cercanas`).
- La agenda del usuario: cómo se le ofrecen las ventanas de una hora (`agenda-de-recoleccion`).
- El alta de repartidores (F-D02, `repartidor`), las comisiones por socio (F-D05, `administracion`) y la vista
  reducida de administración en la app (C-140, `administracion`).
- El historial y las estadísticas de la lavandería (`historial-de-la-lavanderia`).
- El formulario de interés de lavanderías de la landing (F-L05, `landing`).
- El registro no pide datos fuera de la lista del Módulo B #1. Si DD-022 decide que la lavandería entrega con su
  propia gente, se revisa con su respuesta; hoy no se agrega ningún campo.

**Fuera del contrato** (posible Orden de Cambio, `NSV-2026-001-v8.pdf:8:Cláusula IV`)

- Lavado de calzado o cualquier tipo de servicio fuera de los tres del contrato (DD-046). El registro solo admite
  "lavado estándar, lavado con planchado y lavado en seco" (`NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #6`).
- Cobrar por prenda o con tabulador por prenda: el contrato dice "precios por kilo"
  (`NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #5`). DD-010.
- El distintivo de "lavandería fundadora" del kit para sumar lavanderías: el contrato no lo nombra. DD-122.
- Verificar o certificar a las lavanderías, o un estándar de cuidado dentro de la app (DD-047).
- **Exclusión expresa, no Orden de Cambio:** dar de alta las lavanderías reales. El reclutamiento es de EL CLIENTE
  (`NSV-2026-001-v8.pdf:42:ANEXO C, Sección C.8`) y las "altas de socios" están fuera del soporte
  (`NSV-2026-001-v8.pdf:24:ANEXO A, Sección A.7`). Este cambio construye la función; el alta de cada lavandería la
  opera EL CLIENTE.

## Capabilities

### New Capabilities

- `alta-lavanderias`: registro de la lavandería desde la app, su perfil y sus ventanas de disponibilidad, y la gestión
  de lavanderías desde el panel web: alta, edición, suspensión y baja.

### Modified Capabilities

Ninguna.

## Impact

- **`servidor/`:** rutas de la API para el registro, el perfil y las ventanas de la lavandería, con Sanctum
  (ADR-0005); recurso de Filament para gestionar lavanderías, con sus acciones de suspensión y baja (ADR-0003);
  validación de la dirección contra las zonas con PostGIS (ADR-0004) y una sola geocodificación por dirección
  (ADR-0009); subida de la foto al almacenamiento S3 privado (ADR-0006).
- **`movil/`:** las pantallas de registro y de perfil y horarios del Módulo B, con la toma o elección de la foto y su
  compresión antes de subirla (ADR-0006). Es la primera pantalla de la app que sube una foto.
- **Base de datos** (ADR-0016, `vault/arquitectura/modelo-de-datos.md`): completa `lavanderias`, que nace en
  `perfil-de-lavanderia`, con `estado` (`pendiente`, `activa`, `suspendida`, `baja`) y `aprobada_en`. El horario y las
  ventanas van en `lavanderia_horarios`, los servicios en `lavanderia_servicios` y la foto en `lavanderia_fotos`, las
  tres de `perfil-de-lavanderia`. La plaza sale de las `zonas` de `plazas-y-zonas`.
- **Dependencias:** ninguna nueva en el servidor. En la app, una librería para tomar, elegir y comprimir la foto, que
  ningún ADR nombra (ver `design.md`, preguntas abiertas).
- **ADRs:** ADR-0003, ADR-0004, ADR-0005, ADR-0006, ADR-0009 y ADR-0013 (el tema del panel).
- **Cambios de los que depende:** `modelo-de-datos`, `plazas-y-zonas`, `direcciones`, `perfil-de-lavanderia`,
  `lavanderias-cercanas`, `resumen-y-confirmacion` y `autorizacion-por-rol`.
