## Context

- **Qué se decide y dónde.** Arthur eligió "Todo el Módulo D en la app" (C-247; decisión 69 de
  `vault/meta/actas/acta-2026-11-20-modelo-y-pasarela.md`). Cómo se construye lo decide
  `vault/arquitectura/adr/ADR-0022-administracion-en-la-app.md`. Este diseño no repite el ADR: lo aplica función por
  función, para que el especificador escriba los escenarios y el `tasks.md`.
- **Lo que ya existe y no se reescribe.** Las reglas del Módulo D y del A.5 están especificadas y probadas para el panel
  web en `administracion` (26 escenarios), `indicadores` (24), `exportacion` (12), `alta-de-lavanderias` y `repartidor`.
  Viven en clases de dominio de `servidor/app/` que el panel llama. Este cambio pone encima una API y unas pantallas;
  no cambia ninguna cifra ni ninguna regla.
- **Lo que hacía la app con la administración antes del lote 0** (en `9f6b7e5`). `POST /api/sesion` le respondía 403
  sin token (`servidor/app/Http/Controllers/Api/SesionController.php:76-81`), `GET /api/cuenta` y `DELETE /api/sesion`
  la dejaban fuera (`servidor/routes/api.php:58`) y la app le ofrecía abrir el panel web
  (`movil/src/acceso/BotonDelPanel.tsx`; administracion-E25). Por Google todavía recibía token (tarea 6.2 de
  `administracion`). El lote 0 (tareas 1.1 a 1.7 y 2.1 a 2.3) lo reemplazó: hoy `POST /api/sesion` le da su token
  (`servidor/app/Http/Controllers/Api/SesionController.php:85-90`), la cuenta y el cierre la admiten
  (`servidor/routes/api.php:59-62`) y la app abre su módulo (`movil/app/administracion/index.tsx`).
- **La app.** Cada rol entra a su módulo por el rol de su cuenta (`movil/src/sesion/modulos.ts`;
  `movil/app/_layout.tsx`). Los módulos B y C son pilas de `expo-router` con las entradas a sus pantallas como enlaces
  bajo el título (`movil/app/lavanderia/pedidos/index.tsx:13-15`). Las pruebas abren la app real con un servidor
  simulado (`movil/__tests__/apoyo/lavanderia.ts` y `repartidor.ts`).
- **Las maquetas.** Las de la app son las de la Propuesta 6 (`mocks/Lavandi - Propuesta 06 - Administracion/`: 70
  pantallas en HTML y PNG, con su `NOTAS.md`), que hizo un concilio de agentes. Arthur pidió que el desarrollo siga la
  versión que el concilio diera por la mejor (C-248, en `acta-2026-11-20-modelo-y-pasarela.md`): cada pantalla se
  construye como su imagen, con sus textos. Lo que la Propuesta 6 fijó y los escenarios no decían quedó escrito en la
  spec y aquí (secciones 4 y 5), con su imagen. Las maquetas web de la Propuesta 04
  (`mocks/Lavandi - Propuesta 04/Administración/web/`, d01 a d07) solo orientaron el contenido. Que una pantalla se vea
  así es diseño, no contrato.

## Goals / Non-Goals

**Goals:**

- La administración inicia sesión en la app y ahí hace las siete funciones del Módulo D, ve los once indicadores del
  A.5 y descarga sus reportes en CSV y en Excel.
- Una sola fuente de cada regla y de cada cifra: la API llama a las mismas clases que el panel web, y una prueba lo
  compara.
- Sin librerías nuevas.

**Non-Goals:**

- Cambiar una regla del Módulo D o del A.5, o desbloquear un escenario que espera una DD.
- Quitar el panel web o darle funciones nuevas.
- Mover a la app el contenido de la portada (testimonios y logos de prensa; ADR-0022, decisión 21). Quién lo edita en
  el panel espera DD-141.
- Lo de la lista "Fuera del contrato" de `proposal.md`.

## Decisions

Las técnicas están en ADR-0022 y no se repiten. Aquí, cómo se aplican.

### 1. Cómo se asientan los escenarios

- `openspec/specs/` está vacío: `registro`, `autorizacion`, `administracion`, `indicadores` y `exportacion` siguen en
  sus cambios, sin archivar.
- Este cambio trae su capacidad nueva, `administracion-en-la-app`, y los deltas de las que modifica. Cada requisito
  modificado va completo, con todos sus escenarios:
  - `registro` (`openspec/changes/registro-e-inicio-de-sesion/specs/registro/spec.md`), `## MODIFIED Requirements` de:
    - "Sesión que se conserva sin volver a escribir la contraseña" (`spec.md:94`): el token de administración vence;
    - "Inicio de sesión con Google" (`spec.md:164`): registro-E18 no alcanza a la administración;
    - "Cada rol de la app consulta su cuenta y cierra su sesión" (`spec.md:335`), con registro-E33 reescrito;
  - `autorizacion` (`openspec/changes/autorizacion-por-rol/specs/autorizacion/spec.md`), `## MODIFIED Requirements` de:
    - "Cada rol entra solo a su módulo" (`spec.md:22`): el Módulo D vive en la app, bajo `/api/administracion`, y en el
      panel de respaldo; la cuenta y la sesión son comunes a los cuatro roles (autorizacion-E02);
    - "Prueba contra fugas en cada cambio" (`spec.md:177`): para la administración, lo de todas las plazas en su módulo
      no es ajeno, pero sí lo de otra cuenta de administración (autorizacion-E14);
  - `administracion`: `## REMOVED Requirements` de "La administración en la app" (`spec.md:255`, con
    administracion-E25), y `## MODIFIED Requirements` de "Cuenta de administración", sin la línea del 403
    (`spec.md:292`).
- Se archiva después de `registro-e-inicio-de-sesion`, `autorizacion-por-rol`, `administracion`, `indicadores` y
  `exportacion`, para que sus deltas caigan sobre la spec archivada.
- **Cada escenario nuevo lleva su función del Módulo D o su indicador, y nombra el escenario existente que fija su
  regla.** No copia la regla: dice "con la regla de administracion-E03". Así, si la regla cambia, cambia en un solo
  lugar. Prefijo propuesto: `administracion-app-E01` en adelante.
- **Un escenario existente bloqueado** se lleva a la app igual de bloqueado, con su misma línea `SIN RESPUESTA` y su DD.

### 2. La sesión de la administración

| Ruta | Qué cambia para la administración | Qué hace | Escenarios de la regla |
|---|---|---|---|
| `POST /api/sesion` | Ya no responde 403 | `200 { token, usuario }`, con `usuario.rol = "administracion"`. Token con nombre y habilidad `administracion`, que vence a los `acceso.dias_del_token_de_administracion` días (30 por omisión) | registro-E07, E08 y E30; administracion-E03 (bloqueada) |
| `POST /api/sesion/google` | Ya no da token | `422 { "errors": { "id_token": ["La administración entra con su correo y su contraseña."] } }`, antes de las dos ramas de `EntradaConGoogle::cuentaDe()`: la de Google ya ligado y la del correo; la contraseña, los tokens y las cuentas externas quedan como estaban | registro-E16 a E18 (los otros roles, sin cambio) |
| `GET /api/cuenta` | Ya no responde 403 | Su cuenta, con su rol | registro-E10, E11 y E33 |
| `DELETE /api/sesion` | Ya no responde 403 | Revoca el token con el que llegó | registro-E12 y E13 |

- **El grupo de rutas se parte en dos:** `cuenta` y `sesion` con `autorizar:usuario,lavanderia,repartidor,administracion`;
  `catalogos/municipios` y `codigos-postales/{codigo_postal}/colonias` con los tres roles que los usan.
- **El catálogo de municipios, también para la administración** (adenda del 2026-11-26 de ADR-0022, decisión 6, por
  delegación de Arthur, C-245). La edición de la lavandería elige el municipio de esa lista
  (`movil/src/direcciones/useMunicipios.ts`), y administracion-app-E14 pide que la administración lo cambie. Es un
  catálogo sin datos personales. `catalogos/municipios` va en su propio grupo, con
  `autorizar:usuario,lavanderia,repartidor,administracion`; las colonias por código postal siguen con los tres roles,
  porque la edición no las pide. Un token de administración sin la habilidad no lo abre (administracion-app-E06).
