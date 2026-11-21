## ADDED Requirements

### Requirement: Los avisos salen por el servicio gratuito de Google

La api DEBE (MUST) mandar todos los avisos al celular por Firebase Cloud Messaging, que es el servicio gratuito de Google que nombra el contrato, sin otro intermediario; la app los recibe con el módulo de mensajería de Firebase. Un fallo del servicio de avisos NO DEBE (MUST NOT) detener ni deshacer un paso del pedido.

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Servicios externos, "Avisos al celular") y `NSV-2026-001-v8.pdf:30:ANEXO B, Sección B.5`; D → ADR-0008

#### Scenario: avisos-E01 — Un aviso sale por Firebase Cloud Messaging

- **DADO** un usuario con un teléfono registrado
- **CUANDO** la api le manda un aviso
- **ENTONCES** el aviso sale por el canal de Firebase Cloud Messaging, dirigido al token de ese teléfono
- **Y** en la prueba el servicio está simulado

#### Scenario: avisos-E02 — Si el servicio de avisos falla, el pedido sigue

- **DADO** un pedido aceptado por la lavandería, en Confirmado, y el servicio de avisos sin responder
- **CUANDO** la lavandería marca "Recolectado"
- **ENTONCES** el cambio de estado se guarda igual
- **Y** el fallo del aviso queda registrado en la bitácora del servidor

### Requirement: El teléfono del usuario queda registrado

La app DEBE (MUST) registrar en la api el token de Firebase del teléfono al iniciar sesión, y actualizarlo cuando Firebase lo renueve. La api DEBE (MUST) ligar el token a la cuenta y borrarlo al cerrar la sesión o cuando el servicio avise que ya no sirve, para que los avisos de una cuenta no lleguen a un teléfono que ya no es suyo.

Cita: D → ADR-0008; A → `NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3` (Separación por tipo de usuario); D → ADR-0016 (tabla `dispositivos`, con `token_fcm` único)

#### Scenario: avisos-E03 — La app registra el token del teléfono

- **DADO** un usuario que concedió el permiso de avisos
- **CUANDO** inicia sesión en la app, o Firebase le renueva el token
- **ENTONCES** la app manda el token vigente a la api

#### Scenario: avisos-E04 — La api liga el token a la cuenta

- **DADO** un usuario con la sesión iniciada
- **CUANDO** la app registra el token de su teléfono
- **ENTONCES** la api lo guarda ligado a esa cuenta
- **Y** los avisos de los pedidos de esa cuenta van a ese token

#### Scenario: avisos-E05 — Al cerrar la sesión, el teléfono deja de recibir avisos de esa cuenta

- **DADO** un usuario con su teléfono registrado
- **CUANDO** cierra la sesión en ese teléfono
- **ENTONCES** la api borra el token
- **Y** los avisos de sus pedidos ya no se mandan a ese teléfono

#### Scenario: avisos-E06 — Un token que el servicio rechaza se borra

- **DADO** un teléfono registrado cuyo token el servicio de avisos ya no reconoce
- **CUANDO** la api le manda un aviso y el servicio responde que el token no es válido
- **ENTONCES** la api borra ese token

#### Scenario: avisos-E21 — Un teléfono que pasa a otra cuenta

- **DADO** un teléfono registrado a la cuenta A
- **CUANDO** la cuenta B inicia sesión en ese teléfono y la app registra el mismo token
- **ENTONCES** la api liga el token solo a la cuenta B
- **Y** los avisos de los pedidos de la cuenta A ya no se mandan a ese teléfono

### Requirement: El permiso de avisos y el seguimiento sin avisos

La app DEBE (MUST) pedir el permiso de avisos que exigen Android 13 o superior y iPhone. Si el usuario lo niega, la app DEBE (MUST) seguir funcionando sin errores: ningún paso del pedido depende solo del aviso, y el seguimiento se actualiza con la consulta periódica.

Cita: D → ADR-0008 y ADR-0007

#### Scenario: avisos-E07 — La app pide el permiso de avisos

- **DADO** un usuario con la sesión iniciada que nunca ha respondido el permiso de avisos
- **CUANDO** la app llega al punto donde pide el permiso
- **ENTONCES** se muestra la petición del sistema operativo
- **Y** si el usuario la acepta, la app registra el token (avisos-E03)

#### Scenario: avisos-E08 — Sin permiso, la app sigue y el seguimiento se actualiza

- **DADO** un usuario que negó el permiso de avisos
- **CUANDO** abre el seguimiento de su pedido
- **ENTONCES** la app no muestra error
- **Y** el estado se actualiza con la consulta cada 30 segundos (estados-E15)

### Requirement: Los cuatro momentos que nombra el contrato

La api DEBE (MUST) avisar al usuario en los cuatro momentos que nombra el contrato: la confirmación del pedido, la recolección, cuando está listo para entrega y cuando se entrega. Cada aviso va solo a los teléfonos de la cuenta dueña del pedido y lleva el texto configurado para su momento, no un texto escrito en el código.

Cita: A → `NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3` (Módulo A, numeral 17) y `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` ("cuando su ropa fue recolectada o ya está lista")

#### Scenario: avisos-E09 — Aviso de confirmación

- **DADO** un usuario con su teléfono registrado
- **CUANDO** confirma su pedido (Módulo A, numeral 11)
- **ENTONCES** la api le manda el aviso de confirmación

Criterio: A43-5

#### Scenario: avisos-E10 — Aviso de recolección

