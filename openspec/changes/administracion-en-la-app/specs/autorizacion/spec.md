## MODIFIED Requirements

### Requirement: Cada rol entra solo a su módulo

El sistema DEBE (MUST) dejar que cada cuenta use solo las funciones del módulo de su rol: usuario (Módulo A), socio
lavandería (Módulo B), socio repartidor (Módulo C) o administración (Módulo D, en la app, con las rutas de
`/api/administracion`, y en el panel web, que queda como respaldo interno). Las rutas de un módulo rechazan a los demás
roles sin devolver datos. La consulta de la propia cuenta, el cierre de la sesión y la lista de municipios del catálogo
del territorio son comunes a los cuatro roles: la administración elige de esa lista el municipio de una lavandería al
editarla (administracion-app-E14). Las colonias por código postal, solo a los tres que las usan (usuario, socio
lavandería y socio repartidor).

Cita: A → `NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3`, `NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3` (los cuatro
módulos), `NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3` (Módulo C #1), `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3`
(Módulo D #1 y #2: "Alta") y `NSV-2026-001-v8.pdf:44:ANEXO D, Sección D.1` ("Pantalla de control reservada al dueño del
negocio") · A → `NSV-2026-001-v8.pdf:3:Cláusula I` ("Aplicación única para iPhone y Android, con módulo de usuario,
módulo de socio lavandería, módulo de socio repartidor y panel de administración") · D → ADR-0005, ADR-0003 y ADR-0022
(decisiones 6, 7, 8 y 18, y la adenda del 2026-11-26 de la decisión 6) · C → C-247 y decisión 69 de
`acta-2026-11-20-modelo-y-pasarela.md`, que reemplazan la vista reducida de C-140 y la decisión 56; C → C-245 (la
adenda, por delegación de Arthur). Que el panel web quede como respaldo viene de C-247, no del contrato

#### Scenario: autorizacion-E02 — Ningún rol entra al módulo de otro

- **DADO** una cuenta de cada rol, con sesión válida: la de administración, con su token de administración
  (administracion-app-E01)
- **CUANDO** cada una llama a las rutas de los módulos que no son el suyo
- **ENTONCES** todas esas llamadas se rechazan sin devolver datos
- **Y** cada cuenta sí llega a las rutas de su propio módulo: la administración, a las de `/api/administracion`
- **Y** la consulta de la cuenta, el cierre de la sesión y la lista de municipios admiten a los cuatro roles, y las
  colonias por código postal solo al usuario, a la lavandería y al repartidor
- **Y** cada llamada de la prueba lleva un token real de su cuenta, creado por la misma pieza que crea los tokens de la
  app (ADR-0022, decisión 3), no uno simulado

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

### Requirement: Prueba contra fugas en cada cambio

`servidor/` DEBE (MUST) tener una prueba automática que, con la sesión de cada rol, intente leer por cada ruta datos de
otro socio, de otro usuario y de otra plaza, y que falle si alguna respuesta los trae. Corre en la integración
continua, en cada cambio. Cada rol entra con un token real de su cuenta: la administración, con su token de
administración. Para la administración, que ve todas las plazas en su módulo (autorizacion-E13), los datos de los
socios, de los usuarios y de las plazas que le da `/api/administracion` no son ajenos; sí lo es lo de otra cuenta de
administración: sus descargas (exportacion-E11) y su correo. Las rutas de los Módulos A, B y C le responden 403 sin
ningún dato.

Cita: A → `NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3` y `NSV-2026-001-v8.pdf:10:Cláusula X` ("medidas de seguridad
técnicas razonables") · A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #1 a #4: "todos los pedidos
activos") · D → ADR-0022 (decisiones 8 y 15) · C → C-247

#### Scenario: autorizacion-E14 — El barrido contra fugas

- **DADO** datos sembrados en dos plazas, con dos lavanderías, dos repartidores y dos usuarios en cada una, y dos
  cuentas de administración
- **CUANDO** la prueba recorre todas las rutas de la API con la sesión de cada rol, pidiendo datos que no le
  corresponden
- **ENTONCES** ninguna respuesta trae datos de otro socio, de otro usuario ni de otra plaza
- **Y** si alguna los trae, la prueba falla y nombra la ruta, el rol y el dato
- **Y** la sesión de una cuenta de administración, con su token de administración, recibe 403 sin datos en las rutas de
  los Módulos A, B y C
- **Y** en las rutas de `/api/administracion` ve lo de todas las plazas sin que cuente como fuga, y la descarga de la
  otra cuenta de administración le responde 404 sin datos: ninguna respuesta trae esa descarga ni el correo de esa otra
  cuenta

Criterio: SEG-3
