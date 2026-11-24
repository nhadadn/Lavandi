## Context

- Es el segundo cambio del hito H7 y cierra el Módulo A. Usa la sesión de `registro-e-inicio-de-sesion`, las
  direcciones de `direcciones` y el componente de autorización de `autorizacion-por-rol` (`CLAUDE.md` §9.5).
- Lo sostienen ADRs vigentes: la foto, ADR-0006 (bucket privado, URL firmada, compresión en el teléfono); el
  permiso de avisos, ADR-0008; la tarjeta guardada, ADR-0010, punto 5; el borrado de la cuenta, ADR-0005; las tablas,
  ADR-0016 y `vault/arquitectura/modelo-de-datos.md` (C-193).
- La pasarela es Conekta mientras el cliente no firme el cambio (DD-079, C-192, C-216). Este cambio no nombra
  proveedor y no integra ninguno: la integración es del cambio `pago`.

## Goals / Non-Goals

**Goals:**

- Dejar implementables, sin respuestas pendientes, perfil-usuario-E01 a E06, E08 a E13 y E15.

**Non-Goals:**

- Lo que bloquean DD-055 (E07), BR-25 (E14) y BR-09 (E16).
- Clasificar el reporte por tema y los interruptores por tipo de aviso que muestran los mocks: no tienen cita.
- Cambiar el correo de la cuenta: el numeral 15 no lo lista.
- Ningún componente nuevo de avisos: el perfil solo lee el estado del permiso.

## Decisions

Ninguna decisión técnica nueva, fuera de las decisiones 25 y 27 de `acta-2026-11-20-modelo-y-pasarela` (Open
Questions). Las piezas siguen a sus ADRs:

1. **Foto (ADR-0006).** Se guarda con el `Storage` de Laravel en el bucket privado del entorno y se sirve con URL
   firmada. La app la comprime antes de subirla. El tamaño máximo y la calidad quedan como configuración: el ADR los
   deja a la spec y aquí no se fija un número.
   - **Lo que hizo la tarea 1.2** (Claude por delegación de Arthur, C-236). Usa el mismo almacenamiento que las fotos
     de las lavanderías, el de `servidor/config/fotos.php`: `fotos_s3`, el bucket S3 privado del entorno, en
     producción, y `fotos_local`, el disco local privado que solo sirve con firma, en los demás
     (`alta-de-lavanderias/design.md`, "La foto"). La URL firmada sale de `App\Fotos\AlmacenDeFotos`, con la misma forma
     que la de la lavandería, `{ url, vence_en }`.
   - **Los mismos límites que la foto de la lavandería,** escritos una sola vez en `App\Fotos\ReglasDeLaFoto`: los tipos
     de `FOTOS_TIPOS` (`jpg,jpeg,png,webp`) y el peso de `FOTOS_PESO_MAXIMO_KB` (5120 KB por omisión, provisional: lo
     fija Arthur). No se abrió una variable aparte para el perfil: ningún escenario pide un máximo distinto.
   - **En la base, solo la ruta,** en `usuarios.foto_ruta`, que ya existía (`modelo-de-datos.md`): no hubo migración. El
     archivo va a `usuarios/{id_publico}/` y la ruta nunca sale como campo; `foto_ruta` quedó oculta en el modelo, como
     la contraseña.
   - **Una foto por cuenta** (`App\Identidad\FotoDePerfil`): la nueva reemplaza a la anterior y su archivo se borra
     cuando la nueva ya quedó guardada. La validación corre antes de guardar nada: un archivo rechazado no llega al
     almacenamiento y la anterior sigue (E04).
