## ADDED Requirements

### Requirement: Registro de la lavandería desde la app
La app DEBE (MUST) permitir que una lavandería envíe su registro con nombre, dirección, foto, servicios, precios y
horario por día de la semana, y el servidor DEBE (MUST) rechazar el registro si falta cualquiera de esos datos.

Cita: A → `NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo B #1`; D → `ADR-0006` (la app comprime la foto antes de
subirla)

#### Scenario: alta-lavanderias-E01 — Registro completo
- **DADO** una cuenta de socio lavandería que todavía no tiene una lavandería registrada
- **CUANDO** envía nombre, dirección, una foto, al menos un servicio con su precio y su horario de cada día de la semana
- **ENTONCES** el servidor guarda la lavandería con esos datos
- **Y** responde con la lavandería registrada

Criterio: A43-6

#### Scenario: alta-lavanderias-E02 — Registro incompleto
- **DADO** una cuenta de socio lavandería que todavía no tiene una lavandería registrada
- **CUANDO** envía el registro sin alguno de los datos: nombre, dirección, foto, servicios con su precio u horario
- **ENTONCES** el servidor rechaza el registro y señala cada dato que falta
- **Y** no guarda nada

#### Scenario: alta-lavanderias-E03 — Formulario de registro en la app
- **DADO** la pantalla de registro de lavandería
- **CUANDO** la persona llena los datos, toma una foto o la elige de su galería, y envía
- **ENTONCES** la app comprime la foto con los parámetros de configuración y envía el registro
- **Y** si el servidor rechaza un dato, la app lo muestra junto al campo que corresponde

#### Scenario: alta-lavanderias-E04 — Cómo llega la lavandería a su registro
- **DADO** una persona que quiere registrar su lavandería desde la app
- **CUANDO** busca dónde hacerlo
- **ENTONCES** llega al registro de lavandería por la entrada, con la cuenta y con el rol que fije DD-053

SIN RESPUESTA — bloquea: alta-lavanderias-E04 (DD-053)

### Requirement: Servicios y precio por kilo
El registro DEBE (MUST) indicar cuáles de los tres tipos de servicio del contrato ofrece la lavandería —lavado
estándar, lavado con planchado y lavado en seco— con su precio, y el servidor DEBE (MUST) rechazar cualquier otro tipo
de servicio.

Cita: A → `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #5 y #6`; A →
`NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo B #1`

#### Scenario: alta-lavanderias-E05 — Precio por kilo del lavado estándar y del lavado con planchado
- **DADO** el registro de una lavandería
- **CUANDO** declara lavado estándar y lavado con planchado, cada uno con su precio por kilo
- **ENTONCES** el servidor guarda cada servicio con su precio por kilo

#### Scenario: alta-lavanderias-E06 — Tipo de servicio fuera de los tres
- **DADO** el registro de una lavandería
- **CUANDO** declara un servicio que no es ninguno de los tres del contrato, por ejemplo lavado de calzado
- **ENTONCES** el servidor rechaza el registro y nombra el servicio no admitido
- **Y** no guarda nada

#### Scenario: alta-lavanderias-E07 — Precio del lavado en seco
- **DADO** el registro de una lavandería que ofrece lavado en seco
- **CUANDO** captura el precio de ese servicio
- **ENTONCES** el servidor lo guarda en la unidad que fije DD-010: por kilo o por prenda

SIN RESPUESTA — bloquea: alta-lavanderias-E07 (DD-010; BR-16)

#### Scenario: alta-lavanderias-E08 — Reglas del precio
- **DADO** un precio que la lavandería captura o edita
- **CUANDO** el servidor lo valida
- **ENTONCES** aplica la regla de gobierno del precio que fije DD-010: quién lo fija, si tiene piso y techo, y si
  necesita aprobación

SIN RESPUESTA — bloquea: alta-lavanderias-E08 (DD-010; BR-46)

### Requirement: Plaza de la lavandería según su dirección
El servidor DEBE (MUST) geocodificar una sola vez la dirección de la lavandería, asignarle la plaza de la zona de
servicio que la contiene y rechazar una dirección que quede fuera de las zonas del territorio inicial o que no se pueda
ubicar. Una dirección nueva, editada desde la app o desde el panel, que caiga en la zona de servicio de otra plaza DEBE
(MUST) rechazarse: la lavandería no cambia de plaza.

