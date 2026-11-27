---
estado: vigente
grado: D
fuente: acta-2026-11-20-modelo-y-pasarela.md C-247 y decisión 69 (C); delegación de C-236 y C-245 (C); adenda del 2026-11-26: delegación de Arthur, C-245 (C), y servidor/routes/api.php (B); C-134 (C); NSV-2026-001-v8.pdf:3:Cláusula I (entregables 3 y 4), :5:Cláusula II, :10:Cláusula X, :15:ANEXO A, Sección A.2, :19-20:ANEXO A, Sección A.3, :21:ANEXO A, Secciones A.4.3 y A.4.4, y :22:ANEXO A, Sección A.5 (A); servidor/routes/api.php, servidor/app/Autorizacion/Autorizacion.php, servidor/app/Http/Middleware/Autorizar.php, servidor/app/Http/Controllers/Api/SesionController.php, servidor/app/Identidad/EntradaConGoogle.php, servidor/config/sanctum.php, servidor/database/migrations/2026_11_24_200000_crear_exports.php, servidor/app/Models/Concerns/TieneIdPublico.php, servidor/app/Exportacion/EscribirArchivoDelReporte.php, servidor/tests/Feature/Autorizacion/, movil/package.json, movil/app/_layout.tsx y movil/src/sesion/modulos.ts (B); ADR-0003, ADR-0005, ADR-0007, ADR-0009, ADR-0013, ADR-0016, ADR-0017, ADR-0018 y ADR-0019 (D); DD-040 y DD-141 (B); código instalado de Laravel v13.43.0, Sanctum v4.5.0, Filament v5.11.4 y expo-file-system 58.0.14 (E, SIN VERIFICAR en el teléfono)
fecha: 2026-11-24
dueño: Arthur Naranjo
adr: ADR-0022
supersede:
---
# ADR-0022 — La administración vive en la app móvil

## Contexto