2. **Borrado de la cuenta (ADR-0005).** Revoca todos los tokens de Sanctum de la cuenta y deja de aceptar su inicio de
   sesión por cualquiera de los tres caminos. Qué pasa con los datos queda en E16.
   - **La opción mínima que tomó E15, sin decidir DD-109** (tarea 1.5, Claude por delegación de Arthur, C-236). La
     cuenta se borra en suave, con `usuarios.deleted_at`, que ya existía (`modelo-de-datos.md`, "Borrado suave" en
     cuentas; ADR-0016, regla 6). En una transacción se revocan todos sus tokens y se marca borrada
     (`App\Identidad\BorradoDeCuenta`). No hizo falta código nuevo para el rechazo: el inicio de sesión con correo no
     encuentra cuentas borradas (registro-E08), la entrada con Google ya rechazaba la cuenta borrada sin crear otra
     (registro-E16), y el broker de la recuperación tampoco la encuentra, así que no le manda enlace.
   - **Lo que no se tocó, a propósito:** los datos quedan como están. El nombre, el correo, el teléfono, la foto, las
     direcciones, los pedidos y los reportes siguen en la base, y un pedido en curso sigue su curso. El
     correo sigue ocupado, porque el único de `lower(correo)` incluye las cuentas borradas (registro-E02). Todo eso es
     DD-109 (perfil-usuario-E16): cuando responda, se borra, se anonimiza o se conserva en una tarea y una migración
     hacia adelante, sin rehacer E15. El borrado suave no rompe ninguna llave: las de pedidos, direcciones y reportes
     son `ON DELETE RESTRICT`, y la fila de `usuarios` sigue ahí.
   - **Apple:** la entrada con Apple no existe todavía (DD-069). Cuando llegue, rechaza la cuenta borrada como las
     otras dos.
3. **Reporte.** Se guarda en `api`, en la tabla `reportes_soporte`. Quien lo reciba al final, la administración o un
   correo de EL CLIENTE (E14), lo leerá de ahí; guardarlo es necesario con cualquiera de las dos respuestas.
   - La plaza sigue la decisión 27 (Open Questions) y la resuelve `App\Soporte\RecepcionDeReporte`. Qué pedidos y qué
     direcciones son de la cuenta lo dice el componente único de autorización (ADR-0019): un pedido ajeno responde 404,
     igual que uno que no existe. Sin ninguna plaza, el reporte se guarda sin ella (E17), y del componente solo lo
     recibe la administración (`Autorizacion::reportesDeSoporteDelPanel()`), que es una para todas las plazas.
   - La descripción se topa en 5000 caracteres para proteger la tabla. La spec no fija un tope; si alguien lo quiere
     distinto, es configuración de la validación, no un cambio de esquema (`mensaje` es `text`).
4. **Notificaciones.** Lectura mínima del numeral 15: el perfil muestra el estado del permiso de avisos y lleva a
   activarlo. Si Arthur o el cliente leen otra cosa, cambia E08.
   - **Lo que hizo la tarea 2.3** (Claude por delegación de Arthur, C-236), con el permiso del cambio `avisos`
     (`movil/src/avisos/permiso.ts`; `avisos/design.md`, "Decisiones de la implementación de la app", punto 6). La
     sección "Notificaciones" (`movil/src/perfil-usuario/Notificaciones.tsx`) va después de las direcciones guardadas,
     en el orden del numeral 15, y no depende de que llegue el perfil.
   - **Tres estados:** activos, sin botón; apagados y la persona nunca respondió, con "Activar avisos", que muestra la
     petición del sistema; apagados porque ya la negó o los apagó en el teléfono, con "Abrir ajustes", que abre los
     ajustes de la app en el sistema. En Android 12 o anterior no hay petición: si están apagados, solo los ajustes.
   - **Al volver al frente,** como al regresar de los ajustes, lee otra vez el permiso; y se entera de la respuesta a la
     petición que sale al abrirse la sesión. Si los avisos quedan activos, el teléfono se registra en la api
     (avisos-E03).
   - **Sin avisos en la compilación no aparece:** Expo Go, iPhone hasta avisos-E18 (DD-069) o un APK sin el archivo de
     Firebase (DD-072). No hay qué activar, y es lo mismo que hace la entrada con Google en Expo Go (base-E11).
   - **No usa ninguno de los seis componentes del design system,** como las otras secciones del perfil: se arma con el
     título de sección, el botón y los tokens.
