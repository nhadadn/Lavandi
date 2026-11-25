---
estado: vigente
grado: D
fuente: acta-2026-11-13-cierre-de-la-fase-6.md C-152; restricciones-no-negociables.md RN-10 y RN-28 (A); brecha-ejecutivo-tecnico.md BR-50 (B); deuda-de-decision.md DD-012, DD-055 y DD-056 (B); verificaciones-fase-6.md, fila 5b y sección del 2026-11-12 (E); ADR-0002 y ADR-0003 (D); adenda del 2026-11-22: acta-2026-11-20-modelo-y-pasarela.md, decisión 32, por delegación de C-236 (C); NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3 (A); registro de npm de conekta-elements-react-native, consultado el 2026-11-22 (E); adenda de la conciliación: decisión 34, por delegación de C-236 (C), y la referencia de la API de órdenes de Conekta, consultada el 2026-11-22 (E); tercera adenda: decisión 38, por delegación de C-236 (C), y la documentación de developers.conekta.com consultada el 2026-11-22 (E)
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

## Adenda del 2026-11-22: dónde se captura la tarjeta y con qué se abre el checkout

**Contexto.**

- El contrato pide que el usuario "pague dentro de la aplicación" (`NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3`). Su
  criterio de aceptación es que Conekta procese transacciones en producción (`NSV-2026-001-v8.pdf:21:ANEXO A, Sección
  A.4.3`). No dice con qué campos ni en qué pantalla se escribe la tarjeta.
- Nadir respondió "sí a ambas" a DD-055 (C-198). Eso incluye que la tarjeta se capture dentro de la app, como en la
  maqueta P5 de "Pago". DD-055 dejó a Arthur decidir eso contra el checkout redirigido. DD-102, punto 1, le dejó decidir
  con qué módulo se abre el checkout.
- **`conekta-elements-react-native` (grado E, registro de npm, consultado el 2026-11-22, `SIN VERIFICAR`):**
  - las versiones 0.1.0 y 0.2.0 son del 18 y del 20 de noviembre de 2026;
  - la 0.2.0 dibuja el formulario nativo de Conekta;
  - pide React Native 0.81 o más nuevo e iOS 15;
  - funciona en un development build de Expo, no en Expo Go;
  - sigue en 0.x y no tiene uso probado.

**Decisión.**

- **1, precisión. La tarjeta se escribe en el checkout de Conekta, presentado dentro de la app.**
  - La vista de navegador del sistema se abre encima de la pantalla de pago, sin cambiar a Safari ni a Chrome. Así se
    paga dentro de la aplicación, como pide el contrato.
  - La tarjeta no pasa por Lavandi (RN-10) y ninguna pantalla de la app tiene campos de tarjeta (pago-E02).
  - **El módulo es `expo-web-browser` (grado E, `SIN VERIFICAR`).** Se usa `openAuthSessionAsync` con la dirección de
    regreso de la app. En iPhone va con `preferEphemeralSession`, para que no salga el aviso de inicio de sesión del
    sistema. Si la tarea 4.1 de `pago` encuentra que no se comporta, la alternativa es `openBrowserAsync` y cerrar la
    vista al recibir el enlace de regreso.
  - Regresar a la app no confirma nada: el pago lo confirma el webhook (decisión 1).
- **Por qué no se toma el formulario nativo de Conekta ahora.** Cumple C-198 al pie de la letra, pero tiene dos días en
  una versión 0.x y no corre en Expo Go, con el que se prueba en iPhone (C-240). Lo que el contrato pide sin detallar se
  resuelve con lo mínimo que cumple su criterio (`CLAUDE.md` §2-bis, punto 4). La maqueta orienta, no amplía (punto 5).
- **Criterio de reversión, precisado.** Se pasa a la opción B cuando `conekta-elements-react-native` publique una
  versión 1.0 o mayor que compile con el Expo de ADR-0002. También, antes, si Nadir confirma que C-198 pide los campos
  de la tarjeta en la app y no basta la vista de Conekta dentro de ella. El costo sigue siendo medio: cambian la
  pantalla de pago y el arranque del cobro, y se conservan la orden, los webhooks y la conciliación.
- **"Procesando pago" consulta el cobro cada 5 s y, a los 3 minutos sin respuesta, deja de esperar.** Muestra que el
  pago sigue en revisión y ofrece volver a consultar. ADR-0007 fija 30 s para la pantalla del pedido; la espera del pago
  es corta y la persona la está mirando.

