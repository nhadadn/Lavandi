## Context

- El contrato nombra cuatro avisos en el Módulo A (numeral 17), pide "el servicio gratuito de Google" (p. 19) y en el criterio de aceptación exige "los cinco momentos definidos del pedido" (A.4.3). El quinto no está definido: DD-011.
- ADR-0008 decidió Firebase Cloud Messaging directo: `laravel-notification-channels/fcm` en la api y `@react-native-firebase/messaging` en la app, con development build. Descartó el servicio de Expo porque no es literalmente el de Google.
- ADR-0007 usa el mismo servicio para adelantar la consulta del seguimiento cuando cambia el estado.
- ADR-0012: un proyecto de Firebase por entorno. En local corre en la cuenta de prueba de Arthur (C-170). En iPhone hace falta la clave de la cuenta de desarrollador de Apple, que espera DD-069, DD-017 y DD-084.
- Si la lavandería y el repartidor reciben avisos es DD-054, abierta.

## Goals / Non-Goals

**Goals:**

- Que los cuatro momentos del contrato lleguen al teléfono del usuario en Android, con un test por momento.
- Que el quinto momento entre sin tocar los otros cuatro.
- Que el aviso nunca sea la única forma de enterarse: sin permiso, el seguimiento sigue por consulta.

**Non-Goals:**

- Las notificaciones masivas del panel (cambio `administracion`) y la preferencia de notificaciones del perfil (cambio `perfil-y-soporte`).
- El correo de confirmación (cambio `resumen-y-confirmacion`, ADR-0014).
- Decidir el quinto momento, los textos de los avisos o si avisan a los socios.

## Decisions

No hay una decisión técnica nueva que no esté en un ADR. Este cambio aplica:

- **ADR-0008:** el envío sale de la api por el canal de FCM de Laravel, y la app recibe con `@react-native-firebase/messaging`. Las pruebas del servidor simulan el servicio; la llegada al teléfono se acredita con capturas en `vault/evidencias/avisos/`.
- **ADR-0008:** los momentos de aviso viven en una sola lista: "se programa el mecanismo y la lista se llena después".
- **ADR-0007:** el mensaje de cambio de estado provoca la consulta inmediata con la app abierta.
- **ADR-0012:** la credencial del servidor y los archivos de Firebase de la app van en variables de entorno y en los secretos de EAS, nunca en el repositorio.
- **`CLAUDE.md` §5 y §9.3:** el texto de cada aviso sale de configuración, y el nombre de la app no se escribe a mano en él.
- **ADR-0016:** los teléfonos registrados viven en `dispositivos` (sección siguiente).

## Decisiones de la implementación del servidor

Las tomó Claude por delegación de Arthur (C-236) al implementar las tareas 0.1, 1.1, 2.1, 3.1 y 3.2, el 2026-11-21, en la rama `feat/avisos-servidor`. Ninguna sale del contrato ni de la spec.

### 1. El canal de FCM es la API HTTP v1 con el cliente HTTP de Laravel, sin `laravel-notification-channels/fcm`

- **Lo que dice ADR-0008:** el envío sale "por FCM, con `laravel-notification-channels/fcm` 6.2.0" (versión grado E, sin verificar).
- **Por qué no se pudo usar (grado B):** `composer require laravel-notification-channels/fcm:6.2.0 --dry-run`, corrido el 2026-11-21 en `servidor/`, no resuelve. El paquete pide `kreait/laravel-firebase` y este, `kreait/firebase-php` 8, y ninguna versión de 8 cabe:
  - 8.0 pide `firebase/php-jwt` ^6, y el servidor ya usa ^7.2 para el inicio de sesión con Google (ADR-0005);
  - 8.1 a 8.3 piden `guzzlehttp/guzzle` ^7.9, y el servidor tiene 8.5.2;
  - 8.4 en adelante piden `lcobucci/jwt` 5.4 o mayor, que exige la extensión `sodium` de PHP. En el PHP local de Arthur está apagada (`;extension=sodium` en `php.ini`), y el hosting sigue abierto (DD-070). Encenderla es un cambio de la máquina, fuera del repositorio.
