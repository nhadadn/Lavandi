## ADDED Requirements

### Requirement: Los cinco estados que ve el usuario

El seguimiento del pedido DEBE (MUST) darle al usuario, en la api y en la app, los cinco estados del contrato, con esos nombres y en este orden: Confirmado, Recolectado, En proceso, Listo y Entregado. El pedido entra al flujo en Confirmado cuando la pasarela confirma su pago (C-188); antes de eso está en un estado interno, que fija la máquina de estados (DD-123) y que no es uno de los cinco. Mientras el pedido siga en el flujo, el usuario ve siempre uno de los cinco como estado actual, con su nombre escrito, y ningún nombre que no esté entre ellos.

Cita: A → `NSV-2026-001-v8.pdf:16-17:ANEXO A, Sección A.3` (Módulo A, numerales 10, 11 y 12); D → ADR-0016 (`pedidos.confirmado_en` se llena cuando la pasarela confirma el pago)

#### Scenario: estados-E01 — La api entrega el pedido recién confirmado en Confirmado

- **DADO** un pedido cuyo pago acaba de confirmar la pasarela, con su folio (Módulo A, numerales 10 y 11)
- **CUANDO** el usuario consulta ese pedido a la api
- **ENTONCES** la api responde "Confirmado" como estado actual
- **Y** entrega los cinco estados en el orden del contrato

#### Scenario: estados-E02 — La pantalla de seguimiento muestra los cinco estados

- **DADO** un pedido del usuario en cualquiera de los cinco estados, de Confirmado a Entregado
- **CUANDO** el usuario abre el seguimiento de ese pedido en la app
- **ENTONCES** ve el estado actual con su nombre escrito, los anteriores como cumplidos y los siguientes como pendientes
- **Y** no ve ningún nombre de estado fuera de los cinco

#### Scenario: estados-E24 — Cada estado cumplido lleva su hora

- **DADO** un pedido del usuario que ya pasó por uno o más de los cinco estados
- **CUANDO** el usuario consulta el pedido o abre su seguimiento
- **ENTONCES** cada estado cumplido, y el actual, trae la hora en que el pedido llegó a él, en la hora de la plaza
- **Y** los estados pendientes no traen hora

Cita: A → `NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3`, Módulo A #12 · C → decisión 25 del acta del 2026-11-20 (DD-135,
punto 4, por delegación C-236)

### Requirement: Los botones de la lavandería

La app de la lavandería DEBE (MUST) ofrecer, sobre cada pedido que aceptó, los botones Recolectado, En proceso, Listo y En proceso de entrega, en ese orden. Solo se puede tocar el botón del paso siguiente, y la api DEBE (MUST) rechazar un cambio que salte un paso o que regrese a uno anterior.

Cita: A → `NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3` (Módulo B, numerales 2 y 3)

#### Scenario: estados-E03 — La lavandería marca Recolectado

- **DADO** un pedido en Confirmado que la lavandería aceptó
- **CUANDO** la lavandería marca "Recolectado"
- **ENTONCES** la api guarda el pedido en Recolectado

#### Scenario: estados-E04 — La lavandería avanza en el orden del contrato

- **DADO** un pedido aceptado por la lavandería, en Recolectado
- **CUANDO** la lavandería marca, uno tras otro, "En proceso", "Listo" y "En proceso de entrega"
- **ENTONCES** la api guarda cada cambio en ese orden
- **Y** el pedido queda en En proceso de entrega

#### Scenario: estados-E05 — La api rechaza un salto o un regreso

- **DADO** un pedido aceptado por la lavandería, en Recolectado
- **CUANDO** la lavandería intenta marcarlo "Listo" sin pasar por "En proceso", o regresarlo a Confirmado
- **ENTONCES** la api rechaza el cambio
- **Y** el pedido sigue en Recolectado

#### Scenario: estados-E06 — La app de la lavandería ofrece solo el paso siguiente

- **DADO** un pedido aceptado, en En proceso, en la app de la lavandería
- **CUANDO** la lavandería abre ese pedido
- **ENTONCES** el único botón que puede tocar es "Listo"

### Requirement: Solo quien tiene el pedido lo mueve o lo consulta

La api DEBE (MUST) aceptar un cambio de estado solo de quien tiene el pedido: en este cambio, la lavandería que lo aceptó; el repartidor asignado entra con el cambio `repartidor`. Ni otra lavandería ni el usuario pueden mover el pedido, y la lavandería no mueve un pedido que todavía no acepta o que rechazó. Cada usuario consulta solo el estado de sus propios pedidos.

Cita: A → `NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3` (Separación por tipo de usuario) y `NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3` (Módulo B, numeral 2)

#### Scenario: estados-E07 — Otra lavandería no puede mover el pedido

- **DADO** un pedido aceptado por la lavandería A
- **CUANDO** la lavandería B, de la misma plaza o de otra, intenta marcarlo "Recolectado"
- **ENTONCES** la api rechaza el cambio sin devolver datos del pedido
- **Y** el estado no cambia

Criterio: SEG-3

#### Scenario: estados-E08 — El usuario no cambia el estado de su pedido

