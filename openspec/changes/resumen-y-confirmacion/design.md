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
  `visible_usuario` verdadero. Otra migración siembra `por_pagar`, el estado previo al pago, con orden 0 y
  `visible_usuario` falso (ADR-0021). Los demás internos los siembra el cambio que los usa.

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

## Contrato de la API

Lo agregó el implementador del servidor con las tareas 1.2 a 1.6. Es el mismo que está en el comentario de
`servidor/app/Http/Controllers/Api/PedidosController.php`. Todas las rutas llevan `auth:sanctum` y `autorizar:usuario`:
sin sesión responden 401 (`{ "message": "Unauthenticated." }`) y con una cuenta sin el rol de usuario, 403
(`{ "message": "Esta cuenta no tiene acceso." }`). La API identifica el pedido solo por su `id_publico`.

**Ninguna ruta confirma un pedido.** Lo confirma el componente `cobro` del cambio `pago`, cuando la pasarela confirma el
pago, con la operación de confirmar (`App\Pedidos\ConfirmacionDePedido`), que no es una ruta (C-188; confirmacion-E07).

### El pedido, como sale en todas las respuestas

```json
{
  "id_publico": "01jd8x6m3v2c9q4w5e7r8t0y1z",
  "folio": null,
  "confirmado_en": null,
  "estado": { "codigo": "confirmado", "nombre": "Confirmado", "visible_usuario": true },
  "lavanderia": { "id_publico": "01jd8x6m3v2c9q4w5e7r8t0y1u", "nombre": "Lavandería de prueba 1" },
  "servicios": [
    {
      "codigo": "estandar",
      "nombre": "Lavado estándar",
      "unidad": { "codigo": "kg", "nombre": "kilo" },
      "precio_unitario": "18.00",
      "cantidad_estimada": null,
      "monto_estimado": null
    }
  ],
  "zona_horaria": "America/Mexico_City",
  "ventana": {
    "inicio": "2026-12-03T11:00:00-06:00",
    "fin": "2026-12-03T12:00:00-06:00",
    "fecha": "2026-12-03",
    "hora_inicio": "11:00",
    "hora_fin": "12:00"
  },
  "recogida": {
    "alias": "Casa",
    "calle": "Calle Ejemplo",
    "numero_exterior": "123",
    "numero_interior": "4",
    "colonia": "Centro",
    "codigo_postal": "34000",
    "municipio": "Durango, Dgo.",
    "referencias": "Portón gris",
    "latitud": 24.0277,
    "longitud": -104.6532
  },
  "entrega": { "…": "igual que recogida" },
  "monto_estimado": null,
  "envio": null
}
```

| Campo | Qué es |
|---|---|
| `folio` | `null` hasta Confirmado; texto después. Su formato es DD-101 |
| `confirmado_en` | `null` hasta Confirmado; el instante en UTC (`2026-12-03T15:30:00Z`) |
| `estado` | El estado actual: su código en `estados_pedido`, su nombre para el usuario y si el usuario lo ve. En el estado previo al pago `visible_usuario` es `false` y la app no lo nombra (DD-123) |
| `seguimiento` | Los cinco estados que ve el usuario, en su orden: `[{ "codigo", "nombre", "ocurrido_en" }]`, con la hora en que el pedido llegó a cada uno o `null`. Lo agregó `estados-del-pedido` (estados-E01 y E24); la app ubica el paso actual por `estado.codigo` |
| `servicios` | Uno por ahora (DD-098). `precio_unitario` es el copiado al crear el pedido, en texto con dos decimales. `cantidad_estimada` y `monto_estimado` quedan `null` hasta DD-012 |
| `ventana` | Como la da la agenda: instantes con el desfase de la plaza, y la fecha y las horas en la hora de la plaza |
| `recogida`, `entrega` | La copia que guardó el pedido, no la dirección guardada (confirmacion-E14). Con la entrega en la recogida, las dos son la misma copia. Las señas que no son la colonia pueden ser `null` |
| `monto_estimado`, `envio` | `null` hasta DD-012 y DD-010, y DD-019 y DD-025 (confirmacion-E02 y E03) |

La política de cancelación no viaja todavía: espera DD-016 (confirmacion-E04).

### `POST /api/pedidos` — acepta el resumen y crea el pedido antes del pago (confirmacion-E05, E07, E08 y E14)

```json
{
  "lavanderia": "01jd8x6m3v2c9q4w5e7r8t0y1u",
  "servicio": "estandar",
  "ventana": { "inicio": "2026-12-03T11:00:00-06:00", "fin": "2026-12-03T12:00:00-06:00" },
  "recogida": "01jd8x6m3v2c9q4w5e7r8t0y1v",
  "entrega": "01jd8x6m3v2c9q4w5e7r8t0y1w"
}
```

- `lavanderia`: obligatorio, el `id_publico` de la lavandería elegida.
- `servicio`: obligatorio, el código de uno de los servicios que da su perfil.
- `ventana`: obligatoria, con `inicio` y `fin` tal como los dio `GET /api/lavanderias/{id_publico}/ventanas`.
- `recogida`: obligatorio, el `id_publico` de una dirección de la cuenta.
- `entrega`: opcional; sin ella, o con `null`, se entrega en la recogida.
- Cualquier otro campo se ignora: ni un folio, ni un estado, ni que ya pagó confirman el pedido (confirmacion-E07).

