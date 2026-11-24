## ADDED Requirements

### Requirement: Resumen del pedido antes de confirmar

Antes de pagar y de confirmar, la app DEBE (MUST) mostrar el resumen del pedido con el servicio, la fecha y la ventana
de recolección, la dirección de recogida y la de entrega, el precio estimado y la política de cancelación. El precio DEBE
(MUST) presentarse como estimado.

Cita: A → `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #9`

#### Scenario: confirmacion-E01 — El resumen muestra servicio, fecha y direcciones

- **DADO** un usuario que eligió lavandería, servicio, ventana de recolección y direcciones
- **CUANDO** llega al resumen del pedido
- **ENTONCES** ve la lavandería, el servicio, la fecha con su ventana, la dirección de recogida y la de entrega
- **Y** el botón para aceptar el resumen y seguir al pago

Criterio: A43-2

#### Scenario: confirmacion-E02 — Precio estimado del lavado

- **DADO** una lavandería con su precio por kilo para el servicio elegido
- **CUANDO** se arma el resumen
- **ENTONCES** el resumen muestra el precio estimado del lavado, dicho como estimado

SIN RESPUESTA — bloquea: confirmacion-E02 (DD-012: cómo se estima el precio antes de pesar; DD-010: quién fija el
precio y en qué unidad va el lavado en seco). Mientras tanto, el renglón se reserva sin cifra.

#### Scenario: confirmacion-E03 — El envío en el resumen

- **DADO** un pedido con recogida y entrega
- **CUANDO** se arma el resumen
- **ENTONCES** el resumen muestra el envío como lo fije EL CLIENTE

SIN RESPUESTA — bloquea: confirmacion-E03 (DD-019: si el envío es por pedido o por trayecto, fijo o por distancia;
DD-025: si va aparte, incluido en un pedido mínimo o gratis desde un umbral).

Mientras tanto, el resumen no lleva renglón de envío. El Módulo A #9 enumera "Servicio, fecha, dirección, precio
estimado y política de cancelación" (`NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3`), y el envío como cargo aparte no
aparece: si lo es, y cómo se muestra, es parte de DD-019 y DD-025. La prueba que lo fija lleva confirmacion-E03 en su
nombre (`movil/__tests__/confirmacion/resumen.test.tsx`).

#### Scenario: confirmacion-E04 — La política de cancelación

- **DADO** la política de cancelación de la plaza
- **CUANDO** se arma el resumen
- **ENTONCES** el resumen muestra esa política antes del botón para aceptarlo

SIN RESPUESTA — bloquea: confirmacion-E04 (DD-016: la política la define EL CLIENTE). Se puede construir como texto de
configuración por plaza; su contenido no se inventa.

### Requirement: El pedido nace al aceptar el resumen, antes del pago

Al aceptar el usuario el resumen, el servidor DEBE (MUST) crear el pedido en un estado interno previo al pago, sin folio
y sin `confirmado_en`, con la plaza de su lavandería, la ventana de recolección, el servicio con el precio de la
lavandería en ese momento y la copia de las direcciones de recogida y de entrega. Esa copia NO DEBE (MUST NOT) cambiar
si después cambian el precio de la lavandería o la dirección guardada. Cada cambio de estado del pedido DEBE (MUST)
quedar también en su historial, en la misma transacción.

Cita: A → `NSV-2026-001-v8.pdf:16-17:ANEXO A, Sección A.3, Módulo A #9, #10 y #11` (resumen, pago y confirmación, en
ese orden; el orden apoya la regla, no la dice) · C → C-188 (Arthur) y C-197 (Nadir), que son la regla; A → `NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3` ("historial completo"); A →
`NSV-2026-001-v8.pdf:4:Cláusula I`; D → ADR-0016

#### Scenario: confirmacion-E05 — El pedido nace antes del pago, con su plaza y su copia

- **DADO** un resumen con ventana y direcciones válidas
- **CUANDO** el usuario lo acepta
- **ENTONCES** el servidor crea el pedido en el estado previo al pago, sin folio y sin `confirmado_en`
- **Y** con la plaza de su lavandería, la ventana, el servicio con el precio por kilo copiado de la lavandería y las dos
  direcciones copiadas
- **Y** guarda ese primer estado en `pedido_eventos`

Criterio: A43-2

SIN RESPUESTA — bloquea: solo el código y el nombre del estado previo al pago (DD-123). El escenario se prueba con un
estado de prueba del catálogo `estados_pedido`, que no es ninguno de los cinco del usuario.

#### Scenario: confirmacion-E14 — La copia no cambia con el precio ni con la dirección guardada

- **DADO** un pedido creado con el precio por kilo de su lavandería y con la dirección favorita "Casa"
- **CUANDO** la lavandería cambia su precio y la dirección "Casa" se edita o se borra
- **ENTONCES** el pedido conserva el precio y la dirección que copió al crearse

### Requirement: El pedido se confirma solo con el pago acreditado

El servidor DEBE (MUST) pasar el pedido a Confirmado, con un folio que no se repite en su plaza y con `confirmado_en`,
solo cuando el componente de cobro le avisa que la pasarela confirmó su pago. El botón del resumen y las peticiones de
la app NO DEBEN (MUST NOT) confirmar un pedido.

Cita: A → `NSV-2026-001-v8.pdf:16-17:ANEXO A, Sección A.3, Módulo A #10, #11 y #12` (el pago y después la confirmación
con folio; el orden apoya la regla, no la dice) · C → C-188 (Arthur) y C-197 (Nadir), que son la regla; A → `NSV-2026-001-v8.pdf:4:Cláusula I`; D → ADR-0010 (punto 1: el pago se da por bueno solo con el
webhook) y ADR-0016

