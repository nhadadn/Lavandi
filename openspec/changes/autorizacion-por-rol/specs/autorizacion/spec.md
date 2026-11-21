## ADDED Requirements

### Requirement: Un solo componente decide quién ve qué

`servidor/` DEBE (MUST) decidir en un solo componente, por rol, tenencia y plaza, si quien hace una petición puede ver o
tocar cada dato. Toda ruta de la API y todo recurso del panel declara su regla en ese componente, y una ruta sin regla
hace fallar las pruebas.

Cita: D → ADR-0003 (la autorización por rol, tenencia y plaza vive en `api`) · A →
`NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3` (Separación por tipo de usuario)

#### Scenario: autorizacion-E01 — Una ruta sin regla hace fallar las pruebas

- **DADO** una ruta de la API que devuelve o cambia datos y no declara su regla en el componente de autorización
- **CUANDO** corren las pruebas de `servidor/`
- **ENTONCES** la prueba de cobertura de reglas falla y nombra esa ruta
- **Y** las rutas que no piden sesión, como el inicio de sesión o el aviso de la pasarela (webhook), lo declaran
  expresamente

Criterio: SEG-3

### Requirement: Cada rol entra solo a su módulo

El sistema DEBE (MUST) dejar que cada cuenta use solo las funciones del módulo de su rol: usuario (Módulo A), socio
lavandería (Módulo B), socio repartidor (Módulo C) o administración (Módulo D, en el panel web y en la vista reducida de
la app). Las rutas de un módulo rechazan a los demás roles sin devolver datos.