Antes de crear nada, el servidor vuelve a validar la ventana, con `App\Agenda\Agenda::validar()`, y las direcciones, con
`App\Direcciones\RecogidaYEntrega`, la lógica de `POST /api/direcciones/recogida-y-entrega` (confirmacion-E08). Cada
error va en su campo, para que la app lleve a elegir solo eso de nuevo, con los demás datos del pedido intactos.

| Respuesta | Cuándo |
|---|---|
| `201 { "pedido": { …el pedido… } }` | El pedido nació en el estado previo al pago: `folio` y `confirmado_en` vacíos y `estado.visible_usuario` en `false`. La app sigue al pago |
| `422 { "message": "La ventana ya empezó.", "errors": { "ventana": ["La ventana ya empezó."] } }` | La ventana ya no se ofrece. El motivo es el de la agenda (agenda-E09): no va en ISO 8601 con su desfase, no dura una hora, ya terminó, ya empezó, empieza antes de la anticipación mínima, está después de los días que se pueden agendar, o la lavandería no la ofrece en su horario |
| `422 { "message", "errors": { "recogida": ["…"], "entrega": ["…"] }, "motivos": { "recogida": "…", "entrega": "…" } }` | La recogida, la entrega o las dos ya no sirven; solo vienen las rechazadas. La misma forma que `POST /api/direcciones/recogida-y-entrega`. Motivos: `fuera_de_la_zona_de_servicio`, `fuera_de_la_plaza_de_la_lavanderia` y `plaza_de_la_direccion_desactualizada` (ver abajo) |
| `422 { "message", "errors": { "servicio": ["La lavandería no ofrece ese servicio."] } }` | El servicio no lo ofrece la lavandería, o no está activo |
| `422 { "message", "errors": { "<campo>": ["…"] } }` | Falta un campo o no es válido |
| `404 { "message": "No existe la lavandería." }` | No existe, o el usuario no la ve |
| `404 { "message": "No existe la dirección." }` | La recogida o la entrega no existe, está borrada o es de otra cuenta: las tres responden igual |
| `503 { "message": "La agenda de la plaza no está configurada." }` | A la plaza le falta una de las dos claves de su agenda |
| `503 { "message": "Todavía no se pueden crear pedidos." }` | Falta el estado previo al pago (`config/pedidos.php`, `por_pagar` de ADR-0021) |

En ningún error se crea el pedido.

**La plaza guardada de la dirección puede ser vieja.** `direcciones.id_plazas` se resuelve al guardar la dirección y no
se actualiza si las zonas cambian, y la llave compuesta de `pedido_direcciones` exige que sea la del pedido. Al copiar
la dirección, el servidor la vuelve a resolver: si la validación ya dijo que el punto cae hoy en una zona activa de la
plaza de la lavandería, guarda esa plaza en la dirección y la copia. La única excepción es una dirección que ya usó un
pedido con su plaza anterior: cambiarla rompería ese historial, así que se rechaza con
`plaza_de_la_direccion_desactualizada` ("Esta dirección cambió de zona de servicio. Guárdala de nuevo para usarla."), y
la app ofrece guardarla otra vez con `POST /api/direcciones`.

### `GET /api/pedidos/{id_publico}` — un pedido de la cuenta, con su folio y su resumen (confirmacion-E09 y E12)

`{id_publico}` es el ULID del pedido, el que trae `pedido.id_publico` en la respuesta de `POST /api/pedidos`, en
mayúsculas o en minúsculas.

| Respuesta | Cuándo |
|---|---|
| `200 { "pedido": { …el pedido… } }` | Es de la cuenta. La app consulta aquí si ya pasó a Confirmado: `folio` y `confirmado_en` llenos y `estado.codigo` en `confirmado`; entonces muestra la confirmación con el folio y el resumen (confirmacion-E09) |
| `404 { "message": "No existe el pedido." }` | No existe, es de otra cuenta, o el valor no es un ULID, como un folio: todos responden igual y sin ningún dato. El folio no es un identificador de la API, ni para el pedido propio (confirmacion-E12) |

Quién ve qué pedido lo dice el componente único de autorización (`Autorizacion::pedidosVisibles()`). La ruta no lleva
`whereUlid` a propósito: así un folio responde el mismo 404 que un pedido ajeno, y no el 404 genérico del framework.

### La operación de confirmar, para el cambio `pago` (confirmacion-E06 y E13)

No es una ruta. El componente `cobro` la llama cuando la pasarela confirma el pago:

```php
app(\App\Pedidos\ConfirmacionDePedido::class)->confirmar($pedido); // devuelve el Pedido ya Confirmado
```

- En una transacción, con el pedido bloqueado: pasa a `confirmado`, recibe folio y `confirmado_en`, y guarda su evento
  en `pedido_eventos` sin cuenta (lo hace el sistema).
