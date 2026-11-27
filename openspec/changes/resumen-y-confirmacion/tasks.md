## 1. Servidor: tablas, crear, confirmar, revalidar, correo y aislamiento

Cada tarea va en rojo, verde y refactor: primero el test que falla, con el identificador del escenario en su nombre;
luego el código mínimo; luego la limpieza.

- [x] 1.1 Migración de `estados_pedido`, `pedidos`, `pedido_servicios`, `pedido_direcciones` y `pedido_eventos`, con sus llaves compuestas de plaza, sus únicos, sus `CHECK`, la semilla de los cinco estados del usuario y el trigger `impedir_cambio()` en `pedidos` (`BEFORE DELETE`) y en `pedido_eventos` (`BEFORE DELETE` y `BEFORE UPDATE`); modelos `EstadoPedido`, `Pedido`, `PedidoServicio`, `PedidoDireccion` y `PedidoEvento`; pasa la prueba de esquema de `modelo-de-datos` (modelo-E01 a modelo-E17). La columna `id_repartidores` sigue lo que Arthur decida en las Open Questions de `design.md` — test: `servidor/tests/Feature/Confirmacion/MigracionTest.php` — tamaño M
- [x] 1.2 confirmacion-E05, confirmacion-E14: al aceptar el resumen, la operación de crear registra el pedido en el estado previo al pago (`por_pagar`, ADR-0021), sin folio, con la plaza de su lavandería, la ventana, el precio copiado en `pedido_servicios`, las dos direcciones copiadas en `pedido_direcciones` y su primer evento; la copia no cambia con el origen — test: `servidor/tests/Feature/Confirmacion/CrearPedidoTest.php` — tamaño M
- [x] 1.3 confirmacion-E06, confirmacion-E07, confirmacion-E13: la operación de confirmar pasa el pedido a Confirmado con folio único por plaza garantizado por la base, `confirmado_en` y su evento, llamada directo desde el test; el botón del resumen y las peticiones de la app no confirman — test: `servidor/tests/Feature/Confirmacion/ConfirmarPedidoTest.php` — tamaño S
- [x] 1.4 confirmacion-E08, confirmacion-E15: al aceptar el resumen se revalidan la ventana y las direcciones con las reglas de `agenda` y `direcciones`, sin crear el pedido si falla — test: `servidor/tests/Feature/Confirmacion/RevalidarAlConfirmarTest.php` — tamaño S
- [x] 1.5 confirmacion-E10, confirmacion-E11: correo de confirmación en cola, colgado del evento de pedido confirmado, con folio y resumen, capturado en local; su falla no deshace el pedido — test: `servidor/tests/Feature/Confirmacion/CorreoDeConfirmacionTest.php` — tamaño S
- [x] 1.6 confirmacion-E12: un usuario no ve el pedido de otro, ni por `id_publico` ni por folio — test: `servidor/tests/Feature/Confirmacion/AislamientoTest.php` — tamaño S
- [x] 1.7 Migración que siembra `por_pagar`, que el usuario no ve, y `config/pedidos.php` con su código: `POST /api/pedidos` ya crea pedidos fuera de las pruebas (ADR-0021, que cierra DD-123; el folio, ADR-0020, cierra DD-101) — test: `servidor/tests/Feature/Confirmacion/MigracionTest.php` (modelo-E07) y `CrearPedidoTest.php` — tamaño S

## 2. App: resumen y confirmación

- [x] 2.1 confirmacion-E01, confirmacion-E07: pantalla de resumen con el componente 6 (servicio, fecha con ventana, recogida y entrega; renglones de precio y política reservados sin cifra; el envío, sin renglón: confirmacion-E03) y el botón que lleva al pago — test: `movil/__tests__/confirmacion/resumen.test.tsx` — tamaño M
- [x] 2.2 confirmacion-E09: pantalla de confirmación con el folio y el resumen, cuando la API dice que el pedido ya está Confirmado (API simulada) — test: `movil/__tests__/confirmacion/confirmacion.test.tsx` — tamaño S

## 3. Bloqueadas: no entran al sprint mientras su fila siga abierta

- [ ] 3.1 confirmacion-E02: precio estimado del lavado en el servidor, en `pedidos.monto_estimado` y `pedido_servicios.cantidad_estimada` (DD-012, DD-010) — test: `servidor/tests/Feature/Confirmacion/PrecioEstimadoTest.php` — tamaño M
- [ ] 3.2 confirmacion-E03: envío en el resumen, en `pedidos.envio` (DD-019, DD-025) — test: `servidor/tests/Feature/Confirmacion/EnvioTest.php` — tamaño S
- [ ] 3.3 confirmacion-E04: política de cancelación como texto de `parametros_plaza` (DD-016) — test: `servidor/tests/Feature/Confirmacion/PoliticaDeCancelacionTest.php` — tamaño S
- [ ] 3.4 confirmacion-E02, confirmacion-E03, confirmacion-E04: la app llena los renglones reservados del resumen — test: `movil/__tests__/confirmacion/resumen-completo.test.tsx` — tamaño S

## Horas

- **Banda media de S-006:** S = 2 h, M = 5 h.
- **Listas:** 25 h (3 M y 5 S). Antes eran 18 h. Suman 5 h la migración (tarea 1.1, M) y 2 h la operación de confirmar
  (tarea 1.3, S), que se separa de la de crear y absorbe la antigua tarea bloqueada de confirmacion-E07, resuelta por
  C-188.
- **Bloqueadas:** 11 h (1 M y 3 S). Antes eran 13 h; salen las 2 h de confirmacion-E07.
- **Total:** 36 h, 5 h más que antes, todas por la migración.
- **El plan:** le da 10 h a F-A09 y F-A11 juntas. Las listas quedan 15 h por encima (antes, 8).