- **Lo que se hizo:** el canal `App\Avisos\Fcm\CanalDeFcm` llama directo a `POST https://fcm.googleapis.com/v1/projects/{proyecto}/messages:send`. Se autentica con la cuenta de servicio del entorno: firma con su llave un JWT para el alcance `https://www.googleapis.com/auth/firebase.messaging`, con `firebase/php-jwt`, que ya estaba instalado, y lo cambia por un token de acceso de OAuth en el `token_uri` de la cuenta. El token de acceso se guarda en la caché hasta cinco minutos antes de vencer. **No se agregó ninguna dependencia.**
- **Lo que se conserva de ADR-0008:** FCM directo, sin intermediarios (RN-30), y el envío encapsulado en un solo lugar. Revertir al paquete cuesta poco: se cambia el canal y no se tocan los oyentes, el envío en cola ni la tabla.
- **Pendiente para Arthur:** asentar en ADR-0008 que el canal es la API HTTP v1 sin el paquete, o encender `sodium` y volver al paquete.

### 2. El envío va en cola y un fallo nunca detiene el pedido

- Cierra la primera pregunta abierta ("por cola o en la misma petición") **en cola,** igual que el correo de confirmación (`App\Avisos\CorreoDeConfirmacion`, ADR-0014).
- `App\Avisos\AvisarAlUsuario` cuelga de `PedidoConfirmado` y de `EstadoDelPedidoCambiado`. Los dos se entregan ya guardado el cambio (`ShouldDispatchAfterCommit`), y por cada teléfono de la cuenta dueña pone en cola un `App\Avisos\MandarAvisoAlTelefono`.
- **Al correr, el envío vuelve a preguntar a `App\Autorizacion\Autorizacion`** si el teléfono todavía recibe los avisos del pedido. Si se cerró la sesión, se borró el token o el teléfono pasó a otra cuenta mientras esperaba, no sale nada (avisos-E05 y E21).
- **Un fallo de FCM se queda en el envío** (avisos-E02):
  - queda como advertencia en la bitácora del servidor, con el pedido, el estado, el teléfono por su llave, el intento y el motivo. El token del teléfono nunca se escribe en la bitácora;
  - si puede pasar solo (sin conexión, 5xx, 429 o 401), el envío vuelve a la cola con las esperas de `avisos.esperas`: 60 s, 5 min, 15 min y 1 h. Son cinco intentos, y después queda en `failed_jobs`;
  - sin la cuenta de servicio (DD-072), con la llave rota o con un mensaje que FCM rechaza, no se reintenta.
- Como el envío nunca lanza la falla, el cambio de estado no se detiene aunque la cola corra en la misma petición (`QUEUE_CONNECTION=sync`). En el hosting, la cola necesita un proceso `php artisan queue:work`, el mismo que ya necesita el correo (DD-070).

### 3. Un solo mensaje por cambio de estado

- Cierra la segunda pregunta abierta con la lectura de este diseño. `App\Avisos\MensajeDelPedido` arma un mensaje por cambio, y todos llevan en `data` el pedido y su estado nuevo (avisos-E14):
  - si el estado está en la lista de momentos, es además el aviso visible, con la parte de Android (`android.notification`) y la de iPhone (`apns.payload.aps.alert`), cada una con su título y su texto (avisos-E16);
  - si no, lleva solo los datos: el teléfono no muestra nada, y en iPhone `content-available` despierta a la app.
- Conviene asentarlo en ADR-0008, como ya pedía la pregunta.

### 4. La lista de momentos y sus textos viven en `config/avisos.php`

