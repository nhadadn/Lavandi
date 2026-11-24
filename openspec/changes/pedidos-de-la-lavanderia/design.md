## Context

- **Lo que pide el contrato:** "Lista en tiempo real. Aceptar o rechazar con motivo"
  (`NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo B #2`). No dice qué datos ve la lavandería, en cuánto tiempo
  debe responder ni qué pasa después de un rechazo (BR-26).
- **El tiempo real ya está decidido (ADR-0007):** consulta cada 30 segundos con la pantalla visible, más un aviso FCM
  al cambiar el estado. No hay websockets.
- **El aviso a la lavandería depende de DD-054.** ADR-0008 ya dice cómo se manda si se aprueba: FCM directo.
- **La máquina de estados del pedido sigue pendiente** (`vault/arquitectura/c3-api.md`, componente `pedido`; BR-22,
  DD-011, DD-014, DD-016 y DD-022). Aceptar y rechazar existen por contrato; su relación con los cinco estados del
  usuario, no.
- **El componente existe:** la tarjeta de pedido de socio lavandería (`vault/design-system/componentes.md`,
  componente 4), con los estados Por aceptar, En proceso y Cerrado. El rechazo exige motivo.
- **Orden de los hitos:** H5 va antes que H6, así que esta es la primera pantalla de la app con consulta periódica.

## Goals / Non-Goals

**Goals:**

- Que la lavandería vea sus pedidos activos sin recargar y los acepte o rechace con motivo.
- Que ninguna lavandería vea ni toque pedidos de otra.
- Dejar escritos y marcados los escenarios que esperan una respuesta.

**Non-Goals:**

- La máquina de estados completa y los botones Recolectado → En proceso → Listo → En proceso de entrega
  (`estados-del-pedido`).
- El aviso al usuario cuando su pedido se acepta o se rechaza (`avisos`, DD-011).
- El reembolso después de un rechazo (`pago`, DD-012).
- La asignación del repartidor (`repartidor`, DD-014).

## Decisions

No hay una decisión técnica nueva que no esté en un ADR. Este cambio usa:

- **ADR-0007** para refrescar la lista: consulta cada 30 segundos, solo con la pantalla visible. El gancho de
  consulta periódica nace aquí y lo reutiliza `estados-del-pedido`.
- **ADR-0005:** la app llama a la API con tokens de Sanctum.
- **ADR-0008**, solo si DD-054 se aprueba: aviso FCM de pedido nuevo a la lavandería.
- **La autorización** la decide el componente único de `autorizacion-por-rol`, con rol, tenencia y plaza
  (`CLAUDE.md` §9.5).
- **ADR-0016:** la respuesta de la lavandería se guarda como un evento del pedido (sección siguiente).

## Tablas y migración

Este cambio **no crea tablas ni columnas.** Sus únicas migraciones son de datos: siembran en `estados_pedido` los
estados que usa, como pide ADR-0021, decisión 3 (ver "Adenda propuesta a ADR-0021"). Lee y escribe las de otros cambios,
según la columna
"Nace en" de `vault/arquitectura/modelo-de-datos.md`:

| Tabla | Qué hace aquí | Nace en |
|---|---|---|
| `pedidos` | Lee la lista de la lavandería: sus pedidos por `id_lavanderias`, solo con `confirmado_en` lleno (C-188), con `folio`, `ventana_inicio` y `ventana_fin`. Al responder, cambia `id_estados_pedido` | `resumen-y-confirmacion` |
| `pedido_eventos` | Agrega un evento por respuesta: el estado nuevo en `id_estados_pedido`, la cuenta de la lavandería en `id_usuarios`, el motivo del rechazo en `motivo` y la hora en `ocurrido_en`. Nunca edita ni borra | `resumen-y-confirmacion`; `estados-del-pedido` le pone el trigger `impedir_cambio()` |
| `estados_pedido` | Lee los estados de "espera respuesta" (`confirmado`), "aceptado" (`aceptado`) y "rechazado" (`rechazado`); siembra `aceptado`, `rechazado` y `en_entrega` (ADR-0021 y su adenda propuesta) | `resumen-y-confirmacion` |
| `pedido_servicios` | Lee el servicio que muestra la tarjeta (E03) | `resumen-y-confirmacion` |
| `pedido_direcciones` | La dirección de recolección, solo cuando respondan DD-022 y DD-014 (E04) | `resumen-y-confirmacion` |
| `lavanderias` | Lee la lavandería de la cuenta (`id_usuarios`) y su plaza | `perfil-de-lavanderia`; `alta-de-lavanderias` |
| `dispositivos` | Solo si DD-054 se aprueba: el teléfono de la lavandería para el aviso de pedido nuevo (E15, E16) | `avisos`, **posterior en el plan** (sprints 16 y 17) |

