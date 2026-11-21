## Context

El perfil es la ficha que la persona ve antes de pedir. Toma la lavandería que se eligió en `lavanderias-cercanas` y
lleva a la selección del tipo de servicio, que abre el pedido. El almacenamiento ya está decidido: `Storage` de Laravel
con driver S3, buckets privados y URLs firmadas que vencen (ADR-0006), un bucket por entorno (ADR-0012). Lo que falta
es de negocio: quién fija el precio y en qué unidad va el lavado en seco (DD-010, BR-16). "Aún no hay precios"
(C-131): los de este hito son de prueba.

## Goals / Non-Goals

**Goals:**

- El perfil completo de una lavandería activa, con fotos privadas servidas con URLs firmadas.
- Los servicios que ofrece cada lavandería, con su precio y su unidad como datos, para que DD-010 se conteste sin
  migraciones.
- La selección de un tipo de servicio, guardada en el pedido en curso del teléfono.

**Non-Goals:**

- Subir fotos y comprimirlas en el teléfono (`alta-de-lavanderias`).
- Editar precios y decidir quién los fija (`alta-de-lavanderias`, `administracion`).
- El precio estimado (`resumen-y-confirmacion`) y crear calificaciones (`historial-y-calificacion`).

## Decisions

1. **Fotos:** `Storage` con driver S3 y bucket privado; la app recibe URLs temporales con el vencimiento de la
   configuración (ADR-0006). Las fotos de prueba son imágenes neutras, sin marca (`CLAUDE.md` §5), y solo se siembran en
   local y en prueba (ADR-0012).
2. **Precio por lavandería y tipo de servicio, con monto y unidad como datos.** Es la forma más general: cubre que el
   precio lo fije la lavandería o que lo fije Lavandi, la plataforma, y se cargue en cada una (DD-010), sin migración. Es una fila
   de `lavanderia_servicios`: `precio` en pesos, `numeric(12,2)`, y la unidad como llave a `unidades_medida`
   (ADR-0016, reglas 2 y 7).
3. **Los tres tipos de servicio son las filas del catálogo `tipos_servicio`** (ADR-0016, regla 2), sembradas con los
   nombres del contrato: `estandar`, `planchado` y `seco`. Ninguna pantalla ni comando agrega tipos: un tipo nuevo,
   como el calzado, sería una fila, pero es Orden de Cambio (DD-046). Lo mismo con `unidades_medida`: la semilla es
   `kg`, y la pieza entra solo si DD-010 la pide.
4. **Las reseñas no llevan datos de quien califica** (`NSV-2026-001-v8.pdf:10:Cláusula X`): EL PRESTADOR trata datos
   como encargado y EL CLIENTE define las finalidades. Si el cliente pide mostrar el nombre, cambia
   perfil-lavanderia-E08.
5. **El pedido en curso vive en el teléfono** hasta el resumen. El servidor no guarda borradores en este cambio.
6. **La API identifica la lavandería por su `id_publico`** (ULID), nunca por `id_lavanderias` (`modelo-de-datos`,
   modelo-E15). Las reseñas no llevan ningún identificador de quien calificó (perfil-lavanderia-E08).

Ninguna elige tecnología nueva: siguen ADR-0003, ADR-0006, ADR-0012 y ADR-0016.

## Tablas y migración

Este cambio crea siete tablas, tal como las fija `vault/arquitectura/modelo-de-datos.md` (columna "Nace en"). Las dos
primeras son catálogos, en la lista de tablas sin plaza de `modelo-de-datos`; las otras cinco son de operación y
llevan `id_plazas`, no nula y con llave foránea a `plazas` (plazas-E09).