- `avisos.momentos` es la única lista, por el código del estado que dispara cada momento: `confirmado`, `recolectado`, `listo` y `entregado`. El quinto entra ahí cuando se defina (DD-011, avisos-E13), sin tocar los otros cuatro.
- Los textos son provisionales, en español y neutros, con `:folio` para el folio del pedido. Los escribe y aprueba EL CLIENTE (BR-24). No llevan marca ni dominio.

### 5. Qué hace que la API borre un token

- **Al cerrar la sesión:** la app manda `token_fcm` en el cuerpo de `DELETE /api/sesion`. La API borra ese teléfono solo si es de la cuenta de la sesión, y cierra la sesión en la misma transacción.
- **Al borrar la cuenta:** se borran todos sus teléfonos, porque se cierran todas sus sesiones (`App\Identidad\BorradoDeCuenta`).
- **Cuando FCM lo rechaza** (avisos-E06): con `UNREGISTERED`, con `SENDER_ID_MISMATCH` o con `INVALID_ARGUMENT` cuando el mensaje de Google dice que el token no es válido. Un `INVALID_ARGUMENT` por otra cosa, como un mensaje mal armado, no lo borra.

### 6. El evento `EstadoDelPedidoCambiado` lo publican los botones

- `App\Pedidos\EstadoDelPedidoCambiado(Pedido $pedido, PedidoEvento $evento)` nace aquí, porque la tarea 3.2 lo necesita y los botones de la lavandería todavía no existen.
- **La tarea 2.1 de `estados-del-pedido` lo publica en la misma transacción** en que guarda el estado y su evento: `EstadoDelPedidoCambiado::dispatch($pedido, $evento)`. Después, los botones del repartidor hacen lo mismo.
- Mientras tanto, las pruebas lo publican igual que lo harán ellos (`servidor/tests/Feature/Avisos/Soporte/CambiosDeEstadoDePrueba.php`).
- La confirmación por el pago no lo publica: ya tiene `PedidoConfirmado`.

## Decisiones de la implementación de la app

Las tomó Claude por delegación de Arthur (C-236) al implementar las tareas 4.1 a 4.3, el 2026-11-22, en la rama
`feat/avisos-y-publicacion-movil`. Ninguna sale del contrato ni de la spec.

### 1. Firebase entra a la compilación solo con su archivo, y solo en Android

- **La llave es el archivo.** `app.config.ts` lee `GOOGLE_SERVICES_JSON`, la ruta al `google-services.json` del proyecto
  de Firebase del entorno (ADR-0012): en local, una ruta fuera del repositorio en el `.env`; en la nube, la variable de
  archivo de EAS. Solo con ella entran `android.googleServicesFile`, el permiso `POST_NOTIFICATIONS` y
  `extra.firebaseEnAndroid: true`. Sin ella, la configuración sale como antes y sin avisos en la salida de Expo
  (avisos-E03, `movil/__tests__/avisos/telefono.test.ts`). Si la variable apunta a un archivo que no existe, la
  configuración falla y lo dice: no se compila en silencio sin avisos.
- **No se lista el complemento de `@react-native-firebase/app`.** En Android hace exactamente lo que Expo ya hace con
  `android.googleServicesFile`: copiar el archivo a `android/app/` y aplicar el complemento de Gradle de Google. Su parte
  de iPhone exige el `GoogleService-Info.plist` y agrega `FirebaseApp.configure()`, y con el archivo de Android solo
  rompería una compilación de iPhone. Entra con la tarea 5.2.
- **Tampoco el de `@react-native-firebase/messaging`.** Solo agrega el ícono y el color del aviso en Android, y sin ícono
  deja una advertencia. Sin él, el aviso lleva el ícono de la app. **Pendiente:** el ícono de aviso, una silueta blanca,
  cuando exista el ícono de la marca (`vault/marca/visual/icono-app/`).
