## ADDED Requirements

### Requirement: Servidor de la API en marcha

El servidor DEBE (MUST) vivir en `servidor/` como una aplicación Laravel que recibe y responde las consultas de la app
en el entorno local.

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Servidor de la aplicación); D → ADR-0003

#### Scenario: base-E01 — La API responde en local

- **DADO** el servidor levantado en la máquina de Arthur con su configuración local
- **CUANDO** se consulta la ruta de salud de la API
- **ENTONCES** responde con éxito (HTTP 200)

### Requirement: Base de datos PostgreSQL con PostGIS desde la primera migración

La base de datos DEBE (MUST) ser PostgreSQL, y la primera migración DEBE (MUST) habilitar PostGIS, de modo que todas
las migraciones corran desde cero sobre una base vacía sin pasos a mano.

Cita: A → `NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3` (Base de datos); D → ADR-0004

#### Scenario: base-E02 — Las migraciones corren desde cero y dejan PostGIS listo

- **DADO** una base PostgreSQL vacía
- **CUANDO** corren todas las migraciones desde cero
- **ENTONCES** terminan sin error
- **Y** al consultar la versión de PostGIS por la conexión de la aplicación, la base responde con ella

### Requirement: Panel de administración cerrado

El servidor DEBE (MUST) traer el panel web de administración hecho con Filament, y el panel DEBE (MUST) pedir iniciar
sesión y negar la entrada a toda cuenta sin el rol de administración. En este cambio ninguna cuenta tiene ese rol.

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D); D → ADR-0003, ADR-0005

#### Scenario: base-E03 — Sin sesión, el panel pide iniciar sesión

- **DADO** el servidor en marcha
- **CUANDO** alguien sin sesión abre la dirección del panel
- **ENTONCES** se le lleva a la pantalla de inicio de sesión del panel
- **Y** no ve ningún dato del panel

### Requirement: Una sola app para iPhone y Android

La app DEBE (MUST) ser un solo proyecto en `movil/`, hecho con React Native y Expo SDK 58 en TypeScript, que se compila
para iPhone y para Android desde la misma base de código, con development builds y no con Expo Go.

Cita: A → `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3`; D → ADR-0002

#### Scenario: base-E04 — La app arranca

- **DADO** el proyecto `movil/` con sus dependencias instaladas
- **CUANDO** la prueba monta el componente raíz de la app
- **ENTONCES** se muestra su primera pantalla sin errores

#### Scenario: base-E05 — Una sola configuración para las dos plataformas

- **DADO** la configuración de Expo de la app
- **CUANDO** la prueba la lee
- **ENTONCES** declara iPhone y Android desde el mismo proyecto
- **Y** el identificador de la app en las dos plataformas sale de la configuración del entorno, no de un texto
  escrito a mano

### Requirement: Versiones mínimas iOS 18 y Android 11

La app DEBE (MUST) declarar como versiones mínimas iOS 18 y Android 11 (API 30). Si Expo SDK 58 exige una versión
mayor, DEBE (MUST) declarar la mayor.

Cita: A → `NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3` ("compatible con la gran mayoría de los teléfonos
actualmente en uso"); D → ADR-0009

#### Scenario: base-E06 — La configuración pide iOS 18 y Android 11

- **DADO** la configuración de compilación de la app
- **CUANDO** la prueba la lee
- **ENTONCES** la versión mínima para iPhone es iOS 18.0
- **Y** la versión mínima para Android es la API 30 (Android 11)

Criterio: A43-1 (en parte: los mínimos. La compilación y el envío a las tiendas van en `publicacion`)

### Requirement: Configuración por entorno, sin secretos en el repositorio

La dirección de la API, las llaves y las credenciales DEBEN (MUST) salir de la configuración de cada entorno. El
repositorio DEBE (MUST) versionar solo `.env.example`, sin valores secretos. Mientras DD-070 siga abierta, el único
entorno es el local.

Cita: D → ADR-0012

#### Scenario: base-E07 — La app toma la dirección de la API de su entorno

- **DADO** una dirección de la API definida en la configuración del entorno local
- **CUANDO** la app arma una consulta a la API
- **ENTONCES** usa esa dirección
- **Y** el código de la app no trae ninguna dirección de la API escrita a mano

#### Scenario: base-E08 — El servidor no versiona secretos

- **DADO** el repositorio con `servidor/` creado
- **CUANDO** se revisan los archivos versionados de `servidor/`
- **ENTONCES** no hay ningún `.env`, salvo `.env.example`
- **Y** `.env.example` no trae valor para `APP_KEY` ni para ninguna contraseña, llave o token

### Requirement: Marca y dominio fuera del código

El nombre del producto y el dominio DEBEN (MUST) salir de una variable de configuración en `movil/` y en `servidor/`,
nunca de un texto escrito a mano.

Cita: regla del proyecto → `CLAUDE.md` §5 y §9.3, que verifica `herramientas/ci/verificar_marca.py`. No es A ni D:
ver `design.md`, Open Questions

#### Scenario: base-E09 — La app muestra el nombre que dice su configuración

- **DADO** un nombre de producto de prueba en la configuración de la app
- **CUANDO** se muestra la primera pantalla de la app
- **ENTONCES** aparece el nombre de la configuración

#### Scenario: base-E10 — El servidor usa el nombre y la dirección de su configuración

- **DADO** un nombre de producto y una dirección de prueba en la configuración del servidor
- **CUANDO** se abre la pantalla de inicio de sesión del panel
- **ENTONCES** el título lleva el nombre de la configuración
- **Y** los enlaces absolutos que arma el servidor usan la dirección de la configuración
- **Y** la verificación de marca de la integración continua no encuentra la marca ni el dominio en `movil/` ni en
  `servidor/`

### Requirement: La app abre en Expo Go para probarla en un iPhone sin cuenta de Apple

Mientras no exista la cuenta de Apple del cliente (DD-069), la app DEBE (MUST) poder abrirse en Expo Go para probarla en
un iPhone. Lo que Expo Go no trae, como el módulo nativo de la entrada con Google, NO DEBE (MUST NOT) cerrar la app: esa
parte se oculta en Expo Go y sigue completa en el development build y en las tiendas.

Cita: C → C-240 (Arthur: probarla en un iPhone) · D → ADR-0002 (Expo) y ADR-0005 (Google en la app) · A →
`NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3` (app para iPhone y Android)

#### Scenario: base-E11 — En Expo Go la app abre y no ofrece Google

- **DADO** la app corriendo en Expo Go
- **CUANDO** se abren el inicio de sesión y el registro
- **ENTONCES** las pantallas se muestran sin cerrar la app
- **Y** no aparece el botón de Google, y el correo y la contraseña siguen funcionando
- **Y** el módulo nativo de Google solo se carga cuando la app corre fuera de Expo Go
