## Context

- Es el primer cambio del hito H7. Lee los pedidos que crean `resumen-y-confirmacion` y `pago` y que mueve
  `estados-del-pedido`, y pasa por el componente de autorización de `autorizacion-por-rol` (`CLAUDE.md` §9.5).
- La calificación vive en el componente `calificaciones` de `vault/arquitectura/c3-api.md`, sobre la tabla
  `calificaciones` del modelo de datos (ADR-0016). El mismo componente alimenta el perfil de la lavandería (F-A05), los
  historiales de los socios (F-B04 y F-C04) y el indicador F-I07.
- No hay decisión técnica nueva: la app es la de ADR-0002, la API la de ADR-0003, la base la de ADR-0004 y la sesión
  la de ADR-0005.

## Goals / Non-Goals

**Goals:**

- Dejar implementables, sin respuestas pendientes, historial-E01 a E04, E06 a E09, E12 y E13.

**Non-Goals:**

- Lo que bloquean BR-25 (E05 y E10) y DD-052 (E11 y E14).
- Cómo aparece en la lista un pedido cancelado o rechazado: lo resuelve `estados-del-pedido` (BR-22).
- El promedio y las reseñas del perfil de la lavandería: `perfil-de-lavanderia`.

## Decisions

Tres lecturas del contrato que fijan los escenarios. No son decisiones técnicas nuevas; Arthur las aprueba con la spec.

1. **La fecha del filtro es la del resumen del pedido.** El contrato llama "fecha" a la que muestra el resumen
   (Módulo A #9), que es la de la recolección elegida en el #7. Los días se cuentan en la zona horaria de la plaza
   del pedido, como manda `vault/arquitectura/modelo-multi-plaza.md` (las horas se guardan como instante absoluto y se
   presentan en la zona de la plaza). Alternativa descartada: la fecha en que se creó el pedido, que el contrato no
   nombra.
2. **Mientras DD-052 siga abierta, se construye la lectura literal:** una "calificación del servicio" por pedido
   (Módulo A #14), guardada como calificación de la lavandería del pedido (historial-E12). Si DD-052 suma al
   repartidor, es otra fila de `calificaciones` con `id_repartidores`, sin tocar la tabla (historial-E14); la pantalla
   de una o dos calificaciones entra como un cambio con `MODIFIED` sobre esta spec.
3. **E06 a E09 parten de un pedido Entregado.** Es el caso que vale con cualquier respuesta a BR-25; los demás estados
   quedan en E10.

## Tablas y migración

Fuente: `vault/arquitectura/modelo-de-datos.md` ("Nace en") y ADR-0016. Este cambio **no crea tablas: completa
`calificaciones`**, que nace en `perfil-de-lavanderia` para mostrar la calificación y las reseñas de cada lavandería.

**`calificaciones`** (la completa este cambio). Sus columnas, como en el modelo: `id_calificaciones`, `id_pedidos`,
`id_plazas`, `id_usuarios`, `id_lavanderias`, `id_repartidores`, `estrellas`, `comentario`.

- **Lo que agrega esta migración:**
  - la llave compuesta `(id_pedidos, id_plazas)` → `pedidos(id_pedidos, id_plazas)`, con su índice y
    `ON DELETE RESTRICT`: `calificaciones` nació antes que `pedidos`, sin esa llave (diseño de `modelo-de-datos`,
    decisión 6);
  - `id_lavanderias` pasa a opcional si la base la creó obligatoria: desde aquí, lo que obliga es el `CHECK`;
  - `CHECK (num_nonnulls(id_lavanderias, id_repartidores) = 1)`: exactamente uno de los dos (historial-E13);
  - los únicos `(id_pedidos, id_lavanderias)` y `(id_pedidos, id_repartidores)`: una calificación por pedido y
    calificado. En PostgreSQL, las filas con el calificado vacío no chocan entre sí.
- **Lo que ya trae de `perfil-de-lavanderia`:** la llave `id_calificaciones`; `id_pedidos` e `id_repartidores`,
  opcionales y sin llave; la foránea compuesta `(id_lavanderias, id_plazas)` → `lavanderias`; `id_usuarios` →
  `usuarios.id_usuarios` (quien califica); `CHECK (estrellas BETWEEN 1 AND 5)` y `comentario` opcional.
- **Cómo la usa este cambio:** la calificación del servicio se guarda con `id_lavanderias` igual a
  `pedidos.id_lavanderias` y `id_usuarios` igual al dueño del pedido; que sea la lavandería del pedido lo valida el
  código, porque la llave compuesta solo asegura la plaza.
- **Sin trigger:** `impedir_cambio()` es solo para `pedidos`, `pedido_eventos`, `pagos` y `reembolsos`. Si una
  calificación se puede cambiar es BR-25 (historial-E10).
- **Prueba de esquema:** la migración pasa la de `modelo-de-datos` (modelo-E01 a modelo-E17).

**Tablas de otros cambios que este usa:**

| Tabla | Qué hace aquí | Nace en |
|---|---|---|
| `pedidos` | La lista del usuario (`id_usuarios`), con `folio`, `ventana_inicio` (la fecha del filtro), `id_lavanderias` e `id_estados_pedido` | `resumen-y-confirmacion` |
| `estados_pedido` | El `nombre_usuario` del estado de cada pedido | `resumen-y-confirmacion` |
| `lavanderias` | El nombre en la lista y la lavandería calificada | `perfil-de-lavanderia`; `alta-de-lavanderias` |
| `plazas` | La `zona_horaria` con que se cuentan los días del filtro (decisión 1) | `plazas-y-zonas` |
| `pedido_servicios`, `pedido_direcciones` | Solo para repetir un pedido, cuando responda BR-25 (historial-E05) | `resumen-y-confirmacion` |
| `repartidores` | Ninguna todavía: la llave de `id_repartidores` la agrega `repartidor`, que va después en el plan | `repartidor` |

## Risks / Trade-offs

- [DD-052 cierra con dos calificaciones después de implementar E06 a E09] → la tabla ya lo admite: el repartidor es
  otra fila con `id_repartidores`, y lo ya guardado queda como calificación de la lavandería. Cambia la pantalla, no la
  base.
- [`repartidores` nace en `repartidor`, después de este cambio] → La llave de `id_repartidores` la agrega la migración
  de `repartidor` (decisión 6 de `modelo-de-datos`). historial-E13 prueba "sin ninguno" y "los dos" con la columna
  sin llave; con `repartidor`, la base además exige que el repartidor exista.
- [`perfil-de-lavanderia` crea `calificaciones` por su lado] → Si al implementarse ya trae alguna de las piezas de esta
  migración, esta no la repite; lo que vale es la tabla final de `modelo-de-datos.md`.
- [BR-25 sigue sin respuesta] → "repetir pedido" no se acredita y F-A13 queda a medias para A43-6. La lista y el
  filtro sí se acreditan.

## Open Questions

- **Qué se repite y cuándo se califica es DD-107.**
- **DD-052**, a quién califica el usuario (historial-E11 y E14).
- **El orden de `repartidores`** frente a esta migración (ver Risks).
- **Pantallas sin componente.** El historial y la calificación no usan ninguno de los seis componentes de
  `vault/design-system/componentes.md`, salvo la insignia de estado del componente 3. La Definition of Ready pide que
  el componente exista o que la tarea lo cree.