- **Los módulos nativos se enlazan solo si la compilación trae el archivo** (`movil/react-native.config.js` y
  `movil/firebase-nativo.js`). Android los enlaza si su proyecto nativo tiene `android/app/google-services.json`, el que
  copia Expo; se decide por el archivo y no por la variable porque el enlace corre dentro de Gradle, que no lee el `.env`.
  Sin el archivo, el APK sale sin el código nativo de Firebase, que sin configuración cerraría la app en cuanto alguien
  pidiera la mensajería (`getConstants()` llama a `FirebaseMessaging.getInstance()`). iPhone no los enlaza todavía: desde
  la versión 27, Firebase en iPhone solo se instala con Swift Package Manager y frameworks dinámicos, y sin eso fallaría
  la instalación de los pods. Eso también es de la tarea 5.2.
- **Versión:** `@react-native-firebase/app` y `@react-native-firebase/messaging` 27.1.1, exactas, como dice ADR-0008
  (grado E). La 27.1.1 salió el 2026-11-10 (`npm view`, 2026-11-22). Trae el SDK web de Firebase, que pide Node 24.12 o
  mayor: con el Node 22 de la integración continua, `npm ci` lo advierte y no falla. La app no lo usa en el teléfono.

### 2. La mensajería se carga diferida, como Google

`src/avisos/firebase.ts` es el único lugar que la carga, con `require` y solo si `avisosDisponibles()`: fuera de Expo Go
(`enExpoGo()`, C-240), en Android y con `extra.firebaseEnAndroid`. En Expo Go, en iPhone o en un APK sin el archivo, la
app no la pide y sigue sin avisos (base-E11, `movil/__tests__/base/expo-go.test.tsx`).

### 3. Cuándo se registra el teléfono

- **Cada vez que se abre la sesión de una cuenta de usuario:** al iniciarla y al abrir la app con ella. El registro es
  idempotente (204) y así se repone un token que la api borró o que no llegó por falta de conexión.
- **Solo con el permiso concedido** (avisos-E03), y cada vez que Firebase renueva el token.
- **Solo la cuenta de usuario.** La api solo registra teléfonos de usuarios, y los avisos a los socios esperan DD-054.
- **Si algo falla,** Firebase sin token o la api sin conexión, la app sigue sin error y lo vuelve a intentar en la
  siguiente apertura o renovación.

### 4. Al cerrar la sesión, la app manda el token que registró

El contrato de la api ya lo pedía (avisos-E05): `DELETE /api/sesion` lleva `{ "token_fcm": … }` con el token que la app
registró con esa sesión, y sin cuerpo si no registró ninguno. La app deja de escuchar las renovaciones del token.

### 5. El manejador de segundo plano y la entrada propia

`@react-native-firebase/messaging` pide registrar el manejador de los mensajes con la app al fondo o cerrada fuera de las
pantallas y antes de montarlas. Por eso la entrada de la app pasa de `expo-router/entry` a `movil/index.ts`, que importa
primero `src/avisos/arranque.ts` y después `expo-router/entry`, la forma que documenta expo-router. La prueba de ADR-0017
se ajustó a eso. El manejador no hace nada: el sistema muestra solo el aviso visible de un momento, y al volver la app al
frente el seguimiento consulta de inmediato (ADR-0007).

### 6. Dónde y cuándo se pide el permiso (cierra la pregunta abierta de la pantalla)

- **Al abrirse la sesión de una cuenta de usuario que nunca respondió,** con la petición del sistema y sin pantalla
  propia (avisos-E07, `src/avisos/permiso.ts`). Es lo mínimo que cumple el escenario. La pantalla "Activa los avisos" del
  mock A17 es una explicación previa que el contrato no pide; si se quiere, entra como escenario nuevo.
- **No choca con el permiso de ubicación:** la pantalla principal no lo pide sola, solo al tocar "Continuar"
  (cercanas-E03), y Android no deja tocar nada con la petición de avisos abierta.