| Tabla | Columnas | Llaves, únicos y `CHECK` |
|---|---|---|
| `tipos_servicio` | `id_tipos_servicio`, `codigo`, `nombre`, `activo` | Llave `id_tipos_servicio`. `codigo` único (ver Open Questions). Semilla: `estandar` (lavado estándar), `planchado` (lavado con planchado) y `seco` (lavado en seco) |
| `unidades_medida` | `id_unidades_medida`, `codigo`, `nombre` | Llave `id_unidades_medida`. `codigo` único (ver Open Questions). Semilla: `kg` (kilo) |
| `lavanderias` | `id_lavanderias`, `id_publico`, `id_plazas`, `id_usuarios`, `nombre`, `descripcion`, `telefono`, `calle`, `numero`, `colonia`, `codigo_postal`, `ubicacion` (`geography(Point, 4326)`), `estado`, `aprobada_en`, `deleted_at` | Llave `id_lavanderias`. `id_publico` ULID único. Único `(id_lavanderias, id_plazas)`, al que apuntan las llaves compuestas de sus hijas. Llaves foráneas a `plazas` y a `usuarios` (quien la administra), con índice. Índice GiST en `ubicacion`. `CHECK (estado IN ('pendiente', 'activa', 'suspendida', 'baja'))`. `aprobada_en` en `timestamptz`. Borrado suave con `deleted_at` |
| `lavanderia_servicios` | `id_lavanderia_servicios`, `id_lavanderias`, `id_plazas`, `id_tipos_servicio`, `id_unidades_medida`, `precio`, `activo` | Llave `id_lavanderia_servicios`. Llave compuesta `(id_lavanderias, id_plazas)` a `lavanderias`. Llaves foráneas a `tipos_servicio` y a `unidades_medida`. Único `(id_lavanderias, id_tipos_servicio)`. `precio` en `numeric(12,2)` con `CHECK (precio >= 0)`. Quién fija el precio: DD-010 |
| `lavanderia_horarios` | `id_lavanderia_horarios`, `id_lavanderias`, `id_plazas`, `dia_semana`, `hora_inicio`, `hora_fin` | Llave `id_lavanderia_horarios`. Llave compuesta `(id_lavanderias, id_plazas)` a `lavanderias`. `CHECK (dia_semana BETWEEN 1 AND 7)` y `CHECK (hora_fin > hora_inicio)`. El cupo por ventana entra como columna cuando DD-099 diga cuánto; los festivos, con DD-078 |
| `lavanderia_fotos` | `id_lavanderia_fotos`, `id_lavanderias`, `id_plazas`, `ruta`, `orden` | Llave `id_lavanderia_fotos`. Llave compuesta `(id_lavanderias, id_plazas)` a `lavanderias`. En la base solo va la ruta; la foto vive en S3 (ADR-0006) |
| `calificaciones` | `id_calificaciones`, `id_pedidos`, `id_plazas`, `id_usuarios`, `id_lavanderias`, `id_repartidores`, `estrellas`, `comentario` | Llave `id_calificaciones`. Llave foránea a `usuarios` (quien calificó) y llave compuesta `(id_lavanderias, id_plazas)` a `lavanderias`. `CHECK (estrellas BETWEEN 1 AND 5)`. `CHECK (num_nonnulls(id_lavanderias, id_repartidores) = 1)`: exactamente un calificado. Únicos `(id_pedidos, id_lavanderias)` y `(id_pedidos, id_repartidores)`: una por pedido y calificado |

- **Toda llave foránea** lleva su índice y `ON DELETE RESTRICT` (modelo-E09 y modelo-E10). Las tablas hijas llevan dos
  llaves: la simple de `id_plazas` a `plazas` (plazas-E09) y la compuesta con `id_lavanderias` (modelo-E06), para que
  la base rechace un servicio, un horario, una foto o una calificación de otra plaza.
- **`lavanderia_horarios` guarda horas del día, no instantes:** `hora_inicio` y `hora_fin` son `time`, leídas en la
  `zona_horaria` de la plaza. El día 1 es el lunes (ISO 8601). Pueden ir varias filas por día.
- **`calificaciones` nace como base.** `id_pedidos` e `id_repartidores` nacen opcionales y **sin llave foránea**,
  porque `pedidos` y `repartidores` todavía no existen: las calificaciones de prueba de este hito no tienen pedido. La
  llave compuesta hacia `pedidos` la agrega `historial-y-calificacion`, que completa la tabla; la de `repartidores`, la
  agrega `repartidor`, que la crea. Ninguno de los dos borra ni cambia lo que hay (ADR-0016, regla 9).
