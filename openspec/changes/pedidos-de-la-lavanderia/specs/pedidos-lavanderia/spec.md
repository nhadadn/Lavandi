## ADDED Requirements

### Requirement: Lista de pedidos activos de la lavandería
La app DEBE (MUST) mostrar a la lavandería sus pedidos activos —los confirmados que esperan su respuesta y los que
aceptó y aún no llegan a Entregado—, y el servidor DEBE (MUST) entregarle solo pedidos hechos a esa lavandería.

Cita: A → `NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo B #2` ("Lista en tiempo real"); A →
`NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo A #11 y #12` (folio; estado Entregado); A →
`NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3, Medidas de seguridad` (separación por tipo de usuario)

#### Scenario: pedidos-lavanderia-E01 — La lista trae los pedidos activos
- **DADO** una lavandería con un pedido confirmado que espera su respuesta, uno aceptado que va en proceso, uno
  entregado y uno rechazado
- **CUANDO** pide su lista de pedidos activos
- **ENTONCES** recibe el pedido que espera su respuesta y el aceptado
- **Y** no recibe el entregado ni el rechazado

Criterio: A43-6

#### Scenario: pedidos-lavanderia-E02 — Solo los pedidos de esa lavandería
- **DADO** dos lavanderías, cada una con pedidos activos
- **CUANDO** la primera pide su lista de pedidos activos
- **ENTONCES** no recibe ningún pedido de la segunda
- **Y** no recibe datos de usuarios que no le han hecho pedidos

Criterio: SEG-3

#### Scenario: pedidos-lavanderia-E03 — Cómo se ve cada pedido en la app
- **DADO** una lavandería con un pedido que espera su respuesta y uno aceptado
- **CUANDO** abre su pantalla de pedidos
- **ENTONCES** cada pedido se ve con la tarjeta de pedido de socio lavandería del design system, con su folio, su
  servicio y su fecha y ventana de recolección
- **Y** solo el pedido que espera respuesta ofrece Aceptar y Rechazar

#### Scenario: pedidos-lavanderia-E04 — Datos de recolección y del usuario
- **DADO** un pedido aceptado por la lavandería
- **CUANDO** la lavandería lo consulta
- **ENTONCES** ve la dirección de recolección y los datos del usuario que correspondan a quien recoge y entrega

SIN RESPUESTA — bloquea: pedidos-lavanderia-E04 (DD-022, DD-014)

### Requirement: Consulta de la lista cada 30 segundos
Mientras la pantalla de pedidos está visible, la app DEBE (MUST) volver a consultar la lista cada 30 segundos, y DEBE
(MUST) dejar de consultarla cuando la pantalla no está visible.

Cita: A → `NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo B #2` ("Lista en tiempo real"); D → `ADR-0007`

#### Scenario: pedidos-lavanderia-E05 — Un pedido nuevo aparece sin recargar
- **DADO** una lavandería con su pantalla de pedidos abierta
- **CUANDO** el servidor recibe un pedido nuevo para ella
- **ENTONCES** el pedido aparece en la lista con la siguiente consulta, que ocurre a los 30 segundos de la anterior

#### Scenario: pedidos-lavanderia-E06 — Sin consultas con la pantalla oculta
- **DADO** una lavandería con su pantalla de pedidos abierta
- **CUANDO** la app pasa a segundo plano o la lavandería cambia de pantalla
- **ENTONCES** la app deja de consultar la lista
- **Y** al volver a la pantalla consulta la lista y retoma el ciclo de 30 segundos

### Requirement: Aceptar un pedido
La lavandería DEBE (MUST) poder aceptar un pedido confirmado que espera su respuesta. El servidor DEBE (MUST) guardar
la hora de la aceptación y rechazar la acción si el pedido ya fue respondido o si es de otra lavandería.

Cita: A → `NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo B #2` ("Aceptar o rechazar con motivo"); A →
`NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3, Medidas de seguridad`

#### Scenario: pedidos-lavanderia-E07 — La lavandería acepta un pedido
- **DADO** un pedido confirmado que espera la respuesta de la lavandería
- **CUANDO** la lavandería toca Aceptar
- **ENTONCES** el servidor guarda el pedido como aceptado, con la hora
- **Y** el pedido sigue en la lista como aceptado, ya sin Aceptar ni Rechazar

Criterio: A43-6

#### Scenario: pedidos-lavanderia-E08 — Pedido ya respondido
- **DADO** un pedido que la lavandería ya aceptó o ya rechazó
- **CUANDO** intenta aceptarlo o rechazarlo otra vez
- **ENTONCES** el servidor rechaza la acción
- **Y** el pedido no cambia

#### Scenario: pedidos-lavanderia-E09 — Pedido de otra lavandería
- **DADO** un pedido hecho a otra lavandería
- **CUANDO** la lavandería intenta aceptarlo o rechazarlo
- **ENTONCES** el servidor rechaza la acción sin revelar los datos del pedido
- **Y** el pedido no cambia

Criterio: SEG-3

#### Scenario: pedidos-lavanderia-E10 — Pedido que la lavandería no responde
- **DADO** un pedido confirmado que la lavandería no ha respondido
- **CUANDO** se cumple el plazo para responder que se fije
- **ENTONCES** el pedido sigue la regla que se fije: vence, pasa a otra lavandería o se cancela con reembolso