- **La pieza que crea los tokens,** `App\Identidad\TokensDeLaApp` (ADR-0022, decisión 3), reemplaza las cuatro llamadas
  a `createToken()` (en `9f6b7e5`: `SesionController.php:84`, `SesionConGoogleController.php:30`,
  `RegistroController.php:23` y `RepartidorController.php:85`). Hoy los cuatro llaman a `TokensDeLaApp::crear()`
  (`SesionController.php:88`, `SesionConGoogleController.php:36`, `RegistroController.php:25` y
  `RepartidorController.php:89`). Pregunta a `Autorizacion` la habilidad y el vencimiento; los otros roles siguen como
  hoy. Al crear el token de una cuenta de administración, borra los de esa cuenta que no llevan la habilidad (ADR-0022,
  decisión 4). Las pruebas que necesitan un token de la app lo piden a esta pieza.
- **`Autorizacion::usaLaApp()`** incluye a la administración. El 403 de `SesionController` se queda para una cuenta sin
  ningún rol de la app.
- **La habilidad literal** (ADR-0022, decisión 8) vive en un método aparte,
  `Autorizacion::permiteEnLaApi(Usuario $usuario, string $rol, ?HasAbilities $token)`, que solo llama el middleware
  `Autorizar`, con `$request->user()->currentAccessToken()`:
  - para el rol `administracion`: `permite()` y además `$token instanceof PersonalAccessToken` con `'administracion'` en
    `$token->abilities` (`in_array(..., true)`). Un `TransientToken`, un token sin la habilidad o ninguno no pasan. No
    usa `tokenCan()` ni `can()`, que dejan pasar `*`;
  - para los otros roles: `permite()`, igual que hoy;
  - `permite()` no cambia: la usan el panel (`puedeEntrarAlPanel()`, `puedeVerLosIndicadores()`,
    `puedeDescargarReportes()`), el trabajo de la cola `EscribirArchivoDelReporte` y `usaLaApp()`, donde no hay token.
- **Google** (ADR-0022, decisión 5): `Autorizacion::puedeEntrarConProveedorExterno(Usuario)` responde que no a la
  administración, y `EntradaConGoogle::cuentaDe()` lo pregunta antes de la rama de la cuenta ya ligada y antes de la del
  correo (`servidor/app/Identidad/EntradaConGoogle.php:68-70` y `:81`; en `9f6b7e5`, `:65-67` y `:78`), por
  `siPuedeEntrar()` (`:100-104`). La entrada con Apple lo pregunta igual cuando exista (DD-069).
- **En la app:** la pantalla de inicio de sesión es la misma. Con el rol `administracion`, la app abre su módulo. Un 401
  en cualquier pantalla del módulo borra la sesión del teléfono y vuelve al inicio de sesión (ADR-0018, decisión 1).
  "Cerrar sesión" va en el inicio del módulo, con la confirmación de la app (registro-E13).

### 3. La API de la administración

Todas bajo `/api/administracion`, con `['auth:sanctum', 'autorizar:administracion']` y nombres `administracion.*`.
Los registros van por su `id_publico` (ULID), como el resto de la API (ADR-0016, regla 5); uno que no existe responde
404 (ADR-0019, decisión 3). La descarga también: `exports` recibe su `id_publico` con la única migración de este cambio
(ADR-0022, decisión 15; sección 4, "Descargas en CSV y Excel"). Una transición que ya no se puede, porque otra sesión la hizo antes, responde 409 con el mensaje de la
clase de dominio, como pedidos-lavanderia-E08, y la app vuelve a consultar.

**Lo que se mueve de Filament a una clase de dominio, antes de la primera ruta que lo usa** (ADR-0022, decisión 9).
Las pruebas del panel siguen pasando sin cambios:

| Hoy, dentro de Filament | Qué regla tiene | Lo usan después | Se mueve en |
|---|---|---|---|
| `App\Filament\EstadosDelPedido` | El nombre operativo de cada estado (ADR-0021) | El panel, `MarcadoresDelMonitoreo`, la ficha del usuario (`HistorialDelUsuario`) y el monitoreo de la API | El lote 0, antes de la ficha del usuario y del monitoreo. **Ya se movió** con la tarea 1.7: hoy es `App\Pedidos\NombresDelEstado` |
| `App\Filament\Resources\Lavanderias\Pages\EditLavanderia::handleRecordUpdate()` | Qué campos cambiaron respecto de lo que se leyó al abrir, y que se validan solo esos con `ReglasDeLaLavanderia::para()` y sus mensajes | El panel y la API, desde `EdicionDeLavanderia` | El lote 1, antes de la edición por la API. **Ya se movió** con la tarea 3.2: hoy es `App\Lavanderias\EdicionDeLavanderia::validar()` |
| `App\Filament\Resources\Repartidores\Pages\EditRepartidor::handleRecordUpdate()` | Los textos recortados y el vacío como nulo; las reglas de `RegistroDeRepartidorRequest::reglasDeLosDatos()` solo de los campos que llegan; `CorreoSinCuenta` salvo la propia cuenta | El panel y la API, desde `EdicionDeRepartidor` | El lote 2, antes de la edición por la API. **Ya se movió** con la tarea 5.3: hoy es `App\Repartidores\EdicionDeRepartidor` |
| `App\Filament\Resources\Repartidores\Actions\AccionesDeEstado::plazasActivas()` | Las plazas del alta: las activas de las que la cuenta ve en el panel (`plazasDelPanel()`), como pide repartidor-E05 ("entre las plazas activas", `openspec/changes/repartidor/design.md:209`) | El alta del panel, el de la API y `GET plazas`, desde `App\Repartidores\PlazasDelAlta` (`de()` y `laElegida()`) | El lote 2, antes del alta por la API |
| `App\Filament\Resources\Usuarios\HistorialDelUsuario` | Qué pedidos van en la ficha, su fecha en la hora de la plaza y su monto | La ficha del panel y la de la API | El lote 3. **Ya se movió** con la tarea 7.1: hoy es `App\Pedidos\HistorialDelUsuario`; en el panel queda solo cómo lo escribe |
| El formulario de `App\Filament\Pages\Conciliacion` y los campos de `ExportadorDeIndicador::getOptionsFormComponents()` | La regla del rango: las dos fechas, y la de fin no anterior a la de inicio, con su mensaje | La conciliación y la descarga, del panel y de la API | El lote 6; la descarga la usa en el lote 9. **La regla ya se movió** a `App\Validacion\RangoDeFechas` (`reglas()` y `mensajes()`), que el panel toma por `App\Filament\CamposDelRango`; las dos rutas ya la usan: la conciliación y `App\Exportacion\CreacionDeDescarga::reglas()` (tareas 13.2 y 19.2) |
| `CreateAvisoMasivo` y `AvisoMasivoForm` | Qué se guarda con el aviso, que sale en cola después de guardarlo, y los topes del título (255) y del texto (1000). Ya se movió con la tarea 15.1: hoy es `App\Avisos\CreacionDeAvisoMasivo`, que recorta el título y el texto antes de validarlos, como el middleware `TrimStrings` de la API, y trae los mensajes de sus reglas; el formulario del panel recorta igual sus dos campos y toma de ella sus reglas y sus mensajes, como `App\Filament\CamposDelRango` toma los de `RangoDeFechas`, así que muestra esos mensajes en lugar de los de Laravel por defecto | El panel y la API | El lote 7 |
| `App\Filament\Widgets\Indicadores\MapaDeCalor::agrupacion()` | Qué agrupación del mapa de calor vale: por colonia o por zona; por colonia si no se eligió una que exista | El mapa del panel y la API | El lote 8, con la tarea 17.3. **Ya se movió**: hoy es `App\Indicadores\AgrupacionDelMapa` (`elegida()` y `opciones()`) |
| `App\Filament\Actions\DescargarReporte::generar()` | Cómo se crea la descarga de la cuenta y se pone en cola su archivo | El botón del panel y la API | El lote 9. **Ya se movió** con la tarea 19.1: hoy es `App\Exportacion\CreacionDeDescarga` |

Cada lado sigue convirtiendo los errores a su forma: el panel los pone bajo `data.<campo>` de su formulario, y la API
responde 422 con el error de cada campo.

### 4. Función por función

Resumen. El detalle de cada fila va después.

