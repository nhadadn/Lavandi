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

Este cambio **no crea tablas ni columnas, y no tiene migración**. Lee y escribe las de otros cambios, según la columna
"Nace en" de `vault/arquitectura/modelo-de-datos.md`:

| Tabla | Qué hace aquí | Nace en |
|---|---|---|
| `pedidos` | Lee la lista de la lavandería: sus pedidos por `id_lavanderias`, solo con `confirmado_en` lleno (C-188), con `folio`, `ventana_inicio` y `ventana_fin`. Al responder, cambia `id_estados_pedido` | `resumen-y-confirmacion` |
| `pedido_eventos` | Agrega un evento por respuesta: el estado nuevo en `id_estados_pedido`, la cuenta de la lavandería en `id_usuarios`, el motivo del rechazo en `motivo` y la hora en `ocurrido_en`. Nunca edita ni borra | `resumen-y-confirmacion`; `estados-del-pedido` le pone el trigger `impedir_cambio()` |
| `estados_pedido` | Lee los estados de "espera respuesta", "aceptado" y "rechazado". Cuáles son esas filas lo fija DD-123 | `resumen-y-confirmacion` |
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
- **Prueba de esquema:** no aplica, porque no hay migración. Las tablas que usa ya la pasan en sus cambios.

## Risks / Trade-offs

- [La máquina de estados está pendiente, DD-123] → La respuesta de la lavandería se especifica en términos neutros:
  "espera su respuesta", "aceptado" y "rechazado". Se guarda como evento en `pedido_eventos`; qué fila de
  `estados_pedido` es cada una lo fija DD-123 (límite 2027-01-04, antes del sprint 13). Sin ella, las pruebas de E07,
  E08 y E12 no tienen con qué estado cerrar.
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
   motivo, y el estado actual en `pedidos` (ADR-0016). Falta qué fila de `estados_pedido` representa "espera
   respuesta", "aceptado" y "rechazado", y cómo se relacionan con los cinco estados que ve el usuario: es DD-123.
2. **¿ADR-0007 cubre la lista de la lavandería?** Su decisión habla de "la pantalla del pedido" y de las tablas del
   panel. Aplicarla a esta lista es una lectura. Si Arthur lo lee distinto, se enmienda el ADR.
3. **El motivo.** Se especifica como texto libre obligatorio, que es la lectura mínima del contrato. ¿Tiene longitud
   máxima? Se construye configurable. ¿El usuario ve el motivo? Depende de BR-22 y BR-26.
4. **Qué datos del usuario ve la lavandería** (E04): la dirección de recolección y el nombre dependen de quién recoge
   y entrega (DD-022, DD-014).
5. **El plazo para responder y lo que pasa después de un rechazo** (E10, E14): Es DD-103.
