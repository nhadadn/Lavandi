## ADDED Requirements

### Requirement: Perfil de una lavandería activa

El servidor DEBE (MUST) entregar el perfil de una lavandería activa con su nombre, descripción, fotos, servicios con
su precio, calificación promedio, número de calificaciones y reseñas. Una lavandería suspendida o dada de baja no tiene
perfil para el usuario. Solo responde con sesión iniciada.

Cita: A → `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #5` y `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #1`; D → ADR-0003

#### Scenario: perfil-lavanderia-E01 — El perfil completo

- **DADO** una lavandería de prueba activa, con descripción, tres fotos, dos servicios con precio y cuatro
  calificaciones
- **CUANDO** la app pide su perfil con sesión iniciada
- **ENTONCES** el servidor devuelve su nombre, su descripción, sus tres fotos, sus dos servicios con su precio y sus
  reseñas
- **Y** la calificación promedio es la de sus cuatro calificaciones, y el número de calificaciones es 4

#### Scenario: perfil-lavanderia-E02 — Suspendida, dada de baja o sin sesión, sin perfil

- **DADO** una lavandería de prueba suspendida y otra dada de baja
- **CUANDO** la app pide el perfil de cada una con sesión iniciada
- **ENTONCES** el servidor responde que no existe, sin devolver sus datos
- **Y** una petición sin sesión al perfil de una lavandería activa también se rechaza sin datos

### Requirement: Fotos en almacenamiento privado

Las fotos de la lavandería DEBEN (MUST) guardarse en el almacenamiento de imágenes del entorno, privado, y llegar a la
app solo con URLs firmadas que vencen.

Cita: A → `NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3`; D → ADR-0006 y ADR-0012

#### Scenario: perfil-lavanderia-E03 — Cada foto llega con URL firmada

- **DADO** una lavandería de prueba con tres fotos guardadas en el almacenamiento del entorno
- **CUANDO** la app pide su perfil
- **ENTONCES** cada foto llega como una URL firmada que vence en el tiempo fijado en la configuración
- **Y** la foto no se puede leer por su ruta sin firma

#### Scenario: perfil-lavanderia-E04 — Una URL vencida no entrega la foto

- **DADO** la URL firmada de una foto de prueba
- **CUANDO** se usa después de su vencimiento
- **ENTONCES** el almacenamiento no entrega la foto

### Requirement: Servicios que ofrece y su precio por kilo

El perfil DEBE (MUST) listar solo los servicios que la lavandería ofrece, de entre lavado estándar, lavado con
planchado y lavado en seco, cada uno con el precio registrado para esa lavandería y su unidad. El estándar y el de
planchado van por kilo.

Cita: A → `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #5 y #6` y `NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo B #1`

#### Scenario: perfil-lavanderia-E05 — Solo los servicios que ofrece, con precio por kilo

- **DADO** una lavandería de prueba que ofrece lavado estándar y lavado con planchado, con un precio de prueba para
  cada uno
- **CUANDO** la app pide su perfil
- **ENTONCES** el servidor devuelve solo esos dos servicios
- **Y** cada uno con su precio de prueba y la unidad "kilo"

#### Scenario: perfil-lavanderia-E06 — El precio del lavado en seco

- **DADO** una lavandería de prueba que ofrece lavado en seco
- **CUANDO** la app pide su perfil
- **ENTONCES** el lavado en seco llega con su precio en la unidad que fije DD-010

SIN RESPUESTA — bloquea: perfil-lavanderia-E06 (DD-010; BR-16). La unidad se guarda como dato, así que la respuesta
no exige migración.

### Requirement: Calificación y reseñas sin datos de quien califica

El perfil DEBE (MUST) traer la calificación promedio de 1 a 5 y el número de calificaciones de la lavandería, y sus
reseñas con estrellas, comentario si lo hay y fecha, sin datos personales de quien calificó.

Cita: A → `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #5`, `NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo A #14` y `NSV-2026-001-v8.pdf:10:Cláusula X`

#### Scenario: perfil-lavanderia-E07 — Una lavandería sin calificaciones

- **DADO** una lavandería de prueba activa sin calificaciones
- **CUANDO** la app pide su perfil
- **ENTONCES** la calificación promedio llega vacía, no en cero
- **Y** el número de calificaciones es 0

#### Scenario: perfil-lavanderia-E08 — Las reseñas no dicen quién calificó

- **DADO** una lavandería de prueba con una calificación con comentario y otra sin comentario
- **CUANDO** la app pide su perfil
- **ENTONCES** cada reseña trae sus estrellas, su fecha y, si lo tiene, su comentario
- **Y** ninguna trae nombre, correo, teléfono, foto ni identificador de quien calificó

### Requirement: Pantalla del perfil

La app DEBE (MUST) mostrar el perfil que entrega el servidor: fotos, descripción, servicios con su precio por kilo,
calificación y reseñas, con un acceso para pedir en esa lavandería.

Cita: A → `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #5` y `NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3`

#### Scenario: perfil-lavanderia-E09 — La pantalla muestra el perfil