| Función | Cita | Rutas de `/api/administracion` | Pantallas de `movil/app/administracion/` | Escenarios que fijan la regla | Bloqueados |
|---|---|---|---|---|---|
| F-D01 Lavanderías | `:19:A.3`, Módulo D #1 | `lavanderias`, `lavanderias/{lavanderia}`, su `suspension` y su `baja` | `lavanderias/index`, `lavanderias/[id]`, `lavanderias/editar/[id]` | alta-lavanderias-E13 a E15, E17 y E31 | E11 y E12 (DD-053); E16 (DD-104) |
| F-D02 Repartidores | `:19:A.3`, Módulo D #2 | `repartidores`, `repartidores/{repartidor}`, su `alta`, `suspension` y `baja`; `plazas` | `repartidores/index`, `repartidores/[id]`, `repartidores/editar/[id]` | repartidor-E05 a E09 y E31 | — |
| F-D03 Usuarios | `:19:A.3`, Módulo D #3 | `usuarios`, `usuarios/{usuario}`, su `bloqueo` | `usuarios/index`, `usuarios/[id]` | administracion-E01, E03 y E04 | E02 (DD-111); E05 (DD-016) |
| F-D04 Monitoreo | `:19:A.3`, Módulo D #4 | `monitoreo` | `monitoreo` | administracion-E06, E07, E09 y E10 | E08 (DD-016, BR-22) |
| F-D05 Comisiones | `:19:A.3`, Módulo D #5 | `comisiones`, `comisiones/global`, `comisiones/lavanderias/{lavanderia}` | `comisiones` | administracion-E11 a E13 | E14 (DD-019); E15 y E16 (DD-020, DD-013) |
| F-D06 Reportes financieros | `:19:A.3`, Módulo D #6 | `ingresos`, `conciliaciones` | `ingresos` (con las comisiones del periodo cuando DD-020 responda), `conciliacion` | administracion-E17, E20 y E21 | E18 (DD-012, DD-016); E19 (DD-020), en `ingresos`; E22 (DD-013) |
| F-D07 Avisos masivos | `:19:A.3`, Módulo D #7 | `avisos-masivos` | `avisos/index`, `avisos/nuevo` | administracion-E23 | E24 (BR-32, DD-113) |
| F-I01 a F-I11 Indicadores | `:22:A.5`; `:21:A.4.4` | `indicadores/{categoria}` | `indicadores` | indicadores-E01 a E04, E06, E08, E09, E12, E13, E15, E16 y E19 a E24 | E05, E07, E10, E11, E14, E17 y E18 |
| F-T14 Descargas | `:22:A.5`; `:21:A.4.4` | `descargas`, `descargas/{descarga}`, `descargas/{descarga}/{formato}` | `descargas`, y "Descargar" en `indicadores`, `ingresos` y `conciliacion` | exportacion-E01 a E09, E11 y E12 | Los de su indicador o reporte |
| Inicio del módulo | `:3:Cláusula I`; `:21:A.4.3` | — | `index` | registro-E13 (cerrar sesión) | — |

#### F-D01 — Gestión de lavanderías

- **Rutas:**
  - `GET lavanderias`: todas, de todas las plazas, con su nombre, su estado y su plaza, con búsqueda por nombre.
  - `GET lavanderias/{lavanderia}`: la ficha con sus datos editables (nombre, dirección, foto, servicios y precios,
    horario, descripción y fotos), su estado, su plaza y qué transiciones permite ahora, en `transiciones`: la lista de
    las que permite, en el orden de los botones del panel (`suspender`, `levantar_suspension`, `dar_de_baja`). Es la
    misma forma que la ficha del repartidor (F-D02). Trae además `fotos_leidas`, la marca de las fotos de esa lectura
    (abajo, "Las fotos leídas al abrir").
  - `PATCH lavanderias/{lavanderia}`: la edición. Con fotos va en multipart por `POST` con `_method=PATCH`, como
    `PATCH /api/socio/lavanderias/{lavanderia}`. Con la lista de fotos va `fotos_leidas`, la marca de la ficha con que
    se abrió la edición, tal cual.
  - `POST lavanderias/{lavanderia}/suspension`, `DELETE lavanderias/{lavanderia}/suspension` y
    `POST lavanderias/{lavanderia}/baja`.
- **Clases de dominio:** `Autorizacion::lavanderiasDelPanel()`; `App\Lavanderias\EstadoDeLaLavanderia`
  (`puedeSuspenderse()`, `suspender()`, `puedeLevantarseLaSuspension()`, `levantarSuspension()`, `puedeDarseDeBaja()`,
  `darDeBaja()`); `App\Lavanderias\EdicionDeLavanderia::editar()`, con las fotos leídas al abrir, como el panel, y con
  la validación que hoy vive en `EditLavanderia::handleRecordUpdate()`, movida antes de la ruta (sección 3);
  `App\Lavanderias\ReglasDeLaLavanderia` y `App\Lavanderias\FotosDeLaLavanderia`. La ficha puede salir con
  `App\Http\Resources\LavanderiaDelSocioResource`, que ya usa la edición del panel.
- **Las fotos leídas al abrir** (administracion-app-E14). El panel nombra cada foto por su lugar en la lista que leyó
  al abrir la página, guarda sus rutas (`EditLavanderia::$fotosAlAbrir`) y se las pasa a `editar()`, que rechaza la
  lista si las fotos cambiaron desde entonces (`FotosDeLaLavanderia::reemplazarTodas()`, con
  `FotosDeLaLavanderia::FOTOS_QUE_CAMBIARON`). La API hace lo mismo con la lectura de la app:
  - la ficha trae `fotos_leidas`, una marca de las fotos de esa lectura: sus rutas en su orden, cifradas con la llave
    de la aplicación (`FotosDeLaLavanderia::marcaDeLaLectura()`). La ruta no sale de la API, ni siquiera en la marca, y
    la app no la puede cambiar. Es un texto que cambia en cada respuesta y que la app no lee;
  - la app la guarda al abrir la edición y la devuelve tal cual, con la lista de fotos;
  - la ruta saca de la marca las rutas leídas (`FotosDeLaLavanderia::rutasDeLaMarca()`) y se las pasa a `editar()` como
    `fotosLeidas`, igual que el panel: la regla vive en `reemplazarTodas()`, no en la ruta. Sin la marca, o con una
    que no dio el servidor, las rutas leídas son ninguna, y la lista de una lavandería con fotos se rechaza igual;
  - si se rechaza, responde 422 en `fotos` con el mensaje de `FotosDeLaLavanderia` y nada cambia; la app lo pone junto
    a las fotos, y la persona vuelve a abrir la edición. Sin la lista de fotos, la marca no cuenta.
  - **Alternativas descartadas:** un `id_publico` en `lavanderia_fotos`, que pide una segunda migración en este cambio
    (ADR-0016, regla 9; ADR-0022, decisión 15); y mandar las rutas, que nunca salen como campo de la API
    (`App\Models\LavanderiaFoto`).
- **Pantallas** (imágenes 06 a 13 y 57 a 61 de la Propuesta 6):
  - la lista, con la búsqueda; el alta va en ámbar con "Por confirmar · DD-053", sin botón (06);
  - la ficha, con "Editar" en cualquier estado, también de baja, como el panel
    (`servidor/app/Filament/Resources/Lavanderias/Tables/LavanderiasTable.php:44`), y solo los botones de las
    transiciones que permite, cada uno con su confirmación, fijos abajo. Lo que espera DD-104 va en ámbar en la ficha,
    sobre los botones, y no dentro del diálogo (07 a 11);
  - con 409, el mensaje de `TransicionNoPermitida` tal cual, arriba de la ficha, que se vuelve a consultar (58);
  - la edición, con las piezas del formulario del registro de la lavandería (`movil/src/alta-lavanderias/`) en su orden
    (`movil/src/alta-lavanderias/FormularioDeLaLavanderia.tsx:167-360`): los datos y la dirección (12), el municipio y
    las fotos (60), otra foto y los servicios y precios (13), y las ventanas de disponibilidad (61), con "Guardar
    cambios" fijo abajo. El lavado en seco va en ámbar con "Por confirmar · DD-010", sin interruptor, como en el
    registro (`movil/src/alta-lavanderias/formulario.ts:5-7`; alta-lavanderias-E07). Las instrucciones de las fotos y
    de las ventanas hablan de la lavandería, no de tú (administracion-app-E14).
- **Reglas:** alta-lavanderias-E13, E14, E15, E17 y E31; al editar, también E05 a E10, E25 y E26 a E30.
- **Bloqueados:** el alta, E11 y E12 (DD-053); los pedidos en curso al suspender o dar de baja, E16 (DD-104). No hay
  ruta de alta mientras DD-053 no responda, como en el panel.

#### F-D02 — Gestión de repartidores

