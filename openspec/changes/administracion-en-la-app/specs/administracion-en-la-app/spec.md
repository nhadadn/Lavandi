## ADDED Requirements

Cómo se leen estos escenarios. Cada uno lleva a la app y a la API de la administración una función del Módulo D o un
indicador del A.5 que ya está especificado para el panel web. No repite su regla: la nombra ("con la regla de
administracion-E03"), para que, si cambia, cambie en un solo lugar (`design.md`, decisión 1). Un escenario que ya
estaba bloqueado se lleva igual de bloqueado, con su misma DD. Las rutas van bajo `/api/administracion`, con sesión de
Sanctum y el componente único de autorización (ADR-0019; ADR-0022, decisión 7); su contrato está en `design.md`,
sección 4. Ninguna decisión técnica de aquí viene del contrato: la sostienen ADR-0022 y los ADR que cita.

### Requirement: La administración inicia sesión en la app

La cuenta de administración DEBE (MUST) iniciar sesión en la app con su correo y su contraseña, en la misma pantalla que
los otros roles, y entrar a su módulo sin que nada la mande a una página web. Su token DEBE (MUST) ser de
administración: con la habilidad `administracion` y ninguna otra, y con vencimiento a los días de la configuración (30
por omisión). Se revoca al cerrar la sesión, al crear una contraseña nueva por la recuperación y al vencer. La
administración NO DEBE (MUST NOT) entrar con Google. Las demás reglas de la entrada (credenciales, bloqueo y límite de
intentos) son las de `registro` y no cambian.

Cita: A → `NSV-2026-001-v8.pdf:3:Cláusula I` ("Aplicación única para iPhone y Android, con módulo de usuario, módulo de
socio lavandería, módulo de socio repartidor y panel de administración"); A → `NSV-2026-001-v8.pdf:21:ANEXO A, Sección
A.4.3` ("Los cuatro módulos (usuario, socio lavandería, socio repartidor y administración) accesibles y funcionales
conforme a A.3"); A → `NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3` (Control de acceso: "mantiene su sesión abierta de
forma segura, sin necesidad de que escriba su contraseña cada vez"); A → `NSV-2026-001-v8.pdf:10:Cláusula X` (medidas
de seguridad técnicas razonables); C → C-247 y decisión 69 de `acta-2026-11-20-modelo-y-pasarela.md`; D → ADR-0022,
decisiones 1 a 8

#### Scenario: administracion-app-E01 — Inicia sesión con su correo y su contraseña

- **DADO** una cuenta de administración creada con el comando de administracion-E26
- **CUANDO** la app envía su correo y su contraseña a `POST /api/sesion`
- **ENTONCES** el servidor responde con un token y con la cuenta, con su rol `administracion`
- **Y** el token se llama `administracion`, lleva la habilidad `administracion` y ninguna otra, y vence a los días de
  `acceso.dias_del_token_de_administracion`, 30 por omisión
- **Y** los tokens de las cuentas de usuario, de lavandería y de repartidor salen como antes, sin esa habilidad y sin
  vencimiento
- **Y** con una contraseña incorrecta, con la cuenta bloqueada o con demasiados intentos, responde lo mismo que a
  cualquier cuenta (registro-E08, administracion-E03 y registro-E30)

Criterio: A43-6

Cita: A → `NSV-2026-001-v8.pdf:3:Cláusula I`; A → `NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3` (Control de acceso);
D → ADR-0022, decisiones 1 a 3

#### Scenario: administracion-app-E02 — La app abre el módulo de la administración

- **DADO** la pantalla de inicio de sesión de la app, en iPhone y en Android
- **CUANDO** una cuenta de administración inicia sesión con su correo y su contraseña
- **ENTONCES** la app guarda la sesión y abre el inicio del módulo de la administración
- **Y** no muestra ningún aviso de que la administración entra por otro lado, ni un botón que abra una página
- **Y** al cerrar la app y volver a abrirla, entra al mismo inicio sin pedir otra vez la contraseña (registro-E11)
- **Y** con la sesión de otro rol, las pantallas de la administración llevan al arranque; con la de la administración,
  las de los otros módulos también

Criterio: A43-6

Cita: A → `NSV-2026-001-v8.pdf:3:Cláusula I`; A → `NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3`; A →
`NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3` (Control de acceso y Separación por tipo de usuario); C → C-247; D →
ADR-0022, decisión 11

#### Scenario: administracion-app-E03 — El inicio del módulo

- **DADO** la administración con la sesión iniciada en la app
- **CUANDO** abre el inicio de su módulo
- **ENTONCES** ve el título "Administración" y las entradas en dos grupos
- **Y** en "Gestión", las de Lavanderías, Repartidores, Usuarios, Monitoreo de pedidos, Comisiones, Ingresos,
  Conciliación y Avisos masivos, en el orden del Módulo D; en "Estado del negocio", las de Indicadores y Descargas
- **Y** junto a Repartidores, cuántos repartidores están pendientes de alta, y junto a Monitoreo de pedidos, cuántos
  pedidos activos hay, los mismos que dan esas pantallas (administracion-app-E19 y E28); sin repartidores pendientes,
  Repartidores no lleva conteo
- **Y** cada entrada abre su pantalla dentro de la app
- **Y** abajo, fijo, está "Cerrar sesión"
- **Y** ningún texto del módulo trae escritos en el código el nombre del producto ni el dominio

Criterio: A43-6

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #1 a #7); A → `NSV-2026-001-v8.pdf:22:ANEXO A,
Sección A.5`; C → C-248 de `acta-2026-11-20-modelo-y-pasarela.md` (el desarrollo sigue la Propuesta 6 del concilio:
los grupos y los dos conteos son de sus imágenes 03 y 56, en `mocks/Lavandi - Propuesta 06 - Administracion/`; son
diseño, no contrato); D → ADR-0022, decisiones 11 y 17

#### Scenario: administracion-app-E04 — Cierra su sesión

- **DADO** la administración con la sesión iniciada en la app
- **CUANDO** toca "Cerrar sesión" en el inicio de su módulo y lo confirma
- **ENTONCES** la app pide `DELETE /api/sesion`, que revoca el token con el que llegó (registro-E12)
- **Y** borra la sesión del teléfono y la lleva a la entrada (registro-E13)
- **Y** si no lo confirma, sigue dentro

Cita: A → `NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3` (Control de acceso); D → ADR-0022, decisiones 4 y 6

#### Scenario: administracion-app-E05 — La sesión vence o se revoca

- **DADO** un token de administración vencido, o revocado al cerrar la sesión, o revocado porque la cuenta creó una
  contraseña nueva con la recuperación (registro-E24)
- **CUANDO** la app hace con él cualquier petición, desde cualquier pantalla del módulo
- **ENTONCES** el servidor responde 401
- **Y** la app borra la sesión del teléfono y vuelve al inicio de sesión
- **Y** un token de administración que todavía no vence sigue sirviendo, sin pedir otra vez la contraseña

Cita: A → `NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3` (Control de acceso); A → `NSV-2026-001-v8.pdf:10:Cláusula X`;
D → ADR-0022, decisiones 2 y 4; D → ADR-0018, decisiones 1 y 6

#### Scenario: administracion-app-E06 — Un token sin la habilidad no abre la administración

- **DADO** una cuenta de administración con un token que no lleva la habilidad `administracion`, por ejemplo uno con
  `*` que salió por Google antes de este cambio
- **CUANDO** llama a cualquier ruta de `/api/administracion`, a `GET /api/cuenta` o a `DELETE /api/sesion`
- **ENTONCES** el servidor responde 403, sin datos, y nada cambia
- **Y** la habilidad se revisa literal: `*` no cuenta como `administracion`
- **Y** cuando esa cuenta inicia sesión con su correo y su contraseña, el servidor borra sus tokens que no llevan la
  habilidad, así que ninguno queda vivo sin poder cerrarse (ADR-0022, decisión 4)

Criterio: SEG-3

Cita: A → `NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3` (Separación por tipo de usuario); A →
`NSV-2026-001-v8.pdf:10:Cláusula X`; D → ADR-0022, decisión 8; D → ADR-0019

#### Scenario: administracion-app-E07 — Ningún otro rol entra a la administración

- **DADO** una cuenta de usuario, una de socio lavandería y una de socio repartidor, cada una con su sesión
- **CUANDO** cualquiera llama a una ruta de `/api/administracion`
- **ENTONCES** el servidor responde 403, sin datos, y nada cambia (autorizacion-E02 y repartidor-E09)
- **Y** sin sesión responde 401
- **Y** cada ruta de `/api/administracion` declara su regla en el componente de autorización, y la prueba de cobertura
  de reglas la recorre (autorizacion-E01)

Criterio: SEG-3

Cita: A → `NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3` (Separación por tipo de usuario); D → ADR-0019, decisión 1;
D → ADR-0022, decisión 7

#### Scenario: administracion-app-E08 — La administración no entra con Google

- **DADO** una cuenta de administración con contraseña, sin Google ligado y con una sesión abierta en la app
- **CUANDO** alguien entra con un `idToken` de Google válido, con el correo de esa cuenta y verificado por Google
- **ENTONCES** `POST /api/sesion/google` responde 422 con "La administración entra con su correo y su contraseña." en
  `id_token`, sin token
- **Y** no liga la cuenta de Google, no anula la contraseña y no cierra la sesión abierta: la cuenta queda como estaba,
  a diferencia de registro-E18
- **Y** con una cuenta de administración que ya tiene Google ligado (por el hueco de la tarea 6.2 de `administracion`),
  la respuesta es la misma: 422 en `id_token`, sin token y sin tocar la cuenta
- **Y** la app muestra ese mensaje bajo el botón de Google y no guarda ninguna sesión

Cita: A → `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3` (Módulo A #2: Google Sign-In es opción del registro del
usuario); A → `NSV-2026-001-v8.pdf:10:Cláusula X`; D → ADR-0022, decisión 5

### Requirement: El panel web queda como respaldo y nada lo muestra

La app NO DEBE (MUST NOT) enlazar ni abrir el panel web de Filament: la administración hace en la app el Módulo D y el
A.5. El panel DEBE (MUST) seguir como respaldo interno, con su dirección, su entrada con contraseña y rol de
administración y sus pruebas; no se borra y no recibe funciones nuevas del Módulo D ni del A.5. El contenido de la
portada (testimonios y logos de prensa) se sigue editando ahí; quién lo edita y por qué vía llega al panel espera
DD-141 (ADR-0022, decisión 21).

Cita: A → `NSV-2026-001-v8.pdf:3:Cláusula I`; A → `NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2` (Testimonios y Prensa
/ Reconocimientos, el contenido que se edita en el panel); C → C-247 y decisión 69 de
`acta-2026-11-20-modelo-y-pasarela.md`; D → ADR-0022, decisiones 18 a 21. Que el panel quede como respaldo viene de
C-247, no del contrato

#### Scenario: administracion-app-E09 — La app no lleva al panel web

- **DADO** la app, con la sesión de cualquier rol o sin sesión
- **CUANDO** una cuenta de administración inicia sesión, o cualquiera recorre la entrada
- **ENTONCES** ninguna pantalla dice que la administración entra por el panel ni muestra un botón "Abrir el panel"
- **Y** la configuración de la app no lee `EXPO_PUBLIC_PANEL_URL` ni ninguna dirección del panel
- **Y** a una cuenta sin ningún rol de la app, `POST /api/sesion` le sigue respondiendo 403 sin token, con un mensaje
  que no menciona el panel

Cita: A → `NSV-2026-001-v8.pdf:3:Cláusula I` (el panel de administración, dentro de la "Aplicación única para iPhone y
Android"); C → C-247; D → ADR-0022, decisiones 1 y 19

#### Scenario: administracion-app-E10 — El panel de respaldo sigue igual

- **DADO** el panel web en `/admin`
- **CUANDO** intenta entrar una cuenta de administración con su contraseña, o una de otro rol
- **ENTONCES** el panel admite a la primera y rechaza a la segunda, como en autorizacion-E03
- **Y** las pruebas del panel de `administracion`, `indicadores`, `exportacion`, `alta-de-lavanderias`, `repartidor` y
  `landing` siguen en verde
- **Y** la portada no trae ningún enlace al panel

Criterio: SEG-3

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D, las funciones que el panel sigue teniendo); A →
`NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2` (Testimonios y Prensa / Reconocimientos, el contenido de la portada que
se edita en el panel); C → C-247 (que el panel quede como respaldo interno y nada lo muestre es decisión de Arthur, no
del contrato); D → ADR-0022, decisiones 18 a 21

### Requirement: Una sola fuente de cada regla

Cada ruta de `/api/administracion` DEBE (MUST) llamar a la misma clase de dominio que usa el panel web para la misma
consulta o acción, sin validar ni calcular por su cuenta. Las reglas que hoy viven dentro de clases de Filament (los
nombres operativos del estado, el historial de la ficha del usuario, la creación del aviso masivo y la creación de una
descarga) DEBEN (MUST) moverse a una clase de dominio, sin cambiar lo que hacen, antes de la primera ruta que las use. Un
registro que no existe responde 404, y una acción que otra sesión ya hizo responde 409 con el mensaje de la clase de
dominio.

Cita: A → `NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3` ("Panel de administración muestra datos reales de pedidos y
usuarios activos"); D → ADR-0022, decisión 9; D → ADR-0019, decisión 3

#### Scenario: administracion-app-E11 — La API y el panel dan lo mismo

- **DADO** los mismos datos sembrados
- **CUANDO** la API de la administración responde una consulta o hace una acción del Módulo D o del A.5
- **ENTONCES** su resultado es el que da, para esos datos, la clase de dominio que usa el panel
- **Y** después de mover a una clase de dominio una regla que vivía en Filament, las pruebas del panel siguen pasando
  sin cambios

Criterio: A43-7

Cita: A → `NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3`; D → ADR-0022, decisión 9

#### Scenario: administracion-app-E12 — Un registro que no existe y una acción que ya no se puede

- **DADO** un identificador que no es de ninguna lavandería, repartidor, usuario ni descarga, y una lavandería que otra
  sesión acaba de dar de baja
- **CUANDO** la administración pide el primero, o intenta suspender la segunda
- **ENTONCES** el servidor responde 404 al primero, sin datos
- **Y** 409 a la segunda, con el mensaje de la clase de dominio tal cual, sin cambiar nada: "Una lavandería en estado
  baja no se puede suspender.", con el estado como lo guarda la base
- **Y** la app muestra ese mensaje arriba de la ficha, sin reescribirlo, y vuelve a consultarla: ya no ofrece ninguna
  acción de estado

Cita: A → `NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3` ("Los cuatro módulos [...] accesibles y funcionales conforme
a A.3"); B → `servidor/app/Lavanderias/TransicionNoPermitida.php:16` (el mensaje); C → C-248 (imagen 58 de la
Propuesta 6); D → ADR-0019, decisión 3; D → ADR-0022, decisión 9

### Requirement: Gestión de lavanderías en la app

La administración DEBE (MUST) ver en la app todas las lavanderías, de todas las plazas, con su estado y su plaza, y
editarlas, suspenderlas, levantar la suspensión y darlas de baja, con las reglas del panel (alta-lavanderias-E13 a E15,
E17 y E31). La ficha DEBE (MUST) ofrecer solo las acciones que el estado de la lavandería permite, cada una con su
confirmación. El alta espera DD-053, como en el panel.

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #1, "Alta, baja, suspensión y edición de socios.");
D → ADR-0022, decisiones 9 y 11

#### Scenario: administracion-app-E13 — La lista y la ficha de las lavanderías

- **DADO** lavanderías activas, suspendidas y de baja en dos plazas
- **CUANDO** la administración abre Lavanderías en la app (`GET /api/administracion/lavanderias`)
- **ENTONCES** ve todas, cada una con su nombre, su estado y su plaza, y puede buscarlas por nombre
- **Y** al abrir una ve su ficha (`GET /api/administracion/lavanderias/{lavanderia}`), con sus datos editables, su
  estado, su plaza y solo los botones de las acciones que su estado permite

Criterio: A43-6

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #1); D → ADR-0022, decisión 9 (lo que ve sale de
`lavanderiasDelPanel()`, autorizacion-E13)

#### Scenario: administracion-app-E14 — Editar una lavandería

- **DADO** una lavandería en cualquier estado: pendiente, activa, suspendida o de baja, como en el panel, que ofrece
  "Editar" en toda lavandería
- **CUANDO** la administración toca "Editar" en su ficha, cambia su nombre, su descripción, su dirección con su
  municipio, sus fotos (la primera es la principal), sus servicios y precios o su horario, y toca "Guardar cambios"
  (`PATCH /api/administracion/lavanderias/{lavanderia}`, en multipart si lleva fotos)
- **ENTONCES** se guardan con la regla de alta-lavanderias-E13 y E31, y con las validaciones del registro
  (alta-lavanderias-E05 a E10, E25 y E26 a E30, con los mismos bloqueos que esos escenarios tengan)
- **Y** la edición sigue el orden del formulario del registro de la lavandería: los datos y la dirección, el municipio,
  las fotos, los servicios y precios, y las ventanas de disponibilidad
- **Y** las fotos dicen "La primera es la principal. Puede tener hasta N.", con la N del máximo que da el servidor (5
  por omisión); cada una se puede mover antes o quitar, y se agrega otra con la cámara o la galería
- **Y** cada foto se nombra por su lugar en la ficha con que se abrió la edición, y la app manda con la lista de fotos
  la marca de esa lectura que trae la ficha, `fotos_leidas`, tal cual: si las fotos de la lavandería cambiaron después,
  por ejemplo porque su socio las cambió desde su app, o si la marca no viene, el servidor responde 422 en `fotos` con
  el mensaje del panel, "Las fotos de la lavandería cambiaron desde que abriste la página. Vuelve a abrirla y haz el
  cambio otra vez.", la app lo muestra junto a las fotos y nada cambia, como en el panel
- **Y** las ventanas dicen "Marca, día por día, las horas en que la lavandería puede recolectar. Cada ventana dura una
  hora.", con el día en el selector y las horas de ese día para marcarlas
- **Y** el lavado en seco no se ofrece mientras DD-010 no fije su unidad (alta-lavanderias-E07): la pantalla lo dice
  en su lugar, sin interruptor
- **Y** un dato que no las cumple responde 422 con el error de cada campo, la app lo muestra junto a su campo y nada
  cambia
- **Y** la lavandería ve los datos nuevos en su perfil de la app

Criterio: A43-6

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #1, "edición"); B →
`servidor/app/Filament/Resources/Lavanderias/Tables/LavanderiasTable.php:44` ("Editar" en toda fila del panel, sin
condición de estado); B → `movil/src/alta-lavanderias/textos.ts:48` y `:75` (las instrucciones del perfil del socio,
que hablan de tú; la app de la administración las adapta para hablar de la lavandería), `servidor/config/fotos.php:44`
(5 fotos por omisión) y `movil/src/alta-lavanderias/formulario.ts:5-7` (el lavado en seco, DD-010); B →
`servidor/app/Filament/Resources/Lavanderias/Pages/EditLavanderia.php` (`$fotosAlAbrir`, las fotos que el panel leyó
al abrir) y `servidor/app/Lavanderias/FotosDeLaLavanderia.php` (`FOTOS_QUE_CAMBIARON`, el mensaje); C → C-248
(imágenes 11, 12, 13, 60 y 61 de la Propuesta 6); D → ADR-0022, decisión 9, y `design.md`, F-D01 (la marca de la
lectura)

#### Scenario: administracion-app-E15 — Suspender una lavandería y levantar la suspensión

- **DADO** una lavandería activa
- **CUANDO** la administración toca "Suspender" en su ficha y lo confirma
  (`POST /api/administracion/lavanderias/{lavanderia}/suspension`)
- **ENTONCES** queda suspendida, con la regla de alta-lavanderias-E14
- **Y** al tocar "Levantar la suspensión" y confirmarlo (`DELETE /api/administracion/lavanderias/{lavanderia}/suspension`)
  recupera su estado, con la regla de alta-lavanderias-E15
- **Y** después de cada acción la ficha muestra el estado nuevo y solo las acciones que ese estado permite

Criterio: A43-6

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #1, "suspensión"); D → ADR-0022, decisión 9

#### Scenario: administracion-app-E16 — Dar de baja una lavandería

- **DADO** una lavandería con pedidos entregados
- **CUANDO** la administración toca "Dar de baja" en su ficha y lo confirma
  (`POST /api/administracion/lavanderias/{lavanderia}/baja`)
- **ENTONCES** queda de baja con la regla de alta-lavanderias-E17, con sus datos y su historial guardados
- **Y** la confirmación dice "Dar de baja la lavandería" y "Deja de aparecer a los usuarios y de recibir pedidos. Sus
  datos y sus pedidos pasados se quedan en la base. La baja no se deshace desde la app.": el texto del panel, con "desde
  la app" donde el panel dice "desde el panel"
- **Y** su ficha ya no ofrece ninguna acción de estado, porque ninguna sale de la baja

Criterio: A43-6

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #1, "baja"); B →
`servidor/app/Filament/Resources/Lavanderias/Actions/AccionesDeEstado.php:32-33` (el texto del panel); C → C-248
(imagen 57 de la Propuesta 6); D → ADR-0022, decisión 9

#### Scenario: administracion-app-E17 — Dar de alta una lavandería

- **DADO** una lavandería que envió su registro completo desde la app
- **CUANDO** la administración le da de alta desde la app
- **ENTONCES** se aplica la regla de alta-lavanderias-E11 y E12
- **Y** mientras esa regla no exista, la API no tiene ruta de alta y la ficha no ofrece el botón, como el panel

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #1, "Alta")

SIN RESPUESTA — bloquea: administracion-app-E17 (DD-053; alta-lavanderias-E11 y E12)

#### Scenario: administracion-app-E18 — Pedidos en curso al suspender o dar de baja

- **DADO** una lavandería con pedidos aceptados que todavía no llegan a Entregado
- **CUANDO** la administración la suspende o le da de baja desde la app
- **ENTONCES** esos pedidos siguen la regla de alta-lavanderias-E16
- **Y** la confirmación de la app dice lo que esa regla fije

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #1)

SIN RESPUESTA — bloquea: administracion-app-E18 (DD-104; alta-lavanderias-E16)

### Requirement: Gestión de repartidores en la app

La administración DEBE (MUST) ver en la app a todos los socios repartidores, en cualquier estado, y darlos de alta en
una plaza, suspenderlos, levantar la suspensión, darlos de baja y editarlos, con las reglas del panel (repartidor-E05 a
E09 y E31). La ficha DEBE (MUST) ofrecer solo las acciones que el estado del repartidor permite, cada una con su
confirmación.

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #2, "Alta, baja, suspensión y edición de socios
repartidores."); A → `NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3` (Módulo C #1: "El alta, validación y autorización del
repartidor corresponden a EL CLIENTE"); D → ADR-0022, decisiones 9 y 11

#### Scenario: administracion-app-E19 — La lista y la ficha de los repartidores

- **DADO** repartidores pendientes sin plaza, activos, suspendidos y de baja, en dos plazas
- **CUANDO** la administración abre Repartidores en la app (`GET /api/administracion/repartidores`)
- **ENTONCES** ve a todos, cada uno con su nombre, su estado y su plaza, o sin plaza si no la tiene
- **Y** al abrir uno ve su ficha (`GET /api/administracion/repartidores/{repartidor}`), con su nombre, su teléfono, su
  correo, su foto y su tipo de vehículo, y solo los botones de las acciones que su estado permite

Criterio: A43-6

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #2); D → ADR-0022, decisión 9 (lo que ve sale de
`repartidoresDelPanel()`)

#### Scenario: administracion-app-E20 — Dar de alta a un repartidor en una plaza

- **DADO** un repartidor pendiente
- **CUANDO** la administración toca "Dar de alta", elige una plaza de la lista (`GET /api/administracion/plazas`) y lo
  confirma (`POST /api/administracion/repartidores/{repartidor}/alta`)
- **ENTONCES** queda activo en esa plaza, con la regla de repartidor-E05
- **Y** sin plaza elegida la app no lo manda, y el servidor, si le llega así, responde 422 sin cambiar nada

Criterio: A43-6

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #2, "Alta"); A → `NSV-2026-001-v8.pdf:18:ANEXO A,
Sección A.3` (Módulo C #1); D → ADR-0022, decisión 9

#### Scenario: administracion-app-E21 — Suspender a un repartidor y levantar la suspensión

- **DADO** un repartidor activo
- **CUANDO** la administración lo suspende desde su ficha y lo confirma
  (`POST /api/administracion/repartidores/{repartidor}/suspension`)
- **ENTONCES** queda suspendido, con la regla de repartidor-E06
- **Y** al levantar la suspensión y confirmarlo (`DELETE /api/administracion/repartidores/{repartidor}/suspension`)
  recupera su acceso, con la misma regla

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #2, "suspensión"); D → ADR-0022, decisión 9

#### Scenario: administracion-app-E22 — Dar de baja a un repartidor

- **DADO** un repartidor activo con entregas completadas y otro pendiente que nunca se dio de alta
- **CUANDO** la administración da de baja a cada uno desde su ficha y lo confirma
  (`POST /api/administracion/repartidores/{repartidor}/baja`)
- **ENTONCES** el primero queda de baja con la regla de repartidor-E07 y el segundo con la de repartidor-E31
- **Y** la confirmación dice "Dar de baja al repartidor" y "Ya no podrá iniciar sesión y se cierran sus sesiones
  abiertas. Sus entregas completadas se quedan en la base. La baja no se deshace desde la app.": el texto del panel, con
  "desde la app" donde el panel dice "desde el panel"
- **Y** sus fichas ya no ofrecen ninguna acción de estado

Criterio: A43-6

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #2, "baja"); B →
`servidor/app/Filament/Resources/Repartidores/Actions/AccionesDeEstado.php:102-103` (el texto del panel); C → C-248
(imagen 64 de la Propuesta 6); D → ADR-0022, decisión 9

#### Scenario: administracion-app-E23 — Editar a un repartidor

- **DADO** un repartidor registrado
- **CUANDO** la administración cambia en la app su nombre, su teléfono, su correo, su foto o su tipo de vehículo, y
  guarda (`PATCH /api/administracion/repartidores/{repartidor}`, en multipart si lleva foto)
- **ENTONCES** el cambio se guarda con la regla de repartidor-E08 y con las validaciones de su registro
- **Y** un dato que no las cumple, o un correo que ya es de otra cuenta, responde 422 con el error de cada campo, la
  app lo muestra junto a su campo y nada cambia

Criterio: A43-6

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #2, "edición"); D → ADR-0022, decisión 9

### Requirement: Gestión de usuarios en la app

La administración DEBE (MUST) consultar en la app a los usuarios, abrir el historial de pedidos de cada uno, bloquearlos
y levantar el bloqueo, con las reglas del panel (administracion-E01, E03 y E04). La app NO DEBE (MUST NOT) ofrecer dar
de alta, editar ni borrar usuarios: el Módulo D #3 dice consulta, historial y bloqueo.

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #3, "Consulta de usuarios activos, historial y
bloqueo."); D → ADR-0022, decisiones 9 y 11

#### Scenario: administracion-app-E24 — La lista y la ficha de un usuario con su historial

- **DADO** usuarios de dos plazas, uno con tres pedidos de fechas distintas y otro con un pedido
- **CUANDO** la administración abre Usuarios en la app (`GET /api/administracion/usuarios`) y después la ficha del
  primero (`GET /api/administracion/usuarios/{usuario}`)
- **ENTONCES** la lista trae las cuentas con el rol de usuario, cada una con su correo, su nombre y su estado, en
  páginas, con búsqueda por correo y por nombre
- **Y** la ficha trae su correo, su nombre, su teléfono, su estado y su historial con la regla de administracion-E01:
  sus tres pedidos, cada uno con folio, fecha, lavandería, estado y monto, del más reciente al más antiguo, y ninguno
  del otro usuario

Criterio: A43-6, A43-7

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #3, "historial"); D → ADR-0022, decisión 9

#### Scenario: administracion-app-E25 — Bloquear a un usuario y levantar el bloqueo

- **DADO** un usuario activo con la sesión abierta en la app
- **CUANDO** la administración toca "Bloquear" en su ficha y lo confirma
  (`POST /api/administracion/usuarios/{usuario}/bloqueo`)
- **ENTONCES** queda bloqueado con la regla de administracion-E03
- **Y** al tocar "Levantar el bloqueo" y confirmarlo (`DELETE /api/administracion/usuarios/{usuario}/bloqueo`) puede
  volver a entrar, con la regla de administracion-E04
- **Y** la ficha ofrece "Bloquear" o "Levantar el bloqueo" según su estado

Criterio: A43-6

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #3, "bloqueo"); D → ADR-0022, decisión 9

#### Scenario: administracion-app-E26 — Filtrar los usuarios activos de un periodo

- **DADO** un usuario que usó la aplicación ayer y otro que no la usa desde hace dos meses
- **CUANDO** la administración filtra en la app los usuarios activos del último mes
- **ENTONCES** la lista trae al primero y no al segundo, con la regla de administracion-E02

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #3, "Consulta de usuarios activos"); A →
`NSV-2026-001-v8.pdf:46:ANEXO D, Sección D.5`

SIN RESPUESTA — bloquea: administracion-app-E26 (BR-33; DD-111; administracion-E02)

#### Scenario: administracion-app-E27 — Bloquear a un usuario con un pedido activo

- **DADO** un usuario con un pedido en estado Recolectado
- **CUANDO** la administración lo bloquea desde la app
- **ENTONCES** el pedido y su cobro siguen la regla de administracion-E05
- **Y** la confirmación de la app dice lo que esa regla fije

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #3)

SIN RESPUESTA — bloquea: administracion-app-E27 (DD-016; administracion-E05)

### Requirement: Monitoreo de los pedidos activos en la app

La app DEBE (MUST) mostrar a la administración, en una sola pantalla, todos los pedidos activos de todas las plazas en
una lista y en un mapa, que salen de la misma respuesta del servidor, y ponerlos al día solos cada 30 segundos mientras
la pantalla está visible y la app al frente. El mapa DEBE (MUST) ser el de Apple en iPhone y el de Google en Android, y
NO DEBE (MUST NOT) mostrar la posición de ningún repartidor.

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #4, "Vista en tiempo real de todos los pedidos
activos con mapa."); A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Servicios externos: "Se utilizan los mapas de
Google en teléfonos Android y los mapas de Apple en iPhone"); D → ADR-0007; D → ADR-0009, decisión 1; D → ADR-0022,
decisiones 12 y 13

#### Scenario: administracion-app-E28 — Los pedidos activos en la lista y en el mapa

- **DADO** un pedido Confirmado en una plaza, uno En proceso en otra plaza con su entrega en otra dirección, y uno
  Entregado
- **CUANDO** la administración abre el monitoreo en la app (`GET /api/administracion/monitoreo`)
- **ENTONCES** la lista trae los dos primeros con su folio, su estado operativo y su plaza, y no el entregado, con la
  regla de administracion-E06
- **Y** el mapa trae sus marcadores con la regla de administracion-E09 y E10: uno con R en la recolección de cada pedido
  y otro con E en la entrega cuando es distinta
- **Y** al tocar un marcador dice el folio, el estado, si es la recolección o la entrega, y la plaza
- **Y** el mapa no muestra la posición de ningún repartidor

Criterio: A43-6, A43-7

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #4); D → ADR-0022, decisiones 9, 12 y 13

#### Scenario: administracion-app-E29 — El monitoreo se pone al día solo

- **DADO** el monitoreo abierto en la app, con un pedido Recolectado
- **CUANDO** la lavandería lo pasa a En proceso
- **ENTONCES** la lista y el marcador muestran En proceso en 30 segundos o menos, sin que la administración haga nada,
  con la regla de administracion-E07
- **Y** con la pantalla cerrada o la app al fondo deja de consultar, y al volver consulta de inmediato
- **Y** el mapa no se vuelve a crear en cada consulta: cambian sus marcadores, y se encuadra solo la primera vez que hay
  pedidos

Criterio: A43-6

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #4, "en tiempo real"); D → ADR-0007; D → ADR-0022,
decisión 12

#### Scenario: administracion-app-E30 — El mapa de cada teléfono

- **DADO** el monitoreo abierto con pedidos activos
- **CUANDO** se abre en iPhone y en Android
- **ENTONCES** en iPhone el mapa es el de Apple y en Android el de Google, sin elegir proveedor en el código
- **Y** en una compilación de Android sin la llave del mapa, el mapa no se monta, la pantalla dice que no está disponible
  y la lista de pedidos sigue

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Servicios externos, Mapas y ubicación); D → ADR-0009, decisión
1; D → ADR-0022, decisión 13; D → ADR-0012

#### Scenario: administracion-app-E31 — Un pedido cancelado sale del monitoreo

- **DADO** un pedido activo en el monitoreo de la app
- **CUANDO** el pedido se cancela
- **ENTONCES** deja de aparecer en la lista y en el mapa, con la regla de administracion-E08

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #4, "pedidos activos")

SIN RESPUESTA — bloquea: administracion-app-E31 (DD-016; BR-22; administracion-E08)

### Requirement: Comisiones en la app

La administración DEBE (MUST) ver y fijar en la app el porcentaje de comisión global y el propio de cada lavandería, y
quitar el propio, con las reglas del panel (administracion-E11 a E13). La app NO DEBE (MUST NOT) proponer ningún
porcentaje: los valores los captura EL CLIENTE.

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #5, "Porcentaje de comisión por socio o global.");
D → ADR-0022, decisiones 9 y 11

#### Scenario: administracion-app-E32 — El porcentaje global y el de cada lavandería

- **DADO** un porcentaje global guardado, una lavandería con porcentaje propio y otra sin él, en plazas distintas
- **CUANDO** la administración abre Comisiones en la app (`GET /api/administracion/comisiones`)
- **ENTONCES** ve el porcentaje global y desde cuándo rige
- **Y** cada lavandería, de todas las plazas, con su porcentaje propio si lo tiene y el que le aplica, con de dónde sale:
  propio o global
- **Y** sin porcentaje global guardado, la pantalla dice que no hay y no muestra ningún número

Criterio: A43-6

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #5); D → ADR-0022, decisión 9

#### Scenario: administracion-app-E33 — Fijar el porcentaje global y el propio

- **DADO** la pantalla de comisiones de la app
- **CUANDO** la administración fija el porcentaje global (`PUT /api/administracion/comisiones/global`), fija el propio
  de una lavandería (`PUT /api/administracion/comisiones/lavanderias/{lavanderia}`) o lo quita
  (`DELETE /api/administracion/comisiones/lavanderias/{lavanderia}`)
- **ENTONCES** se guardan con la regla de administracion-E11 y E12
- **Y** la pantalla muestra los porcentajes nuevos y el que aplica a cada lavandería

Criterio: A43-6

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #5); D → ADR-0022, decisión 9

#### Scenario: administracion-app-E34 — Un porcentaje fuera de rango

- **DADO** un porcentaje vigente
- **CUANDO** la administración intenta guardar en la app uno menor que 0 o mayor que 100
- **ENTONCES** el servidor responde 422 con el mensaje de la regla de administracion-E13
- **Y** la app lo muestra junto al campo y el porcentaje vigente no cambia

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #5); D → ADR-0022, decisión 9

#### Scenario: administracion-app-E35 — La comisión de un socio repartidor

- **DADO** un socio repartidor
- **CUANDO** la administración quiere fijarle un porcentaje de comisión en la app
- **ENTONCES** aplica la regla de administracion-E14

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #5, "por socio")

SIN RESPUESTA — bloquea: administracion-app-E35 (DD-019; administracion-E14)

#### Scenario: administracion-app-E36 — La comisión de cada pedido

- **DADO** pedidos con pago confirmado de una lavandería con porcentaje vigente
- **CUANDO** la administración consulta en la app su comisión, o cambia el porcentaje
- **ENTONCES** cada comisión sale con la regla de administracion-E15, y la de los pedidos anteriores con la de
  administracion-E16

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #5 y #6)

SIN RESPUESTA — bloquea: administracion-app-E36 (DD-020; DD-013; administracion-E15 y E16)

### Requirement: Reportes financieros en la app

La administración DEBE (MUST) ver en la app los ingresos del periodo que elija y conciliar ese periodo con la pasarela,
con las reglas del panel (administracion-E17, E20 y E21). Las dos pantallas NO DEBEN (MUST NOT) ponerse al día solas,
como en el panel. La pasarela es Conekta mientras EL CLIENTE no firme el cambio (DD-079); si lo firma, se concilia con
la elegida.

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #6, "Ingresos por periodo, comisiones y
conciliación con Conekta."); D → ADR-0010, segunda adenda del 2026-11-22; D → ADR-0022, decisiones 9 y 11

#### Scenario: administracion-app-E37 — Los ingresos de un periodo

- **DADO** pagos confirmados por la pasarela en marzo y en abril, y una orden de marzo sin confirmar
- **CUANDO** la administración pide en la app los ingresos de marzo, por semana
  (`GET /api/administracion/ingresos` con `desde`, `hasta` y `granularidad`)
- **ENTONCES** ve el total de los pagos confirmados en marzo, cuántos son y el ingreso de cada semana, con la regla de
  administracion-E17
- **Y** sin rango elegido ve el mes en curso hasta hoy, por día, como el panel
- **Y** cada cifra es la misma que da el panel para ese rango
- **Y** bajo los pagos confirmados, la pantalla deja el lugar de "Reembolsos y ajustes" (administracion-app-E39) y el
  de "Comisiones del periodo" (administracion-app-E40), cada uno con "Por confirmar" y su DD (DD-012 y DD-016; DD-020),
  sin ninguna cifra ni descarga mientras su regla no exista

Criterio: A43-6, A43-7

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #6, "Ingresos por periodo, comisiones y
conciliación"); C → C-248 (imagen 31 de la Propuesta 6: los dos lugares en espera); D → ADR-0022, decisión 9

#### Scenario: administracion-app-E38 — Conciliar un periodo

- **DADO** pedidos con pago confirmado en el periodo y los movimientos de la pasarela (simulada): unos que cuadran, uno
  sin pedido, un pedido pagado sin movimiento y un monto que no coincide
- **CUANDO** la administración toca "Conciliar un periodo" en la app, elige el rango y lo confirma
  (`POST /api/administracion/conciliaciones` con `desde` y `hasta`)
- **ENTONCES** el servidor lee la pasarela, guarda sus movimientos y responde el reporte fila por fila, con cuántos hay
  de cada resultado, con la regla de administracion-E20 y E21
- **Y** la app muestra la tabla con cada diferencia y su tipo
- **Y** con la fecha de fin anterior a la de inicio responde 422 con "La fecha de fin no puede ser anterior a la de
  inicio.", y la app lo muestra junto al campo
- **Y** si la pasarela no responde, dice "No se pudo leer la pasarela" y no guarda nada

Criterio: A43-6

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #6, "conciliación"); D → ADR-0010, segunda adenda
del 2026-11-22; D → ADR-0022, decisión 9

#### Scenario: administracion-app-E39 — Reembolsos y ajustes en los ingresos

- **DADO** un pago confirmado que después tuvo un reembolso o un ajuste al pesar
- **CUANDO** la administración pide en la app los ingresos del periodo
- **ENTONCES** el reembolso o el ajuste se refleja con la regla de administracion-E18

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #6)

SIN RESPUESTA — bloquea: administracion-app-E39 (DD-012; DD-016; administracion-E18)

#### Scenario: administracion-app-E40 — Las comisiones del periodo, por socio y en total

- **DADO** pedidos con pago confirmado de dos lavanderías en el periodo
- **CUANDO** la administración pide en la app las comisiones del periodo
- **ENTONCES** ve la comisión de cada lavandería y la suma, con la regla de administracion-E19

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #6, "comisiones")

SIN RESPUESTA — bloquea: administracion-app-E40 (DD-020; administracion-E19)

#### Scenario: administracion-app-E41 — La comisión que retiene la pasarela

- **DADO** movimientos de la pasarela con su comisión por transacción
- **CUANDO** la administración concilia el periodo en la app
- **ENTONCES** la comisión de la pasarela se muestra y se descuenta con la regla de administracion-E22

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #6)

SIN RESPUESTA — bloquea: administracion-app-E41 (DD-013; administracion-E22)

### Requirement: Avisos masivos en la app

La administración DEBE (MUST) escribir en la app un aviso con título y texto, mandarlo a todos los teléfonos
registrados y ver los avisos enviados, con la regla del panel (administracion-E23). Mientras DD-113 no defina los
segmentos, el aviso es global y la app NO DEBE (MUST NOT) ofrecer segmentos.

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #7, "Envío segmentado o global para promociones o
mantenimiento."; Servicios externos, "Avisos al celular": "Se utiliza el servicio gratuito de Google"); D → ADR-0008;
D → ADR-0022, decisiones 9 y 16

#### Scenario: administracion-app-E42 — Mandar un aviso global

- **DADO** tres cuentas con un teléfono registrado para avisos y una sin teléfono
- **CUANDO** la administración escribe en la app un título y un texto, toca "Mandar" y confirma que sale a todos los
  teléfonos registrados (`POST /api/administracion/avisos-masivos`)
- **ENTONCES** el aviso se guarda y sale en cola con la regla de administracion-E23: a los tres teléfonos y a ninguno
  más (FCM simulado)
- **Y** sin título o sin texto, o con un título de más de 255 caracteres o un texto de más de 1000, el servidor responde
  422 con el error de cada campo, la app lo muestra junto a su campo y no sale nada
- **Y** sin título, el error de `titulo` dice "Escribe el título."
- **Y** bajo el título y bajo el texto, la app dice cuántos caracteres lleva de su tope: "30 de 255 caracteres", "43 de
  1000 caracteres"
- **Y** si no confirma, no sale nada

Criterio: A43-6

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #7, "global"); C → C-248 (imágenes 37 y 38 de la
Propuesta 6: el mensaje, con el estilo de `servidor/app/Http/Requests/CambiarPerfilRequest.php:40`, y los
contadores); D → ADR-0008; D → ADR-0022, decisiones 9 y 16

#### Scenario: administracion-app-E43 — Los avisos enviados

- **DADO** dos avisos masivos enviados, uno ya contado y otro todavía en la cola
- **CUANDO** la administración abre Avisos masivos en la app (`GET /api/administracion/avisos-masivos`)
- **ENTONCES** los ve del más reciente al más antiguo, cada uno con su título, su texto, cuándo y quién lo mandó, y a
  cuántos teléfonos salió
- **Y** el que sigue en la cola dice "En cola" en lugar del número

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #7); D → ADR-0022, decisión 9

#### Scenario: administracion-app-E44 — Un aviso a un segmento

- **DADO** cuentas de distintos segmentos con teléfono registrado
- **CUANDO** la administración manda desde la app un aviso a un segmento
- **ENTONCES** solo lo reciben las cuentas de ese segmento, con la regla de administracion-E24

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #7, "segmentado")

SIN RESPUESTA — bloquea: administracion-app-E44 (BR-32; DD-113; administracion-E24)

### Requirement: Indicadores del A.5 en la app

La app DEBE (MUST) mostrar a la administración los once indicadores del A.5, con la visualización que el A.5 nombra
para cada uno y sus cifras también en texto, en una pantalla con una pestaña por categoría y un rango y una granularidad
que valen para todas. Las cifras DEBEN (MUST) ser las que calcula el servidor con la base, las mismas del panel
(indicadores-E01 a E24), y ponerse al día solas, con el intervalo de la configuración, mientras la pantalla está
visible y la app al frente. Un indicador cuya cifra espera una respuesta NO DEBE (MUST NOT) mostrar una cifra inventada.

Cita: A → `NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5` ("Panel de indicadores integrado en la sección de
administración, que muestra en pantalla el estado del negocio sin necesidad de exportar ni procesar información
manualmente."); A → `NSV-2026-001-v8.pdf:3:Cláusula I` (entregable 4, Panel de indicadores del negocio: "Incluido en
app"); A → `NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.4` ("El panel muestra en tiempo real los indicadores definidos
en A.5."); D → ADR-0007, adenda del 2026-11-22; D → ADR-0022, decisiones 9, 13 y 14 y alternativa 5

#### Scenario: administracion-app-E45 — Una pestaña por categoría, con su rango

- **DADO** la administración con la sesión iniciada en la app
- **CUANDO** abre Indicadores sin elegir rango
- **ENTONCES** ve una pestaña por categoría del A.5 (Operaciones, Usuarios, Socios, Financiero y Geográfico) y, arriba,
  el rango y la granularidad, que valen para todas
- **Y** el rango es el mes en curso hasta hoy, por día, con el hoy de la zona horaria de referencia (indicadores-E24)
- **Y** puede escribir las dos fechas o tocar "Este mes" o "Mes anterior", y cambiar la granularidad a semana o a mes
- **Y** la pantalla muestra el rango ajustado a periodos completos que trae la respuesta
  (`GET /api/administracion/indicadores/{categoria}`), como el panel

Criterio: A44-1

Cita: A → `NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5` (categorías; "por día, por semana y por mes"); D → ADR-0022,
decisión 14

#### Scenario: administracion-app-E46 — Los indicadores de operaciones

- **DADO** pedidos confirmados en varios días del rango, unos activos y otros entregados
- **CUANDO** la administración abre la pestaña Operaciones
- **ENTONCES** ve los pedidos totales (F-I01) en barras y en una tabla que pone cada periodo junto al anterior, con la
  regla de indicadores-E01 a E03
- **Y** los pedidos por estado (F-I02) en una dona, con la regla de indicadores-E04
- **Y** cada gráfica trae sus cifras en texto, que se leen sin el dibujo y sin depender del color

Criterio: A44-1

Cita: A → `NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5` (Operaciones: "Gráfica de barras y tabla comparativa";
"Gráfica de dona"); D → ADR-0022, alternativa 5

#### Scenario: administracion-app-E47 — Los indicadores de usuarios

- **DADO** usuarios con actividad, registros de varios meses con sus pedidos, y pedidos del periodo y del anterior
- **CUANDO** la administración abre la pestaña Usuarios
- **ENTONCES** ve los usuarios activos (F-I03) en una línea de tendencia, con la regla de indicadores-E06
- **Y** los que vuelven a pedir por mes de registro (F-I04) en una tabla, con la regla de indicadores-E08
- **Y** el monto promedio por pedido (F-I05) como indicador destacado con su tendencia, con la regla de indicadores-E09
- **Y** cada una trae sus cifras en texto

Criterio: A44-1, A43-7

Cita: A → `NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5` (Usuarios: "Línea de tendencia"; "Tabla comparativa";
"Indicador destacado con tendencia"); D → ADR-0022, alternativa 5

#### Scenario: administracion-app-E48 — Los indicadores de socios

- **DADO** lavanderías con calificaciones y una sin ellas, dos cortes del semáforo en la configuración, y repartidores
  con entregas en el rango
- **CUANDO** la administración abre la pestaña Socios
- **ENTONCES** ve la calificación promedio por lavandería (F-I07) en un ranking con semáforo, con la regla de
  indicadores-E12 y E13: cada color con su texto
- **Y** las entregas completadas por repartidor (F-I08) en una tabla, con la regla de indicadores-E15

Criterio: A44-1

Cita: A → `NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5` (Socios: "Ranking con semáforo"; "Tabla comparativa"); D →
ADR-0022, alternativa 5

#### Scenario: administracion-app-E49 — Los ingresos brutos

- **DADO** pagos confirmados en enero y en febrero
- **CUANDO** la administración abre la pestaña Financiero, por mes
- **ENTONCES** ve los ingresos brutos por periodo (F-I09) en barras, con la regla de indicadores-E16
- **Y** cada cifra es la misma de la pantalla de ingresos de la app para ese mes (administracion-app-E37)

Criterio: A44-1

Cita: A → `NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5` (Financiero: "Gráfica de barras agrupadas"); D → ADR-0022,
alternativa 5

#### Scenario: administracion-app-E50 — El mapa de calor por colonia o por zona

- **DADO** cinco pedidos de la colonia A, dos de la colonia B, y pedidos dentro de dos zonas de servicio, en el rango
- **CUANDO** la administración abre la pestaña Geográfico y elige ver por colonia, y después por zona
- **ENTONCES** por colonia ve un punto por colonia, más grande y más intenso el de más pedidos, con la regla de
  indicadores-E19
- **Y** por zona ve cada zona pintada con su número de pedidos, con la regla de indicadores-E20, con la geometría y el
  color que da el servidor
- **Y** debajo del mapa, una tabla con las mismas cifras
- **Y** en iPhone el mapa es el de Apple y en Android el de Google; sin la llave de Android, el mapa no se monta, la
  pantalla lo dice y la tabla sigue

Criterio: A44-1

Cita: A → `NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5` (Geográfico: "Mapa interactivo"); A →
`NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Servicios externos, Mapas y ubicación); D → ADR-0009, decisiones 1 y 3;
D → ADR-0022, decisión 13

#### Scenario: administracion-app-E51 — Los indicadores se ponen al día solos

- **DADO** la pantalla de indicadores abierta en la pestaña Operaciones
- **CUANDO** se confirma un pedido nuevo
- **ENTONCES** los pedidos totales y los pedidos por estado lo cuentan en la siguiente consulta, sin que la
  administración haga nada, con la regla de indicadores-E21
- **Y** se consulta la pestaña visible, con el intervalo que trae la respuesta, el de `indicadores.refresco` (30
  segundos por omisión; indicadores-E22)
- **Y** con la pantalla cerrada o la app al fondo deja de consultar

Criterio: A44-1

Cita: A → `NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.4` ("en tiempo real"); D → ADR-0007, adenda del 2026-11-22; D →
ADR-0022, decisión 14

#### Scenario: administracion-app-E52 — Cifras de la base, las mismas del panel

- **DADO** una base con pedidos y usuarios sembrados por el test
- **CUANDO** la administración abre cada pestaña de indicadores en la app con un rango
- **ENTONCES** cada indicador da las cifras que salen de esa base, con la regla de indicadores-E23
- **Y** son las mismas que da el panel para ese rango
- **Y** el código de la app no trae cifras de ejemplo

Criterio: A43-7, A44-1

Cita: A → `NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5` ("sin necesidad de exportar ni procesar información
manualmente"); A → `NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3`; D → ADR-0022, decisión 9

#### Scenario: administracion-app-E53 — Un indicador que espera una respuesta

- **DADO** las lavanderías activas frente a registradas (F-I06) y las comisiones por socio y en total (F-I10), que
  todavía no tienen regla
- **CUANDO** la administración abre su pestaña
- **ENTONCES** cada uno aparece en su lugar, con su nombre, y dice que espera una respuesta
- **Y** no muestra ninguna cifra ni ofrece su descarga

Cita: A → `NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5` (Socios y Financiero); D → ADR-0022, decisión 14 (su
aplicación, en `design.md`, sección 4: "Un indicador bloqueado se muestra con su lugar y el texto de que espera una
respuesta, sin cifra inventada")

#### Scenario: administracion-app-E54 — Las cifras de las lavanderías activas y de las comisiones

- **DADO** lavanderías registradas y activas, y pedidos con pago confirmado de dos lavanderías
- **CUANDO** la administración abre las pestañas Socios y Financiero
- **ENTONCES** ve las lavanderías activas frente a registradas con la regla de indicadores-E11, como indicador destacado
- **Y** las comisiones por socio y en total con la regla de indicadores-E18, en una tabla detallada con su total

Cita: A → `NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5` (Socios: "Indicador destacado"; Financiero: "Tabla detallada
con total")

SIN RESPUESTA — bloquea: administracion-app-E54 (DD-083; DD-020; DD-013; indicadores-E11 y E18)

#### Scenario: administracion-app-E55 — Lo que espera respuesta en los indicadores que ya tienen cifra

- **DADO** pedidos cancelados o rechazados, actividad que no es un pedido, un pedido con monto ajustado, los cortes del
  semáforo de EL CLIENTE y el criterio de las barras de ingresos
- **CUANDO** la administración abre los indicadores en la app
- **ENTONCES** la dona cuenta los cancelados con la regla de indicadores-E05, los usuarios activos siguen la de E07, el
  monto promedio la de E10, el semáforo la de E14 y las barras se agrupan con la de E17

Cita: A → `NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5`

SIN RESPUESTA — bloquea: administracion-app-E55 (DD-016 y BR-22; DD-111; DD-012; DD-114; DD-115; indicadores-E05, E07, E10, E14 y E17)

### Requirement: Descarga de los reportes en CSV y en Excel desde la app

La administración DEBE (MUST) pedir desde la app la descarga de cada reporte que tiene cifras, para el rango que elija,
y guardar en el teléfono su archivo CSV y su archivo Excel (XLSX), sin abrir ninguna página web. El archivo DEBE (MUST)
ser el mismo que da el panel, con las reglas de `exportacion` (exportacion-E01 a E09, E11 y E12). Solo la cuenta que la
pidió DEBE (MUST) poder bajarlo.

Cita: A → `NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5` ("Descarga de reportes en formato CSV y Excel para cualquier
rango de fechas."); A → `NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.4` ("Reportes exportables generados correctamente
en formato CSV o Excel."); A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #6); D → ADR-0022, decisión 15 y
alternativa 6

#### Scenario: administracion-app-E56 — Pedir la descarga de un reporte

- **DADO** la pantalla de indicadores, la de ingresos o la de conciliación después de conciliar, con un rango elegido
- **CUANDO** la administración toca "Descargar" en un indicador con cifra o en el reporte, y confirma el rango, que es el
  de la pantalla si no lo cambia (`POST /api/administracion/descargas` con `reporte`, `desde`, `hasta` y
  `granularidad`)
- **ENTONCES** el servidor crea la descarga de su cuenta, pone en cola su archivo y responde 202 con la descarga
- **Y** el archivo sale con la regla de exportacion-E01 a E03: un CSV y un XLSX con las mismas filas y las cifras del
  reporte para ese rango
- **Y** la descarga de la conciliación usa los movimientos ya guardados, sin volver a leer la pasarela
- **Y** un `reporte` que no es uno de los exportadores responde 422, sin crear nada

Criterio: A44-2

Cita: A → `NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5`; A → `NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.4`; D →
ADR-0022, decisión 15

#### Scenario: administracion-app-E57 — Mientras se genera y cuando está lista

- **DADO** una descarga recién pedida desde la app
- **CUANDO** la pantalla espera su archivo
- **ENTONCES** dice que el archivo se está generando y consulta su estado cada 5 segundos
  (`GET /api/administracion/descargas/{descarga}`), solo mientras está visible y la app al frente
- **Y** la descarga está lista cuando la cola la dio por terminada y sus dos archivos CSV, el encabezado y el de las
  filas, están en su disco; entonces ofrece "Guardar .csv" y "Guardar .xlsx", porque el Excel se arma de esos CSV
  (ADR-0022, decisión 15)
- **Y** si la cola la dio por terminada sin alguno de esos archivos, porque el trabajo que los escribe falló, el estado
  dice que no se pudo generar, la pantalla lo dice y ofrece pedirla otra vez
- **Y** en la app esto toma el lugar del aviso del panel de exportacion-E10

Criterio: A44-2

Cita: A → `NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.4` ("generados correctamente"); D → ADR-0022, decisión 15; D →
ADR-0010, adenda del 2026-11-22 (la consulta cada 5 segundos de "Procesando pago")

#### Scenario: administracion-app-E58 — Guardar el archivo en el teléfono

- **DADO** una descarga lista, en iPhone y en Android
- **CUANDO** la administración toca "Guardar .csv" o "Guardar .xlsx" y elige una carpeta en el selector del sistema
- **ENTONCES** la app baja el archivo con su token (`GET /api/administracion/descargas/{descarga}/{formato}`, con
  `csv` o `xlsx`), lo crea en esa carpeta con su nombre y su tipo, y dice dónde quedó
- **Y** el archivo guardado es el mismo que entregó el servidor
- **Y** si la persona cierra el selector sin elegir carpeta, no se guarda nada, no sale ningún error y la descarga sigue
  en la lista
- **Y** ningún paso abre una página web

Criterio: A44-2

Cita: A → `NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5` ("Descarga de reportes en formato CSV y Excel"); C → C-247; D →
ADR-0022, decisión 15 y alternativa 6

#### Scenario: administracion-app-E59 — Las descargas de la cuenta

- **DADO** tres descargas de la cuenta, una de ellas más vieja que los días de vida de la configuración y ya borrada,
  y una de otra cuenta de administración
- **CUANDO** la administración abre Descargas en la app (`GET /api/administracion/descargas`)
- **ENTONCES** ve sus dos descargas vivas, de la más nueva a la más vieja, cada una con su reporte, cuándo se pidió, su
  estado y, si está lista, sus botones para guardar
- **Y** no ve la borrada, con la regla de exportacion-E12, ni la de la otra cuenta

Cita: A → `NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5`; D → ADR-0022, decisión 15

#### Scenario: administracion-app-E60 — El rango de la descarga

- **DADO** la descarga de un reporte desde la app
- **CUANDO** la administración pide el rango del 10 al 20 de marzo, un rango de dos años, o uno con la fecha de inicio
  posterior a la de fin
- **ENTONCES** el archivo trae los días del 10 al 20, incluidos los dos (exportacion-E04), y el de dos años se acepta
  (exportacion-E05)
- **Y** el rango al revés responde 422 con el mensaje de exportacion-E06, la app lo muestra junto al campo y no se crea
  ninguna descarga

Criterio: A44-2

Cita: A → `NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5` ("para cualquier rango de fechas"); D → ADR-0022, decisión 15

#### Scenario: administracion-app-E61 — El archivo es el del panel

- **DADO** un reporte con una colonia con acento y eñe, otra capturada como "=1+1" y una cifra negativa
- **CUANDO** se pide su descarga desde la app y desde el panel, para el mismo rango
- **ENTONCES** los dos CSV y los dos XLSX traen las mismas filas y cifras
- **Y** cumplen exportacion-E07, E08 y E09: los acentos y las eñes se leen en Excel, el texto que empieza con signo
  igual queda como texto y los números siguen siendo números

Criterio: A44-2

Cita: A → `NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.4`; D → ADR-0003; D → ADR-0022, decisión 15

#### Scenario: administracion-app-E62 — Solo la cuenta que la pidió baja el archivo

- **DADO** una descarga lista de una cuenta de administración
- **CUANDO** otra cuenta de administración pide su estado o su archivo, o lo pide una cuenta de otro rol, o una petición
  sin sesión
- **ENTONCES** a la otra cuenta de administración el servidor le responde 404, con la regla de exportacion-E11
- **Y** a la de otro rol, 403; y sin sesión, 401; ninguno recibe el archivo
- **Y** la ruta nombra la descarga por su identificador público (un ULID), nunca por el número de su fila, así que nadie
  llega a la de otro cambiando un número; uno que no es de ninguna descarga responde 404

Criterio: SEG-3, A44-2

Cita: A → `NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3` (Separación por tipo de usuario); D → ADR-0019, decisión 3; D →
ADR-0016, regla 5; D → ADR-0022, decisión 15

#### Scenario: administracion-app-E63 — La descarga de los reportes que esperan respuesta

- **DADO** las lavanderías activas frente a registradas (F-I06), las comisiones por socio y en total (F-I10) y las
  comisiones del periodo (administracion-E19)
- **CUANDO** la administración pide su descarga desde la app
- **ENTONCES** cada archivo sale con las cifras de su regla, como los demás reportes
- **Y** mientras su regla no exista, la app no ofrece "Descargar" en ellos, como el panel

Cita: A → `NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5`; A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D
#6)

SIN RESPUESTA — bloquea: administracion-app-E63 (DD-083; DD-020; DD-013; indicadores-E11 y E18; administracion-E19)
