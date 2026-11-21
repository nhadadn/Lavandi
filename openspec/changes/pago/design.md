## Context

- **Lo que pide el contrato (A):** pagar dentro de la app, con tarjeta y en efectivo; el contrato nombra a Conekta y a
  OXXO Pay (`NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3` y `:19`); el servidor "verifica los pagos" (`:29:ANEXO B,
  Sección B.4`); los números de tarjeta nunca se guardan (`:20:ANEXO A, Sección A.3`); la pasarela procesa
  transacciones en producción (`:21:ANEXO A, Sección A.4.3`).
- **Lo que fijó Arthur (C):**
  - C-188: "no se va a recolectar si no ha sido pagado". El pedido nace antes del pago (`resumen-y-confirmacion`) y se
    confirma con él. El orden del contrato, resumen (#9), pago (#10) y confirmación con folio (#11), es coherente con
    la regla, pero no la dice.
  - C-192: Stripe y Mercado Pago son candidatas para sustituir a Conekta (DD-079). El texto normativo, las tablas y las
    columnas no nombran proveedor (`eventos_pasarela`, `id_pasarela_orden`, columna `pasarela`), para que cambiarla no
    toque la base ni la API.
- **Lo que fijó Nadir (C):** C-197, "se cobra al estimar"; C-198, la tarjeta se captura dentro de la app y se guarda solo
  la referencia de la pasarela (DD-055); C-216, EL CLIENTE dio un OK expreso al cambio de pasarela, pero la firma sigue
  pendiente. Por las Cláusulas IV y XIV (`NSV-2026-001-v8.pdf:8:Cláusula IV`; `NSV-2026-001-v8.pdf:12:Cláusula XIV`), el
  OK no sustituye la firma.
- **La pasarela vigente es Conekta** mientras el cliente no firme el cambio (`CLAUDE.md` §2-bis, punto 3; DD-079). Por
  eso su integración no está bloqueada por DD-079: entra detrás de la interfaz neutral. Si se firma el cambio, la elegida
  se agrega con su ADR. Lo que no se construye es nada de Stripe ni de Mercado Pago (`CLAUDE.md` §2-bis, punto 7).
- **Lo que ya decidió ADR-0010 (D), vigente:** Conekta con su checkout redirigido; el cobro se da por bueno solo con el
  webhook; webhooks con respuesta 200 e idempotentes, con la firma validada en el sandbox antes de producción. Si se
  firma el cambio de pasarela, el ADR de la elegida lo reemplazará.
- **Lo que fijan ADR-0012 y ADR-0016 (D):** sandbox en local y en prueba, producción con la cuenta de EL CLIENTE; las
  tablas del dinero y la regla de que ningún nombre de tabla o columna nombra a un proveedor (regla 10).
- **Lo abierto:** DD-079 (el cambio de pasarela, contractual: OK expreso, firma pendiente), DD-012 (el monto, el ajuste
  y si EL CLIENTE acepta C-188), DD-013 (liquidación y comisión), DD-016 (cancelación), DD-055 (respondida por C-198;
  falta decidir la captura dentro de la app con el ADR de la pasarela), DD-056 (efectivo), DD-102 (precisiones de
  ADR-0010) y DD-123 (el estado previo al pago).

## Goals / Non-Goals

**Goals:**

- Construir lo que no depende del proveedor ni de DD-012: las tablas, el checkout de un cobro dado contra la pasarela
  simulada, los avisos procesados una sola vez, la confirmación solo en el servidor, el pedido confirmado por su pago,
  el registro para la conciliación, la ausencia de datos de tarjeta y las llaves por entorno.
- Integrar Conekta, la pasarela vigente, detrás de la interfaz neutral: la autenticidad de sus avisos, su orden y su
  checkout, la traducción de sus avisos y la prueba contra su sandbox (sección 3 de `tasks.md`).
- Que la app espere la confirmación sin dar el pago por bueno. Cómo abre el pago la app espera pago-E02 (DD-055,
  DD-079).

**Non-Goals:**

- Integrar Stripe, Mercado Pago o cualquier otra pasarela distinta de Conekta mientras el cliente no firme el cambio
  (DD-079; `CLAUDE.md` §2-bis, punto 7). Si se firma, la elegida entra con su ADR detrás de la misma interfaz.
- Cuánto se cobra, ajustes y reembolsos (DD-012, DD-013, DD-016).
- El efectivo (DD-056) y la tarjeta guardada (DD-055).
- La conciliación y los reportes financieros (`administracion`), la liquidación a socios (DD-013) y la factura (BR-31).

## Decisions

No hay decisión técnica nueva: lo sostienen ADR-0010 (el mecanismo), ADR-0012 (las llaves), ADR-0007 (la consulta
periódica) y ADR-0016 (las tablas). Consecuencias para escribir los tests:

1. **El cobro es una fila de `pagos`,** ligada al pedido y con su plaza (`CLAUDE.md` §9.5). En las pruebas se siembra;
   en el flujo real lo crea pago-E14, cuando DD-012 fije el monto.
2. **La pasarela vive detrás de una interfaz del componente `cobro`** (`c3-api.md`: "Habla con la pasarela"): crear la
   orden y su checkout, y traducir un aviso a un evento sin proveedor (aprobado, rechazado o vencido). Las pruebas usan
   una pasarela simulada. Ya lo pedía este diseño, que la llamada a la pasarela viviera en un solo lugar de `servidor/`,
   y lo piden C-192 y ADR-0016, regla 10. La implementación real de la interfaz es la de Conekta, según ADR-0010
   (tarea 3.2); si se firma el cambio de pasarela, la elegida se agrega como otra implementación, con su ADR, sin tocar
   la base ni la API.
3. **Un aviso se guarda primero en `eventos_pasarela`** y se procesa una vez. El único `(pasarela,
   id_pasarela_evento)` hace que un aviso repetido choque en la base, y `servidor/` responde 200 sin procesarlo otra vez
   (pago-E10).
4. **Pago y pedido en la misma transacción.** Al procesar un aviso de aprobado, `servidor/` marca el cobro `pagado`, con
   `pagado_en`, y llama a la operación de confirmar de `resumen-y-confirmacion`. Esa operación comprueba que el pedido
   tenga un cobro `pagado` (pago-E19); la regla nace aquí porque `pagos` nace aquí.
5. **La ruta del webhook no lleva sesión de usuario.** Declara en el componente de autorización que es pública con
   verificación de la pasarela (autorizacion-E01).
6. **Las pruebas automáticas simulan la pasarela,** como manda `vault/formas-de-trabajo/ciclo-openspec-tdd.md`. La
   prueba contra el sandbox de Conekta es la tarea 3.3, con la cuenta de prueba de Arthur (C-170); es la que lleva A43-4
   a `implementado`.

## Tablas y migración

**Crea** tres tablas, en una migración que pasa la prueba de esquema de `modelo-de-datos` (modelo-E01 a modelo-E17).
Las columnas son las de `vault/arquitectura/modelo-de-datos.md`. Toda foránea lleva su índice y `ON DELETE RESTRICT`.
Las tres llevan `created_at` y `updated_at` en `timestamptz` (modelo-E17; ADR-0016, regla 7).

**`pagos`:**

- `id_pagos`, `id_publico`, `id_pedidos`, `id_plazas`, `tipo`, `metodo`, `pasarela`, `monto`, `estado`,
  `id_pasarela_orden`, `referencia_efectivo`, `vence_en`, `pagado_en`.
- Foránea: `(id_pedidos, id_plazas)` a `pedidos(id_pedidos, id_plazas)`, el único que deja `resumen-y-confirmacion`.
- Únicos: `id_publico`; `(pasarela, id_pasarela_orden)`; y `(id_pagos, id_plazas)`, para la foránea compuesta de
  `reembolsos`.
- `CHECK`: `tipo` en `cobro` o `ajuste`; `metodo` en `tarjeta` o `efectivo`; `estado` en `pendiente`, `pagado`,
  `vencido`, `fallido` o `cancelado`; `monto` mayor que cero.
- `pasarela` dice cuál cobró. Su valor es `conekta`, la del contrato; si se firma el cambio de pasarela (DD-079), la
  elegida se agrega en una migración hacia adelante, con su ADR. En las pruebas, la pasarela simulada usa un valor de
  prueba (ver Open Questions).
- Opcionales: `id_pasarela_orden` (hasta que se crea la orden), `referencia_efectivo` y `vence_en` (solo efectivo,
  DD-056) y `pagado_en` (hasta el aviso de aprobado).
- Ningún dato de tarjeta (pago-E05, modelo-E13).
- Trigger: `impedir_cambio()` en `BEFORE DELETE`.

**`reembolsos`:**

- `id_reembolsos`, `id_pagos`, `id_plazas`, `monto`, `motivo`, `id_pasarela_reembolso`, `estado`, `reembolsado_en`.
- Foránea: `(id_pagos, id_plazas)` a `pagos(id_pagos, id_plazas)`. La pasarela es la de su pago.
- `CHECK`: `monto` mayor que cero. Que no pase de lo pagado mira otra fila, así que no cabe en un `CHECK`: lo valida la
  transacción que registra el reembolso (pago-E16).
- `estado`: el modelo no fija sus valores (ver Open Questions).
- Trigger: `impedir_cambio()` en `BEFORE DELETE`.
- Nace vacía: la usan pago-E15 y pago-E16, los dos bloqueados.

**`eventos_pasarela`** (sin plaza, en la lista de excepciones de `servidor/config/modelo.php`):

- `id_eventos_pasarela`, `pasarela`, `id_pasarela_evento`, `tipo`, `contenido` (`jsonb`), `recibido_en`,
  `procesado_en`, `error`.
- Único: `(pasarela, id_pasarela_evento)`.
- Opcionales: `procesado_en` (hasta procesarlo) y `error` (si falló).
- Sin trigger: no está entre las cuatro tablas del historial.

**Modelos de Eloquent,** sobre la clase base `App\Models\Modelo`, cada uno con su `$table` y su `$primaryKey`: `Pago`
(con `HasUlids` para `id_publico`), `Reembolso` y `EventoPasarela`.

**Usa, sin crearlas:**

| Tabla | Para qué | La crea |
|---|---|---|
| `pedidos`, `pedido_eventos` | El pedido que se cobra, su `ventana_inicio` y su paso a Confirmado, por la operación de confirmar | `resumen-y-confirmacion` |
| `plazas` | La plaza del cobro | `plazas-y-zonas` |
| `usuarios` | Quién paga, por su pedido | `registro-e-inicio-de-sesion` |

**Lo que no crea:** `metodos_pago_guardados` (⛔, `perfil-y-soporte`: C-198 dijo que sí; espera lo mismo que pago-E06, DD-055 y
DD-079) y `movimientos_pasarela`
(⛔, `administracion` con DD-112).

## Risks / Trade-offs

- [El cliente firma el cambio de pasarela (DD-079)] → La elegida se agrega con su ADR como otra implementación de la
  misma interfaz y con un valor nuevo de `pasarela`; la base y la API no cambian. Qué pasa con la implementación de
  Conekta lo dice ese ADR. Mientras no se firme, no se construye nada de Stripe ni de Mercado Pago.
- [El efectivo sigue abierto (DD-056)] → pago-E12, pago-E13 y pago-E20 quedan bloqueados. **El resultado de H4 que
  promete el plan, "pago con tarjeta y en efectivo en el sandbox", no está listo:** la tarjeta ya se integra en
  `servidor/` con el sandbox de Conekta, pero la pantalla que la cobra espera pago-E02 (DD-055, DD-079), y el efectivo
  espera DD-056. El plan ya dice que, si DD-012 y DD-079 no llegan antes del 18 de enero, H4 se salta y entra H5.
- [Conekta, o la que se elija si se firma el cambio, no permite apartar el monto, ni ajustes, ni fijar el vencimiento
  del efectivo] → Con Conekta se ve en su sandbox (tareas 3.2 y 3.3; el efectivo, cuando DD-056 lo desbloquee); con la
  elegida, al escribir su ADR. Si cambia el mecanismo, cambian pago-E01, pago-E15 y pago-E20.
- [El aviso de la pasarela trae datos de la tarjeta en su `contenido`] → La prueba de esquema mira columnas, no el
  `jsonb`. Antes de guardar `contenido` se quita cualquier dato de tarjeta que no sea la marca y los últimos cuatro
  dígitos (`:20:A.3`; modelo-E13); qué trae cada aviso se confirma con las cargas del sandbox de Conekta (tarea 3.2) y,
  si se firma el cambio, con las de la elegida.
- [El ADR de la pasarela decide capturar la tarjeta dentro de la app (C-198), en lugar del checkout redirigido] →
  Cambian pago-E02, pago-E01, pago-E08 y la tarea 3.2, es decir, la pantalla de pago y el arranque del cobro; la orden,
  los avisos y la conciliación se conservan (ADR-0010, costo estimado de revertir).
- [El usuario vuelve a la app antes que el webhook] → pago-E07 y pago-E08: la app espera; el servidor no se fía de la
  app.
- [A43-4 necesita la cuenta de EL CLIENTE en la pasarela, que exige cuenta bancaria empresarial y no se ha tramitado
  (`:31:ANEXO B, Sección B.6`; `:34:ANEXO B, Sección B.10`)] → Se acredita en `publicacion`. Los trámites son DD-084.
- [Las comisiones de la pasarela (`:30:ANEXO B, Sección B.6`)] → Quién las absorbe es DD-013; el monto del cobro no se
  toca en este cambio.

## Open Questions

- **Lo que ADR-0010 deja por confirmar en el sandbox de Conekta:** cómo autentica sus avisos (pago-E09; ADR-0010,
  punto 2; DD-102, punto 2), cómo se llaman sus eventos de aprobado, rechazado y vencido (tarea 3.2) y si permite
  reutilizar la tarjeta (pago-E06; ADR-0010, punto 5). Si deja fijar el vencimiento del efectivo (pago-E20) espera
  DD-012 y DD-056, y cómo hace ajustes y reembolsos (pago-E15 y E16; ADR-0010, punto 3) espera DD-012 y DD-016.
- **DD-079: el cambio de pasarela.** EL CLIENTE dio un OK expreso y la firma sigue pendiente (C-216). Si se firma, el
  ADR de la elegida (Stripe o Mercado Pago, C-192) reemplaza a ADR-0010 y responde lo mismo para ella.
- **El `CHECK` de `pasarela` y la pasarela simulada.** El valor real es `conekta`. Si el `CHECK` solo admite ese valor,
  la pasarela simulada de las pruebas no puede usar uno propio. Lo resuelve Arthur al escribir la tarea 1.1.
- **Contrato contra ADR-0010: ¿se paga dentro de la app?** El contrato dice "Permite que el usuario pague dentro de la
  aplicación" (`NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3`); ADR-0010 dice que "el usuario sale un momento de la app
  para pagar". Manda el contrato, y pago-E02 lo escribe así: el checkout se abre en la vista de navegador del sistema
  presentada dentro de la app, no cambiando a Safari o a Chrome. Con qué módulo (por ejemplo `expo-web-browser`, grado
  E, `SIN VERIFICAR`) y cómo se ve en iPhone y en Android lo decide Arthur (DD-102, punto 1). Además, C-198 dice que la
  tarjeta se captura dentro de la app, lo que choca con el checkout redirigido de ADR-0010: capturarla dentro de la app
  o redirigir se decide con el ADR de la pasarela (DD-055, DD-079). **pago-E02 queda bloqueado hasta entonces.**
- **Las llaves de Conekta en `verificar_secretos.py`** (DD-102, punto 3): hoy no las detecta. Recomendación: agregarlas
  antes de que existan llaves de su sandbox en la máquina local (tarea 3.3). Las de la elegida, si se firma el cambio.
- **Cada cuánto consulta la app en "Procesando pago".** ADR-0007 fija 30 s para la pantalla del pedido; para el pago no
  hay cifra. Si Arthur elige otra, se anota en el ADR de la pasarela.
- **El regreso a la app.** Las direcciones de éxito y de error del checkout usan el esquema de la app, que sale de la
  configuración; nunca la marca escrita a mano (`CLAUDE.md` §5).
- **El estado de un intento rechazado.** pago-E04 deja reintentar el mismo cobro; si el cobro sigue `pendiente` o pasa a
  `fallido` y se abre otro lo fija Arthur al escribir la tarea 2.5.
- **Los valores de `reembolsos.estado`.** El modelo no los da; se fijan con pago-E16 y el ADR de la pasarela.
- **`created_at` y `updated_at`.** Resuelta: van en las tres tablas, en `timestamptz` (modelo-E17; ADR-0016, regla 7).