Cita: A → `NSV-2026-001-v8.pdf:4:Cláusula I` (el alta de socios lavandería se desarrolla exclusivamente para el
TERRITORIO INICIAL); A → `NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo B #6`; A →
`NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #1`; D → `ADR-0004`; D → `ADR-0009` (una geocodificación por
dirección); D → `ADR-0016` (la plaza viaja en las llaves compuestas de sus hijas, modelo-E06)

#### Scenario: alta-lavanderias-E09 — Dirección dentro de una zona de servicio
- **DADO** las zonas de servicio del territorio inicial cargadas
- **CUANDO** la lavandería registra una dirección que cae dentro de una de ellas
- **ENTONCES** el servidor la geocodifica una sola vez y guarda su ubicación
- **Y** la lavandería queda en la plaza de esa zona

#### Scenario: alta-lavanderias-E10 — Dirección fuera de las zonas o que no se puede ubicar
- **DADO** las zonas de servicio del territorio inicial cargadas
- **CUANDO** la lavandería registra una dirección fuera de todas las zonas, o una que la geocodificación no ubica
- **ENTONCES** el servidor rechaza el registro y lo indica en el campo de la dirección
- **Y** no guarda nada

#### Scenario: alta-lavanderias-E25 — Una dirección nueva en otra plaza no cambia la plaza
- **DADO** una lavandería registrada en una plaza
- **CUANDO** cambia su dirección, desde la app o desde el panel, a una que cae en una zona de servicio de otra plaza
- **ENTONCES** el servidor rechaza la edición y lo indica en el campo de la dirección, con el motivo `en_otra_plaza`
- **Y** la lavandería conserva su plaza, su dirección y su ubicación

Criterio: A43-6

### Requirement: Alta de la lavandería desde el panel
El panel web de administración DEBE (MUST) permitir dar de alta una lavandería, y solo una lavandería con alta DEBE
(MUST) aparecer al usuario y recibir pedidos.

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #1`; A →
`NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #3` ("lavanderías cercanas activas"); D → `ADR-0003`

#### Scenario: alta-lavanderias-E11 — La administración da de alta una lavandería
- **DADO** una lavandería que envió su registro completo desde la app
- **CUANDO** la administración le da de alta en el panel
- **ENTONCES** la lavandería queda activa y puede recibir pedidos

SIN RESPUESTA — bloquea: alta-lavanderias-E11 (DD-053: si el registro espera la aprobación del Módulo D, o si el alta
la captura la administración)

#### Scenario: alta-lavanderias-E12 — Lavandería registrada sin alta
- **DADO** una lavandería registrada a la que la administración no ha dado de alta
- **CUANDO** un usuario busca lavanderías o intenta hacerle un pedido
- **ENTONCES** la lavandería no aparece
- **Y** el servidor no le crea pedidos

SIN RESPUESTA — bloquea: alta-lavanderias-E12 (DD-053)

### Requirement: Edición de la lavandería desde el panel
El panel DEBE (MUST) mostrar las lavanderías con su estado y su plaza, y permitir a la administración editar los datos
de cualquiera de ellas —nombre, dirección, foto, servicios, precios, horario, descripción y fotos— con las mismas
validaciones del registro.

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #1` ("edición de socios"); A →
`NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo B #1`; D → `ADR-0003`

#### Scenario: alta-lavanderias-E13 — La administración edita una lavandería
- **DADO** la lista de lavanderías del panel, con su estado y su plaza
- **CUANDO** la administración abre una lavandería, cambia su nombre y su horario, y guarda
- **ENTONCES** el servidor guarda los datos nuevos
- **Y** la lavandería los ve en su perfil de la app

Criterio: A43-6

### Requirement: Suspensión de una lavandería
El panel DEBE (MUST) permitir suspender una lavandería y levantar la suspensión. Mientras está suspendida, la
lavandería NO DEBE (MUST NOT) aparecer al usuario ni recibir pedidos nuevos.

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #1` ("suspensión"); A →
`NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #3` ("lavanderías cercanas activas"); D → `ADR-0003`