5. **El número de soporte.** Vive en `servidor/config/soporte.php`, que lee la variable `SOPORTE_WHATSAPP` del `.env`
   del entorno, vacía en `.env.example` (decisión 27). La API lo entrega solo con sus dígitos, para que la app arme el
   enlace de WhatsApp aunque la configuración lo escriba con `+` o espacios; sin dígitos, sale `null` (E10). El número
   real es DD-082, de EL CLIENTE.

## Tablas y migración

Las tablas siguen `vault/arquitectura/modelo-de-datos.md` y ADR-0016 (aprobados en C-193). Es una sola migración,
hacia adelante, y pasa la prueba de esquema de `modelo-de-datos` (modelo-E01 a modelo-E17). Ninguna tabla de este
cambio lleva el trigger `impedir_cambio()`, que es solo de `pedidos`, `pedido_eventos`, `pagos` y `reembolsos`.

**Crea `reportes_soporte`** (perfil-usuario-E11 a E14), tarea 0.1:

- Columnas: `id_reportes_soporte`, `id_usuarios`, `id_pedidos`, `id_plazas`, `mensaje`, `estado`, `atendido_en`.
- Llave primaria: `id_reportes_soporte`, `bigint` autoincremental.
- Llaves foráneas, cada una con su índice y `ON DELETE RESTRICT`:
  - `id_usuarios` → `usuarios.id_usuarios`;
  - `(id_pedidos, id_plazas)` → `pedidos(id_pedidos, id_plazas)`, compuesta con la plaza, para que el reporte no
    difiera de la plaza de su pedido;
  - `id_plazas` → `plazas.id_plazas`.
- `id_plazas` nació **no nula**, por la decisión 27 (Open Questions). La migración
  `2026_11_26_200000_admitir_reportes_soporte_sin_plaza`, hacia adelante, la deja **opcional**: vacía, el reporte es
  de una cuenta sin pedidos ni direcciones con plaza, y se guarda igual (E17). La tabla ya estaba en la lista
  `plaza_opcional` de `servidor/config/modelo.php` (plazas-E09). El `CHECK` `reportes_soporte_pedido_con_plaza_check`
  no deja un reporte con pedido y sin plaza: con la plaza vacía, PostgreSQL no revisa la llave compuesta
  (`MATCH SIMPLE`). Sin pedido, la de `id_plazas` se revisa cuando trae valor.
- `id_pedidos` es opcional: el pedido va "si él quiere" (E11). La descripción del formulario se guarda en `mensaje`.
- `atendido_en` es `timestamptz` y nace vacío.
- Lleva `created_at` y `updated_at` en `timestamptz`, como todas las tablas del negocio con llave propia. La fecha del
  reporte (E11) es `created_at`.
- `estado`: el modelo no fija sus valores; dependen de cómo se atiende el reporte (DD-108, E14). La migración lo crea
  con un `CHECK` de un solo valor inicial (propuesta: `recibido`), y los demás entran con DD-108, en una migración
  hacia adelante (ADR-0016, regla 9).
- Sin únicos: una persona puede mandar varios reportes.

**Solo usa** tablas que crean otros cambios:

| Tabla | Para qué | La crea |
|---|---|---|
| `usuarios` | Cambiar `nombre` y `foto_ruta` (E01 a E04); borrar la cuenta con el borrado suave `deleted_at` (E15) | `registro-e-inicio-de-sesion` |
| `cuentas_externas` | Rechazar la entrada con Google o Apple de una cuenta borrada (E15) | `registro-e-inicio-de-sesion` |
| `personal_access_tokens` | Revocar los tokens de Sanctum al borrar la cuenta (E15). Es del framework | `base-tecnica` |
| `direcciones` | Mostrar solo las direcciones del usuario (E05), sin escribirlas | `direcciones` |
| `pedidos` | Ligar el reporte a un pedido propio y rechazar uno ajeno (E11, E13), sin escribirlo | `resumen-y-confirmacion` |

