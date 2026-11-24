## ADDED Requirements

### Requirement: Ingreso manual de la dirección

La app DEBE (MUST) dejar escribir la dirección con calle y número, código postal, colonia, municipio y referencias, y la
colonia DEBE (MUST) ser obligatoria. El municipio se elige de la lista del territorio (requisito "El municipio, de la
lista del territorio"). Al guardarla, el servidor DEBE (MUST) convertirla en un punto del mapa una sola vez y no volver
a consultarla para esa dirección.

Cita: A → `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #8`; C → C-238 (código postal y municipio de la lista); D → ADR-0009

#### Scenario: direcciones-E01 — Una dirección escrita se guarda con su punto

- **DADO** un usuario con sesión iniciada
- **CUANDO** escribe calle y número y colonia, elige el municipio de la lista, y guarda
- **ENTONCES** el servidor consulta una vez el servicio de geocodificación, simulado en el test
- **Y** guarda la dirección con el punto que devolvió

Criterio: A43-6

#### Scenario: direcciones-E02 — La colonia es obligatoria

- **DADO** un usuario que escribe una dirección sin colonia
- **CUANDO** intenta guardarla
- **ENTONCES** el servidor la rechaza con un error que nombra el campo
- **Y** la app señala el campo de la colonia y no consulta la geocodificación
- **Y** pasa igual si escribió un código postal del catálogo y no eligió ni escribió la colonia: la lista ayuda, no la
  llena sola

#### Scenario: direcciones-E03 — La dirección escrita no se encuentra

- **DADO** una dirección escrita que el servicio de geocodificación no encuentra
- **CUANDO** el usuario la guarda
- **ENTONCES** el servidor no le inventa un punto y responde que no la encontró
- **Y** la app le ofrece marcarla en el mapa

#### Scenario: direcciones-E15 — Fuera de producción y sin llave, la geocodificación es de prueba

- **DADO** un entorno que no es producción y que no tiene la llave del servicio de geocodificación
- **CUANDO** el usuario guarda una dirección escrita en el municipio de Durango
- **ENTONCES** el servidor la ubica dentro de la zona de la plaza de prueba de Durango (plazas-E16), siempre en el
  mismo punto para el mismo texto
- **Y** una dirección de otro municipio no se encuentra, como en direcciones-E03
- **Y** en producción sin llave, el servidor sigue respondiendo que no pudo buscar la dirección
- **Y** con la llave, en cualquier entorno, se usa el servicio real

Decisión: Claude, por delegación de Arthur (C-236), para probar el pedido completo desde un teléfono o el emulador
mientras no existan las llaves de prueba de Google (DD-072). No es el territorio inicial ni un punto real.

### Requirement: Ingreso desde el mapa

La app DEBE (MUST) dejar marcar la dirección moviendo el mapa bajo un pin, con el mapa de Apple en iPhone y el de
Google en Android. El punto marcado DEBE (MUST) guardarse tal cual, sin geocodificación inversa, y el usuario DEBE
(MUST) escribir la colonia y las demás señas.

Cita: A → `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #8`; A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3`; D → ADR-0009

#### Scenario: direcciones-E04 — Marcar la dirección en el mapa

- **DADO** un usuario que elige "en el mapa" al agregar una dirección
- **CUANDO** deja el pin sobre su puerta, escribe la colonia y las señas, y guarda
- **ENTONCES** la dirección se guarda con el punto del pin
- **Y** el servidor no consulta el servicio de geocodificación

Criterio: A43-6

#### Scenario: direcciones-E05 — El mapa es el de cada plataforma

- **DADO** la app en un iPhone y en un teléfono Android
- **CUANDO** el usuario abre el mapa para marcar su dirección
- **ENTONCES** en iPhone se ve el mapa de Apple y en Android el de Google
- **Y** el componente de mapa no fija un proveedor

### Requirement: Direcciones favoritas

El usuario DEBE (MUST) poder guardar una dirección como favorita, con un nombre, y elegirla después de su lista sin
volver a escribirla. Elegir una favorita DEBE (MUST) costar cero consultas de geocodificación.

Cita: A → `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #8`; D → ADR-0009

#### Scenario: direcciones-E06 — Guardar una dirección como favorita

- **DADO** un usuario que agrega una dirección y la marca como favorita con el nombre "Casa"
- **CUANDO** la guarda
- **ENTONCES** "Casa" aparece en su lista de direcciones favoritas

Criterio: A43-6

#### Scenario: direcciones-E07 — Elegir una favorita no vuelve a consultar el mapa

- **DADO** un usuario con la favorita "Casa" ya guardada
- **CUANDO** la elige para un pedido nuevo
- **ENTONCES** el pedido usa el punto guardado de "Casa"
- **Y** no hay ninguna consulta al servicio de geocodificación

#### Scenario: direcciones-E08 — Una dirección que no es favorita no entra a la lista

- **DADO** un usuario que agrega una dirección sin marcarla como favorita
- **CUANDO** la usa en su pedido
- **ENTONCES** la dirección queda en ese pedido
- **Y** no aparece en su lista de favoritas

### Requirement: Dirección de recogida y dirección de entrega

El pedido DEBE (MUST) llevar una dirección de recogida y una de entrega. El usuario DEBE (MUST) poder indicar que la
entrega es en la misma dirección de la recogida, o elegir otra.

Cita: A → `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #8`; A → `NSV-2026-001-v8.pdf:30:ANEXO B, Sección B.5`

#### Scenario: direcciones-E09 — Entrega en la misma dirección

- **DADO** un usuario que eligió "Casa" para la recogida
- **CUANDO** indica que la entrega es en la misma dirección y continúa
- **ENTONCES** el pedido lleva "Casa" como recogida y como entrega
- **Y** las dos llegan al resumen del pedido

Criterio: A43-2

#### Scenario: direcciones-E10 — Entrega en otra dirección

- **DADO** un usuario que eligió "Casa" para la recogida
- **CUANDO** elige "Oficina" para la entrega y continúa
- **ENTONCES** el pedido lleva "Casa" como recogida y "Oficina" como entrega

Criterio: A43-6

### Requirement: Dentro de la zona de servicio de la plaza

El servidor DEBE (MUST) aceptar como recogida o como entrega solo una dirección cuyo punto cae dentro de una zona de
servicio de la plaza de la lavandería elegida, y DEBE (MUST) responder el motivo cuando la rechaza.

Cita: A → `NSV-2026-001-v8.pdf:4:Cláusula I`; D → ADR-0004

#### Scenario: direcciones-E11 — Dirección fuera de toda zona de servicio

- **DADO** una dirección cuyo punto no cae en ninguna zona de servicio cargada
- **CUANDO** el usuario la elige como recogida o como entrega
- **ENTONCES** el servidor la rechaza con el motivo "fuera de la zona de servicio"
- **Y** la app lo dice y deja elegir otra dirección

#### Scenario: direcciones-E12 — Dirección en otra plaza

- **DADO** una lavandería de la plaza de prueba A
- **Y** una dirección dentro de una zona de servicio de la plaza de prueba B
- **CUANDO** el usuario la elige para un pedido con esa lavandería
- **ENTONCES** el servidor la rechaza para ese pedido, con su motivo

#### Scenario: direcciones-E13 — Dirección lejos de la lavandería, dentro de su plaza

- **DADO** una dirección dentro de la plaza de la lavandería, pero lejos de ella
- **CUANDO** el usuario la elige como recogida
- **ENTONCES** el servidor la acepta o la rechaza según el radio de servicio que fije EL CLIENTE

SIN RESPUESTA — bloquea: direcciones-E13 (DD-083: qué es "cercana" y si la lavandería atiende toda su plaza o solo un
radio).

### Requirement: Cada cuenta ve solo sus direcciones

El servidor DEBE (MUST) mostrar y dejar usar las direcciones guardadas solo a la cuenta que las guardó.

Cita: A → `NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3`

#### Scenario: direcciones-E14 — Otro usuario no ve ni usa mis direcciones

- **DADO** dos usuarios, cada uno con sus direcciones guardadas
- **CUANDO** uno pide la lista, el detalle o el uso en un pedido de una dirección del otro
- **ENTONCES** el servidor responde como si esa dirección no existiera
- **Y** no devuelve ningún dato de ella

Criterio: SEG-3

### Requirement: El catálogo del territorio para la dirección

El servidor DEBE (MUST) tener un catálogo con los municipios del territorio del contrato, cada uno con su estado, y con
las colonias de cada código postal de esos municipios, cargado del catálogo de códigos postales de Correos de México
(SEPOMEX). Del Estado de Durango DEBEN (MUST) entrar todos sus municipios y de Coahuila, solo Torreón y Matamoros. La
carga DEBE (MUST) ser un comando que se puede repetir sin duplicar filas y que, si falla, no deja nada a medias.

Cita: A → `NSV-2026-001-v8.pdf:4:Cláusula I`; A → `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #8`; C → C-238; D → ADR-0016, regla 2

#### Scenario: direcciones-E16 — La carga del catálogo toma solo el territorio

- **DADO** un archivo de Correos de México del Estado de Durango y otro del de Coahuila, con el formato oficial (en el
  test, archivos de prueba con ese formato y datos inventados)
- **CUANDO** corre el comando de carga con los dos
- **ENTONCES** quedan en el catálogo todos los municipios de Durango y, de Coahuila, solo Torreón y Matamoros, cada uno
  con su estado
- **Y** quedan las colonias de esos municipios, cada una con su código postal y su municipio
- **Y** las colonias de otro municipio de Coahuila, como Saltillo, no entran
- **Y** correrlo otra vez con los mismos archivos deja el catálogo igual, sin filas repetidas
- **Y** un archivo al que le falta una columna del formato no carga nada, y el comando dice cuál falta

### Requirement: El municipio, de la lista del territorio

La app DEBE (MUST) ofrecer el municipio de la dirección como una lista con solo los municipios del catálogo del
territorio, y el servidor DEBE (MUST) rechazar un municipio que no esté en él, en la dirección escrita y en la marcada en
el mapa. El estado de la dirección DEBE (MUST) salir del municipio, sin pedirlo.

Cita: A → `NSV-2026-001-v8.pdf:4:Cláusula I`; A → `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #8`; C → C-238; D → ADR-0016, regla 2

#### Scenario: direcciones-E17 — La lista de municipios es la del territorio

- **DADO** el catálogo cargado
- **CUANDO** la persona abre el formulario de una dirección nueva
- **ENTONCES** la app pide al servidor la lista de municipios y la ofrece para elegir uno, sin campo de texto libre para
  el municipio
- **Y** la lista trae solo los municipios del catálogo, cada uno con su estado
- **Y** la dirección guardada con un municipio de la lista sale con el nombre del municipio y el de su estado

#### Scenario: direcciones-E18 — Un municipio fuera del territorio se rechaza

- **DADO** una dirección con un municipio que no está en el catálogo, como Saltillo, Coahuila, o con un código de
  municipio que no existe
- **CUANDO** se intenta guardarla, escrita o marcada en el mapa
- **ENTONCES** el servidor la rechaza con un error en el campo del municipio
- **Y** no consulta la geocodificación ni guarda nada

### Requirement: La colonia, por código postal

El formulario DEBE (MUST) pedir el código postal, que es opcional y, si viene, son cinco números. Con un código postal
del catálogo, la app DEBE (MUST) ofrecer sus colonias para elegir una. El catálogo ayuda y no bloquea: la colonia DEBE
(MUST) poder escribirse a mano, y el servidor NO DEBE (MUST NOT) rechazar una colonia ni un código postal por no estar
en el catálogo. La colonia sigue siendo obligatoria (direcciones-E02).

Cita: A → `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #8`; C → C-238; D → ADR-0009 (la colonia se captura en el formulario: decisión 4 de `presupuesto-de-mapas.md`, que el ADR adopta)

#### Scenario: direcciones-E19 — Las colonias de un código postal

- **DADO** el catálogo cargado con las colonias de un código postal
- **CUANDO** la persona escribe ese código postal en el formulario
- **ENTONCES** la app pide sus colonias al servidor y las ofrece en una lista, por nombre
- **Y** al elegir una, el campo de la colonia queda con el nombre del catálogo
- **Y** si la persona no había elegido municipio, queda elegido el de esa colonia

#### Scenario: direcciones-E20 — Un código postal sin catálogo, o una colonia que no aparece, se escribe a mano

- **DADO** un código postal de cinco números que no está en el catálogo, o uno cuya lista no trae la colonia de la
  persona
- **CUANDO** la persona escribe la colonia a mano y guarda
- **ENTONCES** el servidor guarda la dirección con la colonia y el código postal tal como vinieron, sin compararlos con
  el catálogo
- **Y** si el código postal no tenía colonias en el catálogo, la app lo dice junto al campo y deja escribir la colonia
- **Y** un código postal que no son cinco números se rechaza con un error en su campo, sin consultar la geocodificación

### Requirement: El autocompletado de la calle, apagado hasta tener la llave y el costo

Con el autocompletado encendido por configuración y con la llave del entorno, la app DEBE (MUST) sugerir la calle con
Google Places, consultado desde el servidor, con una sesión por dirección que termina en una sola consulta de detalle al
guardar, en lugar de la geocodificación. Apagado, o sin llave, la calle DEBE (MUST) ser texto libre y el servidor NO DEBE
(MUST NOT) consultar Places. Encenderlo o apagarlo NO DEBE (MUST NOT) cambiar el resto del formulario ni de la API.

Cita: A → `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #8`; A → `NSV-2026-001-v8.pdf:29:ANEXO B, Sección B.5` (Google retiró el crédito gratuito y cada función tiene su cuota; RN-23); C → C-238; D → ADR-0009, con la adenda propuesta en `design.md`, que falta asentar

#### Scenario: direcciones-E21 — Encendido, la calle se sugiere

- **DADO** el autocompletado encendido, con la llave del entorno, y un municipio elegido
- **CUANDO** la persona escribe al menos tres caracteres de la calle
- **ENTONCES** la app pide sugerencias al servidor con el token de sesión de esa dirección, y el servidor las pide a
  Places, simulado en el test, solo en México
- **Y** la app las muestra bajo la calle, con la atribución de Google que pidan sus condiciones
- **Y** al elegir una, la calle queda llena con ella

SIN RESPUESTA — bloquea: direcciones-E21 (DD-072, cerrada en quién abre las cuentas, C-170, pero la llave de Places de
cada entorno todavía no existe; y el costo por búsqueda, que espera su fila en la deuda, propuesta en `design.md`).

#### Scenario: direcciones-E22 — Una sesión por dirección, que cierra al guardar

- **DADO** una dirección escrita con una sugerencia elegida y su token de sesión
- **CUANDO** la persona la guarda
- **ENTONCES** el servidor pide a Places, una sola vez y con el mismo token, solo el punto de esa sugerencia
- **Y** no consulta la geocodificación
- **Y** la siguiente dirección nueva usa un token nuevo
- **Y** si la persona cambió la calle después de elegir la sugerencia, la app no manda la sugerencia y el servidor
  geocodifica como en direcciones-E01

SIN RESPUESTA — bloquea: direcciones-E22 (DD-072, la llave de Places del entorno; y el costo por búsqueda, fila
propuesta en `design.md`).

#### Scenario: direcciones-E23 — Apagado o sin llave, la calle es texto libre

- **DADO** el autocompletado apagado por configuración, o encendido sin la llave del entorno
- **CUANDO** la persona escribe la calle
- **ENTONCES** el servidor responde que el autocompletado está apagado, sin consultar Places
- **Y** la app deja de pedir sugerencias para esa dirección y deja la calle como texto libre
- **Y** el resto del formulario no cambia
- **Y** una dirección que llega con una sugerencia se guarda geocodificada, como en direcciones-E01

SIN RESPUESTA — bloquea: direcciones-E23 (DD-072, la llave de Places del entorno; y el costo por búsqueda, fila
propuesta en `design.md`). Mientras siga bloqueado, la calle ya es texto libre (direcciones-E01).