- **Qué pide el contrato (A):**
  - la Cláusula I pone el panel de administración dentro de la app: "Aplicación única para iPhone y Android, con módulo
    de usuario, módulo de socio lavandería, módulo de socio repartidor y panel de administración"
    (`NSV-2026-001-v8.pdf:3:Cláusula I`, entregable 3);
  - y pone ahí mismo el panel de indicadores: "Panel de indicadores del negocio", con plazo "Incluido en app"
    (`NSV-2026-001-v8.pdf:3:Cláusula I`, entregable 4);
  - el Módulo D tiene siete funciones (`NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3`, Módulo D #1 a #7; O-16), y el
    A.5, once indicadores "integrado en la sección de administración" (O-21), con la descarga en CSV y Excel "para
    cualquier rango de fechas" (O-22) (`NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5`);
  - sus criterios: "Los cuatro módulos (usuario, socio lavandería, socio repartidor y administración) accesibles y
    funcionales conforme a A.3" y "Panel de administración muestra datos reales de pedidos y usuarios activos"
    (`NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3`); "El panel muestra en tiempo real los indicadores definidos en
    A.5" y "Reportes exportables generados correctamente en formato CSV o Excel" (`:21:ANEXO A, Sección A.4.4`);
  - la sesión se guarda "de forma segura" y sin pedir la contraseña cada vez (`NSV-2026-001-v8.pdf:20:ANEXO A, Sección
    A.3`, Control de acceso), con "medidas de seguridad técnicas razonables" (`NSV-2026-001-v8.pdf:10:Cláusula X`).
  - **El contrato no dice** cómo se separa la sesión de la administración, cómo navega su módulo ni cómo se dibuja o se
    guarda un reporte en el teléfono. Eso se decide aquí; nada de esto viene del contrato.
- **Qué decidió Arthur (C).** Viendo el emulador objetó que la administración lo mandaba a una página: "El
  administrador debe iniciar sesión. Eso del panel es algo tonto, ya que en sí esta app es una app móvil, no una app
  web". Eligió **"Todo el Módulo D en la app"**: el administrador inicia sesión en la app y ahí hace todo lo del
  Módulo D y del A.5; el panel web se queda como respaldo interno y no se muestra. Revoca la parte web de C-140, la
  decisión 31 y la decisión 56 (C-247; decisión 69 de [[acta-2026-11-20-modelo-y-pasarela]]).
- **Lo que hay (B),** en el código de `9f6b7e5`, cuando se decidió. Las citas con número de línea de esta lista son de
  ese commit: el lote 0 de `administracion-en-la-app` (tareas 1.1 a 1.7 y 2.1 a 2.3, unidas en `ae3cd0e` a `c998b7a`)
  cambió ese código, y dónde quedó cada pieza lo dicen el `design.md` y el `tasks.md` del cambio.
  - El Módulo D y el A.5 ya están construidos en el panel de Filament, y sus reglas viven en clases de dominio de
    `servidor/app/` que el panel llama: `EstadoDeLaLavanderia`, `EstadoDelRepartidor`, `BloqueoDeCuenta`,
    `PorcentajesDeComision`, `Ingresos`, `ConciliacionConLaPasarela`, `MarcadoresDelMonitoreo`, las consultas de
    `app/Indicadores/` y los exportadores de `app/Exportacion/`. Las especifican y prueban `administracion` (26
    escenarios), `indicadores` (24) y `exportacion` (12), en `openspec/changes/`.
  - Algunas reglas que la app también necesita viven todavía dentro de clases de Filament:
    `App\Filament\EstadosDelPedido` (que ya usa una clase de dominio, `MarcadoresDelMonitoreo`),
    `App\Filament\Resources\Usuarios\HistorialDelUsuario`, la creación del aviso masivo en `CreateAvisoMasivo` y la de
    la descarga en `App\Filament\Actions\DescargarReporte::generar()`.
  - Todos los tokens de la app se crean con el nombre `app` y sin habilidades propias
    (`servidor/app/Http/Controllers/Api/SesionController.php:84`, `SesionConGoogleController.php:30`,
    `RegistroController.php:23` y `RepartidorController.php:85`), y ninguno vence (`servidor/config/sanctum.php`,
    `'expiration' => null`). La tabla ya tiene `expires_at`
    (`servidor/database/migrations/2026_11_20_142107_create_personal_access_tokens_table.php:21`).
  - A una cuenta de administración, `POST /api/sesion` le responde 403 sin token
    (`servidor/app/Http/Controllers/Api/SesionController.php:76-81`; registro-E33), y `GET /api/cuenta` y
    `DELETE /api/sesion` la dejan fuera (`servidor/routes/api.php:58`; decisión 31). La app le muestra un botón que
    abre el panel web (`movil/src/acceso/BotonDelPanel.tsx`; administracion-E25; decisión 56).
  - **Hueco abierto:** por Google, la administración todavía recibe token y, si su Google no estaba ligado, se le anula
    la contraseña antes de ligarlo (`servidor/app/Identidad/EntradaConGoogle.php:78` y `:110-114`). Si ya estaba ligado,
    la cuenta sale por otra rama, antes de esa (`:65-67`). Es la tarea 6.2 de `administracion`, sin hacer.
  - **La autorización de la API** pasa por el middleware `Autorizar`, que pregunta a `Autorizacion::permite(Usuario,
    string $rol)` (`servidor/app/Http/Middleware/Autorizar.php`; `servidor/app/Autorizacion/Autorizacion.php:165`).
    `permite()` no recibe el token, y la usan también el panel y la cola, donde no hay token de la app.
  - **Las pruebas de la autorización fijan la regla vieja:** `RolPorModuloTest` (autorizacion-E02) espera la cuenta y la
    sesión con tres roles, manda al Módulo A toda ruta que no reconoce y exige que la administración sea rechazada en
    todas las rutas de la API; `BarridoContraFugasTest` (autorizacion-E14) trata como ajeno a la administración todo lo
    de la app; y las dos crean los tokens con `createToken('app')`, que lleva `*`
    (`servidor/tests/Feature/Autorizacion/RolPorModuloTest.php:41-49`, `:115` y `:153-162`;
    `BarridoContraFugasTest.php:195-198`; `Soporte/LlamadasSinEfecto.php:39`).
  - **La tabla `exports`** de la descarga es la de Filament, con su `id` y sin `id_publico`
    (`servidor/database/migrations/2026_11_24_200000_crear_exports.php:25-37`). Lo que sale por la API usa un ULID
    público, nunca el `bigint` (ADR-0016, regla 5; `servidor/app/Models/Concerns/TieneIdPublico.php`), y las migraciones
    van solo hacia adelante, con lo nuevo opcional (ADR-0016, regla 9). `exports` está en la lista `del_framework` de
    `servidor/config/modelo.php`, fuera de las reglas de la prueba de esquema.
  - **El trabajo que escribe el archivo de un reporte** (`App\Exportacion\EscribirArchivoDelReporte`) deja en el disco de
    la descarga `headers.csv` y un archivo de filas, y no escribe nada si la cuenta ya no puede bajar reportes.
  - La app entra a cada módulo por el rol (`movil/src/sesion/modulos.ts`; `movil/app/_layout.tsx`). Los módulos B y C
    son una pila con las entradas a sus pantallas como enlaces bajo el título, "mientras no exista una barra inferior"
    (`movil/app/lavanderia/pedidos/index.tsx:13-15`).
  - Lo instalado en la app (`movil/package.json`): `react-native-maps`, `expo-file-system`, `expo-web-browser`,
    `expo-secure-store` y `expo-router`, entre otros. **No hay** librería de gráficas, ni `react-native-svg`, ni
    `expo-sharing`.
- **Lo que trae el código instalado** (grado E: leído en `vendor/` y `node_modules/`, que fijan
  `servidor/composer.lock:2650-2651` y `:1158-1159` y `movil/package-lock.json:9645-9647`; el comportamiento en el
  teléfono es `SIN VERIFICAR`):
  - Sanctum v4.5.0: `PersonalAccessToken::can()` da por buena cualquier habilidad a un token con `*`
    (`src/PersonalAccessToken.php:77-81`), y el guard rechaza un token cuyo `expires_at` ya pasó (`src/Guard.php:129`).
  - Filament v5.11.4: la ruta de descarga de una exportación entrega el archivo con
    `$export->exporter::getDownloader($format)($export)`
    (`actions/src/Exports/Http/Controllers/DownloadExport.php:46`). La cadena de la cola es: el lote con el trabajo que
    escribe el CSV, con `allowFailures()`; luego `CompleteExport`, que marca `completed_at`; y al final `CreateXlsxFile`
    (`actions/src/Exports/ExportDispatcher.php:51-82`). Laravel v13.43.0 corre el resto de la cadena al terminar el lote
    aunque un trabajo falle, salvo que se cancele (`Illuminate/Bus/ChainedBatch.php:133-134`). Así, `completed_at` no
    dice que los archivos existan. El descargador de XLSX arma el archivo al vuelo desde los CSV si todavía no existe, y
    los dos responden 404 si no está el directorio de la descarga (`actions/src/Exports/Downloaders/XlsxDownloader.php`
    y `CsvDownloader.php`).
  - Laravel v13.43.0: `HasUlids` asigna el ULID a las columnas de `uniqueIds()` y, en una ruta con campo explícito
    (`{descarga:id_publico}`), responde 404 a un valor que no es ULID; si la llave primaria no está en `uniqueIds()`, la
    tabla sigue con su `id` autoincremental y la ruta que resuelve por `id` no cambia
    (`Illuminate/Database/Eloquent/Concerns/HasUniqueStringIds.php:54-60` y `:72-90`).
  - Sanctum v4.5.0: el token de la petición es un `PersonalAccessToken` con su lista `abilities`
    (`src/PersonalAccessToken.php:16` y `:77-81`), o un `TransientToken` cuando Sanctum autentica por la sesión web
    (`src/Guard.php:35`); en el panel y en la cola no hay token de la app.
  - expo-file-system 58.0.14: `File.downloadFileAsync(url, destino, { headers })` (`src/NetworkTasks.types.ts:15-21`);
    `Directory.pickDirectoryAsync()`, que abre el selector de carpetas del sistema en iPhone y en Android
    (`ios/FileSystemModule.swift:244-257`; `android/.../FileSystemModule.kt:132-138`); y
    `Directory.createFile(nombre, tipo)` (`build/Directory.d.ts:39`).

## Criterios y pesos

Los de la matriz `vigente` (C-142): familiaridad 30, velocidad 25, proveedor 15, tiempo real 10, costo 10 y
multi-plaza 10. Puntajes de 1 a 5; el total es la suma ponderada sobre 100. La familiaridad se califica con el perfil
de Arthur (C-135). Los puntajes son de Claude.

**Puerta, antes del puntaje: C-247.** La administración hace sus funciones dentro de la app; nada la manda a una
página web. Una alternativa que no la pasa no se pondera.

Donde las alternativas usan lo mismo que ya está (Sanctum, `expo-router`), la matriz no las separa y se comparan con
una tabla de lo que pasa con cada una, como en las adendas de ADR-0009.

## Alternativas evaluadas

### 1. Cómo se distingue la sesión de la administración

| Alternativa | Qué pasa con ella |
|---|---|
| **A. Token de Sanctum con una habilidad propia, `administracion`, y con vencimiento** | Un token de administración se reconoce por sí mismo y se puede revocar o dejar vencer sin tocar los de los otros roles. Usa `expires_at`, que Sanctum ya revisa. **Se elige** |
| B. El token de la app de siempre, sin habilidad ni vencimiento, y que decida solo el rol | Funciona, porque la ruta revisa el rol. Pero el token de la cuenta con más poder (bloquea usuarios, fija comisiones, manda avisos a todos los teléfonos) no vencería nunca, y no se distingue de uno de usuario si se filtra. No es una medida "razonable" para esa cuenta (`:10:Cláusula X`) |
| C. La sesión web de Sanctum, con cookies, dentro de la app | La app nativa usa tokens (ADR-0005, "Sesiones"). Sería otro mecanismo solo para un rol |

**Cómo se revisa la habilidad.** Con `can()` o `tokenCan()`, un token con `*` pasaría, y todos los tokens de hoy
llevan `*`. La habilidad se revisa literal: el token tiene `administracion` en su lista.

### 2. Cuándo vence el token de la administración

| Alternativa | Qué pasa con ella |
|---|---|
| Sin vencimiento, como los otros roles | Mismo problema de B arriba |
| 7 días | Pide la contraseña cada semana al dueño del negocio. Se acerca a pedirla cada vez, que es lo que el control de acceso evita (`:20:A.3`) |
| **30 días desde que se abre la sesión** | Una vez al mes. Usa `expires_at` de Sanctum, sin código propio. **Se elige** |
| 30 días sin usarla (vencimiento por inactividad) | Quien la usa a diario nunca vuelve a escribir la contraseña. Pide código propio sobre `last_used_at` |

La cifra es técnica, de Claude, no del contrato ni del negocio. Vive en configuración.

### 3. Si la administración entra con Google

| Alternativa | Qué pasa con ella |
|---|---|
| **A. No entra con Google: la respuesta lo dice y no se toca la cuenta** | La cuenta nace con contraseña, por comando (administracion-E26), y el panel de respaldo solo entra con contraseña (`App\Filament\Auth\IniciarSesion`). Google Sign-In es la opción del registro del usuario (`NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3`, Módulo A #2; RN-17), no de la administración. **Se elige** |
| B. Como cualquier cuenta: liga Google y anula la contraseña (registro-E18) | Deja a la administración sin entrada al panel de respaldo, que es solo con contraseña |
| C. Solo si su Google ya estaba ligado, sin ligar uno nuevo | Hoy ninguna lo está, salvo por el hueco de la tarea 6.2. Abre un caso aparte en registro-E18 sin necesidad |

### 4. Cómo navega el módulo de la administración

| Alternativa | Qué pasa con ella |
|---|---|
| **A. Una pila con un inicio que lista las secciones, como los módulos B y C** | Las mismas piezas y el mismo patrón que ya tiene la app (`movil/app/lavanderia/pedidos/index.tsx:13-15`). **Se elige** |
| B. Una barra inferior de pestañas | Son nueve destinos (siete funciones, los indicadores y las descargas). Habría que agruparlos en pocas pestañas y decidir qué va junto, algo que el contrato no ordena, y ningún módulo de la app tiene barra todavía (`movil/app/lavanderia/pedidos/index.tsx:13-15`) |
| C. Un menú lateral | Pide una librería que no está instalada, y ningún módulo lo usa |

### 5. Cómo se dibujan las gráficas del A.5

El A.5 nombra la visualización de cada indicador: barras con tabla comparativa, dona, línea de tendencia, tabla
comparativa, indicador destacado con tendencia, ranking con semáforo, barras agrupadas, tabla con total y mapa
interactivo (`NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5`). Las barras, las tablas, los destacados y el ranking son
rectángulos y texto. La dona y la línea piden curvas o diagonales.

| Alternativa | Familiaridad (30) | Velocidad (25) | Proveedor (15) | Tiempo real (10) | Costo (10) | Multi-plaza (10) | **Total** |
|---|---|---|---|---|---|---|---|
| **A. Con las vistas de React Native y los tokens, sin librería** | 4 | 4 | 5 | 5 | 5 | 5 | **89** |
| B. `react-native-svg`, nueva, con las gráficas dibujadas a mano | 4 | 3 | 4 | 5 | 5 | 5 | **81** |
| C. Una librería de gráficas sobre `react-native-svg`, por ejemplo `react-native-gifted-charts` | 3 | 4 | 2 | 5 | 5 | 5 | **74** |

- **Familiaridad.** A son las mismas vistas, con `flex` y los tokens, que ya arman las estadísticas de los socios
  (`movil/src/piezas/TarjetaDeCifras.tsx`). B es SVG, que Arthur conoce de la web. C pide aprender la API de la
  librería y ajustarla a los tokens.
- **Velocidad.** A no cambia la compilación nativa: se prueba con el Jest de siempre. La dona y la línea piden algo de
  geometría (abajo). B y C son módulos nativos nuevos: hay que volver a compilar el development build de Android y el
  de iPhone (grado E, `SIN VERIFICAR`), y simularlos en Jest. C dibuja rápido una vez instalada.
- **Proveedor.** Se califica por lo que entra a `movil/package.json`, sin juzgar a quién la mantiene. A no suma nada.
  B suma una dependencia nativa. C suma dos, y la de gráficas va encima de la otra: hay que seguir las versiones de las
  dos con cada SDK de Expo.
- **Tiempo real, costo y multi-plaza:** las tres igual; son gratuitas.

**Cómo se dibuja con vistas lo que no es rectángulo:**
- **La línea de tendencia:** un punto por periodo y, entre cada par, un segmento: una vista delgada del largo de la
  distancia entre los puntos, girada con el ángulo entre ellos (`transform: rotate`).
- **La dona:** un anillo de marcas cortas alrededor de un centro, cada una girada a su ángulo y del color del estado
  en que cae su porción.
- **Siempre con sus cifras en texto,** como etiqueta o tabla debajo: la gráfica se lee sin el dibujo y sin depender
  del color (reglas transversales del design system), como el mapa de calor del panel trae su tabla.

### 6. Cómo se guarda en el teléfono el archivo CSV o Excel

| Alternativa | Puerta C-247 | Familiaridad (30) | Velocidad (25) | Proveedor (15) | Tiempo real (10) | Costo (10) | Multi-plaza (10) | **Total** |
|---|---|---|---|---|---|---|---|---|
| **A. `expo-file-system`, instalada: la app baja el archivo con su token y lo guarda en la carpeta que elige la administración** | pasa | 4 | 4 | 5 | 5 | 5 | 5 | **89** |
| B. Una dirección firmada que vence, abierta con `expo-web-browser`, instalada, que baja el archivo | no pasa: abre la vista del navegador | — | — | — | — | — | — | — |
| C. `expo-sharing`, nueva, con la hoja de compartir del sistema | pasa | 4 | 3 | 5 | 5 | 5 | 5 | **84** |

- **B se descarta por la puerta.** Es el mismo camino que el checkout (adenda del 2026-11-22 de ADR-0010) y sería lo
  más rápido, pero abre la vista del navegador, que es lo que Arthur objetó (C-247).
- **A frente a C.** Las dos dejan el archivo donde la persona lo pueda abrir o mandar. C da la hoja de compartir
  (correo, WhatsApp, Archivos), pero es un módulo nativo nuevo, con otra compilación. A usa lo que ya compila la app y
  que ya usan sus pruebas (`movil/__tests__/apoyo/formulario.ts:13`). El selector de carpetas y la descarga con
  encabezados están en la versión instalada (grado E, arriba); cómo se ven en cada teléfono, `SIN VERIFICAR`.

## Decisión

La tomó Claude, por delegación de Arthur Naranjo (C-236 y C-245), para construir lo que Arthur decidió en C-247
(decisión 69 de [[acta-2026-11-20-modelo-y-pasarela]]). Ninguna de estas decisiones viene del contrato: el contrato
solo pone la administración dentro de la app (Cláusula I) y dice qué hace (Módulo D y A.5).

**La sesión**

1. **La administración inicia sesión en la app con su correo y su contraseña,** en la misma pantalla y con
   `POST /api/sesion`, como los otros roles. Ya no recibe 403.
2. **Su token es de administración.** Se crea con el nombre `administracion`, con la habilidad `administracion` y
   ninguna otra, y vence a los 30 días de abierto (`expires_at`). Los días viven en `servidor/config/acceso.php`, junto a
   los valores de ADR-0018, con su variable de entorno. Los tokens de los otros roles no cambian.
3. **El token lo crea una sola pieza del servidor,** `App\Identidad\TokensDeLaApp`, que pregunta al componente de
   autorización qué habilidad y qué vencimiento lleva la cuenta. Hoy cuatro controladores llaman a `createToken()` cada
   uno por su lado. Las pruebas que necesitan un token de la app lo piden a esa misma pieza, también las de la
   autorización (decisión 8).
4. **Se revoca:**
   - al cerrar la sesión, con `DELETE /api/sesion`, que revoca el token con el que llegó (registro-E12);
   - al crear una contraseña nueva por la recuperación, que revoca todos los tokens de la cuenta (ADR-0018, decisión
     6). Es el camino si se pierde el teléfono;
   - al vencer, a los 30 días;
   - y los de antes, sin la habilidad: cuando una cuenta de administración inicia sesión con su contraseña, la pieza
     única borra sus tokens que no llevan `administracion`, como los `*` que salieron por Google antes de la decisión 5.
     Esos no abren ninguna ruta (decisión 8), así que tampoco pueden cerrarse con `DELETE /api/sesion`; sin este paso
     quedarían vivos, sin vencer, hasta una recuperación de contraseña.

   Con cualquiera de las tres primeras, la siguiente petición responde 401 y la app borra la sesión del teléfono
   (ADR-0018, decisión 1) y vuelve al inicio de sesión.
5. **La administración no entra con Google.** `POST /api/sesion/google` le responde 422 en `id_token` con "La
   administración entra con su correo y su contraseña.", sin token, sin ligar nada y sin anular su contraseña ni borrar
   sus tokens. Se rechaza **antes de las dos ramas** de `EntradaConGoogle::cuentaDe()`: la de la cuenta con Google ya
   ligado (`:65-67` en `9f6b7e5`), que entonces la devolvía tal cual, y la del correo, que liga y anula la contraseña
   (`:78`). Con la tarea 1.4 quedó así: las dos ramas (`servidor/app/Identidad/EntradaConGoogle.php:68-70` y `:81`)
   preguntan en `siPuedeEntrar()` (`:100-104`). La pregunta
   es un método del componente, `Autorizacion::puedeEntrarConProveedorExterno(Usuario)`, que la entrada con Apple usará
   igual cuando exista (DD-069). Cierra el hueco de la tarea 6.2 de `administracion`, con 422 en lugar de 403: la app ya
   muestra el mensaje de `id_token` bajo el botón de Google.
6. **`GET /api/cuenta` y `DELETE /api/sesion` son también de la administración.** La cuenta le dice a la app que el
   rol es `administracion`. El catálogo del territorio (`catalogos/municipios` y las colonias por código postal) se queda
   para los tres roles que lo usan. La lista de municipios la cambió la adenda del 2026-11-26: también es de la
   administración.

**La API de la administración**

7. **Un grupo de rutas propio, `/api/administracion/…`,** con `auth:sanctum` y `autorizar:administracion`, como los
   grupos de los otros módulos (ADR-0019, decisión 1). Entra en la prueba de cobertura de la autorización
   (autorizacion-E01) como cualquier ruta, y en las de autorizacion-E02 y E14, que se ajustan para saber que la
   administración tiene módulo en la API (decisión 23).
8. **El componente de autorización exige además la habilidad literal** cuando el rol que pide la ruta es
   `administracion`: el middleware `Autorizar` no decide nada y le pasa el token a `App\Autorizacion\Autorizacion`. Una
   cuenta de otro rol responde 403 (ADR-0019, decisión 2). También responde 403 un token de una cuenta de
   administración que no lleva la habilidad, como los que salieron por Google antes de la decisión 5: no abre ninguna
   ruta de la administración, ni la cuenta ni el cierre de la sesión.
   - **Dónde vive:** en un método aparte, `Autorizacion::permiteEnLaApi(Usuario $usuario, string $rol, ?HasAbilities
     $token)`, que solo llama `Autorizar`, con `$request->user()->currentAccessToken()`. Para el rol `administracion`
     exige `permite()` más `$token instanceof PersonalAccessToken && in_array('administracion', $token->abilities,
     true)`: un `TransientToken`, uno sin la habilidad o ninguno no pasan. Para los otros roles es `permite()` igual que
     hoy.
   - **`permite()` no cambia,** porque no recibe el token y lo usan el panel (`puedeEntrarAlPanel()`,
     `puedeVerLosIndicadores()`, `puedeDescargarReportes()`), el trabajo de la cola `EscribirArchivoDelReporte` y
     `usaLaApp()`, donde no hay token de la app. Si la habilidad se revisara dentro de `permite()`, el panel y la cola se
     quedarían sin acceso.
   - **Las pruebas usan tokens reales,** creados por la pieza de la decisión 3 y mandados con `withToken()`, nunca
     `Sanctum::actingAs()`, que simula `can()` y no la lista de habilidades.
9. **Cada ruta llama a la misma clase de dominio que usa el panel.**
   - Lo que la administración ve sale de los métodos del componente que ya usa el panel: `lavanderiasDelPanel()`,
     `repartidoresDelPanel()`, `plazasDelPanel()`, `usuariosDelPanel()`, `pedidosDelPanel()`,
     `pedidosActivosDelPanel()`, `avisosMasivosDelPanel()` y los `puede…()`. Los nombres se quedan: ahora sirven a la
     app y al panel.
   - Lo que cambia algo lo hace la misma clase que llama el panel. La API no valida ni calcula por su cuenta: usa las
     reglas de esas clases (`PorcentajesDeComision::reglas()`, `ReglasDeLaLavanderia` y las del registro del
     repartidor).
   - **Antes de la primera ruta que las use,** las reglas que hoy viven dentro de Filament se mueven a una clase de
     dominio, sin cambiar lo que hacen, y el panel pasa a llamarla: los nombres operativos del estado
     (`App\Filament\EstadosDelPedido`), el historial de la ficha del usuario (`HistorialDelUsuario`), la creación del aviso
     masivo y sus reglas (`CreateAvisoMasivo` y `AvisoMasivoForm`), la creación de una descarga
     (`DescargarReporte::generar()`), y la validación de las dos ediciones: la de `EditLavanderia::handleRecordUpdate()`
     (qué campos cambiaron y `ReglasDeLaLavanderia::para()` de esos campos) y la de `EditRepartidor::handleRecordUpdate()`
     (textos recortados, el vacío como nulo, las reglas de `RegistroDeRepartidorRequest::reglasDeLosDatos()` de los campos
     que llegan y `CorreoSinCuenta` salvo la propia cuenta), que pasan a `EdicionDeLavanderia` y `EdicionDeRepartidor`.
     Las pruebas del panel siguen pasando sin cambios.
   - Lo que es solo de presentación (el orden y la búsqueda de una lista, el formato de una tabla) lo define cada lado.
   - Un registro que no existe responde 404 (ADR-0019, decisión 3); la administración ve todas las plazas.
10. **El contrato de cada ruta** lo fija `openspec/changes/administracion-en-la-app/design.md`, función por función.

**La app**

11. **Un módulo `administracion` en `movil/app/administracion/`,** con su guarda por rol en `movil/app/_layout.tsx`,
    como `lavanderia/` y `repartidor/`. Es una pila: su inicio lista las secciones en el orden del contrato (Módulo D #1
    a #7), los indicadores y las descargas, y lleva "Cerrar sesión". Cada sección es una pantalla; las fichas de una
    lavandería, un repartidor y un usuario son otra pantalla de la pila. La tabla de pantallas está en el `design.md`.
12. **El monitoreo se consulta cada 30 s** mientras la pantalla está visible y la app al frente, con el gancho de la
    app (`movil/src/estados/useConsultaPeriodica.ts`; ADR-0007). Los pedidos y los marcadores llegan en la misma
    respuesta, así que la lista y el mapa nunca difieren.
13. **Los mapas de la administración son `react-native-maps` sin `provider`** (ADR-0009, decisión 1): Apple en iPhone y
    Google en Android. El monitoreo pone los marcadores que da `MarcadoresDelMonitoreo`; el mapa de calor dibuja los
    agregados del servidor (puntos por colonia, zonas pintadas) con los colores de `App\Indicadores\Intensidad`, sin la
    capa de calor del proveedor (ADR-0009, decisión 3, aplicada a la app). Sin la llave de Android, el mapa no se monta
    y la pantalla lo dice, como hoy (`movil/src/cercanas/mapa.ts`); las cifras siguen en la lista o la tabla.
14. **Los indicadores** van en una pantalla con una pestaña por cada categoría del A.5 (Operaciones, Usuarios, Socios,
    Financiero y Geográfico), con la pieza `Pestanas`, y el rango y la granularidad arriba. Se consulta la categoría
    visible, con el intervalo de `indicadores.refresco` que trae la respuesta (adenda del 2026-11-22 de ADR-0007; 30 s
    por omisión). Las gráficas se dibujan con vistas (alternativa 5, A).
15. **La descarga en CSV y Excel** sale con la misma pieza que la del panel, en segundo plano. La app consulta su
    estado cada 5 s mientras la pantalla está visible, como "Procesando pago" (adenda del 2026-11-22 de ADR-0010), y una
    pantalla "Descargas" lista las de la cuenta mientras viven (7 días; `exportacion`, pregunta 2). Lista, la app baja
    cada formato con su token y lo guarda en la carpeta que elige la administración (alternativa 6, A). La ruta del
    archivo pregunta a `puedeDescargarLaExportacion()` y lo entrega con el mismo descargador de Filament: a otra cuenta
    le responde 404 (exportacion-E11).
    - **Cómo se nombra en la ruta.** Por un `id_publico`, como el resto de la API (ADR-0016, regla 5), nunca por el
      `id` de `exports`:
      - una migración solo hacia adelante, la única de este cambio, agrega a `exports`
        `$tabla->ulid('id_publico')->nullable()->unique()`; opcional, por ADR-0016, regla 9;
      - `App\Exportacion\Exportacion` asigna el ULID con `HasUlids` y `uniqueIds(): ['id_publico']`, **sin cambiar
        `getRouteKeyName()`**: la ruta de descarga de Filament sigue resolviendo por `id` y las pruebas del panel no
        cambian;
      - las rutas `descargas/{descarga:id_publico}` y `descargas/{descarga:id_publico}/{formato}` resuelven por
        `id_publico`; a una que no existe, a un valor que no es ULID y a la de otra cuenta les responden 404;
      - las filas de antes de la migración se quedan sin `id_publico`: la API no las lista y se borran solas a los días
        de vida.
    - **Cuándo está lista y cuándo no se pudo.** Como `completed_at` se marca aunque el trabajo del CSV falle (contexto,
      Filament), el estado sale de los archivos:
      - **generándose:** `completed_at` vacío;
      - **lista:** `completed_at` marcado, y `headers.csv` y el archivo de filas están en el disco de la descarga. Los dos
        formatos se pueden bajar: el XLSX lo arma el descargador desde los CSV si todavía no existe;
      - **no se pudo:** `completed_at` marcado sin alguno de esos dos archivos, por ejemplo porque el trabajo falló o
        porque la cuenta ya no podía bajar reportes.

      Sin columna nueva para el lote: los archivos son lo que el descargador necesita.
16. **Los avisos masivos** se escriben y se listan en la app. La creación y su cola son las mismas del panel
    (administracion-E23). Mientras DD-113 no defina los segmentos, el aviso es global (administracion-E24).
17. **Ni marca ni dominio en el código** (`CLAUDE.md` §5): los textos del módulo no los traen, y la dirección de la
    API sale de la configuración, como en el resto de la app.

**El panel web de Filament**

18. **Se queda como respaldo interno.** Mismas rutas (`/admin`), misma entrada con contraseña y rol de administración,
    mismas pruebas. No se borra.
19. **Nada lo muestra.** La app no lo enlaza: se quitan el botón "Abrir el panel" (`BotonDelPanel`), su aviso y la
    variable `EXPO_PUBLIC_PANEL_URL`. La portada tampoco lo enlaza.
20. **No recibe funciones nuevas del Módulo D ni del A.5:** una función nueva entra primero en la API de la
    administración y en la app. Como las dos llaman a las mismas clases, lo que ya tiene el panel sigue igual.
21. **Lo que no es del Módulo D ni del A.5 se queda en el panel:** el contenido de la portada, los testimonios y los
    logos de prensa (landing-E15 a E19; ADR-0011). C-247 habla del Módulo D y de los indicadores; moverlo a la app sería
    otra decisión. El contrato pide ese contenido en la portada (`NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2`), pero no
    dice quién lo edita; con el panel sin mostrarse, este ADR no lo decide.

    SIN RESPUESTA — bloquea: quién edita los testimonios y los logos de prensa, y por qué vía llega al panel de respaldo
    (DD-141; BR-11).

**Lo que reemplaza**

22. La decisión 31, en su parte "La administración sigue fuera"; la decisión 56; administracion-E25 y su requisito "La
    administración en la app"; el 403 de registro-E33 a la administración, en el inicio de sesión y en las dos rutas;
    la línea de administracion-E26 que dice que la cuenta recibe 403 en la app; la tarea 6.2 de `administracion`, que
    queda en la decisión 5; los Non-Goals que dejaban fuera de la app las consultas y los avisos
    (`openspec/changes/administracion/design.md`), los indicadores (`openspec/changes/indicadores/proposal.md`) y las
    descargas (`openspec/changes/exportacion/proposal.md` y `design.md`); y la nota de la vista reducida en ADR-0003. La
    parte web de C-140 ya la revocó C-247, y con ella la respuesta que C-140 le dio a DD-040.
23. **Los requisitos vigentes que contradecía, con su delta en el cambio** `openspec/changes/administracion-en-la-app/`:
    - de `autorizacion`, "Cada rol entra solo a su módulo" (que decía "Módulo D, en el panel web y en la vista reducida
      de la app") y "Prueba contra fugas en cada cambio" (autorizacion-E14), que ahora sabe que la administración ve
      todas las plazas en su módulo;
    - de `registro`, "Sesión que se conserva sin volver a escribir la contraseña" (el token de administración vence) e
      "Inicio de sesión con Google" (registro-E18 no alcanza a la administración).
24. **Las notas de otros ADR que decían lo contrario** llevan su nota fechada en este mismo cambio: ADR-0003 (el
    contenedor `panel-admin` y la vista reducida) y ADR-0009, decisión 3 (los mapas del monitoreo y del calor en el panel,
    con Google Maps JavaScript).

## Consecuencias

- **Positivas:**
  - Se cumple la Cláusula I al pie de la letra: el panel de administración está dentro de la app única.
  - Las reglas no se duplican: la app y el panel llaman a las mismas clases, con las mismas pruebas.
  - Los mapas nativos no tienen costo por uso (grado E, del contexto de ADR-0009), a diferencia de las cargas del mapa
    web del panel.
  - El token de la administración vence y se distingue de los demás; se cierra el hueco de Google, con o sin Google
    ligado.
  - No entra ninguna librería nueva.
- **Negativas:**
  - Son diecisiete pantallas nuevas en la app y treinta y dos rutas nuevas en la API, encima de lo que ya existe. Lo
    dimensiona el `tasks.md` del cambio: 207 horas sin bloqueo al decidir, 209 desde que entraron los dos conteos del
    inicio de la Propuesta 6 (tarea 10.3), que **no caben** en la ventana que el contrato le da a
    O-21 y O-22, las semanas 12 y 13, del 23 de noviembre al 6 de diciembre (`NSV-2026-001-v8.pdf:5:Cláusula II`). El
    `design.md` del cambio dice el orden en que se hacen.
  - Una migración nueva, `exports.id_publico` (decisión 15).
  - Las pruebas de la autorización (autorizacion-E02 y E14) cambian: hoy fijan que la administración no tiene módulo en
    la API.
  - Leer once indicadores y bajar un Excel en un teléfono es más incómodo que en una pantalla grande; es la razón por la
    que el C2 recomendaba web ([[c2-contenedores]]). Lo decidió Arthur sabiéndolo (C-247). Se atiende con tablas que se
    desplazan, una categoría por pestaña y la carpeta que elige la persona.
  - El panel de respaldo sigue en producción: es otra puerta que cuidar. Tiene su entrada con contraseña y rol, y su
    prueba (autorizacion-E03).
  - La dona y la línea con vistas piden algo de geometría propia.
  - El selector de carpetas y la descarga se prueban en el teléfono: el Android de Arthur y un iPhone (C-240; DD-069).
- **Condiciona:**
  - el cambio `openspec/changes/administracion-en-la-app/`, que escribe los escenarios;
  - las specs de `registro` (la sesión que se conserva, la entrada con Google y registro-E33), de `autorizacion`
    (autorizacion-E02 y E14) y de `administracion` (E25 y E26), que ese cambio modifica; las de `indicadores`,
    `exportacion`, `alta-lavanderias` y `repartidor` no cambian sus reglas: el cambio las lleva a la app;
  - `servidor/app/Autorizacion/Autorizacion.php` (`usaLaApp()` incluye a la administración; `permiteEnLaApi()` y
    `puedeEntrarConProveedorExterno()`), `servidor/app/Http/Middleware/Autorizar.php` y `servidor/routes/api.php`;
  - las pruebas `servidor/tests/Feature/Autorizacion/RolPorModuloTest.php`, `BarridoContraFugasTest.php` y
    `Soporte/LlamadasSinEfecto.php`;
  - DD-040, "¿Dónde vive el Módulo D?": su fila en `vault/meta/deuda-de-decision.md` dice que C-140 la cerró con "panel
    web completo, más una vista reducida"; C-247 (decisión 69) reemplaza esa respuesta en su parte web. La fila se
    actualiza fuera de este cambio;
  - DD-141: quién edita el contenido de la portada en el panel de respaldo (decisión 21);
  - las notas que hablaban de la vista reducida o del botón del panel, que llevan su nota del 2026-11-24 en este mismo
    cambio: [[c2-contenedores]], ADR-0003, ADR-0009 (decisión 3) y la columna "Corre en" del Módulo D y del A.5 en
    `vault/contrato/inventario-funcional.md`.

## Criterio de reversión

- **Las gráficas (alternativa 5):** si la dona o la línea no se leen bien con vistas después de 2 sesiones de trabajo,
  o Arthur las rechaza al verlas en el emulador, se pasa a B, `react-native-svg`, con una adenda de este ADR.
- **El archivo (alternativa 6):** si en el Android de Arthur o en un iPhone el archivo no queda en la carpeta elegida, o
  no se puede abrir desde ahí, se pasa a C, `expo-sharing`, con una adenda.
- **El vencimiento:** se ajusta en configuración, sin código, si una revisión de seguridad o Arthur lo piden.
- **Dónde vive la administración:** solo si Arthur lo vuelve a decidir. El panel sigue entero, así que volver a él no
  pierde nada.

## Costo estimado de revertir

- **Volver al panel web como cara del Módulo D:** de 3 a 5 horas: quitar el grupo de rutas y el módulo de la app, y
  volver a poner el aviso. Las clases de dominio y el panel no cambian. Se pierden las pantallas de la app.
- **Las gráficas a `react-native-svg`:** de 6 a 10 horas, más una compilación nueva de la app; cambian solo las piezas
  de la dona y la línea.
- **El archivo a `expo-sharing`:** de 2 a 4 horas, más una compilación nueva; cambia solo la pieza que guarda.
- **El token:** sin pérdida de datos. Si se quitara el vencimiento, la administración solo volvería a iniciar sesión
  una vez.

## Trazabilidad

- **Obligaciones:** O-12, O-16, O-21 y O-22 (`NSV-2026-001-v8.pdf:3:Cláusula I`, entregable 3, y entregable 4,
  "Incluido en app"; `:19:ANEXO A, Sección A.3`, Módulo D; `:22:ANEXO A, Sección A.5`, los indicadores y la "Descarga de
  reportes en formato CSV y Excel para cualquier rango de fechas"), con sus criterios del A.4.3 y del A.4.4 (`:21`).
- **Restricciones:** RN-14 (los indicadores en tiempo real) y RN-29 (los mapas de Google en Android y de Apple en
  iPhone); la Cláusula X y el control de acceso (`:20:ANEXO A, Sección A.3`).
- **Decisiones:** C-247 y decisión 69 de [[acta-2026-11-20-modelo-y-pasarela]]; reemplaza las decisiones 31 y 56 y la
  parte web de C-140, y con ella la respuesta de C-140 a [[deuda-de-decision|DD-040]]; delegación de C-236 y C-245.
- **Pendientes que abre:** [[DD-141]] (quién edita la portada en el panel de respaldo).
- **Pendientes que no cambia:** DD-053 (el alta de la lavandería), DD-111, DD-113, DD-083, DD-114, DD-115, DD-020,
  DD-013, DD-012, DD-016, DD-019, DD-104 y DD-069 (Apple); los escenarios que bloquean siguen bloqueados en la app.
- **Specs:** `openspec/changes/administracion-en-la-app/`; se apoya en `administracion`, `indicadores`, `exportacion`,
  `alta-de-lavanderias`, `repartidor`, `registro-e-inicio-de-sesion` y `autorizacion-por-rol`.
- **ADRs relacionados:** [[ADR-0003-backend-y-lenguaje]], [[ADR-0005-autenticacion]], [[ADR-0007-tiempo-real]],
  [[ADR-0009-mapas]], [[ADR-0010-pasarela-de-pago]], [[ADR-0013-adaptador-de-tokens]], [[ADR-0016-modelo-de-datos]],
  [[ADR-0017-navegacion-de-la-app]], [[ADR-0018-sesion-y-recuperacion]] y [[ADR-0019-autorizacion]].

## Adenda del 2026-11-26: la administración lee el catálogo de municipios

**Contexto.**

- administracion-app-E14 pide que la administración cambie "su dirección con su municipio" al editar una lavandería, y
  la edición elige el municipio de `GET /api/catalogos/municipios` (`movil/src/direcciones/useMunicipios.ts`), como el
  registro de la lavandería.
- La decisión 6 dejaba ese catálogo para los tres roles de los Módulos A, B y C. Con el 403, la edición mostraba el
  municipio que tenía sin poder cambiarlo (tarea 4.3 de `openspec/changes/administracion-en-la-app/tasks.md`).

**Decisión.**

- **La administración lee `GET /api/catalogos/municipios`**, con su token de administración. Es un catálogo sin datos
  personales: los municipios del territorio con su estado.
- La ruta va en su propio grupo, con `autorizar:usuario,lavanderia,repartidor,administracion`, por el componente de
  autorización (ADR-0019). Un token de administración sin la habilidad no la abre (decisión 8).
- **Las colonias por código postal siguen con los tres roles**: la edición de la lavandería no las pide.
- **Alternativa descartada:** servir el catálogo bajo `/api/administracion` con el mismo controlador. Duplica la ruta y
  obliga al formulario de la lavandería a recibir de dónde salen los municipios.

**Trazabilidad.** Escenarios: administracion-app-E14, registro-E33 y autorizacion-E02 (deltas en
`openspec/changes/administracion-en-la-app/specs/`). Pruebas: `servidor/tests/Feature/Autorizacion/RolPorModuloTest.php`,
`servidor/tests/Feature/Registro/CuentaDeLosRolesDeLaAppTest.php` y
`servidor/tests/Feature/Direcciones/CatalogoTest.php`. Cierra el bloqueo de la tarea 4.3.

**La tomó** la sesión principal del 2026-11-26 por delegación de Arthur Naranjo (C-245).
