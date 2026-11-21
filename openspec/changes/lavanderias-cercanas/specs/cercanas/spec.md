## ADDED Requirements

### Requirement: Mapa nativo de cada plataforma

La app DEBE (MUST) mostrar las lavanderías en el mapa nativo de cada plataforma: el de Apple en iPhone y el de Google
en Android. La llave de Google Maps sale de la configuración del entorno, nunca del código.

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3`; D → ADR-0009 y ADR-0012

#### Scenario: cercanas-E01 — En iPhone, el mapa de Apple

- **DADO** la app en un iPhone
- **CUANDO** se abre la pantalla principal
- **ENTONCES** el mapa es el de Apple, porque el componente de mapa no fija proveedor

#### Scenario: cercanas-E02 — En Android, el mapa de Google con la llave de la configuración

- **DADO** la app en un teléfono Android
- **CUANDO** se abre la pantalla principal
- **ENTONCES** el mapa es el de Google
- **Y** la llave de Google Maps sale de la configuración del entorno, no de un literal en el código

### Requirement: Ubicación solo mientras se usa la app

La app DEBE (MUST) pedir la ubicación del teléfono solo mientras está abierta, con un texto que diga para qué se usa,
y no declarar ubicación en segundo plano. La usa para buscar las lavanderías cercanas.

Cita: A → `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #3` y `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3`

#### Scenario: cercanas-E03 — El permiso es solo mientras se usa

- **DADO** la primera vez que la persona abre la pantalla principal
- **CUANDO** la app pide la ubicación
- **ENTONCES** pide el permiso de uso mientras la app está abierta, con el texto que dice para qué se usa
- **Y** la configuración de la app no declara ubicación en segundo plano, ni en iPhone ni en Android

#### Scenario: cercanas-E04 — Con permiso, la consulta usa la ubicación del teléfono

- **DADO** que la persona dio el permiso
- **CUANDO** se abre la pantalla principal
- **ENTONCES** la app consulta las lavanderías con la ubicación actual del teléfono
- **Y** centra el mapa en ella

#### Scenario: cercanas-E05 — Sin permiso de ubicación

- **DADO** que la persona negó el permiso de ubicación
- **CUANDO** abre la pantalla principal
- **ENTONCES** la app consulta con el punto de referencia que fije DD-083 (por ejemplo, el centro de una plaza o una
  dirección escrita) y no deja el mapa en blanco

SIN RESPUESTA — bloquea: cercanas-E05 (DD-083)

### Requirement: Lavanderías cercanas activas

El servidor DEBE (MUST) devolver, para un punto, las lavanderías activas de las plazas activas, cada una con su
distancia en línea recta calculada en la base. Suspendida o dada de baja no es activa. Solo responde con sesión
iniciada.

Cita: A → `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #3`, `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #1` y `NSV-2026-001-v8.pdf:4:Cláusula I`; D → ADR-0003, ADR-0004 y ADR-0009

#### Scenario: cercanas-E06 — Cada lavandería trae su distancia en línea recta

- **DADO** lavanderías de prueba activas en una plaza activa
- **CUANDO** la app consulta con un punto
- **ENTONCES** cada lavandería devuelta trae su distancia en línea recta desde ese punto, calculada en la base
- **Y** la consulta no llama a ningún servicio externo de rutas ni de mapas

#### Scenario: cercanas-E07 — Suspendida, dada de baja o de una plaza no activa, no aparece

- **DADO** una lavandería suspendida, una dada de baja y una de una plaza que no está activa, las tres cerca del punto
- **CUANDO** la app consulta con ese punto
- **ENTONCES** ninguna de las tres aparece

#### Scenario: cercanas-E08 — El límite de cercanía

- **DADO** una lavandería activa más lejos del límite de cercanía
- **CUANDO** la app consulta con un punto
- **ENTONCES** esa lavandería no aparece
- **Y** el límite es un dato de configuración, no un número en el código

SIN RESPUESTA — bloquea: cercanas-E08 (DD-083). La forma del límite (radio u otra) y su valor los fija DD-083.

#### Scenario: cercanas-E09 — El resto de la definición de "activa"

- **DADO** una lavandería que no está suspendida ni dada de baja, pero no cumple el resto de la definición de "activa"
  que fije DD-083 (por ejemplo, aprobación u horario cargado)
- **CUANDO** la app consulta con un punto cercano a ella
- **ENTONCES** esa lavandería no aparece

SIN RESPUESTA — bloquea: cercanas-E09 (DD-083)

#### Scenario: cercanas-E10 — Sin sesión no hay consulta

- **DADO** una petición sin sesión iniciada
- **CUANDO** pide lavanderías cercanas
- **ENTONCES** el servidor la rechaza sin devolver lavanderías

#### Scenario: cercanas-E11 — Un punto sin lavanderías alrededor

- **DADO** un punto donde no hay lavanderías activas, por ejemplo en Monterrey, donde la app se puede descargar pero no
  se opera
- **CUANDO** la app consulta con ese punto
- **ENTONCES** la respuesta es una lista vacía, sin error

### Requirement: Pantalla principal con el mapa de lavanderías

La app DEBE (MUST) pintar en el mapa un pin por cada lavandería que devuelve la consulta, mostrar su tarjeta al
tocarlo y abrir su perfil desde la tarjeta. Si no hay lavanderías cercanas, lo dice con texto.