- **Rutas:**
  - `GET repartidores`: todos, en cualquier estado, también los pendientes sin plaza y los de baja, cada uno con su
    nombre, su estado y su plaza, o ninguna (`id_publico`, `nombre`, `estado` y `plaza`, como la lista de F-D01). Del
    registro más nuevo al más viejo, como la lista del panel. Solo esas cuatro llaves: el teléfono, el correo, la foto
    y el tipo de vehículo son datos de la ficha, que la fila de la lista no muestra (imágenes 14 y 62 de la Propuesta
    6), así que no salen en la lista y la app no los pide ahí.
  - `GET repartidores/{repartidor}`: la ficha, con su nombre, su teléfono, su correo, su foto, su tipo de vehículo, su
    estado y su plaza, como los ve él en su perfil (`App\Http\Resources\RepartidorResource`, sin `puede_operar`), y
    qué transiciones permite en `transiciones`: la lista de las que permite, en el orden de los botones del panel
    (`dar_de_alta`, `suspender`, `levantar_suspension`, `dar_de_baja`). Es la misma forma que la ficha de F-D01: una
    lista de nombres, no un objeto de banderas, y la app muestra los botones de los nombres que trae. La edición, el
    alta, la suspensión, su levantamiento y la baja responden `{ "repartidor": <la ficha> }`.
  - `PATCH repartidores/{repartidor}`: nombre, teléfono, correo, foto y tipo de vehículo; con foto, en multipart.
  - `POST repartidores/{repartidor}/alta` con la plaza por su nombre, que es único
    (`servidor/database/migrations/2026_11_21_100000_crear_plazas_zonas_y_parametros_plaza.php:24`): la plaza no tiene
    identificador público y su llave interna no sale (ADR-0016, regla 5). Sin plaza, o vacía, responde 422 en `plaza`
    con "Elige la plaza."; con una que no es de `GET plazas`, porque no existe o no está activa, con "Esa plaza no está
    entre las del alta.". `POST` y `DELETE repartidores/{repartidor}/suspension`; `POST repartidores/{repartidor}/baja`.
  - `GET plazas`: las plazas para el alta, `{ "plazas": [ { "nombre": "…" } ] }`, por nombre.
- **Clases de dominio:** `Autorizacion::repartidoresDelPanel()` y `plazasDelPanel()`;
  `App\Repartidores\PlazasDelAlta` (`de()`, las activas de `plazasDelPanel()`, y `laElegida()`, la plaza del alta con
  su 422), con la regla que vivía en `AccionesDeEstado::plazasActivas()` (sección 3);
  `App\Repartidores\EstadoDelRepartidor` (`darDeAlta()`, `suspender()`, `levantarSuspension()`, `darDeBaja()` y sus
  `puede…()`); `App\Repartidores\EdicionDeRepartidor::editar()`, con la validación que hoy vive en
  `EditRepartidor::handleRecordUpdate()` (las reglas de `RegistroDeRepartidorRequest` y `App\Validacion\CorreoSinCuenta`),
  movida antes de la ruta (sección 3). La API no la copia.
- **Pantallas:** la lista, la ficha y la edición. La lista los ordena por estado, con lo que ya trae `estado`: primero
  los pendientes sin plaza, luego los activos, los suspendidos y los de baja (imágenes 14 y 62 de la Propuesta 6); el
  servidor da el orden del panel.
- **Reglas:** repartidor-E05 a E09 y E31.

#### F-D03 — Gestión de usuarios

- **Rutas:**
  - `GET usuarios`: las cuentas con el rol de usuario, con su correo, su nombre y su estado, con búsqueda por correo y
    por nombre, en páginas.
  - `GET usuarios/{usuario}`: la cuenta (correo, nombre, teléfono y estado) y su historial de pedidos, cada uno con
    folio, fecha, lavandería, estado y monto, del más reciente al más antiguo.
  - `POST usuarios/{usuario}/bloqueo` y `DELETE usuarios/{usuario}/bloqueo`.
- **Clases de dominio:** `Autorizacion::usuariosDelPanel()`, `pedidosDelPanel()` y `puedeBloquear()`; el historial,
  movido desde `HistorialDelUsuario` (sección 3); `App\Identidad\BloqueoDeCuenta::bloquear()` y `levantar()`.
- **Pantallas:** la lista y la ficha, con "Bloquear" o "Levantar el bloqueo" según su estado, con confirmación.
- **Reglas:** administracion-E01, E03 y E04.
- **Bloqueados:** el filtro de usuarios activos, E02 (BR-33, DD-111); bloquear con un pedido activo, E05 (DD-016).

#### F-D04 — Monitoreo de pedidos

- **Ruta:** `GET monitoreo`. Una sola consulta da los pedidos activos (folio, estado operativo, plaza y recolección) y
  sus marcadores, así que la lista y el mapa nunca difieren. El contrato, llave por llave:
  - cada pedido: `id_publico`, `folio`, `estado` (`codigo` y `nombre`), `plaza` (su nombre) y `ventana` (`inicio`,
    `fin`, `fecha`, `hora_inicio`, `hora_fin`), y nada más. Sin `zona_horaria`: la recolección ya llega en la hora de
    la plaza y ninguna pantalla la muestra (imágenes 25 a 27 de la Propuesta 6);
  - cada marcador: `pedido` (el `id_publico` de su pedido), `folio`, `estado`, `plaza`, `tipo` (`recoleccion` o
    `entrega`), `etiqueta`, `latitud` y `longitud`, solo de los pedidos de la lista.
- **Clases de dominio:** `Autorizacion::pedidosActivosDelPanel()`, que usa `Pedido::activosDelMonitoreo()`;
  `App\Pedidos\MarcadoresDelMonitoreo::deLosPedidos()`; los nombres operativos (sección 3).
- **Pantalla:** el mapa arriba y la lista debajo.
  - Se consulta cada 30 s mientras la pantalla está visible y la app al frente
    (`movil/src/estados/useConsultaPeriodica.ts`; ADR-0007).
  - El mapa es `react-native-maps` sin `provider` (ADR-0009, decisión 1). Cada marcador lleva su letra, R en la
    recolección y E en la entrega, y al tocarlo dice el folio, el estado, si es la recolección o la entrega y la plaza,
    como el panel. Ningún repartidor.
  - El mapa se crea una vez. Cada consulta cambia sus marcadores; se encuadra solo la primera vez que hay pedidos.
  - Sin la llave de Android, el mapa no se monta y la pantalla lo dice (`movil/src/cercanas/mapa.ts`); la lista sigue.
- **Reglas:** administracion-E06, E07, E09 y E10.
- **Bloqueado:** el pedido cancelado, E08 (DD-016; BR-22).

#### F-D05 — Configuración de comisiones

- **Rutas:**
  - `GET comisiones`: el porcentaje global vigente y desde cuándo rige, o ninguno; y cada lavandería, de todas las
    plazas, con su porcentaje propio y el que le aplica, con de dónde sale (propio o global).
  - `PUT comisiones/global`; `PUT comisiones/lavanderias/{lavanderia}`; `DELETE comisiones/lavanderias/{lavanderia}`.
- **Clases de dominio:** `App\Comisiones\PorcentajesDeComision` (`reglas()`, `mensajes()`, `fijarGlobal()`,
  `fijarPropio()`, `quitarPropio()`, `globalVigente()`, `propioVigente()` y `queAplica()`);
  `Autorizacion::puedeFijarLaComisionGlobal()` y `lavanderiasDelPanel()`.
- **Pantalla:** el global arriba, con "Fijar porcentaje", y la lista de lavanderías, cada una con "Fijar porcentaje
  propio" y, si tiene uno, "Quitar porcentaje propio". Sin valores de fábrica.
- **Reglas:** administracion-E11, E12 y E13.
- **Bloqueados:** el repartidor, E14 (DD-019); la comisión de cada pedido y el cambio de porcentaje, E15 y E16 (DD-020,
  DD-013).

#### F-D06 — Reportes financieros

- **Rutas:**
  - `GET ingresos` con `desde`, `hasta` y `granularidad`: el total del periodo, cuántos pagos confirmados son, y los de
    cada periodo.
  - `POST conciliaciones` con `desde` y `hasta`: lee la pasarela, guarda sus movimientos y responde el reporte, fila
    por fila, con cuántos hay de cada resultado. Un rango al revés responde 422 con "La fecha de fin no puede ser
    anterior a la de inicio."; sin pasarela, "No se pudo leer la pasarela" y no guarda nada.
- **Clases de dominio:** `App\Finanzas\Ingresos` (`delRango()` y `porPeriodo()`); `App\Indicadores\Rango::deLosFiltros()`;
  `App\Finanzas\ConciliacionConLaPasarela` (`conciliar()`, `reporte()` y `resumen()`); `App\Finanzas\Pesos`;
  `Autorizacion::puedeVerLosReportesFinancieros()`.
- **Pantallas:** los ingresos (imagen 31 de la Propuesta 6), con el rango y la granularidad plegados en la tarjeta de
  los indicadores (abajo, "Indicadores del A.5"), cuya hoja no trae "Este mes" ni "Mes anterior", que
  administracion-app-E37 no nombra; el total, los pagos confirmados, la tabla de cada periodo y "Descargar". La
  conciliación, con "Conciliar un periodo" y, después de conciliar, la tabla y "Descargar" (32 a 35). Ninguna se
  refresca sola, como en el panel.
