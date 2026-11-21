## Context

F-A09 (resumen) y F-A11 (confirmación) cierran el hito H3 (`NSV-2026-001-v8.pdf:16-17:ANEXO A, Sección A.3`, Módulo A
#9 y #11). Llegan con la lavandería y el servicio de H2, la ventana de `agenda-de-recoleccion` y las direcciones de
`direcciones`. El cobro llega después, en H4 (`pago`), y el aviso al teléfono en H6 (`avisos`).

Con C-188 (Arthur, 2026-11-19), "no se va a recolectar si no ha sido pagado": el pedido nace al aceptar el resumen, en
un estado interno previo al pago, y pasa a Confirmado, el primer estado que ve el usuario (`:17:A.3 #12`), solo cuando
la pasarela confirma su pago. El orden lo sostiene el contrato: resumen (#9), pago (#10) y confirmación con folio (#11).
El C3 deja el componente `pedido` como "ESPECIFICACIÓN PENDIENTE" por la máquina de estados (`c3-api.md`), que es
DD-123. Las tablas salen del modelo de datos aprobado (ADR-0016, C-193). Las decisiones abiertas que tocan este cambio
son DD-123, DD-012, DD-010, DD-016, DD-019, DD-025, DD-098, DD-101, DD-071 y DD-079.

## Goals / Non-Goals

**Goals:**

- Una operación en `api` que crea el pedido al aceptar el resumen, con su copia, y otra que lo confirma cuando el
  cobro queda pagado.
- Revalidación de la ventana y de las direcciones al aceptar el resumen.
- El historial del pedido desde su primer estado.
- El correo de confirmación, en cola, sin que su falla deshaga el pedido.
- Las pantallas de resumen y de confirmación con el componente 6.

**Non-Goals:**

- Calcular el precio estimado, el envío o mostrar la política de cancelación con contenido (E02, E03 y E04).
- El cobro y la integración con la pasarela (`pago`: Conekta mientras no se firme el cambio, DD-079).
- Qué pasa con un pedido que nunca se paga (DD-123, DD-012).
- El aviso al teléfono, los estados siguientes y la cancelación.

## Decisions

1. **Dos operaciones del pedido en `api`, y ninguna sabe quién la llama** (ADR-0003: las reglas viven en `api`).
   - **Crear,** al aceptar el resumen: revalida, crea el pedido en el estado previo al pago, copia el precio y las
     direcciones y guarda su primer evento.
   - **Confirmar:** asigna folio, llena `confirmado_en`, pasa a Confirmado, guarda el evento y publica el evento de
     pedido confirmado. No la llama la app: la llama el componente `cobro` del cambio `pago` cuando la pasarela confirma
     el pago (C-188; ADR-0010, punto 1). En este cambio la llaman los tests. La regla de que exista un cobro pagado
     (pago-E19) la agrega `pago`, porque `pagos` nace ahí.
2. **Ya no hay camino de botón que confirme.** El diseño anterior dejaba que el botón del resumen confirmara en local
   para enseñar H3; C-188 lo impide. Sin `pago`, H3 se enseña hasta el pedido creado que espera su pago, y la pantalla
   de confirmación se prueba en `movil/` con la API simulada.
3. **La copia del pedido es historial, no duplicado** (ADR-0016, la primera de sus dos excepciones). El precio unitario
   y las direcciones se copian al crear el pedido, porque el cobro se calcula antes de confirmarlo.
4. **El estado actual va en `pedidos` y su historia en `pedido_eventos`,** en la misma transacción (ADR-0016, la
   segunda excepción; ADR-0007).
5. **El pedido lleva la plaza de su lavandería,** y la base lo impone con llaves compuestas (`modelo-multi-plaza.md`;
   ADR-0016, regla 4; `CLAUDE.md` §9.5).
6. **La unicidad del folio la garantiza la base de datos,** con el único `(id_plazas, folio)`, no solo el código. El
   folio queda vacío hasta Confirmado; PostgreSQL admite varios vacíos en un único.
7. **El correo cuelga del evento de pedido confirmado y va en cola.** Un error del proveedor no regresa la
   confirmación. El cambio `avisos` agrega después su oyente al mismo evento, sin tocar esta operación (ADR-0014, en
   borrador); en local el correo se captura sin enviarse.
8. **El aislamiento vive en el componente de autorización** que nace en H1 (`CLAUDE.md` §9.5). Un pedido ajeno responde
   igual que uno que no existe, y la API lo identifica por `id_publico`.
9. **Los renglones bloqueados se reservan sin cifra ni texto inventado,** como pide el componente 6 del design system
   (`design-system/componentes.md`, componente 6).

## Tablas y migración

**Crea** cinco tablas, en una migración que pasa la prueba de esquema de `modelo-de-datos` (modelo-E01 a modelo-E17).
Las columnas son las de `vault/arquitectura/modelo-de-datos.md`. Toda foránea lleva su índice y `ON DELETE RESTRICT`.

**`estados_pedido`** (catálogo, sin plaza):

- `id_estados_pedido`, `codigo`, `nombre_usuario`, `orden`, `visible_usuario`.
- Único: `codigo`.
- Semilla: los cinco estados del usuario, Confirmado, Recolectado, En proceso, Listo y Entregado (`:17:A.3 #12`), con
  `visible_usuario` verdadero. El estado previo al pago y los demás internos los fija DD-123; hasta entonces existen
  solo en las pruebas.

**`pedidos`:**

- `id_pedidos`, `id_publico`, `id_plazas`, `folio`, `id_usuarios`, `id_lavanderias`, `id_repartidores`,
  `id_estados_pedido`, `ventana_inicio`, `ventana_fin`, `monto_estimado`, `monto_final`, `envio`,
  `comision_porcentaje`, `confirmado_en`, `cancelado_en`.
- Foráneas: `id_plazas` a `plazas`; `id_usuarios` a `usuarios`; `(id_lavanderias, id_plazas)` a
  `lavanderias(id_lavanderias, id_plazas)`; `id_estados_pedido` a `estados_pedido`. `id_repartidores` nace opcional y sin llave: la llave
  compuesta `(id_repartidores, id_plazas)` → `repartidores` la agrega la migración de `repartidor` (diseño de
  `modelo-de-datos`, decisión 6).
- Únicos: `id_publico`; `(id_plazas, folio)`; y `(id_pedidos, id_plazas)`, para las foráneas compuestas de sus hijas
  (`pedido_servicios`, `pedido_direcciones` y `pedido_eventos` aquí; `pagos`, `pedido_asignaciones`, `evidencias` y
  `calificaciones` después).
- `CHECK`: `ventana_fin > ventana_inicio`; `monto_estimado`, `monto_final` y `envio` no negativos;
  `comision_porcentaje` de 0 a 100.
- Vacías al crear el pedido: `folio` y `confirmado_en` (las llena la confirmación); `monto_estimado` (DD-012 y DD-010,
  confirmacion-E02); `envio` (DD-019 y DD-025, confirmacion-E03); `monto_final` (al pesar, DD-012); `cancelado_en`
  (DD-016); `comision_porcentaje` (DD-020; `comisiones` nace en `administracion`, H9).
- Trigger: `impedir_cambio()` en `BEFORE DELETE`. Se puede editar (el estado actual cambia), nunca borrar.

**`pedido_servicios`:**

- `id_pedido_servicios`, `id_pedidos`, `id_plazas`, `id_tipos_servicio`, `id_unidades_medida`, `cantidad_estimada`,
  `cantidad_real`, `precio_unitario`.
- Foráneas: `(id_pedidos, id_plazas)` a `pedidos(id_pedidos, id_plazas)`; `id_tipos_servicio` a `tipos_servicio`;
  `id_unidades_medida` a `unidades_medida`.
- `precio_unitario` se copia de `lavanderia_servicios.precio` al crear el pedido.
- `CHECK`: `precio_unitario` no negativo; `cantidad_estimada` y `cantidad_real` mayores que cero cuando existen.
- Los subtotales son columnas generadas, `cantidad × precio_unitario`, que no se capturan. El modelo no les da nombre
  (ver Open Questions).
- Uno o varios por pedido es DD-098; la tabla admite varios y, sin respuesta, la app deja elegir uno.
- `cantidad_estimada` espera DD-012, igual que `monto_estimado`.

**`pedido_direcciones`:**

- `id_pedido_direcciones`, `id_pedidos`, `id_plazas`, `tipo`, `id_direcciones`, la copia de la dirección (`alias`,
  `calle`, `numero_exterior`, `numero_interior`, `colonia`, `codigo_postal`, `municipio`, `referencias`) y `ubicacion`
  (`geography(Point, 4326)`, con índice GiST).
- Foráneas: `(id_pedidos, id_plazas)` a `pedidos(id_pedidos, id_plazas)`; `(id_direcciones, id_plazas)` a
  `direcciones(id_direcciones, id_plazas)` (único que crea `direcciones`). Así la base rechaza una dirección de otra
  plaza o sin plaza (direcciones-E11 y E12).
- Único: `(id_pedidos, tipo)`.
- `CHECK`: `tipo` en `recoleccion` o `entrega`.

**`pedido_eventos`** (la base; `estados-del-pedido` agrega los demás cambios de estado):

- `id_pedido_eventos`, `id_pedidos`, `id_plazas`, `id_estados_pedido`, `id_usuarios`, `motivo`, `ocurrido_en`.
- Foráneas: `(id_pedidos, id_plazas)` a `pedidos(id_pedidos, id_plazas)`; `id_estados_pedido` a `estados_pedido`;
  `id_usuarios` a `usuarios`, vacía cuando el cambio lo hace el sistema, como la confirmación por el pago.
- `motivo` es opcional; lo usa el rechazo de `pedidos-de-la-lavanderia`.
- Trigger: `impedir_cambio()` en `BEFORE DELETE` y en `BEFORE UPDATE`. Solo se agrega.

**Modelos de Eloquent,** sobre la clase base `App\Models\Modelo`, cada uno con su `$table` y su `$primaryKey`:
`EstadoPedido`, `Pedido` (con `HasUlids` para `id_publico`), `PedidoServicio`, `PedidoDireccion` y `PedidoEvento`.

**Usa, sin crearlas:**

| Tabla | Para qué | La crea |
|---|---|---|
| `usuarios` | Quién hace el pedido y a quién va el correo | `registro-e-inicio-de-sesion` |
| `plazas`, `zonas`, `parametros_plaza` | La plaza; la zona de las direcciones; la política de cancelación y el envío, cuando respondan DD-016, DD-019 y DD-025 | `plazas-y-zonas` |
| `lavanderias`, `lavanderia_servicios`, `tipos_servicio`, `unidades_medida`, `lavanderia_horarios` | La lavandería, el precio que se copia y el horario con que se revalida la ventana | `perfil-de-lavanderia` |
| `direcciones` | El origen de la copia | `direcciones` |
| `pagos` | Que el pedido tenga un cobro pagado antes de confirmarlo (pago-E19) | `pago`, después de este cambio |

## Risks / Trade-offs

- [EL CLIENTE no acepta la regla de C-188 (DD-012 sigue parcial)] → Cambian confirmacion-E05, E07 y E13 y quién llama a
  la confirmación; las tablas no cambian.
- [La confirmación con folio de H3 depende del pago, y el pago desde el teléfono, de pago-E02 (DD-055, DD-079)] →
  **No está listo** para enseñar el folio en el teléfono con un pago real mientras pago-E02 siga bloqueado. H3 se
  enseña hasta el pedido creado; el folio se prueba con los tests, contra el sandbox de Conekta.
- [Un pedido se queda en el estado previo al pago y nunca se paga] → No recibe folio, no lo ve la lavandería y no se
  recolecta (C-188). Si vence o se cancela lo fijan DD-123 y DD-012; este cambio no lo inventa.
- [Confirmado antes de que la lavandería acepte] → Si la lavandería rechaza después (`:17:A.3`, Módulo B #2), el pedido
  confirmado tiene que cambiar, con su pago. Es BR-22, BR-26 y DD-103, en H5 y H6.
- [Resend corta en 100 correos al día] (ADR-0014) → Con la cola, un rechazo del proveedor se reintenta y no se pierde
  el pedido.
- [El correo depende del dominio del cliente] (DD-023) → En local no hace falta; fuera de local, el remitente es
  `lavandi.mx` desde la configuración.

## Open Questions

- **DD-123:** el código y el nombre del estado previo al pago, y qué pasa con un pedido que no se paga.
- **El nombre de los subtotales generados de `pedido_servicios`.** El modelo no los nombra. Propuesta, para que la regla
  de dinero por nombre los reconozca (diseño de `modelo-de-datos`, decisión 3): `monto_estimado` y `monto_real`. Lo
  confirma Arthur en `modelo-de-datos.md`.
- **"Al confirmar" en el modelo.** `modelo-de-datos.md` dice que el precio y la dirección se copian "al confirmar"; con
  C-188 se copian al crear el pedido, antes del pago. Conviene corregir la nota del modelo.
- **El único del folio.** La spec anterior pedía un folio que no se repite en ningún lado; el modelo lo hace único por
  plaza. Si el formato de DD-101 no incluye la plaza, dos plazas pueden repetir el número. **Necesita ADR** de Arthur
  (DD-101) antes de fusionar.
- **`created_at` y `updated_at`:** las tablas de este cambio los llevan, en `timestamptz` (modelo-E17; ADR-0016, regla 7). La hora en que el
  usuario confirma sigue siendo el `ocurrido_en` del primer evento.
- **DD-012 y DD-010:** cómo se estima el precio (confirmacion-E02) y que EL CLIENTE acepte C-188.
- **DD-019 y DD-025:** el envío (confirmacion-E03).
- **DD-016:** la política de cancelación (confirmacion-E04).
- **DD-098:** uno o varios servicios por pedido.
- **DD-071:** el proveedor de correo fuera de local.