Cita: A → `NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3`, `NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3` (los cuatro
módulos), `NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3` (Módulo C #1), `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3`
(Módulo D #1 y #2: "Alta") y `NSV-2026-001-v8.pdf:44:ANEXO D, Sección D.1` ("Pantalla de control reservada al dueño del
negocio") · D → ADR-0005 y ADR-0003

#### Scenario: autorizacion-E02 — Ningún rol entra al módulo de otro

- **DADO** una cuenta de cada rol, con sesión válida
- **CUANDO** cada una llama a las rutas de los módulos que no son el suyo
- **ENTONCES** todas esas llamadas se rechazan sin devolver datos
- **Y** cada cuenta sí llega a las rutas de su propio módulo

Criterio: SEG-3, A43-6

#### Scenario: autorizacion-E03 — El panel web solo admite la administración

- **DADO** una cuenta de usuario, una de socio lavandería y una de socio repartidor
- **CUANDO** cada una intenta entrar al panel de administración
- **ENTONCES** el panel la rechaza
- **Y** una cuenta de administración sí entra

Criterio: SEG-3

#### Scenario: autorizacion-E04 — Una cuenta con más de un rol

- **DADO** una persona que es usuario y también socio
- **CUANDO** entra a la app
- **ENTONCES** el componente de autorización aplica el rol o los roles que fije la regla de entrada

SIN RESPUESTA — bloquea: autorizacion-E04 (DD-053). Si una cuenta puede tener dos roles o se usa una cuenta por rol. La
recomendación de Claude, una cuenta y un rol en la v1, no está decidida.

#### Scenario: autorizacion-E05 — Un socio con el registro sin aprobar

- **DADO** una lavandería o un repartidor que se registró y todavía no tiene alta en el Módulo D
- **CUANDO** llama a las rutas de operación de su módulo
- **ENTONCES** no recibe pedidos ni datos de usuarios mientras no tenga alta

SIN RESPUESTA — bloquea: autorizacion-E05 (DD-053; BR-26). Cómo entra un socio a su módulo y qué ve mientras espera la
aprobación. Del repartidor el contrato dice que "El alta, validación y autorización del repartidor corresponden a EL
CLIENTE" (`NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3`).

#### Scenario: autorizacion-E06 — La app abre el módulo del rol de la cuenta

- **DADO** una cuenta que inicia sesión en la app
- **CUANDO** la app recibe su rol de `servidor/`
- **ENTONCES** abre el módulo de ese rol y no muestra la entrada a los demás

SIN RESPUESTA — bloquea: autorizacion-E06 (DD-053). La seguridad no depende de esta pantalla sino de autorizacion-E02:
la app solo esconde lo que `servidor/` ya rechaza.

### Requirement: El usuario solo ve lo suyo

`servidor/` DEBE (MUST) mostrar a cada usuario solo sus propios pedidos, direcciones, cobros, calificaciones y perfil,
de cualquier plaza. Lo único de otros que ve es lo que el contrato hace público dentro de la app: el perfil de las
lavanderías, con sus calificaciones y reseñas.

Cita: A → `NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3` ("Cada tipo de usuario accede únicamente a la información que
le corresponde") y `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3` (Módulo A #5)

#### Scenario: autorizacion-E07 — Un usuario no ve los datos de otro

- **DADO** dos usuarios, cada uno con un pedido, una dirección guardada y un cobro
- **CUANDO** el primero pide por su `id_publico` el pedido, la dirección o el cobro del segundo
- **ENTONCES** `servidor/` no devuelve ningún dato del segundo usuario

Criterio: SEG-3

#### Scenario: autorizacion-E08 — El usuario ve sus pedidos de todas las plazas

- **DADO** un usuario con un pedido en la plaza P1 y otro en la plaza P2
- **CUANDO** consulta sus pedidos
- **ENTONCES** recibe los dos

### Requirement: La lavandería solo ve lo suyo y a quienes le pidieron

`servidor/` DEBE (MUST) mostrar a un socio lavandería solo los pedidos, el historial, las estadísticas y el perfil de su
propia lavandería. De un usuario solo ve los datos que vienen en los pedidos que ese usuario le hizo; nunca los datos de
otra lavandería ni los de usuarios que no le han hecho pedidos.

Cita: A → `NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3` y `NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3` (Módulo B)

#### Scenario: autorizacion-E09 — Una lavandería no ve los datos de otra

- **DADO** dos lavanderías de la misma plaza, cada una con pedidos
- **CUANDO** la primera pide los pedidos, el historial, las estadísticas o el perfil de la segunda
- **ENTONCES** `servidor/` no devuelve ningún dato de la segunda
- **Y** la lista de pedidos de la primera no trae pedidos de la segunda

Criterio: SEG-3

#### Scenario: autorizacion-E10 — La lavandería solo ve a los usuarios que le pidieron

- **DADO** una lavandería, un usuario que le hizo un pedido y otro que nunca le ha pedido
- **CUANDO** la lavandería consulta sus pedidos, y pide por su `id_publico` al segundo usuario o un pedido suyo con otra
  lavandería
- **ENTONCES** ve los datos del primer usuario solo dentro del pedido que le hizo
- **Y** no recibe ningún dato del segundo

Criterio: SEG-3

### Requirement: El repartidor solo ve los pedidos que tiene asignados

`servidor/` DEBE (MUST) mostrar a un socio repartidor solo los pedidos que tiene asignados, su historial de entregas y
sus estadísticas. No ve pedidos que no tiene asignados, ni los datos de otro repartidor, ni los de usuarios fuera de sus
pedidos asignados.

Cita: A → `NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3` (Módulo C #2: "pedidos asignados") y
`NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3`

#### Scenario: autorizacion-E11 — Un repartidor no ve lo que no tiene asignado

- **DADO** dos repartidores de la misma plaza, cada uno con un pedido asignado, y un pedido sin asignar
- **CUANDO** el primero pide el pedido del segundo, el pedido sin asignar o el historial del segundo
- **ENTONCES** `servidor/` no devuelve ningún dato de esos pedidos ni del segundo repartidor
- **Y** la lista del primero solo trae su pedido asignado

Criterio: SEG-3

### Requirement: La plaza separa a los socios

`servidor/` DEBE (MUST) restringir a cada socio lavandería y a cada socio repartidor a la plaza en la que opera: no ve
pedidos, socios ni usuarios de otra plaza, aunque conozca su `id_publico`.

Cita: D → ADR-0003 (autorización por rol, tenencia y plaza) · A → `NSV-2026-001-v8.pdf:4:Cláusula I` (expansión a otras
plazas sin rediseño)

#### Scenario: autorizacion-E12 — Un socio no ve otra plaza

- **DADO** una lavandería y un repartidor de la plaza P1, y un pedido de la plaza P2
- **CUANDO** cada uno pide ese pedido por su `id_publico`
- **ENTONCES** `servidor/` no le devuelve ningún dato

Criterio: SEG-3

### Requirement: La administración ve todas las plazas

El sistema DEBE (MUST) dejar que la administración consulte los socios, los usuarios y los pedidos de todas las plazas,
porque el Módulo D gestiona a todos los socios y usuarios y monitorea todos los pedidos activos.

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #1 a #4: "todos los pedidos activos") y
`NSV-2026-001-v8.pdf:44:ANEXO D, Sección D.1`

#### Scenario: autorizacion-E13 — La administración consulta cualquier plaza

- **DADO** pedidos, socios y usuarios en las plazas P1 y P2
- **CUANDO** la administración los consulta
- **ENTONCES** el componente de autorización le permite ver los de las dos plazas

### Requirement: Prueba contra fugas en cada cambio

`servidor/` DEBE (MUST) tener una prueba automática que, con la sesión de cada rol, intente leer por cada ruta datos de
otro socio, de otro usuario y de otra plaza, y que falle si alguna respuesta los trae. Corre en la integración
continua, en cada cambio.

Cita: A → `NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3` y `NSV-2026-001-v8.pdf:10:Cláusula X` ("medidas de seguridad
técnicas razonables")

#### Scenario: autorizacion-E14 — El barrido contra fugas

- **DADO** datos sembrados en dos plazas, con dos lavanderías, dos repartidores y dos usuarios en cada una
- **CUANDO** la prueba recorre todas las rutas de la API con la sesión de cada rol, pidiendo datos que no le
  corresponden
- **ENTONCES** ninguna respuesta trae datos de otro socio, de otro usuario ni de otra plaza
- **Y** si alguna los trae, la prueba falla y nombra la ruta, el rol y el dato

Criterio: SEG-3