- **Las comisiones del periodo** (la parte "comisiones" del Módulo D #6; administracion-app-E40) van en `ingresos.tsx`,
  debajo de los ingresos y con el mismo rango, cuando DD-020 fije su regla. Mientras tanto la pantalla deja su lugar,
  "Comisiones del periodo", en ámbar con "Por confirmar · DD-020", sin ninguna cifra ni descarga, y su ruta no existe
  (imagen 31; tareas 14.1, 21.5 y 22.6). Lo mismo con los reembolsos y ajustes: "Reembolsos y ajustes", con "Por
  confirmar · DD-012, DD-016" (administracion-app-E39). Los dos lugares van bajo los pagos confirmados.
- **Reglas:** administracion-E17, E20 y E21.
- **Bloqueados:** los reembolsos y ajustes, E18 (DD-012, DD-016); las comisiones del periodo, E19 (DD-020); la
  comisión de la pasarela, E22 (DD-013).

#### F-D07 — Avisos masivos

- **Rutas:** `GET avisos-masivos`, los enviados, del más reciente al más antiguo, con su título, su texto, cuándo y
  quién lo mandó y a cuántos teléfonos salió ("En cola" mientras no se cuentan); `POST avisos-masivos` con título y
  texto.
- **Quién lo mandó** (administracion-app-E43) va como "esta cuenta u otra", porque el correo de otra cuenta de
  administración no sale en ninguna respuesta de `/api/administracion` (delta de autorizacion-E14): la ruta dice si lo
  mandó la cuenta de la sesión (`enviado_por_esta_cuenta`) y no trae el correo, el nombre ni la llave de quien lo
  mandó. La app pone, en el aviso propio, el correo de la cuenta, que ya tiene de `GET /api/cuenta` (como la imagen
  36). El texto del aviso que mandó otra cuenta no lo fijan E43 ni la imagen 36: lo decide Arthur antes de la tarea
  16.1 (`mocks/Lavandi - Propuesta 06 - Administracion/NOTAS.md`, «Lo que queda abierto», punto 9).
- **Clases de dominio:** `Autorizacion::avisosMasivosDelPanel()` y `puedeMandarAvisosMasivos()`; la creación movida
  desde `CreateAvisoMasivo` (sección 3); `App\Avisos\MandarAvisoMasivo`, en cola.
- **Pantallas:** la lista y "Escribir aviso", con su confirmación: sale a todos los teléfonos registrados (imágenes 36
  a 40 de la Propuesta 6). Bajo el título y el texto, cuántos caracteres lleva de su tope ("30 de 255 caracteres", "43
  de 1000 caracteres"): es un texto de la app, que sale de los topes de administracion-E23. Sin título, el error de
  `titulo` es "Escribe el título.", con el estilo de los mensajes de `CambiarPerfilRequest` ("Escribe tu nombre.");
  vive con la creación movida a la clase de dominio (tareas 15.1 y 15.2). "A quién le llega" lleva el texto del panel
  (`servidor/app/Filament/Resources/AvisosMasivos/Schemas/AvisoMasivoForm.php:51`), y el aviso a un segmento va en
  ámbar con "Por confirmar · DD-113", sin ofrecerlo.
- **Reglas:** administracion-E23.
- **Bloqueado:** el aviso a un segmento, E24 (BR-32, DD-113). La pantalla no ofrece segmentos.

#### Indicadores del A.5

- **Ruta:** `GET indicadores/{categoria}` con `desde`, `hasta` y `granularidad`, y `agrupacion` en la geográfica. La
  respuesta trae el rango ajustado (`Rango`) y el intervalo de refresco (`App\Indicadores\Refresco`, de
  `indicadores.refresco`). Pregunta a `Autorizacion::puedeVerLosIndicadores()`.
- **Pantalla** (imágenes 41 a 50 y 69 de la Propuesta 6): una pestaña por categoría del A.5, con la pieza `Pestanas`,
  que se desliza de lado con un desvanecido en la orilla por donde sigue la fila (tarea 18.3). Se consulta la categoría
  visible, con el intervalo que trae la respuesta (30 s por omisión; adenda del 2026-11-22 de ADR-0007), mientras la
  pantalla está visible y la app al frente.
- **El rango y la granularidad** van arriba de las pestañas, plegados en una tarjeta, y valen para las cinco:
  - la tarjeta dice el rango ajustado con el texto de `Rango::descripcion()` ("Del 26/10/2026 al 29/11/2026, por
    semana"; `servidor/app/Indicadores/Rango.php:105-108`), debajo "Las cifras se actualizan solas cada N segundos.",
    con la N del intervalo que trae la respuesta (30 por omisión), y a un lado "Cambiar". Así la primera gráfica queda
    arriba del pliegue (41);
  - "Cambiar" abre la hoja "Rango y granularidad", con el `Modal` del `Selector` (pantalla completa en Android y
    `pageSheet` en iPhone) y "Cerrar": "Vale para las cinco categorías.", las dos fechas, "Desde" y "Hasta", en dos
    `CampoDeTexto`, los atajos "Este mes" y "Mes anterior", "Ver por" con Día, Semana o Mes, cómo se ajusta el rango
    ("Por semana, el rango se ajusta a semanas completas, de lunes a domingo: …") y "Aplicar" (42). Sin librería de
    calendario.
  - Sin filtros, el mes en curso hasta hoy, como `Rango::deLosFiltros()`.

| Categoría | Indicador | Visualización del A.5 | Clase de dominio | Pieza de la app | Escenarios | Bloqueado |
|---|---|---|---|---|---|---|
| `operaciones` | F-I01 Pedidos totales | Barras y tabla comparativa | `PedidosTotales::porPeriodo()` | Barras y tabla | indicadores-E01 a E03 | — |
| `operaciones` | F-I02 Pedidos por estado | Dona | `PedidosPorEstado::delRango()` | Dona | indicadores-E04 | E05 (DD-016, BR-22) |
| `usuarios` | F-I03 Usuarios activos | Línea de tendencia | `UsuariosActivos::porPeriodo()` | Línea | indicadores-E06 | E07 (DD-111) |
| `usuarios` | F-I04 Vuelven a pedir | Tabla comparativa | `RetencionPorMesDeRegistro::porMes()` | Tabla | indicadores-E08 | — |
| `usuarios` | F-I05 Monto promedio | Destacado con tendencia | `MontoPromedio::delRango()` y `Tendencia` | Destacado | indicadores-E09 | E10 (DD-012) |
| `socios` | F-I06 Activas frente a registradas | Destacado | — | Destacado | — | E11 (DD-083) |
| `socios` | F-I07 Calificación por lavandería | Ranking con semáforo | `RankingDeLavanderias::calcular()`, `CortesDelSemaforo` y `Semaforo` | Ranking | indicadores-E12 y E13 | E14 (DD-114) |
| `socios` | F-I08 Entregas por repartidor | Tabla comparativa | `EntregasPorRepartidor::delRango()` | Tabla | indicadores-E15 | — |
| `financiero` | F-I09 Ingresos brutos | Barras agrupadas | `App\Finanzas\Ingresos::porPeriodo()` | Barras | indicadores-E16 | E17 (DD-115) |
| `financiero` | F-I10 Comisiones | Tabla con total | — | Tabla | — | E18 (DD-020, DD-013) |
| `geografico` | F-I11 Mapa de calor | Mapa interactivo | `PedidosPorColonia::delRango()`, `PedidosPorZona::delRango()` e `Intensidad::color()` | Mapa y tabla | indicadores-E19 y E20 | — |

- **Para todo el panel de indicadores:** indicadores-E21 y E22 (el refresco y su intervalo), E23 (cifras de la base) y
  E24 (el día de lo que no tiene plaza).
- **F-I07 en la respuesta:** `calificacion_por_lavanderia` trae `semaforo_configurado` (verdadero si la configuración
  trae los dos cortes; sin ellos, ninguna lavandería lleva color, como el "Sin cortes configurados" del panel) y
  `lavanderias`, cada una con `id_publico`, `lugar`, `lavanderia`, `promedio`, `semaforo` y `semaforo_texto`. Las
  cifras de los cortes no salen: las fija EL CLIENTE (DD-114) y la pantalla solo dice "Cortes del semáforo" con "Por
  confirmar · DD-114" (imágenes 46 y 47 de la Propuesta 6), así que la app no las pide.
- **Las piezas nuevas** viven en `movil/src/piezas/` y se arman con vistas y los tokens (ADR-0022, alternativa 5; ADR-0013):
  barras, línea, dona, destacado con tendencia, tabla comparativa y ranking con semáforo. Cada una trae sus cifras en
  texto, para leerse sin el dibujo y sin depender del color.
- **El mapa de calor** usa `react-native-maps`: un círculo por colonia, más oscuro y más grande cuantos más pedidos, o
  cada zona como polígono pintado, con el color que da `Intensidad::color()` en la respuesta. La geometría de la zona
  llega en GeoJSON, como al panel (`App\Indicadores\PedidosPorZona`). La misma cifra va en una tabla debajo.
- **Un indicador bloqueado** se muestra con su lugar y el texto de que espera una respuesta, sin cifra inventada.

#### Descargas en CSV y Excel

- **Rutas:**
  - `POST descargas` con `reporte`, `desde`, `hasta` y `granularidad`: crea la descarga de la cuenta y pone en cola su
    archivo; responde 202 con la descarga. `reporte` es uno de los doce exportadores de `App\Exportacion\Exportadores\`.
    La de la conciliación no relee la pasarela. Un rango al revés responde 422 (exportacion-E06).
  - `GET descargas`: las de la cuenta que siguen vivas (7 días, `EXPORTACION_DIAS_DE_VIDA`) y tienen `id_publico`, de
    la más nueva a la más vieja, con `dias_de_vida`, los de la configuración, para que la app los diga.
  - `GET descargas/{descarga:id_publico}`: su estado (generándose, lista o no se pudo) y el nombre de cada archivo.
  - Cada descarga, en las tres respuestas, trae también `desde` y `hasta`: el rango que cubre su archivo, ya ajustado a
    periodos completos, leído del nombre del archivo (`ExportadorDeIndicador::diasDelArchivo()`, lo contrario de
    `getFileName()`). La granularidad no llega: la fila de `exports` no guarda las opciones de la descarga y la única
    migración del cambio es la del `id_publico` (ADR-0022, decisión 15). Sus llaves son `id_publico`, `reporte`,
    `titulo`, `desde`, `hasta`, `pedida_en`, `estado` y `archivos` (`csv` y `xlsx`), y la app la lee campo por campo,
    como las demás respuestas del módulo: una que no es del contrato cuenta como falla del servidor.
  - `GET descargas/{descarga:id_publico}/{formato}`, con `csv` o `xlsx`: el archivo. Pregunta a
    `Autorizacion::puedeDescargarLaExportacion()`; a otra cuenta le responde 404. Lo entrega el mismo descargador que
    usa la ruta de Filament (`$export->exporter::getDownloader($format)($export)`; ADR-0022, contexto). Un formato que
    no es `csv` ni `xlsx`, o uno que todavía no se puede bajar, responde 404.
- **El identificador** (ADR-0022, decisión 15). La descarga se nombra por su `id_publico`, nunca por el `id` de
  `exports` (ADR-0016, regla 5):
  - la migración del cambio, solo hacia adelante, agrega `$tabla->ulid('id_publico')->nullable()->unique()` a `exports`;
    opcional, por ADR-0016, regla 9. `exports` está en `del_framework` de `config/modelo.php`, así que la prueba de
    esquema no cambia;
  - `App\Exportacion\Exportacion` usa `HasUlids` con `uniqueIds(): ['id_publico']` y **no cambia `getRouteKeyName()`**:
    la ruta de descarga de Filament sigue con `id`, y las pruebas del panel no cambian;
  - la ruta de la API resuelve por el campo explícito `id_publico`: un valor que no es ULID, uno que no existe y el de
    otra cuenta responden 404;
  - las filas de antes de la migración no tienen `id_publico`: la API no las lista y se borran solas a los días de vida.
- **El estado** (ADR-0022, decisión 15), sin columna nueva:
  - **generándose:** `completed_at` vacío;
  - **lista:** `completed_at` marcado, y `headers.csv` y el archivo de filas (`0000000000000001.csv`, el que escribe
    `EscribirArchivoDelReporte`) están en el disco de la descarga. Los dos formatos se pueden bajar: si el XLSX todavía
    no existe, el descargador lo arma desde los CSV;
  - **no se pudo:** `completed_at` marcado sin alguno de esos dos archivos. Pasa si el trabajo del CSV falló, porque el
    lote admite fallas y la cadena marca `completed_at` de todos modos, o si la cuenta ya no podía bajar reportes.
  - Lo verificado en el código instalado (grado E, leído en `vendor/` con las versiones de `servidor/composer.lock`:
    Filament v5.11.4 y Laravel v13.43.0): el orden de la cadena (`ExportDispatcher.php:51-82`), que el resto de la cadena
    corre aunque el lote tenga fallas (`ChainedBatch.php:133-134`), y que el descargador de XLSX lo arma al vuelo
    (`XlsxDownloader.php`). Está en el contexto de ADR-0022.
- **Clases de dominio:** la creación movida desde `DescargarReporte::generar()` (sección 3); los exportadores, sobre
  `App\Exportacion\ExportadorDeIndicador`; `App\Exportacion\EscribirArchivoDelReporte`; `App\Exportacion\Exportacion`,
  que da también el estado; `Autorizacion::puedeDescargarReportes()`.
- **En la app:**
  - "Descargar" en la pantalla de indicadores (un reporte por indicador con cifra), en la de ingresos y, después de
    conciliar, en la de conciliación. Pide el rango, con el de la pantalla por omisión.
  - Mientras se genera, la pantalla lo dice y consulta su estado cada 5 s, solo visible y al frente, como "Procesando
    pago" (adenda del 2026-11-22 de ADR-0010). Lista, ofrece "Guardar .csv" y "Guardar .xlsx". Si no se pudo, lo dice
    y ofrece pedirla otra vez.
  - La pantalla "Descargas" lista las de la cuenta, para guardarlas después, con los días de vida que da el servidor.
    Cada una lleva el nombre de su reporte, el que da el servidor, salvo los ingresos y la conciliación, que llevan el
    de su pantalla, "Ingresos" y "Conciliación", y el rango de su archivo y cuándo se pidió, en renglones separados
    (imagen 54). La que no se pudo generar ofrece "Pedirla otra vez": la de la conciliación se pide con el mismo rango;
    las demás abren la pieza de la descarga con su rango, para elegir la granularidad, por día por omisión, como el
    formulario del panel. Si el servidor no dio su rango, la pieza se abre con los dos campos vacíos, también en la
    conciliación. La nueva entra al principio de la lista. Si la lista no llega, la pantalla lo dice con los mensajes
    generales de la app y ofrece "Intentar de nuevo", como la imagen 05 y las demás listas del módulo.

    SIN RESPUESTA — bloquea: la granularidad en el renglón del rango de "Descargas" ("por semana", imagen 54) y que
    "Pedirla otra vez" la repita sin preguntarla. Guardarla pide una columna en `exports`, con su adenda de ADR-0022,
    decisión 15; o se acepta la desviación en acta y en `NOTAS.md` de la Propuesta 6. Lo decide Arthur.
  - Los campos de fecha de la pieza revisan el formato como el filtro de "Mis pedidos" (`src/historial/fechas.ts`,
    `fechaDeTexto()` y `conDiagonales()`): una fecha a medias o que no existe se dice junto a su campo, con su texto, y
    no se manda. La regla del rango la sigue juzgando el servidor (exportacion-E06; administracion-app-E60).
  - **Guardar** (ADR-0022, alternativa 6): la app baja el archivo con su token a su caché (`File.downloadFileAsync` con el
    encabezado de autorización), pide la carpeta con el selector del sistema (`Directory.pickDirectoryAsync()`), crea
    ahí el archivo con su nombre y su tipo y le escribe el contenido. Dice dónde quedó. Si la persona cierra el
    selector, no se guarda nada y la descarga sigue en la lista. Si en la carpeta ya hay un archivo con ese nombre, lo
    crea con el primero libre, "nombre (2).csv": en iPhone, `createFile` no reemplaza uno que ya existe. Se guarda un
    archivo a la vez: mientras tanto, ningún botón para guardar de la pantalla se puede tocar.
- **Reglas:** exportacion-E01 a E09, E11 y E12. E10 es el aviso del panel; en la app su lugar lo toma el estado de la
  descarga, con su escenario nuevo.
- **Bloqueados:** un reporte sin cifra no tiene descarga: F-I06, F-I10 y las comisiones del periodo
  (administracion-E19), como en el panel.

### 5. La navegación de la app

| Archivo en `movil/app/administracion/` | Qué es |
|---|---|
| `_layout.tsx` | La pila del módulo, sin encabezado nativo, como `lavanderia/` y `repartidor/` |
| `index.tsx` | El inicio (imágenes 03 y 56 de la Propuesta 6): el título "Administración" y las entradas como enlaces, en dos grupos con `TituloDeSeccion`: "Gestión", con las secciones en el orden del contrato (lavanderías, repartidores, usuarios, monitoreo, comisiones, ingresos, conciliación y avisos), y "Estado del negocio", con los indicadores y las descargas. Junto a Repartidores, una insignia con cuántos repartidores están pendientes de alta, y junto a Monitoreo de pedidos, otra con cuántos pedidos activos hay (tarea 10.3). Abajo, fijo en el área de acciones de `Pantalla`, "Cerrar sesión" |
| `lavanderias/index.tsx`, `lavanderias/[id].tsx`, `lavanderias/editar/[id].tsx` | F-D01 |
| `repartidores/index.tsx`, `repartidores/[id].tsx`, `repartidores/editar/[id].tsx` | F-D02 |
| `usuarios/index.tsx`, `usuarios/[id].tsx` | F-D03 |
| `monitoreo.tsx` | F-D04 |
| `comisiones.tsx` | F-D05 |
| `ingresos.tsx`, `conciliacion.tsx` | F-D06; `ingresos.tsx` lleva también las comisiones del periodo (administracion-app-E40) cuando DD-020 responda |
| `avisos/index.tsx`, `avisos/nuevo.tsx` | F-D07 |
| `indicadores.tsx` | A.5 |
| `descargas.tsx` | La descarga del A.5 |

- **La raíz** (`movil/app/_layout.tsx`) suma `<Stack.Protected guard={modulo === 'administracion'}>` con la pantalla
  `administracion`. `movil/src/sesion/modulos.ts` suma el módulo `administracion`, con su inicio en `/administracion`.
- **El código del módulo** vive en `movil/src/administracion/`: el cliente de la API, los textos y los ganchos de cada
  pantalla, como `src/pedidos-lavanderia/` y `src/repartidor/`.
- **Las piezas** son las de la app (`Pantalla`, `Encabezado`, `Titulo`, `TituloDeSeccion`, `Parrafo`, `Enlace`,
  `Boton`, `CampoDeTexto`, `Selector`, `Pestanas`, `TarjetaDeCifras`, `MensajeDeError`, `InsigniaDeEstado`) más las de
  las gráficas. Ningún texto trae la marca ni el dominio (`CLAUDE.md` §5).
- **Los dos conteos del inicio** (administracion-app-E03) salen de las rutas de sus pantallas, sin ruta nueva: los
  pendientes, de `GET repartidores` (los que están en estado pendiente), y los activos, de `GET monitoreo` (cuántos
  pedidos trae). Sin repartidores pendientes, Repartidores va sin insignia (imagen 56). La maqueta no dice cuándo se
  consultan ni qué pasa si la consulta falla; se decide aquí, como aplicación técnica: el inicio los consulta cada vez
  que queda a la vista, sin consulta periódica, y si una consulta falla su entrada va sin insignia y el inicio sigue
  igual. Con la tarea 10.3 lo hace `movil/src/administracion/useConteosDelInicio.ts`, con las mismas funciones de las
  dos pantallas, y cada insignia lleva su texto ("2 pendientes", "3 activos", en singular con uno), en el color de
  atención y en el de información de los tokens, como las imágenes 03 y 56.
- **Los textos** son los del panel de respaldo, del servidor y de la app, citados en cada pantalla de la Propuesta 6,
  para que la app y el panel digan lo mismo. La app adapta solo estos:
  - "La baja no se deshace desde la app.", donde el panel dice "desde el panel", en la confirmación de dar de baja una
    lavandería y a un repartidor (administracion-app-E16 y E22; imágenes 57 y 64);
  - las instrucciones de las fotos y de las ventanas de la edición de la lavandería, que en el perfil del socio le
    hablan de tú y aquí hablan de la lavandería (administracion-app-E14; imágenes 60 y 61);
  - los diálogos de suspender una lavandería y de bloquear a un usuario, que dicen la frase del panel sin el número de
    la DD, porque la DD queda en ámbar en la ficha (imágenes 08 y 23; frente a
    `servidor/app/Filament/Resources/Lavanderias/Actions/AccionesDeEstado.php:51` y
    `servidor/app/Filament/Resources/Usuarios/Actions/AccionesDeBloqueo.php:36`).

  El mensaje del 409 no se adapta: es el de `TransicionNoPermitida`, con el estado como lo guarda la base ("Una
  lavandería en estado baja no se puede suspender."; `servidor/app/Lavanderias/TransicionNoPermitida.php:16`;
  administracion-app-E12, imagen 58). Otro texto cambiaría la clase de dominio y, con ella, el panel. Tampoco el 422 de
  las fotos que cambiaron desde que se abrió la edición, que dice "página" como en el panel
  (`FotosDeLaLavanderia::FOTOS_QUE_CAMBIARON`; administracion-app-E14).
- **Lo que espera una DD** va en ámbar, con la marca "Por confirmar" del resumen del pedido
  (`movil/src/pedido/ResumenDelPedido.tsx:100`) seguida de su número ("Por confirmar · DD-104"), sin cifra ni botón.

### 6. Las pruebas

- **Del servidor,** en `servidor/tests/Feature/AdministracionEnLaApp/`, con el identificador del escenario nuevo en el
  nombre:
  - cada ruta, con la misma respuesta que da la clase de dominio para los mismos datos, como exportacion-E03 compara
    el archivo con el panel;
  - cada ruta, con 403 para una cuenta de usuario, de lavandería y de repartidor, y para un token de administración sin
    la habilidad; 401 para un token vencido;
  - **con tokens reales:** creados por `App\Identidad\TokensDeLaApp` y mandados con `withToken()`, nunca
    `Sanctum::actingAs()`, que simula `can()` y no la lista de habilidades (ADR-0022, decisión 8);
  - la prueba de cobertura de la autorización (autorizacion-E01) recorre las rutas nuevas sin cambios;
  - **autorizacion-E02 y E14 sí cambian** (su delta en `specs/autorizacion/spec.md`), porque hoy fijan que la
    administración no tiene módulo en la API:
    - `servidor/tests/Feature/Autorizacion/RolPorModuloTest.php`: la cuenta, la sesión y el catálogo de municipios,
      comunes a los cuatro roles, y las colonias del catálogo, a los tres de siempre (los municipios se sumaron con la
      tarea 4.3; adenda del 2026-11-26 de ADR-0022, decisión 6); las rutas bajo `api/administracion/` como Módulo D,
      que admite solo a la administración; la comprobación de que la administración es rechazada, solo en las rutas de
      los Módulos A, B y C (en `9f6b7e5`, en todas: `:115`; hoy, solo en esas: `:136-142`); y la administración entre
      las cuentas que llegan a su módulo;
    - `servidor/tests/Feature/Autorizacion/Soporte/LlamadasSinEfecto.php`: el token sale de `TokensDeLaApp` (en
      `9f6b7e5`, `createToken('app')`, que lleva `*`: `:39`; hoy, `:41`), así la cuenta de administración llega con su
      habilidad `administracion` y no recibe 403 en su propio módulo;
    - `servidor/tests/Feature/Autorizacion/BarridoContraFugasTest.php`: para la administración, lo ajeno es lo de otra
      cuenta de administración, su correo y, desde el lote 9, su descarga; ya no lo de todas las plazas, que su módulo sí
      le da (en `9f6b7e5`, `:195-198`; hoy, `:262-271`). Su propio correo, que le da `GET /api/cuenta`, tampoco es
      ajeno para ella;
    - una ruta con un parámetro nuevo (`{repartidor}`, `{usuario}`, `{descarga}`, `{formato}` o `{categoria}`) le da
      datos al barrido en la misma tarea que la crea: si no, el barrido falla y nombra el parámetro, como hoy;
  - las de la sesión, en `servidor/tests/Feature/Registro/`, donde ya está `CuentaDeLosRolesDeLaAppTest.php`.
- **De la app,** en `movil/__tests__/administracion/`, con su servidor simulado en
  `movil/__tests__/apoyo/administracion.ts`, sobre el apoyo de `lavanderia.ts`. Los apoyos `lavanderia.ts` y
  `repartidor.ts` dejan de responder 403 a la administración en `/cuenta` y en `DELETE /sesion`, como el servidor real
  después del lote 0.
- **El guardado del archivo** se prueba sobre la simulación de `expo-file-system` que ya usa
  `movil/__tests__/apoyo/formulario.ts`, pero esa simulación no alcanza sola: su `downloadFileAsync` escribe
  `mock:<url>` sin hacer la petición y su `pickDirectoryAsync` siempre devuelve una carpeta
  (`node_modules/expo-file-system/mocks/FileSystem.ts`, de la versión 58.0.14). La prueba reemplaza con `jest.spyOn`
  `File.downloadFileAsync`, para que pase por el servidor simulado, que comprueba el encabezado `Authorization` y
  entrega el contenido, y `Directory.pickDirectoryAsync`, para simular que la persona cierra el selector.
- **Los contratos con la app.** El servidor simulado de las pruebas de la app no prueba nada si no responde como el
  real: sus respuestas se escriben a mano, y una llave de más o de menos pasa las pruebas y la app real dice "Algo
  salió mal" (así pasó con `zona_horaria` del monitoreo, la lista y la ficha de los repartidores y el semáforo de
  F-I07). Por eso cada ruta de `/api/administracion` tiene sus contratos en `servidor/tests/Contratos/administracion/`:
  un JSON por ruta y por estado (con datos, vacío, con un indicador en espera de su DD, 422, 409, 404 y 503), con la
  petición (`GET /api/administracion/repartidores/{repartidor}`), el código y el cuerpo de la respuesta real para los
  datos que siembra su prueba.
  - **El servidor los escribe y los vigila.** Una prueba por ruta, en la de su función de
    `servidor/tests/Feature/AdministracionEnLaApp/`, compara la forma de la respuesta con la de su contrato
    (`Soporte\ContratosDeLaApp`): cada llave, en cada nivel, con el tipo de su valor. Los valores no cuentan. Si la
    forma cambia, la prueba falla; si el cambio es del contrato de esta sección, se reescribe el archivo con
    `CONTRATOS_ESCRIBIR=1` y la prueba de la app dice si la app lo sigue. `ContratosTest` comprueba además que cada
    ruta de `/api/administracion` tiene al menos un contrato: una ruta nueva sin el suyo falla.
  - **La app los lee.** `movil/__tests__/administracion/contratos.test.ts` pasa cada contrato por el cliente de la
    administración y por la función de la app que lee esa respuesta, y espera lo que la pantalla necesita: sin falla
    con datos, y en un error la falla que muestra, con el campo de su 422. Comprueba que la app pide la ruta y el método
    del contrato, que el servidor simulado responde la misma petición con el mismo código y la misma forma (null aparte)
    y que cada contrato tiene su caso: un contrato nuevo pide decir qué hace la app con él. Los 422 de la validación de
    la edición los pone cada prueba a mano: esos no se comparan con el simulado.
- **La de `abrir-el-panel.test.tsx` se reemplaza** por la de la entrada de la administración a su módulo.
- **Las del panel no cambian,** y siguen en verde después de mover las reglas de la sección 3.

## Risks / Trade-offs

- [Un controlador de la API valida o calcula por su cuenta, y la app y el panel dejan de coincidir] → ADR-0022,
  decisión 9; cada ruta tiene la prueba que compara su respuesta con la clase de dominio (sección 6).
- [Once indicadores y un Excel en un teléfono son más incómodos que en una pantalla grande] → lo decidió Arthur
  sabiéndolo (C-247). Una categoría por pestaña, tablas que se desplazan y la carpeta que elige la persona.
- [Cinco pestañas no caben en el ancho de un teléfono pequeño] → `Pestanas` (`movil/src/piezas/Pestanas.tsx`) hoy es
  una fila sin desplazamiento, y la usa la dirección nueva (`movil/app/pedido/direccion-nueva.tsx`). Se le agrega el
  desplazamiento de lado en su propia tarea, con su prueba, y las de la dirección nueva siguen en verde: cuando las
  pestañas caben, ocupan el ancho como hoy. Los nombres son los de las categorías del A.5. Para que la fila no parezca
  cortada, la orilla por donde sigue lleva un desvanecido de 64 px y la elegida queda siempre entera, fuera de él
  (imágenes 41 y 46 de la Propuesta 6; tarea 18.3).
- [El selector de carpetas y la descarga se ven distinto en cada teléfono (`SIN VERIFICAR`)] → se prueban en el Android
  de Arthur y en un iPhone (C-240; DD-069). Si no sirven, se pasa a `expo-sharing` (ADR-0022, criterio de reversión).
- [La dona y la línea con vistas no se leen bien] → se pasa a `react-native-svg` (ADR-0022, criterio de reversión).
  Cambian solo esas dos piezas.
- [El mapa de Android sin su llave] → la pantalla lo dice y las cifras siguen en la lista o la tabla. La llave es
  `GOOGLE_MAPS_ANDROID_API_KEY` (`movil/app.config.ts:59`; ADR-0012).
- [Un teléfono perdido con un token de administración] → vence a los 30 días; antes, recuperar la contraseña cierra
  todas las sesiones (ADR-0018, decisión 6).
- [Los tokens que la administración recibió por Google antes de esto] → no llevan la habilidad y no abren nada
  (ADR-0022, decisión 8), ni el cierre de la sesión. Se borran cuando esa cuenta inicia sesión con su contraseña
  (decisión 4). Fuera de local no hay entornos todavía (DD-070).
- [Una descarga cuyo trabajo falla se quedaría "generándose"] → no: la cadena marca `completed_at` aunque el lote
  falle, y el estado sale de los archivos (sección 4, "Descargas en CSV y Excel"): sin sus dos CSV, "no se pudo", y la
  app deja pedirla otra vez.
- [Una cola que no corre deja la descarga "generándose" para siempre] → igual que en el panel. En local, la cola de
  `composer dev`; fuera de local, la del entorno (DD-070).
- [La exportación de Filament deja además su aviso en las notificaciones del panel] → no estorba: solo se ve si alguien
  abre el panel de respaldo.
- [Dos caras de la administración que cuidar] → el panel no recibe funciones nuevas (ADR-0022, decisión 20) y las dos
  llaman a las mismas clases.
- [El calendario] → **no está listo: no cabe.**
  - La cuenta. El `tasks.md` suma 209 horas sin bloqueo (más 42 bloqueadas): 207 al escribirlo, y 2 de los conteos del
    inicio de la Propuesta 6 (tarea 10.3). El contrato pone el panel de indicadores
    (O-21) y su descarga (O-22) en las semanas 12 y 13, del 23 de noviembre al 6 de diciembre, y el desarrollo de la
    app (O-12 y O-16) hasta la semana 13 (`NSV-2026-001-v8.pdf:5:Cláusula II`; `vault/contrato/obligaciones-ejecutivas.md`,
    O-16, O-21 y O-22). Al 2026-11-24 quedan 13 días. Con las 14 horas por semana que declaró Arthur (C-134), son unas 28
    horas: menos de una sexta parte.
  - Cuánto rinden los agentes en paralelo no está medido: los tamaños son la banda media de S-006, un supuesto.
  - El plan de sprints ya decía **no está listo** contra la Cláusula II con todo el alcance (DD-064 y DD-068, de
    Nadir), y contaba el Módulo D en el panel web (`vault/formas-de-trabajo/sprints/plan-de-sprints.md:262`). Las horas de
    la app de este cambio no estaban en esa cuenta.
  - **El orden, si no cabe todo:**
    1. el lote 0, la sesión y el inicio del módulo: sin él la administración no entra (A43-6);
    2. el lote 8, los indicadores (A44-1);
    3. el lote 9, las descargas (A44-2);
    4. los lotes 4 y 3, el monitoreo y los usuarios: los "datos reales de pedidos y usuarios activos" (A43-7);
    5. los lotes 1, 2, 5, 6 y 7, el resto de las funciones del Módulo D (A43-6, que pide las siete).
  - Qué hacer con la fecha no se decide aquí: el aviso de retraso y la Orden de Cambio por etapas son de Nadir (DD-064 y
    DD-068).

## Open Questions

Ninguna técnica queda para Arthur: ADR-0022 las decidió por delegación (C-236, C-245). Quedan las que no son de este
cambio:

1. **El alta de una lavandería** (alta-lavanderias-E11 y E12) sigue esperando DD-053, de Nadir. Cuando responda, su
   ruta y su botón entran aquí con la regla que fije.
2. **Probar en iPhone** necesita una cuenta de Apple para la prueba temprana (DD-069) o la vía de C-240.
3. **El correo de la cuenta de administración en producción** lo da EL CLIENTE, por medio de Nadir, y se corre el comando
   cuando exista el alojamiento (decisión 68; DD-070).
4. **Las DD que bloquean escenarios** siguen abiertas con sus dueños: DD-111, DD-113, DD-083, DD-114, DD-115, DD-020,
   DD-013, DD-012, DD-016, DD-019 y DD-104.
5. **Quién edita el contenido de la portada** (testimonios y logos de prensa) en el panel de respaldo, y por qué vía
   llega a él (DD-141, de Arthur; ADR-0022, decisión 21). No bloquea el código de este cambio.