- **DADO** un pedido en Confirmado de un usuario con su teléfono registrado
- **CUANDO** el pedido pasa a Recolectado
- **ENTONCES** la api le manda al usuario el aviso de recolección

Criterio: A43-5

#### Scenario: avisos-E11 — Aviso de listo para entrega

- **DADO** un pedido en En proceso de un usuario con su teléfono registrado
- **CUANDO** el pedido pasa a Listo
- **ENTONCES** la api le manda al usuario el aviso de listo para entrega

Criterio: A43-5

#### Scenario: avisos-E12 — Aviso de entregado

- **DADO** un pedido de un usuario con su teléfono registrado
- **CUANDO** el pedido pasa a Entregado
- **ENTONCES** la api le manda al usuario el aviso de entregado
- **Y** el aviso no llega a ningún teléfono de otra cuenta

Criterio: A43-5

### Requirement: El quinto momento

La api DEBE (MUST) avisar al usuario también en el quinto momento que exige el criterio de aceptación, en cuanto se defina cuál es. Los momentos de aviso DEBEN (MUST) vivir en una sola lista, para sumar el quinto sin tocar los otros cuatro.

Cita: A → `NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3`; D → ADR-0008

#### Scenario: avisos-E13 — Aviso en el quinto momento

- **DADO** el quinto momento del pedido, cuando se haya definido
- **CUANDO** el pedido llega a ese momento
- **ENTONCES** la api le manda al usuario el aviso de ese momento

Criterio: A43-5

SIN RESPUESTA — bloquea: avisos-E13 (DD-011)

### Requirement: El aviso al cambiar el estado adelanta la consulta

En cada cambio de estado de un pedido, la api DEBE (MUST) mandar un mensaje de Firebase Cloud Messaging a los teléfonos del usuario dueño del pedido. Si la app está abierta en el seguimiento de ese pedido, al recibirlo DEBE (MUST) consultar el estado en ese momento, sin esperar a que se cumplan los 30 segundos.

Cita: D → ADR-0007 y ADR-0008; A → `NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3`

#### Scenario: avisos-E14 — Cada cambio de estado manda el mensaje

- **DADO** un pedido de un usuario con su teléfono registrado
- **CUANDO** la lavandería cambia su estado con cualquiera de sus botones
- **ENTONCES** la api manda al teléfono del usuario un mensaje con el pedido y su estado nuevo

Criterio: A43-3

#### Scenario: avisos-E15 — Con la app abierta, el mensaje adelanta la consulta

- **DADO** el seguimiento de un pedido abierto en la app
- **CUANDO** llega el mensaje de cambio de estado de ese pedido
- **ENTONCES** la app consulta el estado de inmediato
- **Y** muestra el estado nuevo

Criterio: A43-3

### Requirement: Los avisos llegan en Android y en iPhone

Los avisos DEBEN (MUST) llegar tanto en Android como en iPhone. Cada mensaje de la api DEBE (MUST) llevar lo que cada plataforma necesita para mostrarse; en iPhone, Firebase Cloud Messaging se apoya en la clave de la cuenta de desarrollador de Apple.

Cita: A → `NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3`; D → ADR-0008

#### Scenario: avisos-E16 — El mensaje lleva lo que necesita cada plataforma

- **DADO** el aviso de uno de los momentos del pedido
- **CUANDO** la api lo arma
- **ENTONCES** el mensaje trae la parte de Android y la de iPhone, cada una con su título y su texto

Criterio: A43-5

#### Scenario: avisos-E17 — El aviso llega a un teléfono Android

- **DADO** la app en un teléfono Android, con la sesión iniciada y el permiso concedido
- **CUANDO** el pedido llega a uno de los momentos de aviso con la app cerrada o en segundo plano
- **ENTONCES** el aviso aparece en el teléfono

Criterio: A43-5

#### Scenario: avisos-E18 — El aviso llega a un iPhone

- **DADO** la app en un iPhone, con la sesión iniciada y el permiso concedido
- **CUANDO** el pedido llega a uno de los momentos de aviso con la app cerrada o en segundo plano
- **ENTONCES** el aviso aparece en el teléfono

Criterio: A43-5

SIN RESPUESTA — bloquea: avisos-E18 (DD-069, DD-017, DD-084)

### Requirement: Aviso a la lavandería y al repartidor

Si se aprueba, la api DEBE (MUST) avisar a la lavandería de un pedido nuevo y al repartidor de un pedido asignado, con el mismo servicio de avisos. El ANEXO B describe un sistema central que "avisa a las lavanderías", sin decir cómo, y el A.3 solo enumera los avisos del usuario: si se mandan y cuándo lo decide DD-054.

Cita: A → `NSV-2026-001-v8.pdf:29:ANEXO B, Sección B.4` y `NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3` (Módulo B, numeral 2); D → ADR-0008

#### Scenario: avisos-E19 — Aviso a la lavandería de un pedido nuevo

- **DADO** una lavandería con su teléfono registrado
- **CUANDO** le llega un pedido nuevo
- **ENTONCES** la api le manda el aviso del pedido nuevo

SIN RESPUESTA — bloquea: avisos-E19 (DD-054)

#### Scenario: avisos-E20 — Aviso al repartidor de un pedido asignado

- **DADO** un repartidor con su teléfono registrado
- **CUANDO** se le asigna un pedido
- **ENTONCES** la api le manda el aviso del pedido asignado

SIN RESPUESTA — bloquea: avisos-E20 (DD-054, DD-014)