Cita: A → `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #3 y #5` y `NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3`

#### Scenario: cercanas-E12 — Del pin al perfil

- **DADO** que la consulta devuelve tres lavanderías de prueba
- **CUANDO** se pinta la pantalla principal
- **ENTONCES** hay tres pines, cada uno en la ubicación de su lavandería
- **Y** al tocar un pin se ve la tarjeta de esa lavandería con su nombre, su distancia y su calificación
- **Y** al tocar la tarjeta se abre el perfil de esa lavandería

Criterio: A43-2 (tramo "elegir lavandería"), A43-6 (F-A03)

#### Scenario: cercanas-E13 — El estado de disponibilidad en la tarjeta

- **DADO** una lavandería de la consulta
- **CUANDO** se pinta su tarjeta
- **ENTONCES** la tarjeta muestra su estado de disponibilidad con texto y con color (componente 1 de
  `vault/design-system/componentes.md`)

SIN RESPUESTA — bloquea: cercanas-E13 (DD-083, DD-078)

#### Scenario: cercanas-E14 — No hay lavanderías cercanas

- **DADO** que la consulta devuelve una lista vacía
- **CUANDO** se pinta la pantalla principal
- **ENTONCES** la app dice con texto que no hay lavanderías cercanas, en lugar de dejar un mapa vacío sin explicación

### Requirement: Acceso rápido a pedido nuevo

La pantalla principal DEBE (MUST) tener un acceso rápido a pedido nuevo que empiece por elegir la lavandería, primer
paso del recorrido del pedido.

Cita: A → `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #3` y `NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3`

#### Scenario: cercanas-E15 — "Pedido nuevo" lleva a elegir lavandería

- **DADO** la pantalla principal con lavanderías cercanas
- **CUANDO** la persona toca "Pedido nuevo"
- **ENTONCES** se abre la lista de lavanderías cercanas para elegir una

Criterio: A43-2 (tramo "elegir lavandería")

### Requirement: Filtros en el servidor

El servidor DEBE (MUST) filtrar la consulta por distancia máxima en línea recta, por calificación promedio mínima y
por disponibilidad, y combinar los filtros que se pidan.

Cita: A → `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #4`; D → ADR-0004 y ADR-0009

#### Scenario: cercanas-E16 — Filtro por distancia máxima

- **DADO** lavanderías de prueba activas a 1, 3 y 6 km en línea recta de un punto
- **CUANDO** la app consulta con ese punto y una distancia máxima de 3 km
- **ENTONCES** el servidor devuelve solo las de 1 y 3 km

#### Scenario: cercanas-E17 — Filtro por calificación mínima

- **DADO** lavanderías de prueba activas con calificación promedio de 3.5, 4.2 y 4.8
- **CUANDO** la app consulta con una calificación mínima de 4
- **ENTONCES** el servidor devuelve solo las de 4.2 y 4.8

#### Scenario: cercanas-E18 — Los filtros se combinan

- **DADO** lavanderías de prueba activas que cumplen solo la distancia, solo la calificación, las dos o ninguna
- **CUANDO** la app consulta con distancia máxima y calificación mínima a la vez
- **ENTONCES** el servidor devuelve solo las que cumplen las dos

#### Scenario: cercanas-E19 — Filtro por disponibilidad

- **DADO** lavanderías de prueba activas, unas disponibles y otras no según la definición de DD-083
- **CUANDO** la app consulta con el filtro de disponibilidad
- **ENTONCES** el servidor devuelve solo las disponibles

SIN RESPUESTA — bloquea: cercanas-E19 (DD-083, DD-078). El control de este filtro en la app se especifica cuando haya
respuesta.

#### Scenario: cercanas-E20 — El orden por defecto

- **DADO** varias lavanderías de prueba activas en la consulta
- **CUANDO** la app consulta sin pedir un orden
- **ENTONCES** el servidor las devuelve en el orden por defecto que fije DD-083

SIN RESPUESTA — bloquea: cercanas-E20 (DD-083)

### Requirement: Pantalla de filtros

La app DEBE (MUST) ofrecer los filtros de distancia y de calificación con los cortes que vengan de la configuración, no
escritos en la pantalla, y decir con texto cuándo ninguna lavandería coincide.

Cita: A → `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #4`

#### Scenario: cercanas-E21 — Elegir filtros y quitarlos

- **DADO** cortes de distancia y de calificación en la configuración de prueba
- **CUANDO** la persona elige un corte de cada uno
- **ENTONCES** la lista muestra solo las lavanderías que el servidor devuelve con esos dos filtros
- **Y** si no queda ninguna, la app lo dice y deja quitar los filtros

Criterio: A43-6 (F-A04). Los valores de los cortes son configuración y los fija DD-083; no bloquean el código.

### Requirement: Datos de prueba fuera de producción

El sembrado de plazas, lavanderías y calificaciones de prueba de este hito DEBE (MUST) correr solo en local y en
prueba, nunca en producción.

Cita: D → ADR-0012

#### Scenario: cercanas-E22 — En producción no se siembra nada de prueba

- **DADO** el servidor configurado como entorno de producción
- **CUANDO** se corre el sembrado de la base
- **ENTONCES** no se crea ninguna plaza, lavandería ni calificación de prueba
