---
estado: borrador
grado: E
fuente: búsqueda de un subagente de investigación del 2026-11-18, revisada por Claude; ampliación del efectivo del 2026-11-19; páginas oficiales de cada proveedor, citadas abajo; SIN VERIFICAR; acta-2026-11-18-pedidos-de-noche.md C-180 (C)
fecha: 2026-11-18
dueño: Arthur Naranjo
---
# Alternativas a Conekta para cobrar con tarjeta y en efectivo

> **Ojo (revisión de Nadir del PR #2):** las cifras de Openpay y Mercado Pago no coinciden con las de
> [[pasarelas-candidatas]], otra búsqueda del mismo periodo. Las dos son grado E sin verificar; se reconcilian con la
> cotización escrita de cada pasarela (DD-079).

Arthur pidió "las opciones para meterle los pagos con tarjeta a la app; se descarta Conekta" (C-180), y aclaró que la
app debe cobrar con tarjeta y en efectivo. **Conekta es grado A**: cambiarla exige Orden de Cambio (DD-079). Esta nota
es el insumo para esa negociación, no una decisión. Todo es **grado E**, consultado el 2026-11-18.

**La regla de las tiendas no estorba.** Apple (3.1.3(e)) y Google Play (Pagos, 3.1) piden **no** usar su cobro dentro
de la app cuando se paga un servicio físico; Google pone "cleaning services" como ejemplo. La pasarela externa es
obligatoria, no opcional.

## Los viables

| | Stripe México | Mercado Pago | Openpay (BBVA) | Clip |
|---|---|---|---|---|
| Tarjeta nacional | 3.6 % + $3 + IVA | 3.49 % (al instante) + IVA + $4 | 2.25 % o 2.55 % + $1.90 (las dos páginas de BBVA no coinciden) | 3.6 % + IVA |
| Efectivo en OXXO | **Sí** (solo OXXO): 4 % + $3 + IVA; sin reembolsos ni contracargos | **Sí**, más 7-Eleven, Circle K, Soriana, Extra, BBVA y Banamex: 3.79 % + IVA + $4 | Red Paynet (unos 32,000 puntos); OXXO **no confirmado** | **Sí**, OXXO Pay en Clip Checkout |
| SPEI | $7 + IVA | 3.49 % + IVA + $4 | Sí | Anunciado; vigencia sin verificar |
| Apple Pay / Google Pay | Sí | Apple Pay con acuerdo comercial; Google Pay sin confirmar | No encontrado | No encontrado |
| App con Expo | **SDK oficial** `@stripe/stripe-react-native`, en la referencia de Expo SDK 58 | Checkout por redirección, con guía oficial para Expo | Sin SDK oficial | Checkout por redirección |
| Laravel | `stripe-php` oficial | `dx-php` oficial | `openpay-php` | No encontrado |
| Webhooks firmados | Sí (HMAC) | Sí (HMAC) | No documentado | Sin confirmar |
| Guardar tarjeta (DD-055) | Sí | Sí | Sin confirmar | Sin confirmar |
| Depósitos | Cada 3 días hábiles; OXXO a T+4 | Según el plazo; puede retener fondos semanas | Al día hábil siguiente | Sin confirmar |
| Alta | Persona física con actividad empresarial o moral, RFC y CLABE | Persona física o moral, Constancia de Situación Fiscal | **Exige cuenta BBVA** | Desde su app |

**Comisión por un pago con tarjeta de $300**, con IVA (ejemplo que calculó Claude con las comisiones de esta tabla, no un cobro real): Stripe ≈ $16.01; Mercado Pago ≈ $16.79; Clip ≈ $12.53;
Openpay ≈ $11.08.

## Descartados

| Proveedor | Por qué |
|---|---|
| PayPal México | La más cara (3.95 % más una cuota fija); OXXO en beta, solo por gerente de cuenta |
| Braintree | Sin alta directa para comercios en México |
| Adyen | Exige facturación mínima; es para empresas grandes |
| Kushki | Tiene tarjeta, OXXO y SPEI, pero solo vende por cotización |
| NetPay | Sin precios públicos; a lo más, complemento para OXXO |
| Digital@FEMSA (OXXO Pay directo) | Compró OXXO Pay a Conekta. **Corrección del 2026-11-19:** sí documenta una API (`api.digitalfemsa.io`, la misma estructura que Conekta, SDK de PHP), pero la guía es para los comercios que migraron; el alta de uno nuevo no está confirmada. Solo cobra en OXXO: a lo más, complemento |
| dLocal y EBANX | Atienden a comercios globales, no a uno local |
| Pomelo | Emite tarjetas; no acepta pagos de comercios |

## El efectivo en tiendas, a detalle

Ampliación del 2026-11-19, **grado E, SIN VERIFICAR**. En todos el pago es diferido: el usuario recibe una referencia,
paga en la caja de una tienda y la pasarela avisa al servidor por webhook horas después.

| | Stripe | Mercado Pago | Openpay (red Paynet) | Clip |
|---|---|---|---|---|
| Dónde paga el usuario | **Solo OXXO** | **OXXO**; 7-Eleven, Circle K, Soriana, Extra, Calimax y Santander (por PayCash); BBVA, Farmacias del Ahorro y Casa Ley; Banamex, Chedraui y Telecomm | 7-Eleven, Walmart, Walmart Express, Bodega Aurrera, Sam's Club, Soriana, Circle K, Farmacias del Ahorro y Farmacias Benavides. **OXXO no aparece en ninguna lista** | OXXO (OXXO Pay en Clip Checkout); otras tiendas sin confirmar |
| Comisión al comercio | 4 % + $3 + IVA | 3.79 % + IVA + $4 | $2.50 + IVA; otra tabla de Openpay dice 2.9 % (no coinciden) | Sin confirmar |
| Cargo al usuario en caja | El de OXXO: $15 a $17 según terceros | El de cada tienda | $9 a $12 según la cadena | El de OXXO |
| Vencimiento de la referencia | 1 a 7 días; 5 por omisión | 1 a 30 días; recomiendan 3 | Sin confirmar | Sin confirmar |
| Aviso de pago | Al día hábil siguiente | Hasta 2 horas hábiles | Sin confirmar | Sin confirmar |
| Monto por pago | $10 a $10,000 | Sin confirmar | Hasta $5,000 en Farmacias del Ahorro, $15,000 en 7-Eleven, $29,999 en Walmart | Sin confirmar |
| Reembolso | **No se puede**; tampoco hay contracargos | Sin confirmar | Sin confirmar | Sin confirmar |

**Comisión por un pago en efectivo de $300**, con IVA y sin el cargo en caja (el mismo tipo de ejemplo): Stripe ≈ $17.40; Mercado Pago ≈ $17.19;
Openpay ≈ $2.90 o ≈ $12.99, según cuál de sus dos tablas valga.

**Complementos que solo cobran en OXXO:** Digital@FEMSA (ver Descartados) y NetPay, que tiene una API para generar la
referencia, se activa con un asesor comercial y no publica precios. **PayCash** es una red propia (7-Eleven, Soriana,
Circle K y otras); sin API directa documentada, se usa a través de Mercado Pago u otro agregador.

Lo que esto cambia:
- **El contrato nombra OXXO Pay** (`NSV-2026-001-v8.pdf:16:A.3`). Si la Orden de Cambio debe conservar OXXO, Openpay no
  basta sola.
- **Mercado Pago es la red más amplia en una sola integración**, OXXO incluido, y su checkout por redirección trae el
  efectivo sin configurar nada aparte.
- **Una referencia se paga por un monto fijo.** Choca con estimar al pedir y cobrar el peso real
  (`vault/negocio/competencia.md:200`): o la referencia se genera después de pesar, o hace falta cobrar la diferencia.
- **Con Stripe, devolver un pago de OXXO** exige un proceso propio fuera de la pasarela.

## Recomendación de la investigación

1. **Stripe como principal.**
   - Es el único con SDK oficial dentro de Expo SDK 58.
   - Cubre tarjeta, Apple Pay, Google Pay, OXXO y SPEI en una integración, con tarjeta guardada para cobrar después de
     pesar.
   - En contra: es de las más caras; los pagos en OXXO no se reembolsan; y Stripe advierte que las tarjetas de débito
     mexicanas se rechazan con frecuencia.
2. **Mercado Pago como alternativa o segundo método.** Tiene la red de efectivo más amplia y un checkout por
   redirección para Expo. En contra: retiene fondos, retiene ISR e IVA a cuentas de negocio, y tiene poco soporte de
   billeteras.
3. **Openpay**, si pesa más el costo y el cliente tiene o abre cuenta BBVA. Es la más barata y deposita al día
   siguiente. En contra: no tiene SDK oficial ni webhooks firmados documentados, y OXXO no está confirmado.

**Antes de decidir:**
- **PCI:** solo el checkout por redirección encaja claramente en SAQ A, que es el esquema del `ADR-0010`.
- **Cotización:** pedir cotización por escrito.
- **Pruebas:** probar en sandbox.
- **Cuenta:** la cuenta va a nombre del cliente (ANEXO B.11).

## Fuentes

Apple https://developer.apple.com/app-store/review/guidelines/ · Google Play
https://support.google.com/googleplay/android-developer/answer/9858738 · Stripe https://stripe.com/mx/pricing ,
https://stripe.com/es-mx/pricing/local-payment-methods , https://docs.stripe.com/payments/oxxo ,
https://docs.expo.dev/versions/latest/sdk/stripe/ · Mercado Pago
https://www.mercadopago.com.mx/herramientas-para-vender/check-out ,
https://www.mercadopago.com.mx/developers/es/docs/checkout-pro-orders/mobile-integration/react-native-expo-go.md ·
Openpay https://www.bbva.mx/empresas/productos/cobros-y-pagos/pago-electronico-Openpay.html · Clip
https://www.clip.mx/soluciones/pagos-online-clip , https://blog.clip.mx/articulo/pagos-en-oxxo · PayPal
https://developer.paypal.com/oxxo · Adyen https://www.adyen.com/pricing · Kushki https://kushkipagos.com/markets/mexico ·
Digital@FEMSA
https://www.femsa.com/wp-content/uploads/2024/04/Digital@FEMSA-firma-acuerdo-con-Conekta-para-compra-de-activos-que-habilitan-OXXO-PAY.pdf
· Pomelo https://pomelo.la/

Ampliación del efectivo (2026-11-19): Stripe https://docs.stripe.com/payments/oxxo · Mercado Pago
https://www.mercadopago.com.mx/developers/es/docs/checkout-api-orders/payment-integration/other-payment-methods ·
Openpay https://www.openpay.mx/comisiones , https://www.paynet.com.mx/ · Paynet y OXXO Pay en Bamboo
https://docs.bamboopayment.com/docs/mx-cash-payments · Comparativa OXXO Pay, Paynet y PayCash
https://www.unlimit.com/blog/payments/blog-payments-oxxo-pay-vs-paynet-vs-paycash-cash-payments-mexico/ · Digital@FEMSA
https://developers.digitalfemsa.io/docs/guia-de-migraci%C3%B3n-comercios-exclusivos-de-oxxo-pay · NetPay
https://docs.netpay.com.mx/reference/api-pagos-en-efectivo-por-oxxo · Cargo de OXXO al usuario
https://www.tiendanube.com/mx/blog/como-cobrar-en-oxxo/
