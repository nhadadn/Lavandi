## ADDED Requirements

### Requirement: Pago con tarjeta de crédito o débito por la pasarela

El sistema DEBE (MUST) permitir que el usuario pague su pedido dentro de la app con tarjeta de crédito o débito a
través de la pasarela, con su checkout redirigido. `servidor/` crea la orden en la pasarela con el monto que él mismo
calcula, nunca con un monto que mande la app, y la app abre el checkout que esa orden devuelve.

Cita: A → `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3` (Módulo A #10: "Conekta: tarjeta de crédito o débito y OXXO
Pay") y `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` ("Permite que el usuario pague dentro de la aplicación") · D →
ADR-0010 (el mecanismo y la pasarela vigente, Conekta; si el cliente firma el cambio, la elegida entra con su ADR,
DD-079)

#### Scenario: pago-E01 — Crear el checkout de un cobro pendiente

- **DADO** un pedido del usuario en el estado previo al pago, con un cobro pendiente de monto M preparado en la prueba
- **CUANDO** el usuario pide pagarlo con tarjeta, aunque su petición traiga otro monto
- **ENTONCES** `servidor/` crea en la pasarela simulada una orden por M, ligada a ese cobro por su `id_pasarela_orden`
- **Y** devuelve la URL del checkout
- **Y** si el cobro ya tenía un checkout vigente, devuelve ese mismo en lugar de crear otro

Con la pasarela simulada, este escenario no depende del proveedor. La misma orden contra el sandbox de Conekta, la
pasarela vigente, es la tarea 3.3.

#### Scenario: pago-E02 — La app abre el checkout sin pedir la tarjeta

- **DADO** la pantalla de pago de un pedido con un cobro pendiente
- **CUANDO** el usuario toca pagar con tarjeta
- **ENTONCES** la app pide el checkout a `servidor/` mandando solo el pedido y el método
- **Y** abre la URL que recibe en el navegador del sistema, sin que el usuario salga de la app
- **Y** ninguna pantalla de la app tiene campos de número de tarjeta, vencimiento o código de seguridad

Criterio: SEG-4

Cita: D → adenda del 2026-11-22 de ADR-0010, asentada en la decisión 32 de `acta-2026-11-20-modelo-y-pasarela.md`
(Claude, por delegación de Arthur, C-236): la tarjeta se escribe en el checkout de la pasarela, en la vista de navegador
del sistema presentada dentro de la app con `expo-web-browser` (`openAuthSessionAsync` con la dirección de regreso; en
iPhone, con `preferEphemeralSession`). Así el usuario paga dentro de la aplicación (`NSV-2026-001-v8.pdf:19:ANEXO A,
Sección A.3`) y ninguna pantalla de la app tiene campos de tarjeta. Cierra la segunda parte de DD-055 y el punto 1 de
DD-102. Si Nadir confirma que C-198 pide los campos de la tarjeta en la app, se pasa al formulario nativo de la pasarela,
como dice el criterio de reversión de la adenda.

#### Scenario: pago-E03 — Pago con tarjeta aprobado

- **DADO** un cobro pendiente con su checkout creado
- **CUANDO** llega a `servidor/` el aviso auténtico de la pasarela (webhook) que confirma el pago de esa orden
- **ENTONCES** el cobro queda `pagado`, con su `pagado_en`
- **Y** `servidor/` responde 200 al aviso

Criterio: A43-2. A43-4 avanza a `implementado` solo con la tarea 3.3, contra el sandbox de Conekta, la pasarela
vigente; en producción se acredita en `publicacion`.

#### Scenario: pago-E04 — Pago con tarjeta rechazado

- **DADO** un cobro pendiente con su checkout creado
- **CUANDO** llega el aviso auténtico de la pasarela que dice que el pago se rechazó
- **ENTONCES** el cobro sigue sin pagar y el pedido sigue en el estado previo al pago, sin folio
- **Y** el usuario puede volver a intentar el pago del mismo cobro

Criterio: A43-2

### Requirement: Ningún número de tarjeta en la plataforma

La plataforma NO DEBE (MUST NOT) guardar nunca números de tarjeta, fechas de vencimiento ni códigos de seguridad. La
tarjeta la captura y la resguarda la pasarela; de cada pago, la base de datos solo guarda la referencia que devuelve la
pasarela (`id_pasarela_orden`) y cuál fue (`pasarela`).

Cita: A → `NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3` (Datos de tarjeta) · D → ADR-0016

#### Scenario: pago-E05 — El esquema no tiene dónde guardar una tarjeta

- **DADO** la base de datos con todas las migraciones aplicadas
- **CUANDO** la prueba recorre todas sus tablas y columnas
- **ENTONCES** ninguna columna está hecha para un número de tarjeta, una fecha de vencimiento o un código de seguridad
- **Y** lo único que liga un pago con la pasarela es su `id_pasarela_orden`, junto con la columna `pasarela`

Criterio: SEG-4

#### Scenario: pago-E06 — Pagar con una tarjeta guardada

- **DADO** un usuario que ya pagó un pedido con tarjeta
- **CUANDO** paga un pedido nuevo
- **ENTONCES** puede usar esa tarjeta como una referencia guardada en la pasarela
- **Y** la plataforma sigue sin conocer su número

SIN RESPUESTA — bloquea: pago-E06 (DD-055, DD-079). DD-055 ya la respondió Nadir (C-198): se guarda solo la referencia
de la pasarela, nunca el número. Falta: si el checkout de la pasarela vigente, Conekta, permite reutilizarla (ADR-0010,
punto 5: se verifica en su sandbox y, si no, la v1 cobra cada vez), y si la tarjeta se captura dentro de la app, que es
lo de pago-E02. Si se puede reutilizar, nace `metodos_pago_guardados` en `perfil-y-soporte`.

### Requirement: El pago se confirma solo en el servidor

`servidor/` DEBE (MUST) dar un cobro por pagado únicamente cuando recibe la confirmación de la pasarela por webhook.
Ninguna petición de la app, ni su regreso desde el checkout, marca un cobro como pagado. Mientras la confirmación no
llega, la app muestra que el pago se está procesando.

Cita: A → `NSV-2026-001-v8.pdf:29:ANEXO B, Sección B.4` ("verifica los pagos") · D → ADR-0010 (punto 1)

#### Scenario: pago-E07 — Volver del checkout no confirma el pago

- **DADO** un cobro pendiente con su checkout creado
- **CUANDO** la app avisa a `servidor/` que volvió del checkout por la dirección de éxito, o manda cualquier petición
  que diga que ya pagó
- **ENTONCES** el cobro sigue pendiente
- **Y** solo un aviso auténtico de la pasarela lo puede marcar como pagado

#### Scenario: pago-E08 — La app espera la confirmación del servidor

- **DADO** que el usuario volvió a la app desde el checkout de la pasarela
- **CUANDO** la app consulta el cobro en `servidor/` y sigue pendiente
- **ENTONCES** muestra "Procesando pago" y sigue consultando
- **Y** cuando `servidor/` lo da por pagado, o informa que el intento se rechazó, la app muestra ese resultado

### Requirement: El pago acreditado confirma el pedido; sin pago no hay recolección

Cuando un cobro queda `pagado`, `servidor/` DEBE (MUST) pasar su pedido a Confirmado en la misma transacción, con la
operación de confirmar de `resumen-y-confirmacion`. Un pedido sin un cobro `pagado` NO DEBE (MUST NOT) confirmarse, y
por eso no llega a la lavandería ni al repartidor y no se recolecta.

Cita: C → C-188 (Arthur: "no se va a recolectar si no ha sido pagado") y C-197 (Nadir: "se cobra al estimar"), que
son la regla · A, como apoyo y sin que digan la regla → `NSV-2026-001-v8.pdf:16-17:ANEXO A, Sección A.3` (Módulo A
#9 a #11: resumen, pago y confirmación con folio, en ese orden) y `NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3`
("elegir lavandería, agendar recolección, pagar y dar seguimiento") · D → ADR-0010 (punto 1)

#### Scenario: pago-E18 — El pago acreditado confirma el pedido

- **DADO** un pedido en el estado previo al pago, con su cobro pendiente
- **CUANDO** se procesa el aviso auténtico de la pasarela que confirma ese cobro
- **ENTONCES** el cobro queda `pagado` y el pedido pasa a Confirmado, con folio y `confirmado_en`, en la misma
  transacción
- **Y** el aviso queda en `eventos_pasarela` como procesado

Criterio: A43-2

#### Scenario: pago-E19 — Sin pago acreditado el pedido no se confirma

- **DADO** un pedido en el estado previo al pago cuyo cobro está `pendiente`, `fallido` o `vencido`
- **CUANDO** cualquier petición o componente intenta pasarlo a Confirmado
- **ENTONCES** `servidor/` lo rechaza
- **Y** el pedido sigue sin folio y sin `confirmado_en`

Que un pedido sin confirmar no llega a la lavandería ni al repartidor lo prueban `pedidos-de-la-lavanderia` y
`repartidor`.

### Requirement: Avisos de la pasarela auténticos y procesados una sola vez

`servidor/` DEBE (MUST) comprobar que cada aviso (webhook) viene de la pasarela antes de usarlo, y procesar cada evento
una sola vez: lo guarda en `eventos_pasarela` y, si el mismo evento llega dos veces, la segunda no cambia nada. A cada
evento auténtico responde 200.

Cita: D → ADR-0010 (punto 2) y ADR-0016 (regla 10: `eventos_pasarela`) · A → `NSV-2026-001-v8.pdf:29:ANEXO B, Sección
B.4` ("verifica los pagos")

#### Scenario: pago-E09 — Un aviso que no viene de la pasarela se rechaza

- **DADO** un cobro pendiente
- **CUANDO** llega al webhook una petición que dice que ese cobro se pagó, sin la prueba de autenticidad de la pasarela
- **ENTONCES** `servidor/` la rechaza sin responder 200
- **Y** el cobro sigue pendiente

Con la pasarela vigente, Conekta, la prueba de autenticidad es la que fija ADR-0010, punto 2: su firma, que está por
confirmar en su sandbox. Si no firma sus avisos, la alternativa que anota DD-102, punto 2, es consultar la orden antes
de darla por pagada. Se confirma en la tarea 3.1. Si el cliente firma el cambio de pasarela, la elegida trae la suya en
su ADR (DD-079).

#### Scenario: pago-E10 — El mismo evento llega dos veces

- **DADO** un aviso auténtico de pago ya procesado y guardado en `eventos_pasarela`
- **CUANDO** la pasarela lo vuelve a mandar con el mismo `id_pasarela_evento`
- **ENTONCES** `servidor/` responde 200
- **Y** no duplica el pago ni cambia nada, porque el único `(pasarela, id_pasarela_evento)` lo impide también en la base

### Requirement: Cada pago queda registrado para la conciliación

`servidor/` DEBE (MUST) guardar de cada pago confirmado la pasarela que cobró, la referencia de su orden, el monto, el
método (tarjeta o efectivo), la fecha de confirmación, el pedido y su plaza, para que el Módulo D lo concilie con la
pasarela.

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #6: "conciliación con Conekta"),
`NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3` (la base de datos guarda "pagos") y `NSV-2026-001-v8.pdf:4:Cláusula I`
(la plaza, por el modelo multi-plaza) · D → ADR-0016

#### Scenario: pago-E11 — Pago confirmado con su referencia de la pasarela

- **DADO** un cobro de un pedido de la plaza P
- **CUANDO** se procesa el aviso auténtico que confirma su pago
- **ENTONCES** el pago queda guardado con su `pasarela`, su `id_pasarela_orden`, su `monto`, su `metodo`, su
  `pagado_en`, su `id_pedidos` y la plaza P en `id_plazas`

### Requirement: Pago en efectivo

El sistema DEBE (MUST) permitir que el usuario pague su pedido en efectivo a través de la pasarela: recibe una ficha o
referencia para pagar en tienda, y el cobro queda pendiente hasta que la pasarela confirme el pago al servidor.

Cita: A → `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3` (Módulo A #10), `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3`
("generar fichas de pago en tiendas OXXO") y `NSV-2026-001-v8.pdf:45:ANEXO D, Sección D.3` (OXXO Pay)

#### Scenario: pago-E12 — Ficha para pagar en efectivo

- **DADO** un pedido con un cobro pendiente
- **CUANDO** el usuario elige pagar en efectivo
- **ENTONCES** recibe en la app la ficha o referencia para pagar en tienda, guardada en `referencia_efectivo`
- **Y** el cobro queda pendiente hasta que llegue el aviso auténtico de la pasarela que confirma el pago

SIN RESPUESTA — bloquea: pago-E12 (DD-056). Qué efectivo se ofrece lo decide EL CLIENTE por escrito, firmado por ambas
partes. La pasarela es Conekta mientras no se firme el cambio; con la elegida, si se firma el cambio (DD-079).

#### Scenario: pago-E13 — Ficha vencida sin pagar

- **DADO** una ficha de efectivo que el usuario no pagó
- **CUANDO** llega su `vence_en`, o la pasarela avisa que venció
- **ENTONCES** el cobro queda `vencido`, sin pagar
- **Y** el pedido no se confirma ni se recolecta; lo demás que le pase lo fija el modelo de cobro

SIN RESPUESTA — bloquea: pago-E13 (DD-056, DD-012). Qué pasa con el pedido mientras la ficha espera el pago y cuando
vence (BR-20, DD-123).

#### Scenario: pago-E20 — Cuándo vence la referencia de efectivo

- **DADO** un pedido con su ventana de recolección y un cobro en efectivo pendiente
- **CUANDO** se genera su ficha o referencia
- **ENTONCES** su `vence_en` queda como lo fijen DD-012 y DD-056
- **Y** si llega `vence_en` sin pago, el cobro queda `vencido` y el pedido no se recolecta

SIN RESPUESTA — bloquea: pago-E20 (DD-012, DD-056). Que el efectivo venza antes de la ventana de recolección es una
pregunta abierta (DD-012, DD-056), no una regla: no sale del contrato, de un acta ni de un ADR. Cuándo vence la
referencia lo fijan DD-012 y DD-056; si la pasarela deja fijar ese vencimiento se verifica con el efectivo que elija
DD-056.

### Requirement: Cuándo se cobra, por cuánto y cómo se ajusta

El sistema DEBE (MUST) cobrar cada pedido antes de recolectarlo, después de que el usuario acepta el resumen, por el
monto que fije el modelo de cobro de EL CLIENTE a partir del precio estimado, y aplicar por la pasarela los ajustes y
reembolsos que ese modelo indique.

Cita: A → `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3` (Módulo A #9: "precio estimado"; #10) y
`NSV-2026-001-v8.pdf:16-17:ANEXO A, Sección A.3` (#9 a #11, en ese orden; el orden apoya la regla, no la dice) · C → C-188 (Arthur) y C-197 (Nadir), que son la
regla · D → ADR-0010 (punto 3)

#### Scenario: pago-E14 — Momento y monto del cobro

- **DADO** un pedido recién creado al aceptar el resumen, en el estado previo al pago, con su precio estimado
- **CUANDO** el usuario elige cómo pagar
- **ENTONCES** `servidor/` crea su cobro pendiente, de `tipo` `cobro`, por el monto que fije el modelo de cobro
- **Y** el pedido no se recolecta mientras ese cobro no quede `pagado`

Criterio: A43-2

SIN RESPUESTA — bloquea: pago-E14 (DD-012, DD-013). El momento ya lo fijaron C-188 y C-197: antes de recolectar. Falta el monto:
cómo se estima el peso antes de recolectar, si el envío va en el mismo cobro y si el monto incluye la comisión de la
pasarela o la absorbe alguien más.

#### Scenario: pago-E15 — Ajuste cuando el monto final es distinto del estimado

- **DADO** un pedido pagado con el monto estimado
- **CUANDO** se conoce su monto final y es distinto del estimado
- **ENTONCES** `servidor/` cobra la diferencia, con otra fila de `pagos` de `tipo` `ajuste`, o reembolsa el excedente,
  con una fila de `reembolsos`, por la pasarela y según el modelo de cobro

SIN RESPUESTA — bloquea: pago-E15 (DD-012). ADR-0010, punto 3, prevé el cargo adicional o el reembolso parcial con las
operaciones de órdenes y reembolsos de Conekta, la pasarela vigente, solo si DD-012 lo confirma. Con la elegida, si se
firma el cambio (DD-079).

#### Scenario: pago-E16 — Reembolso al cancelar o al rechazar el pedido

- **DADO** un pedido pagado
- **CUANDO** el pedido se cancela, o la lavandería lo rechaza
- **ENTONCES** `servidor/` reembolsa por la pasarela lo que fijen la política de cancelación y el modelo de cobro, con
  una fila de `reembolsos` que no pasa de lo pagado

SIN RESPUESTA — bloquea: pago-E16 (DD-012, DD-016). Qué pasa con el pago si la lavandería rechaza el pedido es DD-103.
El reembolso va por Conekta, la pasarela vigente; con la elegida, si se firma el cambio (DD-079).

### Requirement: Llaves de la pasarela por entorno

`servidor/` DEBE (MUST) tomar las llaves de la pasarela de la configuración de cada entorno, nunca del código: en local
y en prueba, las de su sandbox; en producción, las de la cuenta de EL CLIENTE.

Cita: D → ADR-0012 · A → `NSV-2026-001-v8.pdf:34:ANEXO B, Sección B.11` (cuentas a nombre de EL CLIENTE) y
`NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3` ("en modo producción")

#### Scenario: pago-E17 — La llave sale de la configuración del entorno

- **DADO** el entorno de pruebas automáticas, con una llave de prueba en su configuración
- **CUANDO** `servidor/` crea una orden en la pasarela simulada
- **ENTONCES** la petición va autenticada con la llave de esa configuración
- **Y** el código de `servidor/` no contiene ninguna llave de la pasarela escrita a mano