#### Scenario: alta-lavanderias-E14 — Suspender una lavandería
- **DADO** una lavandería activa
- **CUANDO** la administración la suspende en el panel
- **ENTONCES** la lavandería deja de aparecer en la búsqueda de lavanderías del usuario
- **Y** el servidor rechaza un pedido nuevo para ella

Criterio: A43-6

#### Scenario: alta-lavanderias-E15 — Levantar la suspensión
- **DADO** una lavandería suspendida
- **CUANDO** la administración levanta la suspensión
- **ENTONCES** la lavandería recupera el estado que tenía antes de la suspensión
- **Y** vuelve a recibir pedidos nuevos si ese estado lo permitía

#### Scenario: alta-lavanderias-E16 — Pedidos en curso al suspender o dar de baja
- **DADO** una lavandería con pedidos aceptados que aún no llegan a Entregado
- **CUANDO** la administración la suspende o le da de baja
- **ENTONCES** esos pedidos siguen la regla que se fije: terminarse, pasar a otra lavandería o cancelarse con reembolso
- **Y** la lavandería ve en su app lo que esa misma regla fije

SIN RESPUESTA — bloquea: alta-lavanderias-E16 (DD-104)

### Requirement: Baja de una lavandería
El panel DEBE (MUST) permitir dar de baja una lavandería sin borrar sus datos ni su historial de pedidos. Una
lavandería dada de baja NO DEBE (MUST NOT) aparecer al usuario ni recibir pedidos.

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #1` ("baja"); A →
`NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3, Servicios externos` (la base de datos guarda "pedidos, pagos e historial
completo"); D → `ADR-0003`

#### Scenario: alta-lavanderias-E17 — Baja que conserva el historial
- **DADO** una lavandería con pedidos entregados
- **CUANDO** la administración le da de baja en el panel
- **ENTONCES** la lavandería deja de aparecer en la búsqueda y el servidor rechaza pedidos nuevos para ella
- **Y** la lavandería y sus pedidos pasados siguen guardados en la base

Criterio: A43-6

### Requirement: Perfil y horarios desde la app
La app DEBE (MUST) permitir que la lavandería edite su información —nombre, dirección, foto, servicios, precios y
horario— con las mismas validaciones del registro, y el servidor DEBE (MUST) impedir que una lavandería edite la
información o las ventanas de otra.

Cita: A → `NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo B #6`; A →
`NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3, Medidas de seguridad` (separación por tipo de usuario)

#### Scenario: alta-lavanderias-E18 — La lavandería edita su información
- **DADO** una lavandería registrada
- **CUANDO** cambia su foto y su horario desde la app y guarda
- **ENTONCES** el servidor guarda los datos nuevos, con las validaciones del registro

Criterio: A43-6

#### Scenario: alta-lavanderias-E19 — Pantalla de perfil y horarios
- **DADO** una lavandería registrada
- **CUANDO** abre su pantalla de perfil y horarios
- **ENTONCES** ve su información y sus ventanas de disponibilidad actuales
- **Y** puede editarlas y guardarlas, con cada error del servidor junto a su campo

#### Scenario: alta-lavanderias-E20 — Una lavandería no edita la información de otra
- **DADO** dos lavanderías registradas
- **CUANDO** la primera intenta editar la información o las ventanas de la segunda
- **ENTONCES** el servidor rechaza la petición
- **Y** los datos de la segunda no cambian

Criterio: SEG-3

### Requirement: Descripción y fotos del perfil de la lavandería
La lavandería DEBE (MUST) poder escribir una descripción opcional y mantener varias fotos ordenadas de su perfil desde
Perfil y horarios, y la administración DEBE (MUST) poder hacer lo mismo desde el panel con las mismas reglas. El
registro sigue pidiendo una sola foto.

Cita: A → `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #5` ("Fotos, descripción"); A →
`NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo B #6` ("Edición de información de la lavandería"); A →
`NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #1` ("edición de socios"); D → `ADR-0006`

#### Scenario: alta-lavanderias-E26 — La lavandería escribe su descripción
- **DADO** una lavandería registrada sin descripción
- **CUANDO** escribe su descripción en Perfil y horarios y guarda
- **ENTONCES** el servidor la guarda
- **Y** el perfil que ve el usuario la muestra (perfil-lavanderia-E01)
- **Y** si la deja vacía, el perfil queda sin descripción

Criterio: A43-6

#### Scenario: alta-lavanderias-E27 — Agregar una foto
- **DADO** una lavandería con menos fotos que el máximo de la configuración
- **CUANDO** agrega una foto, tomada o elegida y comprimida como la del registro (E03), y guarda
- **ENTONCES** la foto queda al final de su orden
- **Y** el perfil del usuario la muestra en ese lugar

Criterio: A43-6

#### Scenario: alta-lavanderias-E28 — Quitar una foto
- **DADO** una lavandería con dos fotos o más
- **CUANDO** quita una y guarda
- **ENTONCES** esa foto deja de estar en su perfil y su archivo se borra del almacenamiento
- **Y** la última foto no se puede quitar, porque el registro pide una (Módulo B #1)

Criterio: A43-6

#### Scenario: alta-lavanderias-E29 — Cambiar el orden
- **DADO** una lavandería con tres fotos
- **CUANDO** pasa la tercera antes de la segunda y guarda
- **ENTONCES** el perfil del usuario las muestra en el orden nuevo
- **Y** la primera es la principal

Criterio: A43-6

#### Scenario: alta-lavanderias-E30 — El máximo de fotos
- **DADO** una lavandería con el máximo de fotos de la configuración
- **CUANDO** intenta agregar otra
- **ENTONCES** la app no ofrece agregarla
- **Y** el servidor rechaza una lista con más fotos que el máximo, con un mensaje que lo dice, y nada cambia

Criterio: A43-6

#### Scenario: alta-lavanderias-E31 — El panel edita la descripción y las fotos
- **DADO** una lavandería
- **CUANDO** la administración cambia su descripción o sus fotos desde el panel
- **ENTONCES** se guardan con las reglas de E26 a E30

Criterio: A43-6

### Requirement: Ventanas de disponibilidad
La lavandería DEBE (MUST) poder definir, para cada día de la semana, las ventanas de una hora en que tiene
disponibilidad, y el servidor DEBE (MUST) rechazar una ventana que no dure exactamente una hora.

Cita: A → `NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo B #6` ("ventanas de disponibilidad"); A →
`NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #7` ("ventanas de 1 hora")

#### Scenario: alta-lavanderias-E21 — La lavandería define sus ventanas
- **DADO** una lavandería registrada
- **CUANDO** marca para los jueves las ventanas de 10:00 a 11:00 y de 11:00 a 12:00, y guarda
- **ENTONCES** el servidor guarda esas dos ventanas para los jueves

Criterio: A43-6

#### Scenario: alta-lavanderias-E22 — Ventana que no dura una hora
- **DADO** una lavandería registrada con ventanas guardadas
- **CUANDO** envía una ventana de 10:00 a 11:30
- **ENTONCES** el servidor la rechaza
- **Y** las ventanas guardadas no cambian

#### Scenario: alta-lavanderias-E23 — Cupo por ventana
- **DADO** una ventana de disponibilidad de la lavandería
- **CUANDO** la lavandería define cuántas recolecciones acepta en ella
- **ENTONCES** el servidor guarda ese cupo
- **Y** la agenda del usuario lo descuenta como fije la respuesta

SIN RESPUESTA — bloquea: alta-lavanderias-E23 (BR-17; DD-099)

#### Scenario: alta-lavanderias-E24 — Ventanas fuera del horario de atención
- **DADO** una lavandería con horario de atención de 9:00 a 18:00
- **CUANDO** define una ventana de disponibilidad fuera de ese horario
- **ENTONCES** el servidor la acepta o la rechaza según la regla de horario y de pedidos de noche que confirme el
  cliente

SIN RESPUESTA — bloquea: alta-lavanderias-E24 (DD-078)
