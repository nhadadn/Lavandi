Cada tarea se hace en rojo, verde y refactor: primero el test que falla, con el identificador del escenario en su
nombre; luego el código mínimo que lo pone en verde; luego la limpieza con el test en verde. Tamaños de S-006: S = 2 h,
M = 5 h, L = 12 h.

## 1. Lista y respuesta de la lavandería en el servidor

- [x] 1.1 pedidos-lavanderia-E01, pedidos-lavanderia-E02: lista de pedidos activos de la lavandería, solo con los suyos — test: `servidor/tests/Feature/PedidosLavanderia/ListaTest.php` — tamaño M. GET /api/lavanderia/pedidos, de la recolección más próxima a la más lejana. Prueba también que el aceptado sigue en la lista (E07) y que el rechazado sale (E12). La lista no trae datos del usuario: esperan E04
- [x] 1.2 pedidos-lavanderia-E07, pedidos-lavanderia-E08: aceptar, con su evento en `pedido_eventos` y el estado actual en `pedidos` en la misma transacción, y rechazo de la acción sobre un pedido ya respondido — test: `servidor/tests/Feature/PedidosLavanderia/AceptarTest.php` — tamaño S. Siembra el estado `aceptado`, que el usuario ve como Confirmado (adenda propuesta a ADR-0021, en `design.md`). El pedido ya rechazado que se intenta aceptar o rechazar va en `RechazarTest.php`, con la tarea 1.3
- [x] 1.3 pedidos-lavanderia-E12, pedidos-lavanderia-E13: rechazar, con el motivo y la hora en su evento de `pedido_eventos`, y rechazo de la petición sin motivo — test: `servidor/tests/Feature/PedidosLavanderia/RechazarTest.php` — tamaño S. Siembra el estado `rechazado`, fuera de los cinco (ADR-0021, decisión 3); el largo máximo del motivo es `pedidos.motivo_de_rechazo_maximo`. Prueba también el pedido ya rechazado de E08. Que el rechazado sale de la lista va en `ListaTest.php`, con la tarea 1.1
- [x] 1.4 pedidos-lavanderia-E09: aceptar o rechazar un pedido de otra lavandería no lo cambia ni lo revela — test: `servidor/tests/Feature/PedidosLavanderia/TenenciaTest.php` — tamaño S. La regla vive en Autorizacion::pedidosDeLaLavanderia(), que nació con la tarea 1.2: la prueba pasó desde su primera corrida y su rojo se comprobó quitando de la regla la cuenta de la lavandería
- [x] 1.5 pedidos-lavanderia-E17, pedidos-lavanderia-E18: la lista y las dos acciones solo ven pedidos con `confirmado_en` lleno; un pedido sin pago acreditado no aparece ni se responde (C-188) — test: `servidor/tests/Feature/PedidosLavanderia/SinPagoTest.php` — tamaño S. La regla es parte de Autorizacion::pedidosDeLaLavanderia() desde la tarea 1.2: la prueba pasó desde su primera corrida, y su rojo se comprobó quitando de la regla el pago acreditado (E18; la lista además deja fuera por_pagar porque no es un estado activo)

## 2. Pantalla de pedidos en la app

- [x] 2.1 pedidos-lavanderia-E03: pantalla de pedidos con la tarjeta de pedido de socio lavandería — test: `movil/__tests__/pedidos-lavanderia/lista.test.tsx` — tamaño M
- [x] 2.2 pedidos-lavanderia-E05, pedidos-lavanderia-E06: consulta cada 30 segundos con la pantalla visible y pausa con la pantalla oculta, con temporizadores simulados — test: `movil/__tests__/pedidos-lavanderia/consulta-periodica.test.tsx` — tamaño M
- [x] 2.3 pedidos-lavanderia-E07: aceptar desde la tarjeta y verla como aceptada — test: `movil/__tests__/pedidos-lavanderia/aceptar.test.tsx` — tamaño S
- [x] 2.4 pedidos-lavanderia-E12, pedidos-lavanderia-E13: pantalla de rechazo con motivo obligatorio — test: `movil/__tests__/pedidos-lavanderia/rechazar.test.tsx` — tamaño S

## 3. Bloqueadas: no entran al sprint mientras su fila siga abierta

- [ ] 3.1 pedidos-lavanderia-E04: datos de recolección y del usuario que ve la lavandería, bloqueada por DD-022 y DD-014 — test: `servidor/tests/Feature/PedidosLavanderia/ListaTest.php` — tamaño S
- [ ] 3.2 pedidos-lavanderia-E10: plazo para responder y pedido sin respuesta, bloqueada por BR-26 y DD-103 — test: `servidor/tests/Feature/PedidosLavanderia/PlazoTest.php` — tamaño M
- [ ] 3.3 pedidos-lavanderia-E11: pedido que llega con la lavandería cerrada, bloqueada por DD-078 — test: `servidor/tests/Feature/PedidosLavanderia/PlazoTest.php` — tamaño S
- [ ] 3.4 pedidos-lavanderia-E14: el pedido y el cobro después del rechazo, bloqueada por DD-016, DD-012 y la fila de BR-26 — test: `servidor/tests/Feature/PedidosLavanderia/RechazarTest.php` — tamaño M
- [ ] 3.5 pedidos-lavanderia-E15: envío del aviso FCM de pedido nuevo, simulado, bloqueada por DD-054 y por la tabla `dispositivos`, que nace en `avisos` — test: `servidor/tests/Feature/PedidosLavanderia/AvisoPedidoNuevoTest.php` — tamaño S
- [ ] 3.6 pedidos-lavanderia-E15: recibir el aviso con la app cerrada y abrir la lista al tocarlo, bloqueada por DD-054 — test: `movil/__tests__/pedidos-lavanderia/aviso.test.tsx` — tamaño M
- [ ] 3.7 pedidos-lavanderia-E16: el aviso con la app abierta provoca una consulta inmediata, bloqueada por DD-054 — test: `movil/__tests__/pedidos-lavanderia/aviso.test.tsx` — tamaño S

## Horas

- **Sin migración:** este cambio no crea tablas ni columnas (`design.md`, "Tablas y migración").
- **Listas para el sprint** (grupos 1 y 2): 3 tareas M y 6 tareas S, **27 h**. Antes de alinear con el modelo eran
  25 h; la tarea 1.5, de C-188, suma 2 h.
- **Bloqueadas** (grupo 3), tamaño tentativo: 3 M y 4 S, **23 h**, sin cambio.
- **Contra el plan:** el plan de sprints le da **12 h** a este cambio (F-B02, una L). Las listas pasan el plan por 15 h
  (antes, por 13 h); con las bloqueadas, por 38 h (antes, por 36 h).
