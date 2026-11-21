## ADDED Requirements

### Requirement: Ingreso manual de la dirección

La app DEBE (MUST) dejar escribir la dirección con calle y número, colonia, municipio y referencias, y la colonia DEBE
(MUST) ser obligatoria. Al guardarla, el servidor DEBE (MUST) convertirla en un punto del mapa una sola vez y no volver
a consultarla para esa dirección.

Cita: A → `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #8`; D → ADR-0009

#### Scenario: direcciones-E01 — Una dirección escrita se guarda con su punto

- **DADO** un usuario con sesión iniciada
- **CUANDO** escribe calle y número, colonia y municipio, y guarda
- **ENTONCES** el servidor consulta una vez el servicio de geocodificación, simulado en el test
- **Y** guarda la dirección con el punto que devolvió

Criterio: A43-6

#### Scenario: direcciones-E02 — La colonia es obligatoria

- **DADO** un usuario que escribe una dirección sin colonia
- **CUANDO** intenta guardarla
- **ENTONCES** el servidor la rechaza con un error que nombra el campo
- **Y** la app señala el campo de la colonia y no consulta la geocodificación

#### Scenario: direcciones-E03 — La dirección escrita no se encuentra

- **DADO** una dirección escrita que el servicio de geocodificación no encuentra
- **CUANDO** el usuario la guarda
- **ENTONCES** el servidor no le inventa un punto y responde que no la encontró
- **Y** la app le ofrece marcarla en el mapa

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
