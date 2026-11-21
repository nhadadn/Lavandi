Cada tarea va en rojo, verde y limpieza: primero el test con el identificador del escenario en su nombre, que falla;
luego el código mínimo que lo pone en verde; luego la limpieza con el test en verde. pago-E02 está bloqueado (DD-055,
DD-079); cuando se desbloquee, antes de implementarlo se resuelve la precisión de ADR-0010 que pide DD-102, punto 1 (ver
`design.md`). Antes de pago-E09 se confirma en el sandbox de Conekta la del punto 2. Ninguna tarea de las secciones 1, 2
y 4 llama a un proveedor real: todas usan la pasarela simulada. La sección 3 llama solo a Conekta.

## 1. Servidor: tablas y base del cobro

- [ ] 1.1 Migración de `pagos`, `reembolsos` y `eventos_pasarela`, con sus llaves compuestas de plaza, sus únicos, sus `CHECK` y el trigger `impedir_cambio()` en `pagos` y `reembolsos`; modelos `Pago`, `Reembolso` y `EventoPasarela`; cierra pago-E05 junto con la prueba de esquema de `modelo-de-datos` (modelo-E01 a modelo-E17, sobre todo modelo-E13 y modelo-E14) — test: `servidor/tests/Feature/Pago/MigracionTest.php` — tamaño M
- [ ] 1.2 pago-E17: configuración de la pasarela tomada del entorno, con una llave de prueba y la pasarela simulada — test: `servidor/tests/Feature/Pago/LlavesPorEntornoTest.php` — tamaño S
- [ ] 1.3 pago-E01: la interfaz de la pasarela en el componente `cobro` y su versión simulada; crear la orden y el checkout por el monto del servidor, con un solo checkout vigente por cobro — test: `servidor/tests/Feature/Pago/CrearCheckoutTest.php` — tamaño M

## 2. Servidor: los avisos de la pasarela, sin proveedor

- [ ] 2.1 pago-E10: cada aviso se guarda en `eventos_pasarela` y se procesa una sola vez; el repetido recibe 200 y no cambia nada — test: `servidor/tests/Feature/Pago/WebhookUnaSolaVezTest.php` — tamaño S
- [ ] 2.2 pago-E03: el aviso de pago aprobado, ya traducido por la pasarela simulada, marca el cobro `pagado` con `pagado_en` — test: `servidor/tests/Feature/Pago/PagoAprobadoTest.php` — tamaño S
- [ ] 2.3 pago-E18, pago-E19: el cobro pagado confirma su pedido en la misma transacción, con la operación de `resumen-y-confirmacion`, y esa operación rechaza un pedido sin cobro pagado — test: `servidor/tests/Feature/Pago/PagoConfirmaElPedidoTest.php` — tamaño S
- [ ] 2.4 pago-E11: guardar `pasarela`, `id_pasarela_orden`, monto, método, `pagado_en`, pedido y plaza del pago — test: `servidor/tests/Feature/Pago/RegistroParaConciliacionTest.php` — tamaño S
- [ ] 2.5 pago-E04: el rechazo deja el cobro sin pagar, el pedido sin folio, y permite volver a intentar — test: `servidor/tests/Feature/Pago/PagoRechazadoTest.php` — tamaño S
- [ ] 2.6 pago-E07: ninguna petición de la app marca un cobro como pagado — test: `servidor/tests/Feature/Pago/SoloElWebhookConfirmaTest.php` — tamaño S

## 3. Servidor: integración con la pasarela vigente (Conekta)

Conekta es la pasarela del contrato mientras el cliente no firme el cambio (`CLAUDE.md` §2-bis, punto 3; ADR-0010,
vigente). DD-079 no bloquea estas tareas: Conekta entra detrás de la interfaz neutral de la tarea 1.3. Si se firma el
cambio, la elegida se agrega con su ADR como otra implementación de la misma interfaz, sin tocar la base ni la API. No se
adelanta nada para Stripe ni para Mercado Pago (`CLAUDE.md` §2-bis, punto 7).