#### Scenario: confirmacion-E06 — Dos pedidos de la misma plaza nunca comparten folio

- **DADO** varios pedidos de la misma plaza que se confirman, algunos al mismo tiempo
- **CUANDO** el servidor les asigna folio
- **ENTONCES** cada pedido tiene un folio distinto
- **Y** la base de datos rechaza un folio repetido en la misma plaza, con el único `(id_plazas, folio)`

#### Scenario: confirmacion-E07 — El botón del resumen no confirma el pedido

- **DADO** un pedido en el estado previo al pago
- **CUANDO** el usuario toca el botón del resumen, o la app manda cualquier petición que diga que ya pagó
- **ENTONCES** el pedido no pasa a Confirmado ni recibe folio
- **Y** el texto del botón dice que lleva al pago

#### Scenario: confirmacion-E13 — El pago acreditado confirma el pedido

- **DADO** un pedido en el estado previo al pago
- **CUANDO** el componente de cobro avisa que la pasarela confirmó su pago (en la prueba, la llamada directa a la
  operación de confirmar)
- **ENTONCES** el pedido pasa a Confirmado, con su folio y con `confirmado_en`
- **Y** el cambio queda en `pedido_eventos`, en la misma transacción
- **Y** se publica el evento de pedido confirmado, del que cuelga el correo

Criterio: A43-2

### Requirement: Revalidación al aceptar el resumen

Al aceptar el usuario el resumen, el servidor DEBE (MUST) volver a validar la ventana de recolección y las direcciones
con las mismas reglas con que se ofrecieron, y DEBE (MUST) rechazarlo, sin crear el pedido, si alguna dejó de valer.

Cita: A → `NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3`; D → ADR-0003

#### Scenario: confirmacion-E08 — La ventana dejó de estar disponible

- **DADO** un resumen abierto con la ventana 10:00–11:00
- **CUANDO** el usuario lo acepta cuando esa ventana ya empezó
- **ENTONCES** el servidor lo rechaza con su motivo, sin crear el pedido
- **Y** la app lleva al usuario a elegir otra ventana, con los demás datos del pedido intactos

Criterio: A43-2

#### Scenario: confirmacion-E15 — La dirección ya se usó con su plaza anterior

- **DADO** una dirección guardada que ya se copió en un pedido de su plaza
- **Y** las zonas cambiaron y su punto cae hoy en una zona de otra plaza
- **CUANDO** el usuario acepta un resumen con esa dirección y una lavandería de la plaza de hoy
- **ENTONCES** el servidor lo rechaza con el motivo `plaza_de_la_direccion_desactualizada`, sin crear el pedido
- **Y** el pedido anterior y su copia de la dirección no cambian
- **Y** la app ofrece guardar la dirección de nuevo

Criterio: A43-2

### Requirement: Pantalla de confirmación

Cuando el pedido queda Confirmado, la app DEBE (MUST) mostrar su número de folio y su resumen.

Cita: A → `NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo A #11`

#### Scenario: confirmacion-E09 — La pantalla muestra el folio y el resumen

- **DADO** un pedido que el servidor ya pasó a Confirmado después de su pago
- **CUANDO** la app lo consulta
- **ENTONCES** muestra el folio del pedido
- **Y** el resumen con la lavandería, el servicio, la fecha con su ventana y la dirección de recogida

Criterio: A43-6

### Requirement: Correo de confirmación

Al confirmarse el pedido, el servidor DEBE (MUST) enviar un correo a la dirección de la cuenta con el folio y el
resumen del pedido. Si el envío falla, el pedido DEBE (MUST) seguir confirmado y el correo DEBE (MUST) reintentarse.

Cita: A → `NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo A #11`; A → `NSV-2026-001-v8.pdf:30:ANEXO B, Sección B.5`

#### Scenario: confirmacion-E10 — El correo lleva el folio y el resumen

- **DADO** un pedido recién confirmado
- **CUANDO** el servidor procesa la confirmación
- **ENTONCES** pone en cola un correo a la dirección de la cuenta con el folio, el servicio, la fecha con su ventana y
  las direcciones
- **Y** el nombre del producto sale de la configuración, nunca escrito en la plantilla

Criterio: A43-6

SIN RESPUESTA — bloquea: solo el envío fuera de local (DD-071: el proveedor de correo; DD-023: el dominio del
remitente). En local el correo se captura sin enviarse (ADR-0014) y el escenario se prueba completo.

#### Scenario: confirmacion-E11 — Si el correo falla, el pedido sigue confirmado

- **DADO** un servicio de correo que falla al enviar
- **CUANDO** se confirma un pedido
- **ENTONCES** el pedido queda Confirmado y el usuario ve su folio
- **Y** el correo queda para reintentarse

Criterio: A43-2

### Requirement: Cada usuario ve solo sus pedidos

El servidor DEBE (MUST) mostrar el folio, el resumen y la confirmación de un pedido solo a la cuenta que lo hizo.

Cita: A → `NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3`

#### Scenario: confirmacion-E12 — Otro usuario no ve un pedido ajeno

- **DADO** dos usuarios, cada uno con un pedido confirmado
- **CUANDO** uno pide el pedido del otro, por su `id_publico` o por su folio
- **ENTONCES** el servidor responde como si ese pedido no existiera
- **Y** no devuelve ningún dato de él

Criterio: SEG-3