- **Una sola vez.** Android no distingue "nunca respondió" de "lo negó", así que la app anota en el almacenamiento del
  teléfono, cuando la persona contesta, que ya le mostró la petición (`avisos.permiso-pedido`); si la petición no llegó a
  mostrarse, sigue sin responder. Android borra la nota al desinstalar. Si la persona la niega, la app no la vuelve a
  mostrar sola: la sección de notificaciones del perfil ofrece activarlos (perfil-usuario-E08).
- **Android 12 o anterior no tiene petición:** los avisos vienen permitidos, y solo se apagan en los ajustes.
- **Android pide con `PermissionsAndroid`** de React Native, sin dependencias nuevas, porque
  `requestPermission()` de `@react-native-firebase/messaging` contesta que sí en Android sin preguntar. **iPhone pide con
  la librería de Firebase:** `requestPermission()` y `hasPermission()`, que la versión 27 marca como obsoletos a favor
  de `expo-notifications` o `react-native-permissions`. No se agregó ninguno de los dos: serían una dependencia nativa
  más solo para el permiso, y `expo-notifications` trae además su propio servicio de mensajes en Android, que compite
  con el de Firebase por los mismos mensajes. Ese camino de iPhone corre con la tarea 5.2.
- **Si la petición falla,** la app sigue sin avisos y sin error (avisos-E08).

### 7. El mensaje de cambio de estado adelanta la consulta del seguimiento abierto

- Con la sesión del usuario, la app escucha los mensajes que llegan con ella al frente (`onMessage`). El que trae
  `data.tipo = "cambio_de_estado"` se entrega al seguimiento abierto de ese pedido (`src/avisos/cambiosDeEstado.ts`), que
  consulta `GET /api/pedidos/{id_publico}` de inmediato con el "consultar de nuevo" de la consulta periódica, y vuelve a
  contar los 30 segundos desde ahí (avisos-E15). El aviso visible de un momento trae los mismos datos y hace lo mismo.
- La app no toma el estado del mensaje: lo pide a la api, que es la que lo sabe. Un mensaje de otro pedido, o que no es
  de cambio de estado, no hace nada.
- Con la app al frente, Android no muestra el aviso visible de un momento: lo entrega a la app. El seguimiento abierto
  lo refleja al instante; mostrar además un aviso dentro de la app no lo pide ningún escenario.

### Lo que no se comprobó en un teléfono

- La captura del aviso en Android (avisos-E17) espera las llaves de Firebase (DD-072).
- El diálogo real del permiso en Android 13 o superior: con el APK sin el archivo de Firebase no se pide, porque sin
  avisos no hay qué permitir.

## Contrato de la API para la app

Las dos rutas llevan sesión y pasan por el componente de autorización (`auth:sanctum` y `autorizar:usuario`):

| Respuesta | Cuándo |
|---|---|
| `401 { "message": "Unauthenticated." }` | Sin sesión |
| `403 { "message": "Esta cuenta no tiene acceso." }` | La cuenta no tiene el rol de usuario |

### Registrar o renovar el token: `POST /api/dispositivos`

La app la llama al iniciar sesión, si el usuario concedió el permiso de avisos, y cada vez que Firebase le renueva el token (avisos-E03 y E04).

```
Authorization: Bearer <token de la sesión>

{ "token_fcm": "<token que da Firebase al teléfono>", "plataforma": "android" }
```

- `plataforma` es `"ios"` o `"android"`.

| Respuesta | Qué significa |
|---|---|
| `204` sin cuerpo | El token queda ligado a la cuenta de la sesión, con su plataforma y su último uso. Si ya estaba, de esta cuenta o de otra, pasa a esta, y los avisos de la otra ya no le llegan (avisos-E21). La respuesta es la misma en los tres casos |
| `422 { "message": "...", "errors": { "token_fcm": ["Falta el token del teléfono."] } }` | Falta el token o no es texto, pasa de 4096 caracteres, o la plataforma falta o no es `ios` ni `android`. No se guarda nada |

