## ADDED Requirements

### Requirement: Ventanas de una hora en la hora de la plaza

El servidor DEBE (MUST) ofrecer la recolección en ventanas de exactamente una hora, cada una con su fecha, su hora de
inicio y su hora de fin, guardadas como instante absoluto y presentadas en la zona horaria de la plaza de la
lavandería. La app DEBE (MUST) mostrarlas en un selector de fecha y hora.

Cita: A → `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #7`; A → `NSV-2026-001-v8.pdf:4:Cláusula I`

#### Scenario: agenda-E01 — Cada ventana dura una hora

- **DADO** una lavandería de prueba con horario de 9:00 a 13:00 el jueves
- **CUANDO** la app pide las ventanas de ese jueves
- **ENTONCES** el servidor responde 9:00–10:00, 10:00–11:00, 11:00–12:00 y 12:00–13:00
- **Y** ninguna ventana dura más ni menos de una hora

Criterio: A43-6

#### Scenario: agenda-E02 — La hora es la local de la plaza

- **DADO** una plaza de prueba configurada con una zona horaria distinta de UTC
- **Y** una lavandería de esa plaza con horario de 9:00 a 10:00
- **CUANDO** la app pide las ventanas de ese día
- **ENTONCES** el servidor devuelve la ventana 9:00–10:00 en la hora de la plaza, con su desfase
- **Y** la guarda como el instante absoluto que corresponde a esa hora local

### Requirement: Ventanas dentro de la disponibilidad de la lavandería

El servidor DEBE (MUST) ofrecer solo ventanas completas que caben dentro del horario y de las ventanas de
disponibilidad de la lavandería elegida, partidas en tramos de una hora desde el inicio de cada periodo. Si ese día la
lavandería no tiene horario, la app DEBE (MUST) decirlo con un mensaje en lugar de una rejilla vacía.

Cita: A → `NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo B #1 y #6`

#### Scenario: agenda-E03 — Solo ventanas completas dentro del horario

- **DADO** una lavandería de prueba con horario de 9:00 a 13:30
- **CUANDO** la app pide las ventanas de ese día
- **ENTONCES** la última ventana que se ofrece es 12:00–13:00
- **Y** ninguna empieza antes de las 9:00 ni termina después de las 13:30

Criterio: A43-6

#### Scenario: agenda-E04 — Día sin servicio

- **DADO** una lavandería de prueba sin horario los domingos
- **CUANDO** el usuario elige un domingo en el selector
- **ENTONCES** el servidor no devuelve ninguna ventana
- **Y** la app muestra que ese día la lavandería no da servicio y deja pasar al día siguiente

Criterio: A43-6

#### Scenario: agenda-E05 — Ventana sin cupo

- **DADO** una ventana que ya tiene el número máximo de pedidos que admite la lavandería
- **CUANDO** la app muestra las ventanas de ese día
- **ENTONCES** esa ventana aparece tachada, visible y sin poder elegirse
- **Y** el servidor la rechaza si alguien la manda

SIN RESPUESTA — bloquea: agenda-E05 (DD-099, el resto de BR-17: si hay cupo por ventana, cuánto es y quién lo
fija). El contrato habla de "ventanas de disponibilidad" (`:17:A.3`, Módulo B #6), no de cupo.

#### Scenario: agenda-E06 — Pedido con la lavandería cerrada en ese momento

- **DADO** una lavandería de prueba que cierra de 20:00 a 8:00
- **Y** la 1:00 de la madrugada en la hora de la plaza
- **CUANDO** el usuario abre la agenda de esa lavandería
- **ENTONCES** puede elegir una ventana desde la siguiente apertura y seguir con su pedido

SIN RESPUESTA — bloquea: agenda-E06 (DD-078: la regla de los pedidos de noche viene de dos notas de voz y EL CLIENTE
no la ha confirmado por escrito). Se puede construir como dato de configuración de la plaza, sin elegir su valor.

#### Scenario: agenda-E07 — Día festivo

- **DADO** un día festivo en el que la lavandería tiene su horario de siempre
- **CUANDO** el usuario elige ese día en el selector
- **ENTONCES** la agenda aplica la regla de días festivos que confirme EL CLIENTE

SIN RESPUESTA — bloquea: agenda-E07 (DD-078, pregunta 5: fines de semana y días festivos).

### Requirement: El usuario elige la ventana y el servidor la valida

La app DEBE (MUST) dejar que el usuario elija la fecha y una de las ventanas ofrecidas, y llevar esa elección hasta el
resumen del pedido. El servidor DEBE (MUST) rechazar cualquier ventana que no esté entre las que ofrece para esa
lavandería en ese momento.

Cita: A → `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #7`; A → `NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3`

#### Scenario: agenda-E08 — El usuario elige una ventana

- **DADO** el selector con ventanas disponibles para el jueves
- **CUANDO** el usuario toca la de 10:00–11:00 y continúa
- **ENTONCES** la ventana queda marcada como elegida, con una señal además del color
- **Y** la fecha y la ventana llegan al resumen del pedido

Criterio: A43-2

#### Scenario: agenda-E09 — El servidor rechaza una ventana que no ofreció

- **DADO** una lavandería de prueba con horario de 9:00 a 13:00
- **CUANDO** se valida una ventana de 14:00–15:00, una de media hora o una que ya terminó
- **ENTONCES** el servidor la rechaza con un error de validación que nombra el motivo
- **Y** esa ventana no se acepta para ningún pedido

Criterio: A43-2

#### Scenario: agenda-E10 — La app propone la primera ventana

- **DADO** el usuario que abre el selector
- **CUANDO** la app carga las ventanas
- **ENTONCES** la app deja propuesta la primera ventana disponible, que el usuario puede cambiar

SIN RESPUESTA — bloquea: agenda-E10 (DD-078, pregunta 3: si el usuario elige la ventana o la app le propone la
primera). Que el usuario no pueda elegir contradiría el "selector" del contrato (`:16:A.3 #7`).

### Requirement: Solo ventanas futuras, con anticipación y horizonte de configuración

El servidor DEBE (MUST) ofrecer solo ventanas que empiezan después del momento de la consulta más la anticipación
mínima, y hasta el número de días hacia adelante. Los dos valores DEBEN (MUST) leerse de la configuración de la plaza;
ninguno se escribe en el código.

Cita: A → `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #7`; A → `NSV-2026-001-v8.pdf:4:Cláusula I`

#### Scenario: agenda-E11 — Una ventana que ya empezó no se ofrece

- **DADO** las 10:20 del jueves en la hora de la plaza y una anticipación de prueba de cero
- **CUANDO** la app pide las ventanas del jueves
- **ENTONCES** la primera que se ofrece es 11:00–12:00

Criterio: A43-2

#### Scenario: agenda-E12 — Anticipación y horizonte salen de la configuración

- **DADO** una plaza configurada, para la prueba, con anticipación de 2 horas y 3 días hacia adelante
- **CUANDO** la app pide las ventanas
- **ENTONCES** no se ofrece ninguna ventana que empiece antes de 2 horas desde ahora ni después del tercer día
- **Y** al cambiar la configuración cambia la respuesta, sin desplegar código ni correr migraciones

SIN RESPUESTA — bloquea: solo el valor de producción de la anticipación y del horizonte (DD-099, el resto de
BR-17). El mecanismo se construye y se prueba con valores de prueba.
