## 0. Servidor: la base de datos

- [ ] 0.1 estados-E14: el historial de un pedido solo crece; el modelo de Eloquent `PedidoEvento` (de `resumen-y-confirmacion`) solo crea eventos, y la base rechaza editarlos o borrarlos con los triggers que puso `resumen-y-confirmacion` — test: `servidor/tests/Feature/Estados/HistorialTest.php` — tamaño S

## 1. Servidor: el estado del pedido y su consulta

- [ ] 1.1 estados-E01 y estados-E11: la api entrega el pedido recién confirmado en Confirmado, con los cinco estados en orden, y la consulta del usuario devuelve el mismo nombre que marcó la lavandería en Recolectado, En proceso y Listo — test: `servidor/tests/Feature/Estados/EstadoVisibleTest.php` — tamaño S

## 2. Servidor: los botones de la lavandería y el historial

- [ ] 2.1 estados-E03, estados-E04, estados-E05 y estados-E14: transiciones de la lavandería en el orden del contrato, rechazo de saltos y regresos, y un evento en `pedido_eventos` por cambio, con su hora y quién lo hizo, junto con `pedidos.id_estados_pedido` en la misma transacción — test: `servidor/tests/Feature/Estados/BotonesDeLaLavanderiaTest.php` — tamaño M

## 3. Servidor: quién mueve y quién consulta

- [ ] 3.1 estados-E07, estados-E08, estados-E09 y estados-E10: reglas en el componente de autorización: otra lavandería, de su plaza o de otra, el usuario y un pedido no aceptado o rechazado no se mueven; un usuario no consulta el pedido de otro — test: `servidor/tests/Feature/Estados/QuienMueveElPedidoTest.php` — tamaño S
- [ ] 3.2 estados-E23: un pedido con `confirmado_en` vacío no se le devuelve a la lavandería ni lo puede mover (C-188) — test: `servidor/tests/Feature/Estados/SinPagoTest.php` — tamaño S

## 4. App: el seguimiento del usuario

- [ ] 4.1 estados-E02: pantalla de seguimiento con el componente 3 del design system: estado actual con su nombre, anteriores cumplidos y siguientes pendientes, solo con los cinco nombres — test: `movil/__tests__/estados/seguimiento.test.tsx` — tamaño M
- [ ] 4.2 estados-E15 y estados-E16: consulta cada 30 segundos solo con la pantalla visible, y consulta inmediata al volver — test: `movil/__tests__/estados/consulta-periodica.test.tsx` — tamaño S
- [ ] 4.3 estados-E17: flujo de Maestro en Android que cronometra el cambio de Confirmado a Recolectado en 60 segundos o menos — test: `movil/.maestro/estados/cambio-en-60-segundos.yaml` — tamaño S

## 5. App: los botones de la lavandería

- [ ] 5.1 estados-E06: tarjeta de pedido de la lavandería (componente 4 del design system) con solo el botón del paso siguiente habilitado — test: `movil/__tests__/estados/botones-de-la-lavanderia.test.tsx` — tamaño M

## 6. Bloqueadas: no entran al sprint mientras su fila siga abierta

- [ ] 6.1 estados-E12: lo que ve el usuario con "En proceso de entrega" (DD-022, DD-011) — test: `servidor/tests/Feature/Estados/EstadoVisibleTest.php` — tamaño S
- [ ] 6.2 estados-E13: la lavandería marca Entregado, si DD-022 lo permite — test: `servidor/tests/Feature/Estados/BotonesDeLaLavanderiaTest.php` — tamaño S
- [ ] 6.3 estados-E18: el mismo flujo de 60 segundos en iPhone (DD-069) — test: `movil/.maestro/estados/cambio-en-60-segundos.yaml` — tamaño S
- [ ] 6.4 estados-E19: el pedido rechazado por la lavandería (BR-22, BR-26, DD-103) — test: `servidor/tests/Feature/Estados/RechazoYCancelacionTest.php` — tamaño S
- [ ] 6.5 estados-E20: el pedido cancelado (DD-016) — test: `servidor/tests/Feature/Estados/RechazoYCancelacionTest.php` — tamaño S
- [ ] 6.6 estados-E21: el ciclo completo del pedido en el servidor, hasta Entregado (DD-012, DD-014, DD-022) — test: `servidor/tests/Feature/Estados/RecorridoCompletoTest.php` — tamaño M
- [ ] 6.7 estados-E22: el recorrido completo en el teléfono, en Android y en iPhone (DD-012; DD-055 y DD-079, por pago-E02; DD-014, DD-022, DD-069) — test: `movil/.maestro/estados/recorrido-completo.yaml` — tamaño M

Cada tarea va en rojo, verde y refactor: primero el test que falla, con el identificador del escenario en su nombre; luego el código mínimo; luego la limpieza. Tamaños de S-006: S = 2 h, M = 5 h, L = 12 h.

## Horas

- **Listas** (grupos 0 a 5): 3 M y 6 S, **27 h**. Antes de alinear con el modelo eran 23 h: la migración de 0.1 suma 2 h y la tarea 3.2, de C-188, otras 2 h.
- **Bloqueadas** (grupo 6): 2 M y 5 S, **20 h**, sin cambio.
- **Contra el plan:** el plan le da 17 h a este cambio (F-B03, M, y F-A12, L). Sin las bloqueadas, se pasa por 10 h (antes, por 6 h); con ellas, por 30 h (antes, por 26 h).