- **DADO** el perfil de una lavandería de prueba con fotos, servicios con precio y reseñas
- **CUANDO** se abre su pantalla desde la tarjeta de la lavandería
- **ENTONCES** se ven sus fotos, su descripción, cada servicio con su precio por kilo, su calificación promedio con el
  número de calificaciones y sus reseñas
- **Y** un acceso para pedir en esa lavandería

Criterio: A43-6 (F-A05)

#### Scenario: perfil-lavanderia-E10 — Sin calificaciones, sin número

- **DADO** el perfil de una lavandería de prueba sin calificaciones
- **CUANDO** se abre su pantalla
- **ENTONCES** la app dice con texto que aún no tiene calificaciones
- **Y** no muestra ningún promedio

### Requirement: Selección del tipo de servicio

Desde el perfil, la app DEBE (MUST) dejar elegir un tipo de servicio de los que ofrece esa lavandería, de entre los
tres del contrato, y guardar la lavandería y el tipo en el pedido en curso para el paso de fecha y hora.

Cita: A → `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #6` y `NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3`

#### Scenario: perfil-lavanderia-E11 — Solo se eligen los tipos que ofrece

- **DADO** el perfil de una lavandería de prueba que ofrece lavado estándar y lavado con planchado
- **CUANDO** la persona toca el acceso para pedir
- **ENTONCES** puede elegir lavado estándar o lavado con planchado
- **Y** no puede elegir lavado en seco, que esa lavandería no ofrece

Criterio: A43-6 (F-A06)

#### Scenario: perfil-lavanderia-E12 — La elección queda en el pedido en curso

- **DADO** la selección de servicio de una lavandería de prueba
- **CUANDO** la persona elige lavado estándar y continúa
- **ENTONCES** el pedido en curso, en el teléfono, guarda esa lavandería y ese tipo
- **Y** la app pasa al paso de fecha y hora

Criterio: A43-2 (tramo "elegir lavandería")

#### Scenario: perfil-lavanderia-E13 — Sin tipo elegido no se continúa

- **DADO** la selección de servicio sin ningún tipo elegido
- **CUANDO** la persona intenta continuar
- **ENTONCES** la app no avanza
- **Y** dice con texto que falta elegir un tipo de servicio

### Requirement: Horario de la lavandería por día de la semana

El servidor DEBE (MUST) guardar el horario de cada lavandería en filas por día de la semana, del 1 al 7, cada una con
su hora de inicio y su hora de fin y de la misma plaza que la lavandería, y la base DEBE (MUST) rechazar un horario que
termina antes de empezar o con un día fuera de rango. En este cambio el horario es de prueba y el perfil no lo muestra.

Cita: A → `NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo B #1 y #6`; D → ADR-0016

#### Scenario: perfil-lavanderia-E14 — El horario de prueba queda guardado por día

- **DADO** una lavandería de prueba activa
- **CUANDO** se guarda un horario de prueba para un día de la semana, con su hora de inicio y su hora de fin
- **ENTONCES** queda una fila de ese día ligada a la lavandería y a su misma plaza
- **Y** un horario de esa lavandería con otra plaza se rechaza

#### Scenario: perfil-lavanderia-E15 — La base rechaza un horario imposible

- **DADO** una lavandería de prueba
- **CUANDO** se intenta guardar un horario cuya hora de fin es igual o anterior a su hora de inicio, o uno con un día
  fuera del 1 al 7
- **ENTONCES** la base de datos rechaza la fila, aunque el código no lo haya validado

### Requirement: Cada calificación es de un solo calificado

La base DEBE (MUST) guardar cada calificación con estrellas de 1 a 5 y de exactamente un calificado, la lavandería o el
repartidor, nunca los dos ni ninguno. El perfil de la lavandería DEBE (MUST) contar solo las calificaciones de esa
lavandería.

Cita: A → `NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo A #14 y Módulo B #4` y `NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3, Módulo C #4`; D → ADR-0016

#### Scenario: perfil-lavanderia-E16 — Sin calificado, o con los dos, no se guarda

- **DADO** la tabla de calificaciones
- **CUANDO** se intenta guardar una calificación sin lavandería ni repartidor, o con los dos
- **ENTONCES** la base de datos la rechaza

#### Scenario: perfil-lavanderia-E17 — Las estrellas van de 1 a 5

- **DADO** una lavandería de prueba
- **CUANDO** se intenta guardar una calificación suya con 0 o con 6 estrellas
- **ENTONCES** la base de datos la rechaza
- **Y** una calificación con 1 estrella y otra con 5 quedan guardadas

#### Scenario: perfil-lavanderia-E18 — La calificación del repartidor no entra al perfil de la lavandería

- **DADO** una lavandería de prueba con dos calificaciones suyas, y un pedido suyo en el que la persona calificó
  también al repartidor
- **CUANDO** la app pide el perfil de la lavandería
- **ENTONCES** el promedio, el número de calificaciones y las reseñas cuentan solo las dos de la lavandería

SIN RESPUESTA — bloquea: perfil-lavanderia-E18 (DD-052). No está decidido si el usuario califica al repartidor; la
tabla ya lo admite sin migración. Además necesita `pedidos` y `repartidores`, que crean `resumen-y-confirmacion` y
`repartidor`.