- **La respuesta y el estado actual van juntos.** Aceptar o rechazar agrega su evento y cambia
  `pedidos.id_estados_pedido` en la misma transacción (ADR-0016, segunda excepción: el estado actual vive también en
  `pedidos` para la consulta cada 30 s). La hora de la respuesta es `ocurrido_en` y el motivo, `motivo`.
- **Sin pago no hay pedido para la lavandería** (C-188). La lista y las dos acciones solo ven pedidos con
  `pedidos.confirmado_en` lleno, que se llena cuando la pasarela confirma el pago. Un pago de efectivo vencido nunca lo
  llena (E17, E18).
- **Plaza:** el evento lleva `id_plazas`, con la llave compuesta `(id_pedidos, id_plazas)` hacia `pedidos` que trae su
  tabla.
- **Prueba de esquema:** no aplica, porque ninguna migración cambia una tabla. Las tablas que usa ya la pasan en sus
  cambios. Cada estado sembrado tiene su prueba (`AceptarTest`, `RechazarTest` y, en `estados-del-pedido`,
  `BotonesDeLaLavanderiaTest`), y `tests/Feature/Confirmacion/MigracionTest.php` (modelo-E07) comprueba que los visibles
  siguen siendo exactamente los cinco.

## Adenda a ADR-0021

Estado: **asentada** en `vault/arquitectura/adr/ADR-0021-maquina-de-estados-del-pedido.md` el 2026-11-22 (decisión 30). La escribió Claude por delegación de Arthur Naranjo (C-236), con la tarea
1.2, porque aceptar necesita un estado operativo propio que ADR-0021 no nombra. Lo que esperan DD-022 (entregar sin
repartidor), DD-016 (cancelación) y DD-103 (plazo y consecuencias del rechazo) **no** se decide aquí.