**En espera (⛔):** `metodos_pago_guardados` (E07) solo nace si DD-055 dice que sí. Es la tarea 3.0, bloqueada. Sus
columnas, según el modelo y C-192, serían `id_metodos_pago_guardados`, `id_usuarios`, `pasarela`, `id_pasarela_cliente`,
`id_pasarela_fuente`, `marca`, `ultimos_4` y `deleted_at`: solo la referencia que da la pasarela, nunca un dato de
tarjeta (modelo-E13). No lleva plaza: está en la lista de tablas sin plaza de `servidor/config/modelo.php`. El
`CHECK` de `pasarela` admite `conekta` mientras no se firme el cambio; la elegida, si se firma (DD-079).

## Risks / Trade-offs

- [El número de WhatsApp llega después de enviar la app a las tiendas (DD-082)] → vive en la configuración del
  servidor (decisión 27): cambiarlo no exige otra versión de la app.
- [Las tiendas piden algo más al borrar una cuenta] → ver Open Questions; grado E, sin verificar.
- [`modelo-de-datos.md` dice que la plaza de `reportes_soporte` queda "vacía si el reporte no es de un pedido"] → con
  E17 queda vacía solo si la cuenta no tiene de dónde tomarla; sin pedido, sigue tomando la del último pedido o la de
  la dirección más reciente. El vault lo pone al día quien lo cuida.

## Open Questions

- **Dónde vive el número de WhatsApp: resuelta.** En la configuración del servidor, que la app lee al abrir Soporte,
  para que DD-082 pueda llegar tarde sin publicar otra versión. Lo decidió Claude por delegación de Arthur (C-236): la
  decisión 25 de `vault/meta/actas/acta-2026-11-20-modelo-y-pasarela.md` acepta las ocho recomendaciones de DD-135, y
  la 27 precisa su punto 7. La tarea 1.3 se queda: `servidor/config/soporte.php`, con la variable `SOPORTE_WHATSAPP`,
  y `null` si no existe (E10).
- **La plaza de un reporte sin pedido: resuelta.** Decisión 27 del mismo acta, Claude por delegación de Arthur
  (C-236), sobre el punto 7 de DD-135 ("un reporte sin pedido va a la plaza de la cuenta"), que se precisa porque
  `usuarios` no lleva plaza:
  1. con pedido, la del pedido, que tiene que ser de la cuenta;
  2. sin pedido, la del último pedido de la cuenta;
  3. sin pedidos, la de su dirección guardada más reciente. Al implementarla se lee como la más reciente **que tiene
     plaza**: una dirección borrada ya no está guardada, y una fuera de toda zona no tiene plaza que dar;
  4. sin ninguna, el servidor lo rechazaba con 422 y el motivo `sin_plaza`, y la app ofrecía el WhatsApp. **Ya no:** el
     Módulo A #16 pide el "formulario de reporte" sin condiciones (`NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3`), así
     que el reporte se guarda sin plaza (E17). Los puntos 1 a 3 siguen igual.

  `id_plazas` nació no nula; la migración `2026_11_26_200000_admitir_reportes_soporte_sin_plaza` la deja opcional, y
  plazas-E09 ya la nombraba entre las de plaza opcional. Lo ve solo la administración, que es una para todas las
  plazas: el contrato no nombra una administración por plaza (`autorizacion-por-rol`, Non-Goals).
- **Apple y Google al borrar una cuenta (grado E, `SIN VERIFICAR`).** Conocimiento de Claude, sin fuente consultada
  hoy: Apple pide revocar el token de "Sign in with Apple" al borrar una cuenta que entró con Apple, y Google Play pide
  además una página web donde pedir el borrado. Sigue sin verificar. E15 se implementó con lo mínimo que pide su texto
  (Decisions, punto 2), y la verificación no lo cambia: la revocación del token de Apple llega con la entrada con Apple,
  que no existe (DD-069), y la página web es de publicacion-E11 (DD-109). Si se confirma, necesita ampliar ADR-0005, y
  la página web tendría que caber en lo que el contrato ya pide.