Mensajes de los campos:
- `token_fcm`: "Falta el token del teléfono.", "El token del teléfono va en texto." y "El token del teléfono es demasiado largo.";
- `plataforma`: "Falta la plataforma del teléfono.", "La plataforma va en texto." y "La plataforma es ios o android.".

### Borrar el token al cerrar la sesión: `DELETE /api/sesion`

Es la ruta que ya cierra la sesión (registro-E12), con un cuerpo opcional (avisos-E05).

```
Authorization: Bearer <token de la sesión>

{ "token_fcm": "<token que da Firebase al teléfono>" }      opcional
```

| Respuesta | Qué significa |
|---|---|
| `204` sin cuerpo | La sesión queda cerrada. Si el token es de un teléfono de la cuenta, se borra. Si es de otra cuenta o no está registrado, no se toca nada y la respuesta es la misma. Sin el token, solo se cierra la sesión: es el caso del teléfono sin permiso de avisos |
| `422 { "message": "...", "errors": { "token_fcm": ["El token del teléfono va en texto."] } }` | El token no es texto o pasa de 4096 caracteres. La sesión sigue abierta |

La app manda el token **antes** de olvidar la sesión. Si no lo manda, el teléfono sigue registrado y le siguen llegando los avisos de la cuenta hasta que otra cuenta registre ese token o FCM lo rechace.

### Lo que recibe la app por FCM

Todo mensaje trae en `data`, con los tres valores en texto:

```
{ "tipo": "cambio_de_estado", "pedido": "<id_publico del pedido>", "estado": "<código del estado nuevo>" }
```

- `estado` es el código de `estados_pedido` (`confirmado`, `recolectado`, `en_proceso`, `listo` y, cuando se siembre, `en_entrega`). Es el mismo `estado.codigo` de `GET /api/pedidos/{id_publico}`.
- **En un momento** (confirmación, recolección, listo y entregado), el mensaje trae además el aviso visible: el título y el texto en `android.notification` y en `apns.payload.aps.alert`. Con la app cerrada o en segundo plano, el sistema lo muestra (avisos-E17).
- **En otro cambio,** solo trae `data`. Con la app abierta en el seguimiento de ese pedido, la app consulta `GET /api/pedidos/{id_publico}` de inmediato (avisos-E15).

## Tablas y migración

Fuente: `vault/arquitectura/modelo-de-datos.md` ("Nace en") y ADR-0016. Este cambio **crea `dispositivos`**.

**`dispositivos`** (la crea este cambio). Columnas, como en el modelo: `id_dispositivos`, `id_usuarios`, `token_fcm`, `plataforma`, `ultimo_uso_en`.

| Columna | Tipo y regla |
|---|---|
| `id_dispositivos` | `bigint` autoincremental, llave primaria |
| `id_usuarios` | Foránea → `usuarios.id_usuarios`, con índice y `ON DELETE RESTRICT` |
| `token_fcm` | Texto, **único**: un teléfono es de una sola cuenta a la vez (avisos-E21) |
| `plataforma` | `CHECK (plataforma IN ('ios', 'android'))`; la manda la app junto con el token |
| `ultimo_uso_en` | `timestamptz`: la última vez que la app registró o renovó el token |

- **Sin `id_plazas`:** está en la lista cerrada de tablas sin plaza de `modelo-de-datos` (`servidor/config/modelo.php`). El teléfono es de la cuenta, y la cuenta no pertenece a una plaza.
- **Sin `id_publico`:** la api no expone la fila; la app registra y borra por el token mismo.
- **Sin borrado suave:** un token que ya no sirve se borra (avisos-E05, E06); no es historial. Registrar un token que ya existe lo pasa a la cuenta que inicia sesión y actualiza `ultimo_uso_en` (avisos-E21).
- **Sin trigger:** `impedir_cambio()` es solo para `pedidos`, `pedido_eventos`, `pagos` y `reembolsos`.
- **Prueba de esquema:** la migración pasa la de `modelo-de-datos` (modelo-E01 a modelo-E17).