- **DADO** un pedido del usuario en Confirmado
- **CUANDO** el usuario intenta cambiar su estado por la api
- **ENTONCES** la api rechaza el cambio
- **Y** el estado no cambia

#### Scenario: estados-E09 — La lavandería no mueve un pedido que no aceptó o que rechazó

- **DADO** un pedido que la lavandería todavía no acepta, u otro que rechazó
- **CUANDO** la lavandería intenta marcarlo "Recolectado"
- **ENTONCES** la api rechaza el cambio
- **Y** el estado no cambia

#### Scenario: estados-E10 — Un usuario no consulta el pedido de otro

- **DADO** dos usuarios con un pedido cada uno
- **CUANDO** uno de ellos consulta a la api el pedido del otro
- **ENTONCES** la api no devuelve ningún dato de ese pedido

Criterio: SEG-3

### Requirement: Sin pago acreditado, la lavandería no ve ni mueve el pedido

La api DEBE (MUST) dejar fuera del alcance de la lavandería un pedido cuyo pago la pasarela no ha confirmado: no se lo devuelve, no le deja moverlo y no lo pasa a Recolectado, porque sin pago no hay recolección. La regla no depende del nombre del estado interno en que el pedido espera su pago (DD-123), sino de que su pago no esté acreditado. Al repartidor se le aplica la misma regla en el cambio `repartidor`.

Cita: A → `NSV-2026-001-v8.pdf:16-17:ANEXO A, Sección A.3` (Módulo A, numerales 9, 10 y 11: resumen, pago y confirmación con folio, en ese orden; el orden apoya la regla, no la dice) · C → C-188 (Arthur) y C-197 (Nadir), que son la regla; D → ADR-0016 (`pedidos.confirmado_en` se llena cuando la pasarela confirma el pago)

#### Scenario: estados-E23 — La lavandería no recibe ni mueve un pedido sin pago

- **DADO** un pedido hecho a la lavandería al aceptar el resumen, cuyo pago la pasarela todavía no confirma
- **CUANDO** la lavandería lo consulta o intenta marcarlo "Recolectado"
- **ENTONCES** la api rechaza la petición sin devolver datos del pedido
- **Y** el estado del pedido no cambia y no se agrega ningún evento a `pedido_eventos`

### Requirement: Correspondencia entre los botones de la lavandería y los estados del usuario

La api DEBE (MUST) traducir cada botón de la lavandería al estado que ve el usuario. Recolectado, En proceso y Listo se ven con el mismo nombre. Lo que ve el usuario cuando la lavandería marca En proceso de entrega, y si la lavandería puede marcar Entregado, no lo dice el contrato: esos escenarios se escriben y quedan bloqueados hasta que se decida.

Cita: A → `NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3` (Módulo A, numeral 12, y Módulo B, numeral 3)

#### Scenario: estados-E11 — Los botones con el mismo nombre se ven igual

- **DADO** un pedido aceptado por la lavandería
- **CUANDO** la lavandería marca "Recolectado", "En proceso" o "Listo"
- **ENTONCES** la siguiente consulta del usuario a la api devuelve ese mismo estado

Criterio: A43-3

#### Scenario: estados-E12 — Lo que ve el usuario con "En proceso de entrega"

- **DADO** un pedido en Listo
- **CUANDO** la lavandería marca "En proceso de entrega"
- **ENTONCES** el usuario ve el estado de los cinco que corresponda, según se decida

SIN RESPUESTA — bloquea: estados-E12 (DD-022, DD-011)

#### Scenario: estados-E13 — La lavandería marca Entregado

- **DADO** un pedido en En proceso de entrega que entrega la propia lavandería
- **CUANDO** la ropa llega al usuario
- **ENTONCES** el pedido pasa a Entregado y el usuario lo ve así

SIN RESPUESTA — bloquea: estados-E13 (DD-022). Los botones del Módulo B terminan en "En proceso de entrega"; "Entregado" solo aparece entre los botones del repartidor.

### Requirement: Historial de cada cambio de estado

La api DEBE (MUST) guardar cada cambio de estado de un pedido como un evento nuevo en `pedido_eventos`, con el estado nuevo, la hora del cambio y quién lo hizo; el estado anterior de cada cambio es el del evento previo del mismo pedido. Los cambios anteriores no se borran ni se sobrescriben, y la base DEBE (MUST) rechazar editar o borrar un evento.

Cita: A → `NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3` (Base de datos: "pedidos, pagos e historial completo"); D → ADR-0016, regla 6

#### Scenario: estados-E14 — Cada cambio queda en el historial

- **DADO** un pedido que la lavandería pasó de Confirmado a Recolectado y luego a En proceso
- **CUANDO** se consulta el historial de ese pedido en `pedido_eventos`
- **ENTONCES** hay un evento por cada uno de esos dos cambios, en orden, después de los eventos anteriores del pedido
- **Y** cada uno lleva el estado nuevo, su hora y la cuenta de la lavandería que lo hizo, y su estado anterior es el del evento previo
- **Y** la base de datos rechaza editar o borrar cualquiera de ellos (modelo-E12)