- **BR-25 y BR-09 ya tienen fila:** DD-108 y DD-109.
- **DD-055** (tarjeta guardada: Nadir dijo que sí, solo la referencia, C-198), **DD-079** (si el checkout de la
  pasarela permite reutilizarla, como en pago-E06) y **DD-082** (número de WhatsApp).
- **La fecha del reporte: resuelta.** Todas las tablas del negocio con llave propia llevan `created_at` y
  `updated_at` en `timestamptz`; la fecha de perfil-usuario-E11 es `reportes_soporte.created_at`.
- **Quitar la foto de perfil, sin escenario.** El numeral 15 lista la "foto" del perfil, y la spec pide ponerla (E03)
  y rechazar un archivo inválido (E04), pero ningún escenario pide quitarla y dejar la cuenta sin foto. La tarea 1.2 no
  creó esa ruta (`CLAUDE.md` §9.2: sin escenario no hay código). Si se quiere, el especificador agrega el escenario
  (por ejemplo, `DELETE /api/perfil/foto`, que borra el archivo y deja `foto` en `null`) y entra como tarea S. Hoy,
  para cambiarla, se sube otra.
- **El límite de subida de PHP.** El servidor rechaza con el mensaje de peso lo que pasa de `FOTOS_PESO_MAXIMO_KB`,
  pero solo si el archivo llega: `upload_max_filesize` y `post_max_size` de PHP tienen que ser al menos ese máximo en
  cada entorno (hoy, 5 MB). Lo revisa quien prepare el servidor (DD-070), igual para la foto de la lavandería.
- **Pantallas sin componente.** Perfil y Ayuda no usan ninguno de los seis componentes de
  `vault/design-system/componentes.md`. La Definition of Ready pide que el componente exista o que la tarea lo cree.

## Contrato de la API

Lo que implementan las tareas 1.1 a 1.5 en `servidor/`, para que la app (grupo 2) se construya contra él. El mismo
contrato vive en el comentario de cada controlador de `servidor/app/Http/Controllers/Api/`.

**Común a las seis rutas.** Todas llevan sesión y pasan por el componente único de autorización (ADR-0019), y todas
actúan sobre la cuenta de la sesión: ninguna recibe de quién.

```
Authorization: Bearer <token de la sesión>        (auth:sanctum y autorizar:usuario)

401  { "message": "Unauthenticated." }              sin sesión, o con un token revocado
403  { "message": "Esta cuenta no tiene acceso." }  la cuenta no tiene el rol de usuario
```

Un error de validación sale como siempre en Laravel: `422 { "message": "<el primero>", "errors": { "<campo>": ["…"] } }`.

### El perfil (tarea 1.1: perfil-usuario-E01, E02 y E06)

El perfil, como sale en las tres respuestas (`GET` y `PATCH /api/perfil`, y `POST /api/perfil/foto`):

```
{
  "id_publico": "01jd8x6m3v2c9q4w5e7r8t0y1z",   el ULID de la cuenta, el mismo de GET /api/cuenta
  "nombre": "Ana Prueba" | null,                vacío hasta que la persona lo escribe: el registro no lo pide (C-225)
  "correo": "ana@ejemplo.com",                  solo para mostrarlo: no se cambia desde el perfil
  "foto": { "url": "https://…firmada…", "vence_en": "2026-11-22T18:00:00Z" } | null
                                                null hasta que sube una (tarea 1.2; ver "La foto de perfil")
}
```

```
GET /api/perfil
200  { "perfil": { ...el perfil... } }

PATCH /api/perfil
{ "nombre": "Ana Prueba" }      obligatorio: texto de hasta 255, sin los espacios de los lados, que se quitan
                                cualquier otro campo se ignora y no se guarda, también un número de tarjeta (E06)
200  { "perfil": { ...el perfil, con el nombre nuevo... } }
422  { "message": "Escribe tu nombre.", "errors": { "nombre": ["Escribe tu nombre."] } }
     falta, vacío o solo con espacios (E02); los otros mensajes: "El nombre va en texto.", "El nombre es demasiado
     largo.". En ningún error cambia el nombre que había.
```

