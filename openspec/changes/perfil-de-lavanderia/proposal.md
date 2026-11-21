## Why

El Módulo A pide un "Perfil de la lavandería" con "Fotos, descripción, servicios, precios por kilo, calificación y
reseñas" y una "Selección de servicio" con "Tipos: lavado estándar, lavado con planchado y lavado en seco"
(`NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3`, Módulo A #5 y #6). Las fotos de las lavanderías se guardan "en un
servicio de almacenamiento en la nube" (`NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3`). Es el tramo del recorrido que
se acepta después de "elegir lavandería" (`NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3`).

## What Changes

- El perfil de una lavandería activa en el servidor: nombre, descripción, fotos, servicios con su precio por kilo,
  calificación promedio, número de calificaciones y reseñas.
- Las fotos en el almacenamiento privado del entorno, entregadas con URLs firmadas que vencen.
- Los servicios que ofrece cada lavandería, de entre los tres del contrato, con su precio y su unidad como datos.
- Las tablas de la lavandería que el modelo de datos pone aquí (ADR-0016, C-193): los catálogos `tipos_servicio` y
  `unidades_medida`, `lavanderias` (con las de prueba), `lavanderia_servicios`, `lavanderia_horarios`,
  `lavanderia_fotos` y la base de `calificaciones`.
- El horario de cada lavandería por día de la semana (`lavanderia_horarios`), que ninguna spec creaba. En este cambio
  es de prueba y el perfil no lo muestra.
- Cada calificación es de la lavandería o del repartidor, exactamente uno, y el perfil cuenta solo las de la
  lavandería. Si el usuario califica al repartidor sigue en DD-052.
- En la app: la pantalla del perfil y la selección de un tipo de servicio, que queda guardada para el paso de agenda.
- Todo con lavanderías y fotos de prueba, sembradas; el alta real llega en H5.

**Qué NO cambia**

- Subir fotos desde el teléfono, con cámara o galería: `alta-de-lavanderias` (F-B01).
- Quién fija y quién edita el precio (DD-010, BR-46): `alta-de-lavanderias` y `administracion`.
- El precio estimado y el resumen del pedido (DD-012): `resumen-y-confirmacion`.
- Crear calificaciones y a quién se califica (DD-052): `historial-y-calificacion`.
- La fecha y la hora de recolección: `agenda-de-recoleccion`.

**Fuera del contrato**

- Cobrar por prenda o con un tabulador por prenda (guía de la reunión y libreta de precios): el contrato dice "precios
  por kilo" (`:16:A.3`, Módulo A #5). DD-010.
- El lavado de calzado: el contrato fija tres tipos (`:16:A.3`, Módulo A #6). DD-046, contractual.
- La foto y la descripción de la ropa al pedir (formulario del 2026-11-05): no está en el Módulo A. DD-100.
- Un beneficio por llevar más prendas (video del 2026-11-14): el contrato no lo nombra. DD-076.
- El distintivo de lavandería fundadora (kit para sumar lavanderías): el contrato no lo nombra. DD-122.
- El horario y la distancia dentro del perfil (mock A05): el Módulo A #5 no los enumera; el horario se captura en el
  Módulo B (#1 y #6). Si DD-083 hace del horario parte de "disponibilidad", se muestra el estado, no el horario
  completo. Se sumó a DD-083.

## Capabilities

### New Capabilities

- `perfil-lavanderia`: el perfil de la lavandería con fotos, descripción, servicios, precios por kilo, calificación y
  reseñas, y la selección del tipo de servicio.

### Modified Capabilities

Ninguna.

## Impact

- **`servidor/`:** la migración de `tipos_servicio`, `unidades_medida`, `lavanderias`, `lavanderia_servicios`,
  `lavanderia_horarios`, `lavanderia_fotos` y `calificaciones`; la consulta del perfil; el disco S3 privado y las URLs
  firmadas; el sembrado de prueba.
- **`movil/`:** la pantalla del perfil y la de selección de servicio; el pedido en curso guardado en el teléfono.
- **Base:** siete tablas nuevas, con las columnas de `vault/arquitectura/modelo-de-datos.md`; las cinco de operación,
  con plaza (`plazas-y-zonas`). `lavanderias-cercanas` lee `lavanderias` y `calificaciones`.
- **Dependencias:** el adaptador S3 de Flysystem para el `Storage` de Laravel (ADR-0006). Sin dependencias nuevas en la
  app.
- **ADRs:** ADR-0003, ADR-0006, ADR-0012 y ADR-0016.
