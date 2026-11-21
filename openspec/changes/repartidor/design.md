## Context

- Es el cambio grande del hito H8: el Módulo C completo y la gestión de repartidores del Módulo D.
- Se apoya en lo que ya existe para entonces:
  - la máquina de estados de `estados-del-pedido`;
  - el componente de autorización único de `autorizacion-por-rol` (`CLAUDE.md` §9.5);
  - la cámara de `perfil-y-soporte`.
- Lo sostienen ADRs vigentes: el panel en Filament (ADR-0003); el inicio de sesión con correo y contraseña para todos
  los roles (ADR-0005); fotos y evidencias en bucket privado (ADR-0006); la consulta cada 30 s (ADR-0007).
- El contrato lo acota: "versión operativa mínima" y "Su alcance es el expresamente descrito"
  (`NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3`).
- **Cobro antes de recolectar (C-188).** Arthur: "no se va a recolectar si no ha sido pagado"
  (`vault/meta/actas/acta-2026-11-19-normativa-y-arranque.md`). El orden lo sostiene el contrato: resumen con precio
  estimado (#9), pago (#10) y confirmación con folio (#11) (`NSV-2026-001-v8.pdf:16-17:ANEXO A, Sección A.3`). Aquí
  significa que un pedido sin pago acreditado no se le ofrece al repartidor (E30).
- Las tablas siguen `vault/arquitectura/modelo-de-datos.md` y ADR-0016 (C-193).

## Goals / Non-Goals

**Goals:**

- Dejar implementables, sin respuestas pendientes:
  - repartidor-E01 a E03 y E05 a E12;
  - E15 a E17, E19 a E21, E23, E25, E28 y E30.
- Para probar sin DD-014, los tests asignan el pedido directo en la base, con una fábrica.

**Non-Goals:**

- Todo lo que el Módulo C excluye (ver la propuesta).
- Ubicación del teléfono, en primer o en segundo plano: ninguna función del Módulo C la usa.
- Modelar documentos del repartidor. Si un día entran por Orden de Cambio, van en una entidad aparte, como deja
  previsto `vault/arquitectura/c3-api.md`.
- Lo que bloquean DD-014, DD-053, DD-054, DD-022, DD-011, DD-019, DD-013, DD-052 y BR-29.

## Decisions

No hay decisión técnica nueva. Cinco lecturas que fijan los escenarios; Arthur las aprueba con la spec:

1. **"Alta" es autorizar un registro pendiente.** El repartidor se registra en el Módulo C (#1), y el alta, la
   validación y la autorización "corresponden a EL CLIENTE". Por eso el alta del Módulo D (#2) autoriza un registro que
   ya existe. Alternativa: que la administración también cree repartidores desde el panel. No se descarta, pero no
   hace falta para cumplir los dos numerales.
2. **La plaza se fija al dar de alta.** Toda fila de operación lleva su plaza (`CLAUDE.md` §9.5 y
   `vault/arquitectura/modelo-multi-plaza.md`). La administración elige la plaza al autorizar. Alternativa: que el
   repartidor la elija al registrarse. **Choca con el modelo:** `repartidores.id_plazas` es no nula (plazas-E09), y un
   registro `pendiente` todavía no tiene plaza. Ver Open Questions.
3. **El tipo de vehículo es texto libre.** Una lista de opciones obligaría a inventar sus valores.
4. **Los cinco datos del registro son obligatorios.** El contrato lista lo que el registro lleva y EL CLIENTE lo
   necesita completo para validar fuera de la plataforma.
5. **"Del día" y "de la semana"** se cuentan en la zona horaria de la plaza (`modelo-multi-plaza.md`). La semana va de
   lunes a domingo. Una entrega es "completada" cuando el repartidor marca Entregado.

**El tiempo real de la lista** usa la consulta cada 30 s del ADR-0007. El ADR la escribe para la pantalla del pedido
del usuario y para el panel; aquí se aplica igual a la lista del socio. No hay mecanismo nuevo.

**El pago acreditado** se lee de `pedidos.confirmado_en`: con C-188, el pedido pasa a Confirmado y lo llena solo
cuando la pasarela confirma el pago (`modelo-de-datos.md`, tabla `pedidos`). La lista, el detalle y la aceptación del
repartidor filtran por esa columna, sin depender del estado interno previo al pago que fije DD-123.

## Tablas y migración

Es una sola migración, hacia adelante (ADR-0016, regla 9), en la tarea 0.1. Pasa la prueba de esquema de
`modelo-de-datos` (modelo-E01 a modelo-E17). Ninguna de las tres tablas lleva el trigger `impedir_cambio()`, que es
solo de `pedidos`, `pedido_eventos`, `pagos` y `reembolsos`. Todas las llaves foráneas llevan su índice y
`ON DELETE RESTRICT` (modelo-E09 y modelo-E10).

**Crea:**

1. `repartidores` (E01 a E09):
   - Columnas: `id_repartidores`, `id_publico`, `id_plazas`, `id_usuarios`, `tipo_vehiculo`, `estado`, `aprobado_en`,
     `deleted_at`.
   - Llave primaria `id_repartidores`. `id_publico` es un ULID único (modelo-E15) y es el que usa la API.
   - Llaves foráneas: `id_plazas` → `plazas.id_plazas`; `id_usuarios` → `usuarios.id_usuarios`.
   - Únicos: `(id_repartidores, id_plazas)`, para las llaves compuestas de sus hijas; `id_usuarios`, porque una cuenta
     es a lo más un repartidor (el diagrama del modelo).
   - `CHECK` de `estado`: `pendiente`, `activo`, `suspendido`, `baja`.
   - `aprobado_en` y `deleted_at` son `timestamptz`. La baja es borrado suave: las entregas se conservan (E07).
   - Nombre, teléfono, correo y foto viven en `usuarios` (`nombre`, `telefono`, `correo`, `foto_ruta`), no aquí.
   - **Sin columnas de documentos:** la verificación documental está excluida
     (`NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3`, Módulo C). Si entra por Orden de Cambio, es una tabla nueva,
     `documentos_repartidor`, sin tocar esta.
2. `pedido_asignaciones` (E10 a E18 y E30):
   - Columnas: `id_pedido_asignaciones`, `id_pedidos`, `id_plazas`, `id_repartidores`, `estado`, `motivo`,
     `respondido_en`.
   - Llaves foráneas compuestas con la plaza: `(id_pedidos, id_plazas)` → `pedidos(id_pedidos, id_plazas)` y
     `(id_repartidores, id_plazas)` → `repartidores(id_repartidores, id_plazas)`. La base rechaza ofrecer un pedido de
     una plaza a un repartidor de otra (modelo-E06).
   - `CHECK` de `estado`: `ofrecido`, `aceptado`, `rechazado`. `respondido_en` es `timestamptz`.
   - Sin único: cada intento es una fila. El rechazo guarda `motivo` y `respondido_en` (E16); que el motivo no venga
     vacío lo valida la API (E17).
   - Quién crea la fila `ofrecido` es DD-014 (E13). Mientras, los tests la siembran con una fábrica.
3. `evidencias` (E23 y E24):
   - Columnas: `id_evidencias`, `id_pedidos`, `id_plazas`, `tipo`, `ruta`, `id_usuarios`, `tomada_en`.
   - Llaves foráneas: `(id_pedidos, id_plazas)` → `pedidos(id_pedidos, id_plazas)`; `id_usuarios` →
     `usuarios.id_usuarios`, quien la tomó.
   - `CHECK` de `tipo`: `recoleccion`, `entrega`. `tomada_en` es `timestamptz`.
   - En la base solo va `ruta`; la foto vive en S3 (ADR-0006). Si es obligatoria y en qué momentos: DD-110 (E24).

**Agrega dos llaves foráneas a tablas que nacen antes**, cuando `repartidores` todavía no existe. Las dos son
compuestas, `(id_repartidores, id_plazas)` → `repartidores(id_repartidores, id_plazas)`, con `ON DELETE RESTRICT` y su
índice. Esta migración no cambia las columnas ni sus datos:

- en `pedidos`, que crea `resumen-y-confirmacion`;
- en `calificaciones`, que crea `perfil-de-lavanderia` con `id_repartidores` opcional y sin llave foránea. Con la columna
  vacía, la llave no se revisa, así que las calificaciones de lavandería no cambian.

**Fechas de creación.** Las tres tablas llevan `created_at` y `updated_at` en `timestamptz`, como todas las tablas del
negocio con llave propia. `pedido_asignaciones.created_at` es la hora en que se ofreció el pedido; `respondido_en`, la
hora de la respuesta.

**Los modelos de Eloquent** (`Repartidor`, `PedidoAsignacion` y `Evidencia`) heredan de `App\Models\Modelo` y declaran
su `$table` y su `$primaryKey` (modelo-E16). `Repartidor` usa el ULID público (modelo-E15).

**Solo usa** tablas que crean otros cambios:

| Tabla | Para qué | La crea |
|---|---|---|
| `usuarios`, `rol_usuario`, `roles` | El registro crea la cuenta con el rol `repartidor` y sus datos (E01, E02); la administración los edita (E08); la baja cierra la entrada (E07) | `registro-e-inicio-de-sesion` |
| `plazas` | La plaza del repartidor y la zona horaria de sus estadísticas (E05, E28) | `plazas-y-zonas` |
| `pedidos` | La lista y el detalle (E10 a E12); al aceptar se escribe `id_repartidores` (E15); cada botón cambia `id_estados_pedido` (E19); `confirmado_en` dice si el pago está acreditado (E30) | `resumen-y-confirmacion`; `confirmado_en` lo llena `pago` |
| `pedido_eventos` | Cada botón agrega su evento con `id_usuarios` y `ocurrido_en` (E19); el de Entregado da la fecha del historial y de las estadísticas (E25, E28) | `resumen-y-confirmacion` (base) y `estados-del-pedido` |
| `estados_pedido` | Recolectado, En tránsito y Entregado. En tránsito es un estado interno que fija DD-123 | `resumen-y-confirmacion` |
| `pedido_direcciones` | La dirección de recogida y la de entrega en la lista (E10) | `resumen-y-confirmacion` |
| `lavanderias` | El nombre de la lavandería en la lista (E10) | `perfil-de-lavanderia` |
| `calificaciones` | La calificación recibida, con `id_repartidores` (E27, bloqueado por DD-052). Esta migración le pone la llave foránea a esa columna | `perfil-de-lavanderia` (base) y `historial-y-calificacion` |
| `dispositivos` | El aviso de asignación (E14, bloqueado por DD-054) | `avisos` |

## Risks / Trade-offs

- [El contrato guarda "evidencias de entrega" (p. 20), pero ninguna pantalla del Módulo C las captura, y su alcance es
  "el expresamente descrito" (p. 18)] → ADR-0006 ya ubica las evidencias en el Módulo C. Aquí entra lo mínimo: una foto
  opcional al marcar Entregado. Si es obligatoria, o si también va al recolectar, es BR-29 (E24).
- [DD-014 sigue abierta en el sprint 19] → la lista, la aceptación y los estados se construyen con asignaciones
  sembradas, pero el recorrido real no corre, y A43-2 tampoco: su fila en la trazabilidad ya marca DD-014 como bloqueo.
- [DD-022 cierra con "la lavandería entrega con su gente"] → el repartidor solo participa en algunos pedidos. No
  cambia E10 a E21, pero sí cambia E22.

## Open Questions

- **Las cinco lecturas de Decisions:** Arthur las confirma o las cambia al aprobar la spec.
- **Dónde se asigna el repartidor** (DD-014). Si es en el panel, es una acción nueva del Módulo D, que no la lista. Si
  es la lavandería desde su módulo, es una acción nueva del Módulo B. Las dos necesitan su cita al cerrarse DD-014.
- **BR-29 es DD-110.**
- **DD-053, DD-054, DD-022, DD-011, DD-019, DD-013 y DD-052.**
- **La plaza de un registro pendiente: necesita decisión de Arthur.** La lectura 2 fija la plaza al dar de alta, así
  que un registro `pendiente` no la tiene; plazas-E09 y el modelo piden `id_plazas` no nula. Dos salidas: (a) que
  `id_plazas` quede vacía solo en `pendiente`, con un `CHECK` que la exija en los demás estados, y que `repartidores`
  entre a la lista de "plaza opcional" de la prueba de esquema de `modelo-de-datos`, como `avisos_masivos`; (b) la
  alternativa de la lectura 2: el repartidor elige su plaza al registrarse y la administración
  la confirma al darlo de alta, lo que agrega un sexto dato a E01 y E02. La tarea 0.1 depende de esta respuesta.
- **En tránsito** no es uno de los cinco estados del usuario: es un estado interno de `estados_pedido` que fija el ADR
  de la máquina de estados (DD-123, límite 2027-01-04). E19 y E20 lo necesitan antes del sprint 18.
- **Pantallas sin componente.** El componente 5 de `vault/design-system/componentes.md` cubre la tarjeta de pedido
  del repartidor. El registro, el historial y las estadísticas no tienen componente; tampoco el recurso del panel.