**Tablas de otros cambios que este usa:**

| Tabla | Qué hace aquí | Nace en |
|---|---|---|
| `usuarios` | La cuenta dueña de cada teléfono | `registro-e-inicio-de-sesion` |
| `pedidos` | El dueño del pedido (`id_usuarios`) y el momento de confirmación: `confirmado_en`, que con C-188 se llena cuando la pasarela confirma el pago, y no al aceptar el resumen (avisos-E09) | `resumen-y-confirmacion` |
| `pedido_eventos`, `estados_pedido` | Los cambios de estado que disparan los momentos y el mensaje que adelanta la consulta (avisos-E10 a E12, E14) | `resumen-y-confirmacion`; `estados-del-pedido` |
| `lavanderias` | Solo si DD-054 se aprueba: la cuenta de la lavandería del pedido (avisos-E19) | `perfil-de-lavanderia`; `alta-de-lavanderias` |
| `repartidores`, `pedido_asignaciones` | Solo si DD-054 se aprueba: el repartidor del pedido asignado (avisos-E20) | `repartidor`, **posterior en el plan** |

## Risks / Trade-offs

- **[iPhone sin cuenta de Apple]** → Sin la clave de Apple, FCM no llega al iPhone. Se construye y se acredita primero en Android. Para que la cuenta del cliente llegue a tiempo, el D-U-N-S se pide a más tardar el 2027-01-11 (DD-084).
- **[A43-5 no se puede acreditar completo]** → Con cuatro momentos de cinco, el criterio queda en `implementado` parcial hasta que DD-011 defina el quinto.
- **[El usuario niega el permiso]** → Con la app abierta, la consulta cada 30 segundos cumple; con la app cerrada, ve el estado al volver (ADR-0007).
- **[El servicio de avisos no garantiza la entrega]** → Ningún paso del pedido depende solo del aviso (ADR-0008); un fallo queda en la bitácora y el pedido sigue (avisos-E02).
- **[Textos provisionales]** → Los textos de cada aviso no están definidos (BR-24). Se construyen como configuración y se cambian sin tocar código.
- **[`pedidos-de-la-lavanderia` necesita `dispositivos` antes que este cambio]** → Su aviso de pedido nuevo (E15, E16) está bloqueado por DD-054 y, además, espera esta tabla, que en el plan nace después. No se mueve aquí.

## Open Questions

- **Mandar los avisos por cola o en la misma petición: necesita ADR.** Mandarlos en la petición hace más lento el botón de la lavandería; una cola necesita un proceso más en el hosting, y DD-070 sigue abierta (un cPanel compartido no lo corre bien, según `arquitectura/verificaciones-fase-6.md`). La decide Arthur.
  - **Resuelta en la implementación del servidor, por delegación (C-236): en cola** ("Decisiones de la implementación del servidor", punto 2). El envío nunca detiene el pedido, aun con `QUEUE_CONNECTION=sync`. Falta asentarla en un ADR, junto con el canal sin el paquete (punto 1).
- **El mensaje de cambio de estado sin aviso visible.** ADR-0008 separa los avisos "al usuario, en los momentos" del mensaje "al cambiar de estado, para el refresco". Este cambio lee que, en los cambios que no son momento, el mensaje no se muestra como aviso. Conviene asentarlo en ADR-0008.
  - **Implementada así** (punto 3): un solo mensaje por cambio, visible solo en los momentos. Sigue faltando asentarlo en ADR-0008.
- **En qué pantalla se pide el permiso.** El mock A17 lo explica con una pantalla propia; el contrato no lo dice. Es decisión de pantalla de Arthur.
  - **Resuelta en la implementación de la app, por delegación (C-236):** sin pantalla propia, con la petición del
    sistema al abrirse la sesión del usuario que nunca respondió ("Decisiones de la implementación de la app", punto 6).
- **Quién escribe y aprueba los textos de los avisos** (BR-24): fila propuesta en el reporte.