Ninguna respuesta del perfil lleva un número de tarjeta (E06). Las direcciones guardadas del perfil (E05) son las de
`GET /api/direcciones/favoritas` y `GET /api/direcciones/{id_publico}`, del cambio `direcciones`.

### La foto de perfil (tarea 1.2: perfil-usuario-E03 y E04)

```
POST /api/perfil/foto           multipart/form-data, por POST porque PHP solo lee los archivos de un POST
  foto    obligatorio: una imagen de los tipos de FOTOS_TIPOS (jpg, jpeg, png o webp) y de hasta FOTOS_PESO_MAXIMO_KB
          (5120 KB por omisión); la app la comprime antes (tarea 2.2)
  cualquier otro campo se ignora

200  { "perfil": { ...el perfil, con la foto nueva... } }
     la foto queda en el almacenamiento privado, ligada a la cuenta de la sesión; reemplaza a la anterior, si había, y
     el archivo anterior se borra (E03)

422  { "message": "Elige una foto.", "errors": { "foto": ["Elige una foto."] } }
     no viene la foto; los otros mensajes, en el mismo campo:
       "La foto debe ser una imagen: jpg, jpeg, png, webp."   no es un archivo, no es una imagen o es de otro tipo (E04)
       "La foto pesa más de 5120 KB."                         pasa el peso máximo de la configuración (E04)
     en ningún error cambia la foto que había ni se guarda un archivo
```

La foto, en las respuestas del perfil (`GET`, `PATCH` y este `POST`):

```
"foto": {
  "url": "https://…firmada…",          la única forma de verla: sin la firma, el almacenamiento responde 403
  "vence_en": "2026-11-22T18:00:00Z"   en UTC, a los FOTOS_VIGENCIA_MINUTOS (60 por omisión); después, 403. Al volver
}                                      a pedir el perfil llega otra URL
"foto": null                           la cuenta todavía no sube una
```

Es la misma forma que la foto de la lavandería (`alta-de-lavanderias` y `perfil-de-lavanderia`). La ruta del archivo
no sale nunca como campo.

**Quitar la foto no tiene ruta.** Ningún escenario lo pide (E03 pone una foto y E04 rechaza un archivo): ver Open
Questions.

#### Cómo sale la foto del teléfono (corrección, Claude por delegación de Arthur, C-236)

- **El defecto.** En el teléfono no se subía ninguna foto: la app decía "No se pudo conectar con el servidor" y la
  petición no salía. Se comprobó en el emulador con el APK local: un servidor anotador no recibió nada, y el servidor sí
  guarda la foto que le llega con `curl`. En Expo SDK 58, `expo/src/winter/runtime.native.ts` reemplaza el `fetch`
  global por `expo/fetch`, salvo que exista `EXPO_PUBLIC_USE_RN_FETCH`. `expo/fetch` arma el multipart con
  `convertFormDataAsync` (`expo/src/winter/fetch/convertFormData.ts`), que solo sabe mandar un texto, un `Blob` o un
  objeto con `bytes()`: la parte `{ uri, name, type }` de React Native la rechaza con "Unsupported FormDataPart
  implementation", y `pedirConEstado` (`movil/src/configuracion/api.ts`) lo da por sin conexión. Las pruebas de Jest no
  lo veían porque simulan `fetch`.
- **La corrección.** Una sola pieza arma la parte de la foto: `movil/src/configuracion/foto-del-formulario.ts`. La
  parte lleva `bytes()`, que lee la foto con `File` de `expo-file-system` al mandarla, el nombre `foto.jpg` y el tipo
  `image/jpeg`, que es como la deja la compresión.
  - **El nombre va en la parte,** además del tercer argumento de `append()`, porque el `FormData` del teléfono
    (`expo/src/winter/FormData.ts`) solo toma ese argumento para un `Blob`, y `File` no lo es: con el `File` solo, el
    archivo llegaría con el nombre al azar que le pone la compresión.
  - **El tipo.** `File` sí lo da por la extensión (UTType en iPhone, MimeTypeMap en Android), y la compresión guarda el
    JPEG con `.jpg`. La parte lo fija de todos modos, para no depender de la extensión.
  - **El `File` se abre al mandar,** no al armar el formulario: si la foto no se puede leer, la petición falla como sin
    conexión y la consulta sigue sin lanzar.