### Requirement: El cambio se ve en 60 segundos o menos

Con el seguimiento del pedido abierto, la app DEBE (MUST) consultar el estado a la api cada 30 segundos, para que un cambio que haga la lavandería o el repartidor se vea en la app del usuario en 60 segundos o menos. La consulta corre solo mientras esa pantalla está visible, y al volver a ella consulta de inmediato.

Cita: A → `NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3`; D → ADR-0007

#### Scenario: estados-E15 — La pantalla consulta cada 30 segundos

- **DADO** el seguimiento de un pedido abierto en la app, con el pedido en Confirmado
- **CUANDO** pasan 30 segundos y la api ya responde "Recolectado"
- **ENTONCES** la app vuelve a consultar el estado
- **Y** muestra "Recolectado" sin que el usuario toque nada

Criterio: A43-3

#### Scenario: estados-E16 — La consulta corre solo con la pantalla visible

- **DADO** el seguimiento de un pedido abierto en la app
- **CUANDO** el usuario sale de esa pantalla o manda la app al fondo
- **ENTONCES** la app deja de consultar el estado
- **Y** al volver al seguimiento consulta de inmediato

#### Scenario: estados-E17 — En un teléfono Android, el cambio aparece en 60 segundos o menos

- **DADO** la app del usuario abierta en el seguimiento de un pedido en Confirmado, en un teléfono Android
- **CUANDO** la lavandería marca "Recolectado"
- **ENTONCES** "Recolectado" aparece en la pantalla del usuario en 60 segundos o menos, sin que toque nada

Criterio: A43-3

#### Scenario: estados-E18 — En un iPhone, el cambio aparece en 60 segundos o menos

- **DADO** la app del usuario abierta en el seguimiento de un pedido en Confirmado, en un iPhone
- **CUANDO** la lavandería marca "Recolectado"
- **ENTONCES** "Recolectado" aparece en la pantalla del usuario en 60 segundos o menos, sin que toque nada

Criterio: A43-3

SIN RESPUESTA — bloquea: estados-E18 (DD-069)

### Requirement: Pedido rechazado o cancelado

La api DEBE (MUST) distinguir un pedido rechazado por la lavandería, o uno cancelado, de los cinco estados del flujo, porque el contrato los trata aparte: la lavandería rechaza con motivo, el resumen muestra una política de cancelación y los indicadores cuentan los pedidos cancelados. Qué ve el usuario y qué pasa después con el pedido no lo dice el contrato.

Cita: A → `NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3` (Módulo B, numeral 2), `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3` (Módulo A, numeral 9) y `NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5`

#### Scenario: estados-E19 — Lo que ve el usuario si la lavandería rechaza su pedido

- **DADO** un pedido en Confirmado
- **CUANDO** la lavandería lo rechaza con su motivo (cambio `pedidos-de-la-lavanderia`)
- **ENTONCES** el usuario ve lo que se decida para un pedido rechazado, y el pedido sigue la regla que se fije: terminar, reasignarse o reembolsarse

SIN RESPUESTA — bloquea: estados-E19 (BR-22 y BR-26; DD-103)

#### Scenario: estados-E20 — Lo que ve el usuario si su pedido se cancela

- **DADO** un pedido dentro del flujo
- **CUANDO** se cancela conforme a la política de cancelación
- **ENTONCES** el usuario ve lo que se decida para un pedido cancelado
- **Y** el pedido cuenta como cancelado

SIN RESPUESTA — bloquea: estados-E20 (DD-016)

### Requirement: Recorrido completo del pedido

El recorrido completo del pedido, desde elegir la lavandería hasta agendar la recolección, pagar y dar seguimiento, DEBE (MUST) poder completarse sin errores que lo impidan, en Android y en iPhone, con el seguimiento hasta el último estado.

Cita: A → `NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3`

#### Scenario: estados-E21 — El ciclo completo del pedido en el servidor

- **DADO** una lavandería de prueba activa, un usuario y una ventana de recolección libre
- **CUANDO** el usuario elige la lavandería, agenda, paga en el sandbox, la lavandería acepta y el pedido avanza
- **ENTONCES** el usuario ve cada estado, de Confirmado a Entregado, sin errores que lo impidan

Criterio: A43-2

SIN RESPUESTA — bloquea: estados-E21 (DD-012, que detiene el cobro del cambio `pago`; DD-014 y DD-022, quién marca Entregado). El cobro corre contra el
sandbox de Conekta, la pasarela del contrato: DD-079 ya no lo detiene

#### Scenario: estados-E22 — El recorrido completo en el teléfono

- **DADO** la app instalada en un teléfono Android y en un iPhone, contra el servidor local
- **CUANDO** el usuario elige la lavandería, agenda, paga en el sandbox y sigue su pedido mientras la lavandería lo avanza
- **ENTONCES** el recorrido llega a Entregado sin errores que lo impidan, en las dos plataformas

Criterio: A43-2

SIN RESPUESTA — bloquea: estados-E22 (DD-012; DD-055 y DD-079, por la pantalla de pago, pago-E02; DD-014 y DD-022; DD-069 en iPhone)
