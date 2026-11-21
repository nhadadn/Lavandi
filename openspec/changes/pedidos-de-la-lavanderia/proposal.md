## Why

El contrato pide que la lavandería gestione sus pedidos activos con una "Lista en tiempo real. Aceptar o rechazar con
motivo" (`NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo B #2`). Sin esa lista, el pedido que el usuario confirma
no llega a nadie y el recorrido completo del pedido no avanza (`NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3`). El
mecanismo de "tiempo real" ya está decidido: consulta cada 30 segundos (ADR-0007).

## What Changes

- **Lista de pedidos activos de la lavandería:** los confirmados que esperan su respuesta y los que aceptó y aún no
  llegan a Entregado, solo de esa lavandería.
- **Consulta cada 30 segundos** mientras la pantalla de pedidos está visible, y ninguna consulta con la pantalla
  oculta (ADR-0007). Es la primera pantalla de la app con consulta periódica; `estados-del-pedido` la reutiliza.
- **Aceptar un pedido,** con la hora de aceptación.
- **Rechazar un pedido con motivo obligatorio,** guardado con el pedido.
- **Sin pago acreditado no hay pedido para la lavandería** (C-188): un pedido cuyo pago la pasarela no ha confirmado no
  aparece en la lista ni se acepta o se rechaza.
- **Escenarios escritos y bloqueados:** los datos de recolección que ve la lavandería (DD-022, DD-014), el plazo para
  responder y lo que pasa si no responde (BR-26), el pedido que llega de noche (DD-078, pregunta 4), lo que pasa con
  el pedido y el cobro después de un rechazo (DD-016, DD-012) y el aviso de pedido nuevo con la app cerrada (DD-054).

**Qué NO cambia**

- Los botones Recolectado → En proceso → Listo → En proceso de entrega (F-B03) y los cinco estados que ve el usuario,
  con su mapeo (BR-22, DD-011): son de `estados-del-pedido`.
- Los avisos al usuario y los cinco momentos (DD-011): son de `avisos`.
- El cobro, los reembolsos y la confirmación del pedido cuando la pasarela confirma el pago (C-188, DD-012): son de
  `pago` y de `resumen-y-confirmacion`. Aquí solo se lee `pedidos.confirmado_en`.
- La asignación del repartidor (DD-014): es de `repartidor`.
- El historial de pedidos completados, con su monto y su calificación (F-B04): es de `historial-de-la-lavanderia`.
- El monitoreo de pedidos activos en el panel (F-D04): es de `administracion`.
- El componente de autorización y la suite contra fugas: viven en `autorizacion-por-rol`. Aquí solo se usan.

**Fuera del contrato** (posible Orden de Cambio, `NSV-2026-001-v8.pdf:8:Cláusula IV`)

- Que la lavandería vea fotos y descripción de la ropa en el pedido: lo pidió el formulario del 2026-11-05 y no está en
  el Módulo A (`NSV-2026-001-v8.pdf:16-17:ANEXO A, Sección A.3`). DD-100.
- Asignar el repartidor en automático al aceptar, o repartir con Rappi, DiDi, Uber o flotillas: la asignación
  automática está excluida (`NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3, Módulo C`). DD-014.

## Capabilities

### New Capabilities

- `pedidos-lavanderia`: la lista en tiempo real de los pedidos activos de la lavandería y su respuesta a cada pedido:
  aceptar, o rechazar con motivo.

### Modified Capabilities

Ninguna.

## Impact

- **`servidor/`:** rutas de la API para la lista de pedidos activos de la lavandería, aceptar y rechazar, con Sanctum
  (ADR-0005) y la autorización por rol, tenencia y plaza de `autorizacion-por-rol`. Si DD-054 se aprueba, el aviso de
  pedido nuevo por FCM con `laravel-notification-channels/fcm` (ADR-0008).
- **`movil/`:** la pantalla de pedidos del Módulo B, con la tarjeta de pedido de socio lavandería
  (`vault/design-system/componentes.md`, componente 4), la pantalla de rechazo y la consulta periódica de 30 segundos.
  Si DD-054 se aprueba, la recepción del aviso con `@react-native-firebase/messaging` (ADR-0008).
- **Base de datos:** ninguna tabla nueva. La respuesta de la lavandería es un evento en `pedido_eventos` (estado, hora
  y motivo) y el estado actual en `pedidos`, las dos de `resumen-y-confirmacion` (ADR-0016).
- **Dependencias:** ninguna nueva mientras DD-054 siga abierta.
- **ADRs:** ADR-0003, ADR-0005, ADR-0007, ADR-0008 y ADR-0016.
- **Cambios de los que depende:** `modelo-de-datos`, `resumen-y-confirmacion` (el pedido con folio), `pago` (la
  confirmación al acreditarse el pago), `alta-de-lavanderias` y `autorizacion-por-rol`. Para E15 y E16, además,
  `avisos` (la tabla `dispositivos`), que va después en el plan.