- **No se usa `EXPO_PUBLIC_USE_RN_FETCH`.** Dependería de que cada perfil de compilación la trajera, y en uno que la
  olvidara las fotos fallarían sin aviso.
- **Las demás subidas usan la misma pieza:** el registro y la edición de la lavandería (alta-lavanderias-E03 y
  E19, `movil/src/alta-lavanderias/api.ts`), el registro del repartidor (repartidor-E01) y la evidencia de entrega
  (repartidor-E23), estas dos en `movil/src/repartidor/api.ts`. La app no sube ningún otro archivo.
- **Las pruebas.** `movil/__tests__/fotos/subida-desde-el-telefono.test.ts` pasa el formulario de cada subida, armado
  con el `FormData` del teléfono, por el `convertFormDataAsync` real. Los servidores simulados de las pantallas leen el
  multipart de la misma forma (`movil/__tests__/apoyo/formulario.ts`). El envío real se prueba en un teléfono.

### El número de soporte (tarea 1.3: perfil-usuario-E09 y E10)

```
GET /api/soporte
200  { "whatsapp": "5210000000001" }    el número de la configuración, solo con sus dígitos, en formato
                                         internacional; la app abre https://wa.me/<whatsapp> (E09)
200  { "whatsapp": null }                sin número configurado (DD-082): la opción aparece como no disponible y la
                                         app no abre un enlace vacío (E10)
```

### El reporte (tarea 1.4: perfil-usuario-E11, E12 y E13)

```
POST /api/soporte/reportes
{
  "descripcion": "La camisa azul llegó con una mancha.",   obligatoria: texto de hasta 5000, sin los espacios de los
                                                           lados, que se quitan
  "pedido": "01jd8x6m3v2c9q4w5e7r8t0y1z"                   opcional: el id_publico de un pedido de la cuenta, en
}                                                          mayúsculas o minúsculas; sin él, o null, no es de un pedido
cualquier otro campo se ignora

201  {
       "reporte": {
         "descripcion": "La camisa azul llegó con una mancha.",
         "pedido": "01jd8x6m3v2c9q4w5e7r8t0y1z" | null,
         "estado": "recibido",                  el único mientras no se responda DD-108
         "recibido_en": "2026-12-03T18:30:00Z"  la fecha del reporte, en UTC
       }
     }
     la app confirma que se recibió, sin prometer un tiempo de respuesta (E11); una cuenta sin pedidos ni direcciones
     guardadas con plaza recibe el mismo 201, y el reporte se guarda sin plaza (E17)

422  { "message": "Cuéntanos qué pasó.", "errors": { "descripcion": ["Cuéntanos qué pasó."] } }
     falta, vacía o solo con espacios (E12); los otros mensajes: "La descripción va en texto.", "La descripción es
     demasiado larga." y, en "pedido", "El pedido va en texto."
404  { "message": "No existe el pedido." }
     el pedido es de otra cuenta, no existe o no es un ULID, como un folio: todos igual y sin ningún dato (E13)

en ningún error se guarda el reporte
```

### Borrar la cuenta (tarea 1.5: perfil-usuario-E15)

```
DELETE /api/cuenta             sin cuerpo; la confirmación previa la pide la app antes de llamar (tarea 2.6)
204  (sin cuerpo)
     la cuenta queda borrada y todos sus tokens revocados, también los de sus otros teléfonos: cualquier petición con
     ellos responde 401. Ya no entra con correo y contraseña (POST /api/sesion: 422, "Las credenciales no son
     válidas.") ni con Google (POST /api/sesion/google: 422, "No se pudo entrar con tu cuenta de Google."). La app
     vuelve a la bienvenida.
```
