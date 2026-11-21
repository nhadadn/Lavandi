---
estado: borrador
grado: E
fuente: búsqueda web de Claude del 2026-11-20, por extractos del buscador sobre dominios oficiales; pedida por Nadir (C-198, C-199 en acta-2026-11-20-decisiones-de-maquetas)
fecha: 2026-11-20
dueño: Nadir Hadad Navarrete
---
# Pasarelas candidatas distintas de Conekta

**Todo en esta nota es grado E, `SIN VERIFICAR`.** No es una matriz de criterios ni una elección: es el insumo
para la matriz de `ADR-0010`. Sin ADR, la decisión no existe (CLAUDE.md §5).

## Dos advertencias antes de leer

1. **Cambiar de pasarela cambia el contrato.** El contrato nombra a Conekta (RN-10, RN-28; A.3 #10). Sustituirla
   exige acuerdo escrito con EL CLIENTE. Es la fila DD-079 de `meta/deuda-de-decision.md`.
2. **Ninguna página se abrió completa.** La red de la sesión bloqueó los sitios de los proveedores. Lo que sigue
   sale de extractos del buscador restringido a dominios oficiales, no de la lectura de cada página. Varias
   páginas indexadas tienen un año o más. Ninguna cifra se usa sin abrir la URL citada.

## Qué se buscó

Seis requisitos por proveedor: (1) tarjeta de crédito y débito en pesos para comercio mexicano; (2) captura de la
tarjeta dentro de la app, sin que el número pase por el servidor; (3) tarjeta guardada como referencia para
cobros posteriores; (4) efectivo en tiendas, con OXXO u otras cadenas; (5) comisiones y plazo de depósito;
(6) reembolsos y autorización con captura posterior.

Los requisitos 2 y 3 salen de C-198. El 4, de C-199 y DD-056. El 6 importa por C-197: si se cobra el estimado
y el peso real difiere, hace falta reembolso parcial o captura por otro monto (DD-125).

## Tabla

| Proveedor | 1. Tarjeta MXN | 2. Captura en la app | 3. Tarjeta guardada | 4. Efectivo en tiendas | 5. Comisiones y depósito | 6. Reembolso y captura posterior |
|---|---|---|---|---|---|---|
| **Stripe México** | Sí. Admite persona física con actividad empresarial; pide RFC y CLABE | Sí. SDK iOS, Android y React Native. Flutter oficial: no encontrado | Sí | Sí, **solo OXXO**. Confirma al día hábil siguiente. De $10 a $10,000 | Tarjeta nacional 3.6% + $3; OXXO 4% + $3; más IVA. Plazo de depósito: no encontrado | Reembolso total y parcial en tarjeta. Captura manual, retención típica de 7 días. **OXXO no admite reembolso** |
| **Mercado Pago** | Sí. Persona física: no encontrado | Sí. SDK nativo iOS y Android (2025). React Native: no encontrado | **Parcial.** Guarda la tarjeta pero un flujo exige recapturar el CVV; otro parece cobrar sin él: sin verificar | Sí. OXXO y Paycash (7-Eleven, Circle K, Soriana, entre otras). OXXO acredita en 1 a 2 días hábiles | Tarjeta 3.49% + $4 al instante, menos si se espera; efectivo 3.79% + $4; sin IVA | Reembolso total y parcial hasta 180 días. Reserva solo en crédito. Captura parcial: contradictorio |
| **Openpay (BBVA)** | Sí, sujeto a aprobación. Persona física: no encontrado | **Parcial.** SDK de tokenización iOS y Android; vigencia de las guías sin verificar | Sí | **Parcial.** Red Paynet (7-Eleven, Walmart, Soriana, farmacias y otras). **OXXO: no encontrado** | Tarjeta 2.9% + $2.50 + IVA. Depósito 1 día hábil (Amex 3) | Reembolso parcial. Captura posterior por monto igual o menor |
| **Clip** | Sí | No encontrado para app nativa (solo SDK web) | No encontrado | Sí, por checkout redireccionado, no dentro de la app | 3.6% + IVA; otra página muestra 2.99% + $1: contradictorio. Depósito en 24 h | Parcial y contradictorio. Efectivo sin reembolso |
| **PayPal México** | Sí, con aprobación previa | Sí. SDK iOS y Android | Sí. Disponibilidad para cuentas mexicanas: sin verificar | **Parcial.** OXXO en beta, con gestor de cuenta | 3.55% + $4. Con o sin IVA: no encontrado | Reembolso total y parcial hasta 180 días. Autorización de 29 días |
| **Kushki** | Sí. Sus términos citan un mínimo mensual en comisiones | Parcial. Guías móviles sin leer | Sí | Parcial. Varias cadenas; **OXXO no aparece** | No encontrado: tarifa negociada | Preautorización y captura. Reembolso parcial en México: contradictorio |
| **PayU México** | Sí | No encontrado | Sí | Sí. OXXO y 7-Eleven. OXXO confirma un día después | 3.50% + $4, sin IVA | Reembolso parcial hasta 175 días. Autorización hasta 7 días |
| **Spin / Digital@FEMSA** (OXXO Pay directo) | No encontrado con detalle | No encontrado | No encontrado | Sí, **solo OXXO**, contratado directo. Máximo $10,000 | 3.9% + IVA. Liquidación dos veces al día | Devolución de efectivo por referencia de retiro en OXXO |

## Quién cubre los requisitos 1 a 4 a la vez

Sin elegir ganador:

- **Stripe México:** los cuatro, con efectivo solo en OXXO, confirmación al día hábil siguiente y sin reembolso de OXXO.
- **Mercado Pago:** 1, 2 y 4. El 3 queda parcial hasta confirmar que el cobro sin CVV aplica a este caso.
- **Openpay:** 1 a 3. El 4 solo si basta con cadenas distintas de OXXO.
- **PayPal México:** 1 a 3. El 4 es parcial.
- **Kushki, Clip, PayU:** les falta verificar al menos uno de 2 o 3.
- **Spin:** cubre solo el efectivo en OXXO; serviría como complemento de otra pasarela de tarjeta.

## Sobre Conekta y OXXO (DD-056, R-028)

**Contradictorio; no quedó confirmado.** La documentación técnica de "Conekta Efectivo" lista cadenas sin OXXO.
Su página de precios conserva la etiqueta "El pago sólo se permite en Cadenas Oxxo". La documentación de OXXO
Pay vive hoy en el dominio de Digital@FEMSA. Hay que preguntarle a Conekta por escrito si la cuenta concreta
tendría OXXO según su giro. R-028 sigue como estaba.

## Lo que no se pudo verificar

- La vigencia a noviembre de 2026 de cada cifra.
- El alta como persona física con RFC en todos menos Stripe.
- SDK oficiales de Flutter (Stripe) y de React Native (Mercado Pago, PayPal, Kushki).
- Si la red Paynet de Openpay y la de Kushki incluyen OXXO: no aparece, pero tampoco hay una negación explícita.
- No se revisaron dLocal, EBANX, Adyen, Rapyd, Billpocket, NetPay ni Getnet.

## Fuentes (consultadas el 2026-11-20, vía buscador)

- **Stripe:** `stripe.com/en-mx/pricing` · `docs.stripe.com/payments/oxxo` · `docs.stripe.com/sdks/react-native` ·
  `docs.stripe.com/payments/save-and-reuse-cards-only` · `docs.stripe.com/payments/place-a-hold-on-a-payment-method` ·
  `docs.stripe.com/refunds` · `support.stripe.com/questions/required-information-to-open-your-stripe-account-in-mexico` ·
  `support.stripe.com/questions/taxes-on-stripe-fees-for-mexico-based-businesses`
- **Mercado Pago:** `www.mercadopago.com.mx/ayuda/costo-recibir-pagos_220` ·
  `www.mercadopago.com.mx/developers/es/docs/checkout-api-payments/integration-configuration/other-payment-methods` ·
  `www.mercadopago.com.mx/developers/en/docs/checkout-api-payments/cards-and-customers-management/receive-payments-with-saved-cards` ·
  `www.mercadopago.com.mx/developers/en/docs/checkout-api/payment-management/make-value-reserve` · `github.com/mercadopago/sdk-ios`
- **Openpay:** `www.openpay.mx/comisiones` · `www.openpay.mx/paynet` · `documents.openpay.mx/docs/api` ·
  `github.com/open-pay/openpay-ios` · `github.com/open-pay/openpay-android`
- **Clip:** `developer.clip.mx/docs/api-de-checkout-transparente` · `developer.clip.mx/reference/auth-and-capture` ·
  `www.clip.mx/soluciones/pagos-online-clip`
- **PayPal:** `www.paypal.com/mx/business/paypal-business-fees` · `developer.paypal.com/docs/checkout/advanced/eligibility/` ·
  `developer.paypal.com/sdk/vault/v3` · `developer.paypal.com/beta/apm-beta/oxxo/`
- **Kushki:** `docs.kushki.com/mx/` · `docs.kushki.com/mx/en/cash-payments/authorized-locations/` ·
  `docs.kushki.com/mx/recurring-payments/one-click-payments/on-demand/`
- **PayU:** `www.payulatam.com/mx/tarifas/` ·
  `developers.payulatam.com/latam/es/docs/integrations/api-integration/payments-api-mexico.html`
- **Spin / Digital@FEMSA:** `www.spinnegocios.com/oxxopay` · `docs.spinnegocios.com/reference/pago-en-efectivo-oxxo` ·
  `developers.digitalfemsa.io/docs/cargo-unico-efectivo-direct-api`
- **Conekta:** `developers.conekta.com/docs/cargo-unico-efectivo` · `www.conekta.com/pricing` ·
  `www.conekta.com/payments/cash` · `www.conekta.com/legal/business-profiles`

## Qué sigue

1. Nadir abre las URL de los dos o tres candidatos que le interesen y confirma las cifras. Lo confirmado sube a
   grado C en un acta.
2. Con eso se arma la matriz de criterios ponderados y `ADR-0010`.
3. En paralelo, DD-079: plantear a EL CLIENTE el cambio de pasarela por escrito.
