---
estado: vigente
grado: D
fuente: acta-2026-11-13-cierre-de-la-fase-6.md C-152; restricciones-no-negociables.md RN-10 y RN-28 (A); brecha-ejecutivo-tecnico.md BR-50 (B); deuda-de-decision.md DD-012, DD-055 y DD-056 (B); verificaciones-fase-6.md, fila 5b y sección del 2026-11-12 (E); ADR-0002 y ADR-0003 (D)
fecha: 2026-11-13
dueño: Arthur Naranjo
adr: ADR-0010
supersede:
---
# ADR-0010 — Pagos con Conekta por su checkout redirigido, confirmados por webhook

## Contexto

- **Qué pide el contrato (A):**
  - Conekta como pasarela, con tarjeta y OXXO Pay en producción (RN-28);
  - los números de tarjeta nunca se guardan en la plataforma (RN-10).
- **OXXO (E):** la lista vigente de Conekta Efectivo no incluye OXXO, y Conekta vendió esos activos a Digital@FEMSA
  en 2024. El tutorial de OXXO Pay sigue publicado. Ya está en R-028 y DD-056.
- **Qué queda abierto:**
  - cuándo se cobra (DD-012). El cliente marcó "al pedir con un estimado y ajustar al pesar", sin confirmar;
  - si se guarda la tarjeta (DD-055).
- **Lo que hay para integrar (E, 2026-11-12):**
  - SDK de PHP oficial, `conekta/conekta-php` 9.0.0.
  - Envoltorio oficial para React Native, `conekta-elements-react-native`, que va en la **0.0.1**.
  - SDK nativos de Android e iOS 1.2.0.
  - Checkout redirigido (`HostedPayment`), que devuelve una URL y confirma el pago por webhook.
  - La firma de los webhooks **no está confirmada**.

## Criterios y pesos

Los de la matriz `vigente` (C-142). RN-28 y RN-10 funcionan como puertas: las tres opciones las cumplen con tarjeta.

## Alternativas evaluadas (cómo cobra la app)

| Alternativa | Familiaridad (30) | Velocidad (25) | Proveedor (15) | Tiempo real (10) | Costo (10) | Multi-plaza (10) | **Total** |
|---|---|---|---|---|---|---|---|
| **A. Checkout redirigido de Conekta (`HostedPayment`),** abierto desde la app en el navegador del sistema y confirmado por webhook | 4 | 5 | 4 | 5 | 5 | 5 | **91** |
| B. `conekta-elements-react-native` 0.0.1, con componentes dentro de la app | 3 | 3 | 3 | 5 | 5 | 5 | **72** |
| C. Módulo propio de Expo que envuelva los SDK nativos 1.2.0 | 2 | 2 | 4 | 5 | 5 | 5 | **64** |

**A gana** porque no depende de un envoltorio inmaduro ni de código nativo. Además deja el manejo de la tarjeta
entero en Conekta (RN-10). Su costo es de experiencia: el usuario sale un momento de la app para pagar.

## Decisión

1. **La app cobra con el checkout redirigido de Conekta.**
   - `api` crea la orden con `conekta-php` y la app abre la URL que devuelve.
   - **El pago se da por bueno solo cuando llega el webhook de Conekta a `api`,** nunca porque la app regrese del
     navegador.
2. **Los webhooks:**
   - responden 200;
   - son idempotentes, así que un mismo evento procesado dos veces no duplica nada;
   - su firma se valida en el sandbox antes de producción.
3. **El ajuste al pesar (si DD-012 lo confirma)** se resuelve en la spec de pago con las operaciones de órdenes y
   reembolsos de Conekta: cargo adicional o reembolso parcial.
4. **El efectivo queda fuera de este ADR hasta cerrar DD-056.** La recomendación vigente de DD-056 es el efectivo que
   Conekta ofrece hoy (7-Eleven, Circle K, Soriana, farmacias y otros), con la misma integración.
5. **Tarjeta guardada (DD-055):** se verifica en el sandbox si el checkout redirigido permite reutilizar la tarjeta
   como referencia de Conekta. Si no, la v1 cobra cada vez.

La aprobó Arthur Naranjo el 2026-11-13 (`acta-2026-11-13-cierre-de-la-fase-6.md`, C-152), con la opción que recomendó Claude.

## Consecuencias

- **Positivas:**
  - Se puede cobrar con tarjeta sin código nativo.
  - La tarjeta nunca pasa por Lavandi.
  - Sirve igual en iPhone y Android.
- **Negativas:**
  - El usuario sale a una página de Conekta para pagar.
  - La confirmación tarda lo que tarde el webhook, así que la pantalla muestra "procesando pago".
  - OXXO y la tarjeta guardada siguen abiertos.
- **Condiciona:**
  - La spec de pago.
  - ADR-0012: cuenta de Conekta del cliente con sandbox y producción.
  - El alta en Conekta del B.10, que exige cuenta bancaria empresarial.

## Criterio de reversión

- Se pasa a la opción B si `conekta-elements-react-native` saca una versión estable compatible con Expo y la
  experiencia del checkout redirigido genera quejas en las pruebas de aceptación.
- Se reabre todo el ADR si el checkout redirigido no logra cobrar en el sandbox.

## Costo estimado de revertir

Medio. Cambia la pantalla de pago y el arranque del cobro. La orden, los webhooks y la conciliación en `api` se
conservan.

## Trazabilidad

- **Obligaciones:** O-18.
- **Restricciones:** RN-10, RN-28 y BR-50.
- **Pendientes:** DD-012, DD-055 y DD-056.
- **Riesgos:** R-028.
- **ADRs relacionados:** ADR-0002, ADR-0003 y ADR-0012.
