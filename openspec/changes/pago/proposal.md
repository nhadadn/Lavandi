## Why

El contrato pide que el usuario pague su pedido dentro de la app: "Conekta: tarjeta de crédito o débito y OXXO Pay"
(`NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3`, Módulo A #10; `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3`). Pide
también que el servidor "verifica los pagos" (`NSV-2026-001-v8.pdf:29:ANEXO B, Sección B.4`), que "Los números de
tarjeta nunca se guardan en la plataforma" (`NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3`) y que la "Pasarela Conekta
procesa transacciones exitosamente en modo producción" (`NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3`). Por C-188 y
C-197, el pago va antes de la recolección: el pedido se confirma solo cuando la pasarela confirma su pago. La pasarela
es Conekta mientras el cliente no firme el cambio (`CLAUDE.md` §2-bis, punto 3; ADR-0010); Stripe y Mercado Pago son
candidatas para sustituirla (C-192), y el cliente dio un OK expreso, con la firma pendiente (C-216, DD-079). Por las
Cláusulas IV y XIV, el OK no sustituye la firma. El texto normativo, las tablas y las columnas no nombran proveedor, para
que cambiarla no toque la base ni la API. Es el hito H4 del plan de sprints.

## What Changes

- En `servidor/`, la base del cobro, sin nombre de proveedor: las tablas `pagos`, `reembolsos` y `eventos_pasarela`; el checkout
  redirigido de un cobro pendiente por el monto que calcula el propio servidor; y los avisos de la pasarela (webhooks),
  procesados una sola vez y como única forma de dar un cobro por pagado. La pasarela vive detrás de una interfaz que las
  pruebas simulan.
- Un cobro pagado confirma su pedido, con la operación de `resumen-y-confirmacion`; sin pago acreditado no hay
  confirmación ni recolección (C-188, C-197).
- Guardar de cada pago la pasarela, la referencia de su orden, el monto, el método, la fecha, el pedido y su plaza, para
  la conciliación del Módulo D. Nunca un dato de tarjeta.
- Tomar las llaves de la pasarela de la configuración de cada entorno (ADR-0012).
- En `movil/`, la pantalla de pago abre el checkout y muestra "Procesando pago" hasta que el servidor confirma. Si la
  tarjeta se captura dentro de la app (C-198) o en el checkout redirigido de ADR-0010, queda `SIN RESPUESTA` en pago-E02
  (DD-055, DD-079).
- La integración con Conekta, la pasarela vigente, detrás de la interfaz neutral: su orden y su checkout reales, la
  autenticidad y la traducción de sus avisos, y la prueba contra su sandbox con la cuenta de prueba de Arthur (C-170).
  No la bloquea DD-079. Si el cliente firma el cambio, la elegida se agrega con su ADR. No se construye nada de Stripe ni
  de Mercado Pago mientras no se firme (`CLAUDE.md` §2-bis, punto 7).
- Quedan escritos, con `SIN RESPUESTA`, el efectivo y cuándo vence su referencia (DD-056, DD-012), la tarjeta guardada
  (DD-055, DD-079), el monto del cobro (DD-012, DD-013), el ajuste y el reembolso (DD-012, DD-016).

**Qué NO cambia**

- Lo que dice el contrato: la pasarela es Conekta mientras no haya Orden de Cambio firmada (`CLAUDE.md` §2-bis.3;
  DD-079). Este cambio integra Conekta y no otra; la base y la API quedan sin nombre de proveedor (C-192).
- El resumen con el precio estimado, la creación del pedido y su confirmación con folio, aviso y correo viven en
  `resumen-y-confirmacion`. Este cambio solo llama a la confirmación cuando el cobro queda pagado.
- Los "métodos de pago" del perfil de usuario (F-A15) viven en `perfil-y-soporte`; `metodos_pago_guardados` nace ahí
  cuando se sepa si el checkout permite reutilizar la tarjeta: C-198 ya dijo que se guarda (DD-055, DD-079).
- La conciliación con la pasarela y los reportes financieros (F-D06) viven en `administracion` (`movimientos_pasarela`,
  DD-112). Este cambio no reparte dinero: la dispersión de pagos a repartidores está excluida
  (`NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3`, Módulo C) y la liquidación a lavanderías es DD-013.
- No se emite factura: la facturación electrónica es un servicio de terceros a cargo de EL CLIENTE
  (`NSV-2026-001-v8.pdf:31:ANEXO B, Sección B.7`; BR-31).
- La transacción en producción (A43-4) se acredita en `publicacion`, con la cuenta de EL CLIENTE en la pasarela, que
  exige cuenta bancaria empresarial (`NSV-2026-001-v8.pdf:31:ANEXO B, Sección B.6`;
  `NSV-2026-001-v8.pdf:34:ANEXO B, Sección B.10`).

**Fuera del contrato** (posibles Órdenes de Cambio, `NSV-2026-001-v8.pdf:8:Cláusula IV`)

- Otra pasarela en lugar de Conekta: Stripe, Mercado Pago, Openpay o Clip (C-180;
  `vault/arquitectura/alternativas-de-pasarela-2026-11-18.md`, grado E). DD-079. Por C-192, las candidatas son Stripe y
  Mercado Pago; el cliente dio un OK expreso y la firma sigue pendiente (C-216). Hasta que se firme, no se construye nada
  de ninguna de las dos; si se firma, la elegida entra con su ADR.
- Efectivo en tiendas que no son OXXO (7-Eleven, Circle K, Soriana, farmacias): es la opción (a) de DD-056 y cambia lo
  que dice el contrato. El mock `mocks/usuario/a10-pago.html` la muestra como variante. DD-056.
- OXXO Pay con Digital@FEMSA como segunda integración: opción (b) de DD-056.
- Efectivo contra entrega (guía de la reunión; tabla "Fuera del contrato" del plan de sprints). DD-056.
- Apple Pay, Google Pay y SPEI: aparecen en la investigación de alternativas; el contrato no los nombra. Sin fila.

## Capabilities

### New Capabilities

- `pago`: el cobro del pedido por la pasarela dentro de la app (tarjeta y efectivo), su confirmación por webhook en el
  servidor, la confirmación del pedido con el pago acreditado y la regla de que ningún número de tarjeta se guarda en la
  plataforma.

### Modified Capabilities

Ninguna.

## Impact

- `servidor/`: una ruta para pedir el checkout y otra para recibir los avisos; las tablas `pagos`, `reembolsos` y
  `eventos_pasarela` (columnas en `design.md`, tomadas de `vault/arquitectura/modelo-de-datos.md`); la interfaz de la
  pasarela en el componente `cobro` (`c3-api.md`), con su versión simulada para las pruebas y su implementación para
  Conekta; la configuración por entorno. La dependencia de Conekta para PHP que nombra ADR-0010 vive solo dentro de esa
  implementación.
- `movil/`: la pantalla de pago (F-A10) y la espera "Procesando pago"; el módulo de Expo que abre el navegador del
  sistema, por precisar (ver `design.md`). La pantalla espera pago-E02 (DD-055, DD-079).
- Base de datos: `pagos`, `reembolsos` y `eventos_pasarela`, con el trigger `impedir_cambio()` en `pagos` y
  `reembolsos` (ADR-0016).
- Servicios externos: el sandbox de Conekta, con la cuenta de prueba de Arthur (C-170), y el túnel para recibir sus
  avisos en la máquina local (ADR-0012).
- ADRs: ADR-0010 (vigente: Conekta y el mecanismo; si se firma el cambio de pasarela, lo reemplaza el ADR de la
  elegida), ADR-0012, ADR-0003, ADR-0007 y ADR-0016.
- Trazabilidad: A43-2 y SEG-4; A43-4 (en sandbox) con la tarea 3.3, contra Conekta.