**La tomó** Claude, por delegación de Arthur (C-236), en la decisión 32 de `acta-2026-11-20-modelo-y-pasarela.md`.
Cierra la segunda parte de DD-055 y el punto 1 de DD-102. Lo que no cierra:
- si la tarjeta se puede guardar como referencia: punto 5 de la decisión, en el sandbox;
- la firma de los webhooks: DD-102, punto 2;
- el cambio de pasarela: DD-079.

## Adenda del 2026-11-22: de dónde salen los movimientos para conciliar

**Contexto.**

- El Módulo D pide "conciliación con Conekta" (`NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3`, Módulo D #6). Este ADR
  cubre los webhooks, no la conciliación. DD-112 le dejó a Arthur de dónde salen los movimientos: la API de órdenes y
  cargos, o el reporte que se descarga del panel de Conekta.
- **La API de órdenes de Conekta (grado E, `developers.conekta.com/reference/getorders`, consultada el 2026-11-22,
  `SIN VERIFICAR`):**
  - lista las órdenes en páginas de 1 a 250, por cursor (`next`, `previous`, `has_more`);
  - filtra por estado del pago y busca por id, cliente, correo, cargo, teléfono o monto;
  - la referencia no muestra un filtro por fecha.

**Decisión.**

- **6. Los movimientos salen de la API de la pasarela, leídos por la misma interfaz neutral del cobro.**
  - La interfaz suma una operación: los pagos del periodo, con el id de la orden, el monto, la comisión si la trae y la
    fecha del pago. La pasarela simulada la implementa en las pruebas.
  - **Con Conekta:**
    - se piden las órdenes pagadas, de la más nueva hacia atrás, hasta pasar el inicio del periodo;
    - el corte por fecha lo hace el servidor;
    - se comprueba en su sandbox, con la tarea de Conekta de `pago`.
- **7. Se leen cuando la administración concilia un periodo, no con una tarea programada.**
  - Se guardan en `movimientos_pasarela`, una fila por orden de la pasarela, sin repetirse aunque se lea dos veces.
  - La tabla no lleva plaza propia: la cuenta de la pasarela es una para todas las plazas, y la plaza de un movimiento
    es la del pedido con el que cuadra. Un movimiento sin pedido es justo una diferencia (administracion-E21).
  - La tabla entra en la lista de tablas sin plaza de `servidor/config/modelo.php`, con esa razón.
- **Descartado: el reporte descargado del panel de Conekta.** Exige un paso a mano cada periodo y depende de un formato
  que el proveedor puede cambiar sin aviso.

**La tomó** Claude, por delegación de Arthur (C-236), en la decisión 34 de `acta-2026-11-20-modelo-y-pasarela.md`.
Cierra DD-112 y desbloquea administracion-E20 y E21. administracion-E22 sigue esperando DD-013: quién absorbe la
comisión de la pasarela.

## Adenda del 2026-11-22 (3): el cliente HTTP de Laravel en lugar de `conekta-php`

**Contexto.**
- La decisión 1 dice que `api` crea la orden "con `conekta-php`".
- Al implementar la pasarela (`pago`, tarea 3.2), la integración resultó ser cuatro llamadas REST:
  - crear la orden;
  - consultarla;
  - listar las órdenes pagadas por cursor;
  - leer los avisos.
- **El SDK (Packagist, consultado el 2026-11-22, grado E, `SIN VERIFICAR`):** la 9.0.0 es del 2026-07-10 y la 7.2.0
  del 2026-07-14.
- **La referencia vigente de `GET /orders`** sí lista filtros por `created_at` y `updated_at`. La segunda adenda decía
  que no mostraba filtro por fecha; su decisión 6 no cambia, porque lo que cuenta es la fecha del pago, que no se
  filtra.

**Decisión.**
- **1, precisión.** `api` habla con la API REST v2.3.0 de Conekta por el cliente HTTP de Laravel (`App\Cobro\PasarelaConekta`),
  sin el SDK.
  - Son las mismas llamadas, sin otra dependencia que vigilar.
  - Las pruebas las simulan con `Http::fake` y `Http::preventStrayRequests()`.
- **Si se prefiere el SDK más adelante,** solo cambia el interior de esa clase: la interfaz del cobro, la base y la API
  no se tocan.

**La tomó** Claude, por delegación de Arthur (C-236), en la decisión 38 de `acta-2026-11-20-modelo-y-pasarela.md`. El
detalle y las diez preguntas para el sandbox están en `openspec/changes/pago/design.md`.
