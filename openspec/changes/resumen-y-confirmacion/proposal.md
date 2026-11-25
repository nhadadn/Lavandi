## Why

El contrato pide un "Resumen del pedido" con "Servicio, fecha, dirección, precio estimado y política de cancelación"
(`NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3`, Módulo A #9; F-A09) y una "Confirmación de pedido" con "Número de
folio, resumen y notificación push más correo" (`NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3`, Módulo A #11; F-A11),
con los correos "al confirmar un pedido" (`NSV-2026-001-v8.pdf:30:ANEXO B, Sección B.5`). Entre los dos está el pago
(#10). Por C-188, el pedido nace al aceptar el resumen y pasa a Confirmado, con folio, solo cuando la pasarela confirma
su pago. Cierra el hito H3.

## What Changes

- La app muestra el resumen con el componente 6 del design system: servicio, fecha y ventana, y direcciones de recogida
  y de entrega. Los renglones de precio estimado y política de cancelación quedan reservados hasta que respondan sus
  filas. El envío no lleva renglón: el Módulo A #9 no lo enumera, y si es un cargo aparte es DD-019 y DD-025
  (confirmacion-E03).
- Al aceptar el resumen, el servidor crea el pedido en un estado interno previo al pago (su nombre lo fija DD-123), con
  la plaza de su lavandería y la copia del precio y de las direcciones. Antes vuelve a validar la ventana (cambio
  `agenda-de-recoleccion`) y las direcciones (cambio `direcciones`); si alguna dejó de valer, no crea el pedido.
- El pedido pasa a Confirmado, con un folio que no se repite en su plaza y con `confirmado_en`, solo cuando el cambio
  `pago` avisa que la pasarela confirmó el pago (C-188). Ni el botón del resumen ni la app lo confirman.
- Cada cambio de estado queda en el historial del pedido (`pedido_eventos`), en la misma transacción.
- La app muestra la confirmación con el folio y el resumen.
- El servidor manda el correo de confirmación con el folio y el resumen. En local se captura sin enviarse (ADR-0014).
  Si el envío falla, el pedido sigue confirmado.
- Cada usuario ve solo sus pedidos (`:20:A.3`, separación por tipo de usuario).
- Nacen las tablas del pedido del modelo de datos: `estados_pedido`, `pedidos`, `pedido_servicios`,
  `pedido_direcciones` y `pedido_eventos` (ADR-0016).

**Qué NO cambia:**

- La "notificación push" de F-A11 se conecta en el cambio `avisos` (H6), cuando exista F-T02. Aquí queda el evento de
  pedido confirmado del que colgará.
- El cobro es del cambio `pago` (H4): crea el cobro, recibe la confirmación de la pasarela y llama a la confirmación del
  pedido. La pasarela es Conekta mientras el cliente no firme el cambio (C-192, C-216, DD-079).
- Los estados después de Confirmado y su seguimiento son de `estados-del-pedido` (H6).
- Que la lavandería acepte o rechace el pedido es de `pedidos-de-la-lavanderia` (H5).
- No hay flujo para cancelar un pedido: el resumen solo muestra la política (DD-016; BR-19, BR-22).

**Fuera del contrato:**

- Foto y descripción de la ropa al pedir (formulario del cliente del 2026-11-05). No está en el Módulo A (`:16-17:A.3`).
  Es DD-100. Es posible Orden de Cambio (`NSV-2026-001-v8.pdf:8:Cláusula IV`).
- Beneficio por llevar más prendas (DD-076; video del cliente del 2026-11-14). El contrato no lo nombra.
- Cobrar por prenda, con tabulador (DD-010). El contrato dice "precios por kilo" (`:16:A.3 #5`).

## Capabilities

### New Capabilities

- `confirmacion`: resumen del pedido, creación del pedido al aceptarlo con su plaza y su copia de precio y direcciones,
  revalidación, paso a Confirmado con folio solo con el pago acreditado, pantalla de confirmación, correo de
  confirmación y aislamiento por usuario.

### Modified Capabilities

Ninguna.

## Impact

- **`servidor/`:** dos operaciones del pedido, crearlo al aceptar el resumen (reusa la validación de `agenda` y de
  `direcciones`) y confirmarlo cuando `pago` lo pide; el evento de pedido confirmado; el correo en cola con el
  transporte de Laravel (ADR-0014).
- **`movil/`:** las pantallas de resumen y de confirmación, con el componente 6 del design system.
- **Base de datos:** nacen `estados_pedido`, `pedidos`, `pedido_servicios`, `pedido_direcciones` y `pedido_eventos`,
  con el trigger `impedir_cambio()` en `pedidos` y `pedido_eventos` (columnas en `design.md`, tomadas de
  `vault/arquitectura/modelo-de-datos.md`). `pago` y `estados-del-pedido` las usan después.
- **Dependencias:** ninguna nueva. El proveedor de correo fuera de local es DD-071, y el remitente usa `lavandi.mx`,
  que espera DD-023.
- **ADRs:** ADR-0002, ADR-0003, ADR-0004, ADR-0010 (punto 1: el pago se da por bueno solo con el webhook), ADR-0014 (en
  borrador) y ADR-0016 (modelo de datos).