- **Las lavanderías de prueba** se siembran con estado `activa`, en una plaza de prueba, y cada una con una cuenta de
  prueba como quien la administra. El alta real y cómo llega el socio son de `alta-de-lavanderias` (DD-053).
  `lavanderias-cercanas` siembra sobre estas mismas tablas.
- **Usa, de otros cambios:** `plazas` (`plazas-y-zonas`) y `usuarios` (`registro-e-inicio-de-sesion`).
- **Modelos de Eloquent:** `TipoServicio`, `UnidadMedida`, `Lavanderia`, `LavanderiaServicio`, `LavanderiaHorario`,
  `LavanderiaFoto` y `Calificacion`, sobre `App\Models\Modelo`, cada uno con su `$table` y su `$primaryKey`, y cada
  relación con sus llaves nombradas. `Lavanderia` usa `SoftDeletes` y el ULID público.
- **La migración pasa la prueba de esquema de `modelo-de-datos`** (modelo-E01 a modelo-E17). Ninguna de estas tablas
  lleva el trigger `impedir_cambio()`, que es solo de `pedidos`, `pedido_eventos`, `pagos` y `reembolsos`.
- **`created_at` y `updated_at`:** las tablas de este cambio los llevan, en `timestamptz` (modelo-E17; ADR-0016, regla 7). La fecha de
  cada reseña es `calificaciones.created_at` (perfil-lavanderia-E08).

## Risks / Trade-offs

- [A quién se califica, DD-052] → si el usuario también califica al repartidor, esas calificaciones no van en este
  perfil. La tabla ya admite las dos, exactamente un calificado por fila (ADR-0016), y el perfil cuenta solo las que
  tienen `id_lavanderias` (perfil-lavanderia-E18, bloqueado por DD-052).
- [La libreta de precios del 2026-11-14] → no se usa ni para los datos de prueba: es un borrador de una lavandería sin
  identificar (`mocks/LEEME.md`) y trae precios por prenda.
- [Una URL vence con la pantalla abierta] → la imagen ya cargada sigue en pantalla; al volver a entrar, la app pide el
  perfil otra vez y recibe URLs nuevas.
- [Almacenamiento en local] → la máquina de Arthur no tiene un servicio S3. Ver Open Questions.

## Open Questions

- **DD-010 (BR-16):** la unidad y el precio del lavado en seco (perfil-lavanderia-E06). Quién fija el precio no bloquea
  la vista.
- **DD-096: autor de las reseñas.** ¿El perfil muestra el nombre o la foto de quien calificó? Mientras no
  responda EL CLIENTE, no se muestra nada de esa persona.
- **DD-098: varios servicios en un pedido.** ¿Un pedido lleva un solo tipo o puede combinar varios? El mock A06
  deja elegir más de uno, sin fila. Sin respuesta, la app deja elegir uno.
- **El orden y la cantidad de reseñas en pantalla.** El contrato no los fija. Lo decide Arthur, o se deja a
  `historial-y-calificacion`.
- **El almacenamiento en local.** ¿Un MinIO en Docker, que habla S3 como dice el ADR-0006, o el disco local de Laravel
  con URLs firmadas? Si se aparta del driver S3, se anota en el ADR-0006.
- **El vencimiento de las URLs:** es un valor técnico de la configuración; lo fija Arthur.
- **Pregunta de diseño:** el design system no tiene componente para la galería de fotos, la lista de reseñas ni la
  selección de servicio. La tarea los crea con los tokens, o se agregan antes a `vault/design-system/componentes.md`.

**Del modelo de datos, para Arthur (las necesita la tarea 1.1):**

- **La fecha de la reseña.** Resuelta: es `calificaciones.created_at`, en `timestamptz` (modelo-E17; ADR-0016,
  regla 7).
- **`codigo` único en los catálogos.** El modelo lo escribe para `roles`, no para `tipos_servicio` ni para
  `unidades_medida`. Este diseño lo pone igual en los dos, porque la semilla y el código buscan por `codigo`; falta que
  el modelo lo diga.
- **El orden con `lavanderias-cercanas`.** Ese cambio va antes en el plan y lee `lavanderias` y `calificaciones`, que
  crea la tarea 1.1 de este cambio (ver el `design.md` de `lavanderias-cercanas`, Open Questions). Lo decide Arthur al
  rehacer el plan.