- [ ] 3.1 pago-E09: comprobar la autenticidad del aviso de Conekta, según ADR-0010 (punto 2), cuya firma está por confirmar en su sandbox (DD-102, punto 2) — test: `servidor/tests/Feature/Pago/WebhookAutenticidadTest.php` — tamaño M
- [ ] 3.2 pago-E01, pago-E03, pago-E04: la implementación de la interfaz para Conekta, que crea su orden y su checkout redirigido y traduce sus avisos de aprobado, rechazado y vencido, probada con cargas capturadas de su sandbox — test: `servidor/tests/Feature/Pago/PasarelaConektaTest.php` — tamaño M
- [ ] 3.3 pago-E01: la misma orden contra el sandbox de Conekta, con la cuenta de prueba de Arthur (C-170); se salta sin llaves y no corre en la integración continua — test: `servidor/tests/Integracion/Pago/SandboxPasarelaTest.php` — tamaño S

## 4. App

- [ ] 4.1 pago-E02: la pantalla de pago pide el checkout con el pedido y el método, y lo abre en el navegador del sistema dentro de la app — **bloqueada por DD-055 y DD-079:** capturar la tarjeta dentro de la app (C-198) contra el checkout redirigido de ADR-0010 se decide con el ADR de la pasarela — test: `movil/__tests__/pago/abrir-checkout.test.tsx` — tamaño M
- [ ] 4.2 pago-E08: la espera "Procesando pago" consulta el cobro hasta que queda pagado o rechazado — test: `movil/__tests__/pago/procesando-pago.test.tsx` — tamaño M

## 5. Bloqueados: no tienen tarea hasta que cierre su fila

- pago-E06, tarjeta guardada: DD-055 y DD-079. C-198 ya dijo que se guarda solo la referencia; falta si el checkout de
  Conekta permite reutilizarla y la captura dentro de la app (pago-E02).
- pago-E12, ficha de efectivo: DD-056.
- pago-E13, ficha vencida: DD-056 y DD-012.
- pago-E14, monto del cobro: DD-012 y DD-013. El momento ya lo fijó C-188.
- pago-E15, ajuste al conocer el monto final: DD-012.
- pago-E16, reembolso al cancelar o al rechazar: DD-012, DD-016 y DD-103.
- pago-E20, cuándo vence la referencia de efectivo: DD-012 y DD-056. Que venza antes de la ventana es pregunta, no
  regla.

En pago-E12, E13, E15, E16 y E20, DD-079 ya no bloquea: la pasarela es Conekta; con la elegida, si se firma el cambio.

La evidencia de A43-4 en sandbox (un pago de prueba en el checkout real, con su aviso por el túnel) se guarda en
`vault/evidencias/pago/` al cerrar la tarea 3.3; no es una tarea de `servidor/` ni de `movil/`.

## Horas

- **Banda media de S-006:** S = 2 h, M = 5 h.
- **Listas (secciones 1, 2 y 3, y la tarea 4.2):** 41 h (5 M y 8 S). Antes eran 34 h (4 M y 7 S): entra la sección 3,
  que ya no espera DD-079 (+12 h: 3.1 M, 3.2 M y 3.3 S), y sale la tarea 4.1, que queda bloqueada con pago-E02 (M,
  −5 h). Diferencia: +7 h.
- **Bloqueadas (tarea 4.1, por DD-055 y DD-079):** 5 h (1 M). Antes eran 12 h, la sección 3 por DD-079. Diferencia:
  −7 h.
- **Total con tarea:** 46 h, sin cambio. Los escenarios de la sección 5 no tienen tarea ni horas.
- **El plan:** le da a `pago` 12 h (F-A10, tamaño L, hito H4). Las listas quedan 29 h por encima (antes, 22); el total,
  34 h por encima.