1. **`aceptado`, un estado operativo nuevo.** Lo pone la lavandería al aceptar (Módulo B #2), desde `confirmado`. El
   usuario lo sigue viendo como **Confirmado**: el contrato no nombra un sexto estado para él (Módulo A #12).
2. **Las transiciones de la lavandería**, en la tabla de `App\Pedidos\CambioDeEstado` (ADR-0021, decisión 4):

   | Desde | Acción de la lavandería | Llega a |
   |---|---|---|
   | `confirmado` | Aceptar | `aceptado` |
   | `confirmado` | Rechazar, con motivo | `rechazado` |
   | `aceptado` | Recolectado | `recolectado` |
   | `recolectado` | En proceso | `en_proceso` |
   | `en_proceso` | Listo | `listo` |
   | `listo` | En proceso de entrega | `en_entrega` |

   - La lavandería marca Recolectado desde `aceptado`, no desde `confirmado`: sin aceptar no hay botones (estados-E09).
     Desde dónde marca el repartidor su Recolectado lo fija `repartidor` (H8).
   - Un pedido aceptado ya no se rechaza, ni uno rechazado se acepta: el pedido ya fue respondido
     (pedidos-lavanderia-E08). Si después de aceptar se puede deshacer, es DD-103.
   - Ninguna acción de la lavandería sale de `en_entrega`: Entregado espera DD-022 (estados-E13).
3. **Cómo se siembra cada estado nuevo** (sin columnas nuevas):

   | Código | `nombre_usuario` | `orden` | `visible_usuario` | Lo siembra |
   |---|---|---|---|---|
   | `aceptado` | Confirmado | el de `confirmado` (1) | falso | `pedidos-de-la-lavanderia`, tarea 1.2 |
   | `rechazado` | Rechazado | 99, fuera del flujo | falso | `pedidos-de-la-lavanderia`, tarea 1.3 |
   | `en_entrega` | Listo | el de `listo` (4) | falso | `estados-del-pedido`, tarea 2.1 |

4. **Cómo se deriva lo que ve el usuario.** `visible_usuario` dice si el estado es **uno de los cinco pasos del
   seguimiento**. Un estado operativo que el usuario ve como uno de los cinco lleva `visible_usuario` falso, el nombre de
   ese paso y su mismo `orden`. La API del usuario le presenta el paso: `estado.codigo` es el del paso, `estado.nombre`
   es el `nombre_usuario` del estado, y `estado.visible_usuario` es verdadero. Un estado sin paso de su orden
   (`por_pagar`, `rechazado`) está fuera del flujo: sale con su propio código y `visible_usuario` falso, como ya salía
   `por_pagar`. Lo hace `App\Http\Resources\PedidoResource::estadoParaElUsuario()`, con la relación
   `EstadoPedido::paso()`.
   - **Por qué así:** no hay columna nueva ni cambio de esquema para otros cambios; la app del Módulo A no cambia, porque
     ubica el paso actual por `estado.codigo` y, tras el pago, espera `confirmado` aunque la lavandería ya haya
     aceptado; el seguimiento sigue trayendo exactamente los cinco (estados-E01); y el criterio de reversión del ADR
     ("En camino") sigue siendo un `nombre_usuario` nuevo.
   - **Lo que ve el usuario de un pedido rechazado** sigue abierto (estados-E19, DD-103): hoy sale fuera del flujo, sin
     nombre que la app muestre.
5. **Cada cambio publica `App\Pedidos\EstadoDelPedidoCambiado`,** también aceptar y rechazar: es un cambio del estado
   actual, y el aviso de solo datos deja que la app del usuario consulte de inmediato (ADR-0007; avisos-E14). Ninguno de
   los dos es un momento de aviso visible (`config/avisos.php`).

## Contrato de la API

Lo implementó el implementador del servidor con las tareas 1.1 a 1.5 de este cambio y 2.1, 3.1 y 3.2 de
`estados-del-pedido`. Es el mismo que está en el comentario de
`servidor/app/Http/Controllers/Api/PedidosDeLaLavanderiaController.php`.

**Sesión y autorización.** Todas las rutas van con `Authorization: Bearer <token>`, el que da `POST /api/sesion` con el
correo y la contraseña de la cuenta (su respuesta trae `usuario.rol` en `"lavanderia"`). Pasan por `auth:sanctum` y por
`autorizar:lavanderia`, y la tenencia la decide `App\Autorizacion\Autorizacion::pedidosDeLaLavanderia()`: los pedidos de
las lavanderías que administra la cuenta, de su misma plaza y con el pago acreditado (C-188).

| Respuesta | Cuándo |
|---|---|
| `401 { "message": "Unauthenticated." }` | Sin sesión |
| `403 { "message": "Esta cuenta no tiene acceso." }` | La cuenta no tiene el rol de lavandería, como una de usuario |
| `404 { "message": "No existe el pedido." }` | El pedido no existe, es de otra lavandería o de otra plaza, su pago no está acreditado, o el valor no es un ULID (un folio, por ejemplo). Todos responden igual y sin ningún dato (ADR-0019, decisión 3) |

**El pedido**, como sale en todas las respuestas:

```json
{
  "id_publico": "01jd8x6m3v2c9q4w5e7r8t0y1z",
  "folio": "7KQ2M9XA",
  "confirmado_en": "2026-12-03T15:30:00Z",
  "estado": { "codigo": "confirmado", "nombre": "Por aceptar" },
  "por_responder": true,
  "siguiente": null,
  "servicios": [
    { "codigo": "estandar", "nombre": "Lavado estándar", "unidad": { "codigo": "kg", "nombre": "kilo" } }
  ],
  "zona_horaria": "America/Mexico_City",
  "ventana": {
    "inicio": "2026-12-03T11:00:00-06:00",
    "fin": "2026-12-03T12:00:00-06:00",
    "fecha": "2026-12-03",
    "hora_inicio": "11:00",
    "hora_fin": "12:00"
  }
}
```

| Campo | Qué es |
|---|---|
| `id_publico` | El ULID del pedido, el único identificador de la API. Se acepta en mayúsculas o minúsculas |
| `folio` | Siempre lleno: la lavandería solo ve pedidos pagados (ADR-0020) |
| `confirmado_en` | El instante en que se acreditó el pago, en UTC |
| `estado.codigo` | El estado operativo (ADR-0021 y su adenda): `confirmado` (espera respuesta), `aceptado`, `recolectado`, `en_proceso`, `listo`, `en_entrega`, `rechazado` o `entregado` |
| `estado.nombre` | Cómo lo nombra la app de la lavandería: "Por aceptar", "Aceptado", "Recolectado", "En proceso", "Listo", "En proceso de entrega", "Rechazado" o "Entregado" |
| `por_responder` | `true` solo en `confirmado`: la app ofrece Aceptar y Rechazar (pedidos-lavanderia-E03) |
| `siguiente` | El único botón que puede tocar (estados-E06): `{ "estado": "recolectado", "boton": "Recolectado" }`, o `null` si espera respuesta, si está rechazado o entregado, o si ya está en `en_entrega` |
| `servicios` | El servicio del pedido, como en el perfil de la lavandería; sin precio |
| `zona_horaria`, `ventana` | La recolección, como en el pedido del usuario: instantes con el desfase de la plaza, y la fecha y las horas en su hora |

No lleva ningún dato del usuario ni las direcciones: cuáles ve la lavandería lo fijan DD-022 y DD-014
(pedidos-lavanderia-E04). Tampoco montos.

**Las rutas:**

| Ruta | Cuerpo | Éxito | Errores propios |
|---|---|---|---|
| `GET /api/lavanderia/pedidos` | — | `200 { "pedidos": [ {pedido}, … ] }` | — |
| `GET /api/lavanderia/pedidos/{id_publico}` | — | `200 { "pedido": {pedido} }` | — |
| `POST /api/lavanderia/pedidos/{id_publico}/aceptar` | — | `200 { "pedido": {pedido} }`, en `aceptado` | `409` ya respondido |
| `POST /api/lavanderia/pedidos/{id_publico}/rechazar` | `{ "motivo": "…" }` | `200 { "pedido": {pedido} }`, en `rechazado` | `422` motivo; `409` ya respondido |
| `POST /api/lavanderia/pedidos/{id_publico}/estado` | `{ "estado": "recolectado" }` | `200 { "pedido": {pedido} }`, en el estado nuevo | `422` no es un botón; `409` no es el paso siguiente |

- **La lista** trae los pedidos activos: los que esperan su respuesta y los que aceptó y aún no llegan a Entregado, de
  `aceptado` a `en_entrega`. Nunca uno entregado, rechazado o sin pago. Van de la recolección más próxima a la más
  lejana; con la misma, del pedido más viejo al más nuevo. Sin paginación; sin pedidos activos, `"pedidos": []`. La app
  la consulta cada 30 segundos con la pantalla visible (ADR-0007; tarea 2.2).
- **Un pedido** sale en cualquier estado, también uno que ya salió de la lista, como uno rechazado: es suyo.
- **Aceptar** no lleva cuerpo. Guarda la hora en su evento. El usuario lo sigue viendo en Confirmado.
- **Rechazar** exige `motivo`: texto, sin los espacios de las orillas, de hasta `pedidos.motivo_de_rechazo_maximo`
  caracteres (500 por omisión). Guarda el motivo y la hora en su evento, y el pedido sale de la lista. Qué pasa después
  con el pedido, el cobro y lo que ve el usuario espera DD-016, DD-012 y DD-103 (pedidos-lavanderia-E14).
- **El botón** lleva en `estado` el estado al que pasa: `recolectado` (Recolectado), `en_proceso` (En proceso), `listo`
  (Listo) o `en_entrega` (En proceso de entrega). Solo el de `siguiente` se acepta.
- Cada acción que cambia el pedido agrega su evento a `pedido_eventos` con la hora y la cuenta, cambia el estado actual
  en la misma transacción y publica `App\Pedidos\EstadoDelPedidoCambiado` (avisos-E14).

**Los errores, con su cuerpo exacto:**

```json
409 { "message": "El pedido ya fue respondido.", "pedido": { ...el pedido como está... } }
409 { "message": "Ese no es el paso siguiente del pedido.", "pedido": { ...el pedido como está... } }
422 { "message": "Escribe el motivo del rechazo.", "errors": { "motivo": ["Escribe el motivo del rechazo."] } }
422 { "message": "El motivo no puede pasar de 500 caracteres.", "errors": { "motivo": ["El motivo no puede pasar de 500 caracteres."] } }
422 { "message": "Ese no es un botón de la lavandería.", "errors": { "estado": ["Ese no es un botón de la lavandería."] } }
```

- `409 ya respondido`: aceptar o rechazar un pedido ya aceptado o rechazado (pedidos-lavanderia-E08).
- `409 no es el paso siguiente`: un salto, un regreso a un botón anterior, el mismo botón otra vez, o un botón sobre un
  pedido que no se ha aceptado, que se rechazó o que ya está en `en_entrega` (estados-E05 y E09). El `pedido` del 409
  deja a la app ponerlo al día.
- `422`: el motivo falta, está vacío, es de puros espacios, no es texto o pasa del máximo; o `estado` falta o no es uno
  de los cuatro botones, como `confirmado`, `entregado`, `aceptado` o `rechazado`. La validación va antes de buscar el
  pedido, así que su respuesta es la misma para cualquier pedido.
- En ningún error cambia el pedido ni se agrega un evento.

**Lo que la app todavía no tiene en el servidor:** cerrar la sesión (`DELETE /api/sesion`) y la cuenta
(`GET /api/cuenta`) son hoy del rol de usuario (`autorizar:usuario`, de `registro-e-inicio-de-sesion`), así que una
cuenta de lavandería recibe 403 en las dos. Ver el reporte de la tarea: no se cambió aquí.

## Risks / Trade-offs

- [La máquina de estados, DD-123] → La cerró ADR-0021 (2026-11-21). "Espera su respuesta" es `confirmado`;
  "rechazado", `rechazado`; "aceptado" es un estado operativo que ADR-0021 no nombraba y que propone la adenda de
  arriba, `aceptado`. Si Arthur no la incorpora, cambian el estado de E07 y E08 y la derivación del estado del usuario.
- [Cuándo un pedido está confirmado] → Lo respondió C-188: cuando la pasarela confirma el pago, y no antes. La lista
  toma `pedidos.confirmado_en`; con un pago de efectivo, el pedido aparece hasta que se acredita. Si `pago` no está
  terminado en H5, la prueba usa pedidos con `confirmado_en` sembrado.
- [Sin DD-054, con la app cerrada la lavandería no se entera de un pedido nuevo, y el recorrido completo del A.4.3 se
  puede atorar] → E15 y E16 quedan bloqueados. La lista con la app abierta funciona sin ellos.
- [En iPhone, los avisos dependen de la cuenta de Apple (DD-069, DD-017)] → Se prueban primero en Android.
- [Sin plazo para responder, un pedido puede quedarse esperando sin fin] → E10 queda bloqueado; es DD-103.
- [El aviso de pedido nuevo necesita `dispositivos`, que nace en `avisos`, después de este cambio en el plan] → E15 y
  E16 ya estaban bloqueados por DD-054; además esperan esa tabla. No se mueve aquí.
- [Las horas no caben en el plan] → Las tareas listas suman 27 h y las bloqueadas 23 h más, contra 12 h del plan para
  F-B02. Se reporta para la recalibración de S-006.

## Open Questions

1. **Cómo se guarda la respuesta de la lavandería.** El modelo lo responde: un evento en `pedido_eventos`, con su
   motivo, y el estado actual en `pedidos` (ADR-0016). Qué fila de `estados_pedido` es cada una y cómo se ve para el
   usuario: ADR-0021 y su adenda propuesta (arriba).
2. **¿ADR-0007 cubre la lista de la lavandería?** Su decisión habla de "la pantalla del pedido" y de las tablas del
   panel. Aplicarla a esta lista es una lectura. Si Arthur lo lee distinto, se enmienda el ADR.
3. **El motivo.** Se especifica como texto libre obligatorio, que es la lectura mínima del contrato. ¿Tiene longitud
   máxima? Se construye configurable. ¿El usuario ve el motivo? Depende de BR-22 y BR-26.
4. **Qué datos del usuario ve la lavandería** (E04): la dirección de recolección y el nombre dependen de quién recoge
   y entrega (DD-022, DD-014).
5. **El plazo para responder y lo que pasa después de un rechazo** (E10, E14): Es DD-103.