- Ya guardada la transacción, publica `App\Pedidos\PedidoConfirmado`, del que cuelga el correo (confirmacion-E10).
- Un aviso repetido no confirma dos veces: si el pedido ya tiene `confirmado_en`, se devuelve como está.
- El folio lo propone `App\Pedidos\FolioDelPedido` y lo garantiza el único `(id_plazas, folio)`: si la base lo rechaza
  por repetido, se propone otro, hasta cinco veces. **El formato lo fija ADR-0020:** ocho caracteres al azar del
  alfabeto de Crockford, que no revelan el volumen del negocio (BR-21, `modelo-multi-plaza.md`).
- La regla de que exista un cobro pagado (pago-E19) la agrega `pago`.

### El correo de confirmación (confirmacion-E10 y E11)

- `App\Avisos\MandarCorreoDeConfirmacion` cuelga de `PedidoConfirmado` (registrado en `AppServiceProvider`) y pone en
  cola `App\Avisos\CorreoDeConfirmacion` a la dirección de la cuenta. El cambio `avisos` agrega su oyente al mismo
  evento para el aviso al teléfono.
- Asunto: `<APP_NAME>: tu pedido <folio> está confirmado`. Cuerpo, en HTML y en texto: el folio, la lavandería, el
  servicio, la fecha de recolección en la hora de la plaza ("jueves 3 de diciembre de 2026"), la ventana ("11:00 a
  12:00") y la recogida y la entrega copiadas, en una línea cada una. El nombre del producto sale de `APP_NAME` y el
  remitente de `MAIL_FROM_ADDRESS`; ninguno está en la plantilla (`resources/views/correo/confirmacion*.blade.php`).
- Va en cola: 5 intentos, con esperas de 60 s, 5 min, 15 min y 1 h. Después del último queda en `failed_jobs`, de
  donde se reintenta con `php artisan queue:retry`. Un error del proveedor nunca deshace la confirmación, porque el
  evento se publica ya guardada.
- En local se captura sin enviarse con `MAIL_MAILER=log` (`.env.example`) y en las pruebas con el transporte `array`
  (ADR-0014). El proveedor fuera de local es DD-071, y el dominio del remitente, DD-023.

## Risks / Trade-offs

- [EL CLIENTE no acepta la regla de C-188 (DD-012 sigue parcial)] → Cambian confirmacion-E05, E07 y E13 y quién llama a
  la confirmación; las tablas no cambian.
- [La confirmación con folio de H3 depende del pago, y el pago desde el teléfono, de pago-E02 (DD-055, DD-079)] →
  **No está listo** para enseñar el folio en el teléfono con un pago real mientras pago-E02 siga bloqueado. H3 se
  enseña hasta el pedido creado; el folio se prueba con los tests, contra el sandbox de Conekta.
- [Un pedido se queda en el estado previo al pago y nunca se paga] → No recibe folio, no lo ve la lavandería y no se
  recolecta (C-188). Si vence o se cancela lo fijan DD-012 y DD-016; este cambio no lo inventa.
- [Confirmado antes de que la lavandería acepte] → Si la lavandería rechaza después (`:17:A.3`, Módulo B #2), el pedido
  confirmado tiene que cambiar, con su pago. Es BR-22, BR-26 y DD-103, en H5 y H6.
- [Resend corta en 100 correos al día] (ADR-0014) → Con la cola, un rechazo del proveedor se reintenta y no se pierde
  el pedido.
- [El correo depende del dominio del cliente] (DD-023) → En local no hace falta; fuera de local, el remitente es
  `lavandi.mx` desde la configuración.

## Open Questions

- **DD-123, resuelta por ADR-0021:** el estado previo al pago es `por_pagar`. Qué pasa con un pedido que no se paga
  sigue en DD-012 y DD-016.
- **El nombre de los subtotales generados de `pedido_servicios`.** El modelo no los nombra. Propuesta, para que la regla
  de dinero por nombre los reconozca (diseño de `modelo-de-datos`, decisión 3): `monto_estimado` y `monto_real`. Lo
  confirma Arthur en `modelo-de-datos.md`.
- **"Al confirmar" en el modelo.** `modelo-de-datos.md` dice que el precio y la dirección se copian "al confirmar"; con
  C-188 se copian al crear el pedido, antes del pago. Conviene corregir la nota del modelo.
- **El único del folio.** La spec anterior pedía un folio que no se repite en ningún lado; el modelo lo hace único por
  plaza. Resuelto por ADR-0020: el folio es único por plaza, y dos plazas pueden repetir el mismo; el pedido se
  busca siempre con su plaza o por su `id_publico`.
- **`created_at` y `updated_at`:** las tablas de este cambio los llevan, en `timestamptz` (modelo-E17; ADR-0016, regla 7). La hora en que el
  usuario confirma sigue siendo el `ocurrido_en` del primer evento.
- **DD-012 y DD-010:** cómo se estima el precio (confirmacion-E02) y que EL CLIENTE acepte C-188.
- **DD-019 y DD-025:** el envío (confirmacion-E03).
- **DD-016:** la política de cancelación (confirmacion-E04).
- **DD-098:** uno o varios servicios por pedido.
- **DD-071:** el proveedor de correo fuera de local.
