## ADDED Requirements

### Requirement: Registro del repartidor
La app DEBE (MUST) dejar que un repartidor se registre con nombre, teléfono, correo, foto de perfil y tipo de vehículo. El registro DEBE (MUST) quedar pendiente, sin acceso a pedidos, hasta que EL CLIENTE lo dé de alta.

Cita: A → `NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3` (Módulo C #1); D → `ADR-0005` (correo y contraseña para todos los roles)

#### Scenario: repartidor-E01 — El repartidor se registra y queda pendiente
- **DADO** una persona sin cuenta
- **CUANDO** se registra como repartidor con nombre, teléfono, correo, contraseña, foto de perfil y tipo de vehículo
- **ENTONCES** queda una cuenta de repartidor en estado pendiente
- **Y** la app le dice que espera la autorización de EL CLIENTE

Criterio: A43-6

#### Scenario: repartidor-E02 — Un registro incompleto se rechaza
- **DADO** una persona sin cuenta
- **CUANDO** intenta registrarse como repartidor sin alguno de los cinco datos
- **ENTONCES** la API rechaza el registro
- **Y** no se crea ninguna cuenta

#### Scenario: repartidor-E03 — Un repartidor pendiente no opera
- **DADO** un repartidor en estado pendiente
- **CUANDO** pide su lista de pedidos o intenta mover un pedido
- **ENTONCES** la API lo rechaza

#### Scenario: repartidor-E04 — Cómo llega el repartidor a su registro
- **DADO** la app instalada en el teléfono de una persona
- **CUANDO** quiere registrarse como repartidor
- **ENTONCES** llega al registro por la entrada que fije la respuesta pendiente
- **Y** su cuenta tiene el rol o los roles que fije esa misma respuesta

SIN RESPUESTA — bloquea: repartidor-E04 (DD-053)

### Requirement: Gestión de repartidores en la administración
El panel de administración DEBE (MUST) permitir dar de alta, dar de baja, suspender y editar a los socios repartidores, y solo la administración DEBE (MUST) poder hacerlo.

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #2) y `NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3` (Módulo C #1, "El alta, validación y autorización del repartidor corresponden a EL CLIENTE"); D → `ADR-0003`

#### Scenario: repartidor-E05 — La administración da de alta a un repartidor
- **DADO** un repartidor en estado pendiente
- **CUANDO** la administración lo da de alta en una plaza
- **ENTONCES** queda activo en esa plaza
- **Y** puede ver los pedidos que se le asignen en ella

Criterio: A43-6

#### Scenario: repartidor-E06 — La administración suspende a un repartidor
- **DADO** un repartidor activo
- **CUANDO** la administración lo suspende
- **ENTONCES** la API le rechaza ver y mover pedidos
- **Y** al quitarle la suspensión recupera ese acceso

#### Scenario: repartidor-E07 — La administración da de baja a un repartidor
- **DADO** un repartidor activo con entregas completadas
- **CUANDO** la administración lo da de baja
- **ENTONCES** ya no puede iniciar sesión
- **Y** sus entregas completadas se conservan para el historial y para el indicador de entregas por repartidor (`NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5`)

#### Scenario: repartidor-E08 — La administración edita a un repartidor
- **DADO** un repartidor registrado
- **CUANDO** la administración cambia su nombre, su teléfono, su correo, su foto o su tipo de vehículo
- **ENTONCES** el cambio queda guardado
- **Y** el repartidor lo ve en su módulo

#### Scenario: repartidor-E09 — Solo la administración gestiona repartidores
- **DADO** un usuario, una lavandería y un repartidor, cada uno con sesión
- **CUANDO** cualquiera de ellos intenta dar de alta, suspender, dar de baja o editar a un repartidor
- **ENTONCES** la plataforma lo rechaza
- **Y** el repartidor no cambia

Criterio: SEG-3

### Requirement: Pedidos asignados en tiempo real
La app DEBE (MUST) mostrar al repartidor la lista de los pedidos que tiene asignados, solo los suyos y solo de su plaza, y mantenerla al día consultando la API cada 30 segundos mientras la pantalla está abierta.

Cita: A → `NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3` (Módulo C #2) y `NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3` (Separación por tipo de usuario); D → `ADR-0007`

#### Scenario: repartidor-E10 — El repartidor ve sus pedidos asignados
- **DADO** un repartidor activo con dos pedidos asignados
- **CUANDO** abre su lista de pedidos asignados
- **ENTONCES** ve los dos
- **Y** cada uno muestra su folio, su ventana, su dirección de recogida y entrega y su lavandería

Criterio: A43-6

#### Scenario: repartidor-E11 — Un pedido recién asignado aparece solo
- **DADO** un repartidor con la lista de pedidos asignados abierta
- **CUANDO** se le asigna un pedido nuevo
- **ENTONCES** el pedido aparece en la lista en 30 segundos o menos, sin que el repartidor haga nada

#### Scenario: repartidor-E12 — No ve pedidos de otro repartidor ni de otra plaza
- **DADO** un pedido asignado a otro repartidor y un pedido de otra plaza
- **CUANDO** el repartidor pide su lista, o pide cualquiera de esos dos pedidos por su identificador
- **ENTONCES** ninguno de los dos aparece en su lista
- **Y** la API rechaza entregarle cualquiera de ellos

Criterio: SEG-3

#### Scenario: repartidor-E13 — Quién asigna el pedido al repartidor
- **DADO** un pedido que necesita repartidor
- **CUANDO** se asigna
- **ENTONCES** lo asigna quien fije la respuesta pendiente, desde la pantalla que fije esa misma respuesta, y sin algoritmo

SIN RESPUESTA — bloquea: repartidor-E13 (DD-014)

#### Scenario: repartidor-E14 — Aviso de un pedido asignado con la app cerrada
- **DADO** un repartidor con la app cerrada
- **CUANDO** se le asigna un pedido
- **ENTONCES** recibe o no un aviso en el teléfono, según fije la respuesta pendiente

SIN RESPUESTA — bloquea: repartidor-E14 (DD-054)

### Requirement: Solo se ofrecen pedidos con pago acreditado
La API DEBE (MUST) ofrecerle al repartidor solo pedidos con su pago acreditado: un pedido que todavía no se confirma por su pago no aparece en su lista, no se le entrega por su identificador y no se puede aceptar.

Cita: A → `NSV-2026-001-v8.pdf:16-17:ANEXO A, Sección A.3` (Módulo A #9, #10 y #11: resumen con precio estimado, pago y confirmación con folio, en ese orden; el orden apoya la regla, no la dice) y `NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3` (Módulo C #2) · C → C-188 (Arthur) y C-197 (Nadir), que son la regla

Regla de Arthur: "no se va a recolectar si no ha sido pagado" (C-188, `vault/meta/actas/acta-2026-11-19-normativa-y-arranque.md`).

#### Scenario: repartidor-E30 — Un pedido sin pago acreditado no se le ofrece
- **DADO** un repartidor activo y un pedido de su plaza cuyo pago la pasarela todavía no confirma, sin `confirmado_en`, con una asignación para él sembrada por la fábrica
- **CUANDO** pide su lista de pedidos asignados, pide ese pedido por su identificador o intenta aceptarlo
- **ENTONCES** el pedido no aparece en su lista
- **Y** la API rechaza entregárselo y rechaza la aceptación
- **Y** cuando la pasarela confirma el pago y el pedido queda Confirmado, el pedido aparece en su lista

### Requirement: Aceptar o rechazar con motivo
El repartidor DEBE (MUST) poder aceptar o rechazar cada pedido que tiene asignado. El rechazo DEBE (MUST) llevar un motivo escrito.

Cita: A → `NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3` (Módulo C #2, "Aceptar o rechazar con motivo")

#### Scenario: repartidor-E15 — El repartidor acepta un pedido
- **DADO** un pedido asignado al repartidor y todavía sin aceptar
- **CUANDO** lo acepta
- **ENTONCES** el pedido queda aceptado por él
- **Y** se le habilitan los botones de estado

#### Scenario: repartidor-E16 — El repartidor rechaza un pedido con motivo
- **DADO** un pedido asignado al repartidor y todavía sin aceptar
- **CUANDO** lo rechaza y escribe un motivo
- **ENTONCES** el rechazo queda registrado con su motivo, su fecha y el repartidor
- **Y** el pedido deja de estar asignado a él

#### Scenario: repartidor-E17 — Un rechazo sin motivo no se acepta
- **DADO** un pedido asignado al repartidor y todavía sin aceptar
- **CUANDO** intenta rechazarlo con el motivo vacío
- **ENTONCES** la API rechaza la petición
- **Y** el pedido sigue asignado a él

#### Scenario: repartidor-E18 — Qué pasa con el pedido rechazado
- **DADO** un pedido que su repartidor acaba de rechazar
- **CUANDO** queda sin repartidor
- **ENTONCES** se reasigna, se avisa o se detiene según fije la respuesta pendiente

SIN RESPUESTA — bloquea: repartidor-E18 (DD-014)

### Requirement: Actualización de estado por el repartidor
El repartidor DEBE (MUST) mover el pedido que aceptó con los botones Recolectado → En tránsito → Entregado, en ese orden. La API DEBE (MUST) rechazar saltos, retrocesos y cambios de quien no es el repartidor del pedido.

Cita: A → `NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3` (Módulo C #3) y `NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3` (Separación por tipo de usuario)

#### Scenario: repartidor-E19 — El repartidor avanza el pedido en orden
- **DADO** un pedido que el repartidor aceptó
- **CUANDO** marca Recolectado, luego En tránsito y luego Entregado
- **ENTONCES** el pedido pasa por los tres estados en ese orden
- **Y** cada cambio queda registrado con su hora

Criterio: A43-6

#### Scenario: repartidor-E20 — No se salta, no se retrocede y no se mueve antes de aceptar
- **DADO** un pedido aceptado en Recolectado y otro asignado pero sin aceptar
- **CUANDO** el repartidor intenta marcar Entregado en el primero sin pasar por En tránsito, volverlo a un estado anterior, o marcar Recolectado en el segundo
- **ENTONCES** la API rechaza cada intento
- **Y** los dos pedidos conservan su estado

#### Scenario: repartidor-E21 — Otro repartidor no mueve el pedido
- **DADO** un pedido aceptado por un repartidor
- **CUANDO** otro repartidor intenta cambiar su estado
- **ENTONCES** la API lo rechaza
- **Y** el pedido conserva su estado

Criterio: SEG-3

#### Scenario: repartidor-E22 — El usuario ve el cambio del repartidor en 60 segundos o menos
- **DADO** un pedido con la pantalla de seguimiento abierta en la app del usuario
- **CUANDO** el repartidor marca un estado
- **ENTONCES** la app del usuario muestra, en 60 segundos o menos, el estado que corresponda de los cinco del Módulo A

Criterio: A43-3

SIN RESPUESTA — bloquea: repartidor-E22 (DD-022 y DD-011: cómo se mapean Recolectado, En tránsito y Entregado a los cinco estados del usuario, y si el pedido lleva un viaje de repartidor o dos)

### Requirement: Evidencia de entrega
Al marcar Entregado, la app DEBE (MUST) dejar al repartidor tomar una foto como evidencia de entrega. La foto DEBE (MUST) guardarse en almacenamiento privado, ligada al pedido.

Cita: A → `NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3` (Almacenamiento de imágenes, "las evidencias de entrega"); D → `ADR-0006`

#### Scenario: repartidor-E23 — El repartidor toma la evidencia al entregar
- **DADO** un pedido En tránsito del repartidor
- **CUANDO** marca Entregado y toma una foto
- **ENTONCES** la foto queda en el almacenamiento privado, ligada al pedido
- **Y** no tiene URL pública

#### Scenario: repartidor-E24 — Cuándo es obligatoria la evidencia
- **DADO** un pedido del repartidor
- **CUANDO** marca Recolectado o Entregado sin foto
- **ENTONCES** se acepta o se rechaza según fije la respuesta pendiente

SIN RESPUESTA — bloquea: repartidor-E24 (BR-29: si la evidencia es obligatoria, en qué momentos y si es foto o firma; DD-110)

### Requirement: Historial de entregas
La app DEBE (MUST) mostrar al repartidor sus entregas completadas con fecha, monto y calificación recibida, solo las suyas.

Cita: A → `NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3` (Módulo C #4) y `NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3` (Separación por tipo de usuario)

#### Scenario: repartidor-E25 — El repartidor ve sus entregas completadas
- **DADO** un repartidor con dos entregas completadas y una en curso, y otro repartidor con una entrega completada
- **CUANDO** el primero abre su historial de entregas
- **ENTONCES** ve sus dos entregas completadas, cada una con su folio y la fecha en que la entregó
- **Y** no ve la que sigue en curso ni la del otro repartidor

Criterio: A43-6

#### Scenario: repartidor-E26 — El monto de cada entrega
- **DADO** una entrega completada del repartidor
- **CUANDO** abre su historial
- **ENTONCES** ve el monto que fije la respuesta pendiente

SIN RESPUESTA — bloquea: repartidor-E26 (DD-019 y DD-013: cuánto recibe el repartidor por entrega y quién se lo paga; la plataforma informa, no liquida)

#### Scenario: repartidor-E27 — La calificación que recibió
- **DADO** una entrega completada y calificada por el usuario
- **CUANDO** el repartidor abre su historial
- **ENTONCES** ve la calificación que le corresponda según la respuesta pendiente

SIN RESPUESTA — bloquea: repartidor-E27 (DD-052)

### Requirement: Estadísticas básicas del repartidor
La app DEBE (MUST) mostrar al repartidor cuántas entregas completó en el día y en la semana, y su ingreso total acumulado, contando solo las suyas.

Cita: A → `NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3` (Módulo C #5)

#### Scenario: repartidor-E28 — Entregas del día y de la semana
- **DADO** un repartidor con 2 entregas completadas hoy, 3 en días anteriores de la semana en curso y 1 de la semana pasada
- **CUANDO** abre sus estadísticas
- **ENTONCES** ve 2 entregas del día y 5 de la semana

Criterio: A43-6

#### Scenario: repartidor-E29 — El ingreso total acumulado
- **DADO** un repartidor con entregas completadas
- **CUANDO** abre sus estadísticas
- **ENTONCES** ve el ingreso total acumulado que fije la respuesta pendiente, desde la fecha que fije esa misma respuesta

SIN RESPUESTA — bloquea: repartidor-E29 (DD-019 y DD-013)