SIN RESPUESTA — bloquea: pedidos-lavanderia-E10 (BR-26; DD-103)

#### Scenario: pedidos-lavanderia-E11 — Pedido que llega con la lavandería cerrada
- **DADO** una lavandería fuera de su horario de atención
- **CUANDO** le llega un pedido de noche
- **ENTONCES** la lavandería lo acepta o lo rechaza en el momento que fije el cliente: al abrir o antes de la
  recolección

SIN RESPUESTA — bloquea: pedidos-lavanderia-E11 (DD-078, pregunta 4)

### Requirement: Rechazar un pedido con motivo
La lavandería DEBE (MUST) poder rechazar un pedido confirmado que espera su respuesta solo si escribe un motivo. El
servidor DEBE (MUST) guardar el motivo y la hora con el pedido, y rechazar la acción si llega sin motivo.

Cita: A → `NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo B #2` ("Aceptar o rechazar con motivo")

#### Scenario: pedidos-lavanderia-E12 — La lavandería rechaza con motivo
- **DADO** un pedido confirmado que espera la respuesta de la lavandería
- **CUANDO** la lavandería toca Rechazar, escribe el motivo y confirma
- **ENTONCES** el servidor guarda el pedido como rechazado, con el motivo y la hora
- **Y** el pedido sale de su lista de pedidos activos

Criterio: A43-6

#### Scenario: pedidos-lavanderia-E13 — Sin motivo no hay rechazo
- **DADO** la pantalla de rechazo de un pedido que espera respuesta
- **CUANDO** el motivo está vacío
- **ENTONCES** la app no deja confirmar el rechazo
- **Y** si la petición llega al servidor sin motivo, el servidor la rechaza y el pedido no cambia

#### Scenario: pedidos-lavanderia-E14 — El pedido y el cobro después del rechazo
- **DADO** un pedido que la lavandería rechazó
- **CUANDO** el servidor registra el rechazo
- **ENTONCES** el pedido, el cobro y lo que se le dice al usuario siguen la regla que se fije: otra lavandería, o
  cancelación con reembolso

SIN RESPUESTA — bloquea: pedidos-lavanderia-E14 (DD-016, DD-012; BR-22 y BR-26; DD-103)

### Requirement: Sin pago acreditado, el pedido no llega a la lavandería
El servidor DEBE (MUST) entregarle a la lavandería solo pedidos cuyo pago ya confirmó la pasarela. Un pedido que se creó al aceptar el resumen y todavía no tiene su pago acreditado, o cuyo pago venció o falló, NO DEBE (MUST NOT) aparecer en su lista ni poder aceptarse o rechazarse, porque sin pago no hay recolección.

Cita: A → `NSV-2026-001-v8.pdf:16-17:ANEXO A, Sección A.3, Módulo A #9, #10 y #11` (resumen, pago y confirmación con folio, en ese orden; el orden apoya la regla, no la dice) · C → C-188 (Arthur) y C-197 (Nadir), que son la regla; D → `ADR-0016` (`pedidos.confirmado_en` se llena cuando la pasarela confirma el pago)

#### Scenario: pedidos-lavanderia-E17 — Un pedido sin pago acreditado no aparece en la lista
- **DADO** una lavandería con un pedido confirmado, con su pago acreditado, y otro creado al aceptar el resumen cuyo
  pago la pasarela todavía no confirma
- **CUANDO** pide su lista de pedidos activos
- **ENTONCES** recibe solo el pedido con el pago acreditado

#### Scenario: pedidos-lavanderia-E18 — Un pedido sin pago acreditado no se acepta ni se rechaza
- **DADO** un pedido hecho a la lavandería cuyo pago la pasarela no ha confirmado
- **CUANDO** la lavandería intenta aceptarlo o rechazarlo
- **ENTONCES** el servidor rechaza la acción sin revelar los datos del pedido
- **Y** el pedido no cambia y no se agrega ningún evento a su historial

### Requirement: Aviso de pedido nuevo a la lavandería
La API DEBE (MUST) avisar por FCM a la lavandería de cada pedido nuevo que le llegue, en los términos que fije DD-054,
y el aviso recibido con la app abierta DEBE (MUST) provocar una consulta inmediata de la lista.

Cita: D → `ADR-0008` (aviso a la lavandería de un pedido nuevo, "si se aprueba DD-054"); D → `ADR-0007` (el aviso con
la app abierta provoca una consulta); A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Servicios externos` (avisos con
"el servicio gratuito de Google")

#### Scenario: pedidos-lavanderia-E15 — Pedido nuevo con la app cerrada
- **DADO** una lavandería con la app cerrada y los avisos permitidos
- **CUANDO** le llega un pedido nuevo
- **ENTONCES** recibe un aviso en el teléfono
- **Y** al tocarlo, la app abre su lista de pedidos con el pedido nuevo

SIN RESPUESTA — bloquea: pedidos-lavanderia-E15 (DD-054; en iPhone, además, DD-069 y DD-017)

#### Scenario: pedidos-lavanderia-E16 — Pedido nuevo con la app abierta
- **DADO** una lavandería con su pantalla de pedidos abierta
- **CUANDO** llega el aviso de un pedido nuevo
- **ENTONCES** la app consulta la lista en ese momento, sin esperar los 30 segundos

SIN RESPUESTA — bloquea: pedidos-lavanderia-E16 (DD-054)
