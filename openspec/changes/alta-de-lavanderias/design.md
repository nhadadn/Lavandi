## Context

- **Lo que pide el contrato:** el registro de la lavandería en la app (Módulo B #1), su perfil y sus ventanas de
  disponibilidad (Módulo B #6) y la gestión de lavanderías en la administración (Módulo D #1). Citas en `proposal.md`.
- **Dónde vive cada parte:** el Módulo B en la app de React Native con Expo (ADR-0002) y el Módulo D en el panel web de
  Filament (ADR-0003; DD-040 cerrada por C-140). La app entra con tokens de Sanctum y el panel con sesión web y rol de
  administración (ADR-0005).
- **Lo que ya existe al llegar a H5:** lavanderías sembradas con su plaza, sus servicios y sus precios
  (`plazas-y-zonas`, `perfil-de-lavanderia`), la búsqueda de lavanderías cercanas (`lavanderias-cercanas`), la
  geocodificación de direcciones (`direcciones`) y el componente de autorización (`autorizacion-por-rol`).
- **Toda lavandería lleva su plaza** (`CLAUDE.md` §9.5). La regla y la zona horaria de cada plaza están en
  `vault/arquitectura/modelo-multi-plaza.md`, que sigue en borrador; las zonas y la consulta espacial son PostGIS
  (ADR-0004).
- **Las fotos** van a un almacenamiento S3 privado, se ven con URLs firmadas y la app las comprime antes de subirlas
  (ADR-0006). Es la primera pantalla de la app que sube una foto.

## Goals / Non-Goals

**Goals:**

- Que una lavandería real pueda registrarse, editar su información y definir sus ventanas desde la app.
- Que la administración pueda editar, suspender y dar de baja lavanderías desde el panel, sin perder el historial.
- Dejar escritos y marcados los escenarios que esperan una respuesta, para que entren en cuanto se cierre su fila.

**Non-Goals:**

- Decidir cómo llega cada rol a su módulo ni si el registro espera aprobación (DD-053).
- Fijar el precio, su unidad en el lavado en seco o sus límites (DD-010).
- Definir el cupo por ventana ni cómo la agenda del usuario usa las ventanas (BR-17, `agenda-de-recoleccion`).
- Cargar las lavanderías reales: es operación de EL CLIENTE (`proposal.md`, "Fuera del contrato").

## Decisions

No hay una decisión técnica nueva que no esté en un ADR. Este cambio usa:

- **ADR-0003:** la gestión de lavanderías es un recurso de Filament. La edición es su formulario; la suspensión, el
  levantamiento y la baja son acciones del recurso.
- **ADR-0004 y ADR-0009:** la dirección se geocodifica una vez al guardarla y la plaza sale de la zona que la contiene,
  con PostGIS.
- **ADR-0005:** Sanctum en la app y sesión web en el panel.
- **ADR-0006:** la foto se sube al almacenamiento S3 privado del entorno.
- **La baja no borra filas.** No es una decisión técnica nueva: lo pide el requisito, porque el contrato guarda el
  "historial completo" en la base de datos (`NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3`).
- **Las validaciones del registro se escriben una vez** y las usan el registro de la app, la edición de la app y la
  edición del panel.
- **ADR-0016:** las tablas y sus nombres son los de `vault/arquitectura/modelo-de-datos.md` (sección siguiente).

## Tablas y migración

Fuente: `vault/arquitectura/modelo-de-datos.md`, columna "Nace en", y las reglas de ADR-0016. Este cambio **no crea
tablas: completa `lavanderias`**, que nace en `perfil-de-lavanderia` con las lavanderías de prueba de H2, con lo que
necesita el alta real.

**`lavanderias`** (la completa este cambio). Sus columnas, como en el modelo: `id_lavanderias`, `id_publico`,
`id_plazas`, `id_usuarios`, `nombre`, `descripcion`, `telefono`, `calle`, `numero`, `colonia`, `codigo_postal`,
`ubicacion` (`geography(Point)`), `estado`, `aprobada_en`, `deleted_at`.

- **Lo que agrega esta migración:**
  - `estado`, obligatorio, con `CHECK (estado IN ('pendiente', 'activa', 'suspendida', 'baja'))` y valor por omisión
    `activa`, para que las lavanderías de prueba que ya existen sigan apareciendo (ADR-0016, regla 9: lo nuevo entra con
    valor por omisión). El estado con que nace un registro de la app no lo da la omisión: depende de DD-053
    (alta-lavanderias-E11 y E12; pregunta abierta 9).
  - `aprobada_en` (`timestamptz`, opcional): la hora del alta en el panel. En las filas que ya existen, la migración la
    llena con la hora en que corre, porque esas lavanderías ya estaban activas. **Al implementarse** (tarea 0.1),
    `perfil-de-lavanderia` ya traía `estado` con su `CHECK` y `aprobada_en`: la migración
    (`servidor/database/migrations/2026_11_22_100000_completar_lavanderias_para_el_alta.php`) no los repite; solo pone
    el valor por omisión y llena `aprobada_en` en las que no están `pendiente`, porque una pendiente nunca tuvo alta y
    E15 la regresa a `pendiente`.
  - **`id_municipios`** (opcional, llave a `municipios` con su índice y `ON DELETE RESTRICT`), decidido por delegación
    de Arthur (C-236) al implementar: la geocodificación (`App\Direcciones\Geocodificador`) necesita el municipio del
    catálogo para no confundir, por ejemplo, Matamoros, Coahuila, con Matamoros, Tamaulipas, y la edición lo necesita
    para volver a geocodificar. Es la misma solución de `direcciones` (C-238). Las lavanderías de prueba no lo tienen;
    la API lo exige en el registro. `vault/arquitectura/modelo-de-datos.md` ya lo lista.
- **Lo que ya trae de `perfil-de-lavanderia`:** la llave `id_lavanderias`; `id_publico` (ULID, único); las foráneas
  `id_plazas` → `plazas.id_plazas` e `id_usuarios` → `usuarios.id_usuarios`, con índice y `ON DELETE RESTRICT`; el único
  `(id_lavanderias, id_plazas)` para las llaves compuestas de sus hijas; el índice GiST de `ubicacion`, y `deleted_at`.
- **Cómo usa los estados este cambio:**
  - Solo una lavandería `activa` aparece al usuario y recibe pedidos nuevos (E12, E14, E17).
  - Suspender (E14) pone `suspendida`. Levantar la suspensión (E15) regresa a `activa` si `aprobada_en` tiene fecha y a
    `pendiente` si no: "el estado que tenía antes" sale de la fila, sin otra columna.
  - Dar de baja (E17) pone `baja` y no borra la fila, ni en suave: `deleted_at` queda vacío, para que el panel la siga
    listando con su estado y sus pedidos la sigan encontrando. Es la lectura de este diseño, ratificada el 2026-11-24
    (pregunta abierta 10, resuelta).
- **Sin trigger:** `impedir_cambio()` es solo para `pedidos`, `pedido_eventos`, `pagos` y `reembolsos`.
- **Prueba de esquema:** la migración pasa la de `modelo-de-datos` (modelo-E01 a modelo-E17).

**Tablas de otros cambios que este usa:**

| Tabla | Qué hace aquí | Nace en |
|---|---|---|
| `lavanderia_servicios` | Escribe cada servicio con su precio (E05, E06) | `perfil-de-lavanderia` |
| `tipos_servicio` | Lee los tres tipos admitidos; un código que no está ahí, como el calzado, se rechaza (E06) | `perfil-de-lavanderia` |
| `unidades_medida` | Lee `kg` para el precio por kilo (E05); la unidad del lavado en seco es DD-010 (E07) | `perfil-de-lavanderia` |
| `lavanderia_horarios` | Escribe el horario y las ventanas de disponibilidad (E01, E18, E21, E22) | `perfil-de-lavanderia` |
| `lavanderia_fotos` | Escribe la ruta de la foto en S3 (E01, E18) | `perfil-de-lavanderia` |
| `plazas`, `zonas` | Lee la zona que contiene el punto para fijar `id_plazas` (E09, E10) | `plazas-y-zonas` |
| `usuarios`, `rol_usuario`, `roles` | Lee la cuenta del socio, que queda en `lavanderias.id_usuarios` | `registro-e-inicio-de-sesion` |
| `pedidos` | La operación que crea el pedido rechaza una lavandería que no está `activa` (E14, E17); los pedidos pasados no se tocan (E17) | `resumen-y-confirmacion` |

**Horario y ventanas en una sola tabla.** El modelo guarda el horario del registro (Módulo B #1) y las ventanas de
disponibilidad (Módulo B #6) en `lavanderia_horarios`: `id_lavanderia_horarios`, `id_lavanderias`, `id_plazas`,
`dia_semana` (1 a 7), `hora_inicio`, `hora_fin`, con `CHECK (hora_fin > hora_inicio)`. Este diseño lee cada fila como
una ventana de una hora, y el horario del día como la suma de sus ventanas. La regla de "exactamente una hora" (E22) la
valida el código: el modelo no la pone como `CHECK`. Los choques que deja esa lectura están en la pregunta abierta 11.

## Implementación en el servidor

Lo que se decidió al implementar, por delegación de Arthur (C-236), con lo mínimo que cumple cada escenario. Ninguna
decisión agrega una librería ni un servicio externo.

- **El código vive en `servidor/app/Lavanderias/`:** `ReglasDeLaLavanderia` (las validaciones, escritas una vez),
  `RegistroDeLavanderia`, `UbicacionDeLaLavanderia` (una geocodificación y la plaza de la zona), `ServiciosYVentanas`
  y `FotosDeLaLavanderia`. El controlador de la app es `App\Http\Controllers\Api\LavanderiaDelSocioController`.
- **Las rutas del socio van bajo `/api/socio/`,** con `auth:sanctum` y `autorizar:lavanderia`, aparte de las del
  usuario (`autorizar:usuario`). La tenencia la decide `Autorizacion::lavanderiasDeLaCuenta()`: el socio solo ve y
  toca su lavandería (ADR-0019).
- **El estado con que nace un registro:** `pendiente`, sin `aprobada_en` (`RegistroDeLavanderia::ESTADO_AL_REGISTRARSE`),
  la lectura de la pregunta abierta 9 mientras DD-053 siga abierta. Hoy una pendiente todavía aparece al usuario, porque
  `Lavanderia::visiblesParaElUsuario()` solo excluye las suspendidas y las de baja (DD-083, cercanas-E09); E12 la quita
  cuando cierre DD-053.
- **Los servicios.** Se admiten los tipos activos del catálogo con su unidad definida en
  `ServiciosYVentanas::UNIDAD_POR_TIPO`: hoy, `estandar` y `planchado`, por kilo (E05). Un código fuera del catálogo
  se rechaza nombrándolo y diciendo cuáles sí hay (E06, `App\Lavanderias\ServicioAdmitido`). **El lavado en seco se
  rechaza mientras DD-010 siga abierta**, con el mensaje de que falta decidir su unidad: aceptarlo por kilo decidiría
  E07 en silencio. Cuando DD-010 responda, entra como una línea de ese mapa (y una fila de `unidades_medida` si es por
  prenda). Un tipo que ya no viene en la lista queda `activo = false`, sin borrarse. El precio va en pesos, sin signo y
  con hasta dos decimales; el piso, el techo y quién lo fija son E08 (DD-010).
- **La edición** (`App\Lavanderias\EdicionDeLavanderia`) es la misma para la app y el panel. Cambia solo lo que viene,
  con las reglas del registro; los servicios, las ventanas y las fotos del perfil, si vienen, son la lista completa.
  Una lavandería ajena responde 404 antes de validar (E20).
- **Las ventanas** (E21, E22) son el mismo campo `ventanas` en el registro y en la edición: el horario completo de la
  semana, una fila de `lavanderia_horarios` por ventana. Cada una dura exactamente una hora
  (`App\Lavanderias\VentanaDeUnaHora`), con horas `HH:MM` en la zona horaria de la plaza; la última del día puede
  terminar a las `24:00`, que la agenda ya lee como la medianoche siguiente. El error queda en la ventana
  (`ventanas.N`). No se revisa si dos ventanas se enciman ni si una empieza en punto: ningún escenario lo pide, y la
  agenda ya ofrece una sola vez la ventana repetida. Con esta lectura, las franjas largas sembradas en las lavanderías
  de prueba (de 9:00 a 14:00) se ven en la app, pero la edición pide reemplazarlas por ventanas de una hora (pregunta
  abierta 11).
- **El cambio de plaza no se hace** (alta-lavanderias-E25): una lavandería que se muda de plaza se da de baja y se
  registra con otra cuenta (`CuentaConLavanderia` impide dos en la misma). Una dirección nueva que cae en una zona de
  otra plaza se rechaza en `direccion` con el motivo `en_otra_plaza`, en la app y en el panel: las hijas de la
  lavandería y sus pedidos van atados a su plaza por las llaves compuestas (modelo-E06), y moverla no es una edición.
- **Una cuenta, una lavandería.** El modelo dice `usuarios ||--o| lavanderias`: el registro de una cuenta que ya tiene
  la suya responde 409 y no guarda nada. La base no lo exige con un único; si DD-053 lo confirma, entra como índice.
- **La foto.** Va al disco privado de fotos del entorno, que elige `config/fotos.php` (`FOTOS_DISCO`): `fotos_s3` en
  producción, el bucket S3 privado de las variables de entorno (ADR-0006, ADR-0012), y hoy `fotos_local` en los demás,
  un disco local privado (`storage/app/private/fotos`) que sirve cada foto en `/archivos/fotos/…` solo con una URL
  firmada que vence. Esto responde, para local, la pregunta abierta "El almacenamiento en local" de
  `perfil-de-lavanderia`, y ya lo anota la adenda del 2026-11-24 de ADR-0006 (`acta-2026-11-20-modelo-y-pasarela.md`,
  decisión 49): `fotos_local` solo es para la máquina local y las pruebas automáticas; el entorno de prueba y
  producción usan `fotos_s3`, y el valor por omisión cambia cuando exista el bucket del entorno de prueba (DD-070). El
  servidor revisa el tipo (`FOTOS_TIPOS`, `jpg,jpeg,png,webp`) y el peso (`FOTOS_PESO_MAXIMO_KB`, 5120 por omisión,
  ratificado el 2026-11-24: pregunta abierta 1, resuelta); la URL dura `FOTOS_VIGENCIA_MINUTOS` (60). La ruta del
  archivo nunca sale como campo de la API. La foto del registro es la principal (`orden` 1); cambiarla reemplaza esa
  fila y borra el archivo anterior. La lista de fotos del perfil está en la sección "La descripción y las fotos del
  perfil".
- **Para producción falta una dependencia:** el driver S3 de Laravel necesita `league/flysystem-aws-s3-v3`. No se
  instaló, porque hoy solo existe el entorno local (C-154); se propone instalarla cuando se decida el hosting (DD-070).

## El panel de administración

El recurso de lavanderías de Filament (ADR-0003, ADR-0011) vive en `servidor/app/Filament/Resources/Lavanderias/`.

- **Quién entra:** solo el rol de administración. El panel le pregunta al componente de autorización con
  `Usuario::canAccessPanel()`, y el recurso toma sus lavanderías de `Autorizacion::lavanderiasDelPanel()`: todas, de
  todas las plazas, para la administración; ninguna para otra cuenta (autorizacion-E03, con su prueba en
  `servidor/tests/Feature/Autorizacion/PanelSoloAdministracionTest.php`). No usa Policies de Filament (ADR-0019).
- **La lista** muestra nombre, estado (Pendiente, Activa, Suspendida, Dada de baja) y plaza (E13).
- **La edición** usa los mismos campos del registro, más la descripción y las fotos del perfil, y pasa por
  `ReglasDeLaLavanderia` y `EdicionDeLavanderia`, como la app; cada error vuelve junto a su campo. Solo se manda lo que
  cambió desde que se abrió la página, como en el `PATCH` de la app: así una lavandería de prueba sin municipio, o con
  sus franjas largas de prueba, se edita en lo demás. La foto del registro es la primera de la lista de fotos
  (alta-lavanderias-E31, en la sección "La descripción y las fotos del perfil").
- **No hay alta ni borrado:** el alta espera DD-053 (E11) y la baja no borra (E17).
- **Suspender y levantar la suspensión** (E14, E15) son acciones de la lista y de la página de cada lavandería, con
  confirmación (`app/Filament/Resources/Lavanderias/Actions/AccionesDeEstado.php`). La regla vive en
  `App\Lavanderias\EstadoDeLaLavanderia`, con la fila bloqueada: se suspende una activa o una pendiente; levantar
  regresa a `activa` si `aprobada_en` tiene fecha y a `pendiente` si no. El efecto en la búsqueda, el perfil y los
  pedidos nuevos sale solo de `Lavanderia::visiblesParaElUsuario()`, que ya excluía las suspendidas: no se tocó la
  creación del pedido. Los pedidos en curso no se tocan (E16, DD-104).
- **Dar de baja** (E17) es otra acción, con confirmación, desde cualquier estado que no sea `baja`. Pone
  `estado = 'baja'` y deja `deleted_at` vacío: el panel la sigue listando como "Dada de baja", y sus servicios, su
  horario, sus calificaciones y sus pedidos pasados se quedan y la siguen encontrando. Es la lectura de la pregunta
  abierta 10, ratificada el 2026-11-24; `deleted_at` queda sin uso en este cambio y no se quita (ADR-0016, regla 9).
  Ninguna acción deshace la baja: el contrato no lo pide.
- **`Usuario` implementa `HasName` de Filament:** el panel no podía pintar la barra de la cuenta de administración
  porque `usuarios` no tiene `name`; muestra el nombre, o el correo mientras el nombre esté vacío (C-225).
- **El idioma de la interfaz de Filament** sale de `APP_LOCALE`, que hoy es `en` en `.env.example`: las etiquetas de
  este recurso están en español, pero los botones propios de Filament salen en inglés hasta que se cambie a `es`.
  No se cambió aquí porque toca a todo el servidor.

### Cómo se entra al panel en local

1. Migrar la base de desarrollo, que recibe `2026_11_22_100000_completar_lavanderias_para_el_alta`, y sembrar:
   `php artisan migrate --seed` (o `php artisan db:seed` si ya está migrada). En local, el sembrado agrega
   `Database\Seeders\CuentasDeDesarrolloSeeder`, que solo corre con `APP_ENV=local`.
2. `php artisan serve` y abrir `http://localhost:8000/admin` (o el `APP_URL` del `.env` más `/admin`).
3. Entrar con las cuentas de prueba, con la contraseña de las cuentas de prueba, `contrasena-de-prueba`
   (`UsuarioFactory::CONTRASENA`), guardada con hash:

| Cuenta | Rol | Para qué |
|---|---|---|
| `admin@ejemplo.com` | Administración | Entra al panel: `/admin/lavanderias` |
| `lavanderia@ejemplo.com` | Socio lavandería, sin lavandería | Entra a la app para probar el registro (`POST /api/socio/lavanderias`) |

Las fotos de local quedan en `servidor/storage/app/private/fotos/` y se ven en `/archivos/fotos/…` solo con la URL
firmada. Ninguna de estas cuentas se siembra en el entorno de prueba ni en producción.

Sin la llave de Google, la geocodificación de local es la de prueba (`App\Direcciones\GeocodificadorDePrueba`,
direcciones-E15): solo ubica direcciones del municipio de Durango (`10005`), dentro de la zona de la Plaza de prueba
Durango; cualquier otro municipio responde `direccion_no_encontrada`. Para registrar una lavandería desde la app en
local, la dirección va en Durango, con el catálogo del territorio ya cargado (`php artisan direcciones:cargar-catalogo`,
con los archivos de Correos de México que no se versionan, DD-138): sin él, ningún municipio pasa la validación.

## La descripción y las fotos del perfil

alta-lavanderias-E26 a E31 (tareas 2.4, 3.4 y 4.3). La pregunta abierta 3 la decidió el concilio del 2026-11-24, por
delegación de Arthur (C-236, C-245; `acta-2026-11-20-modelo-y-pasarela.md`, decisión 44; DD-097): la descripción y las
fotos las captura la lavandería en Perfil y horarios (`NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo B #6`) y el
panel las hereda con las mismas reglas (`:19:Módulo D #1`). El perfil que ve el usuario ya las mostraba
(perfil-lavanderia-E01 y E09) y no cambia.

- **Sin migración.** `lavanderias.descripcion` (`text`, opcional) y `lavanderia_fotos.orden` ya existen.
- **El registro no cambia.** Pide una sola foto y ninguna descripción (`ReglasDeLaLavanderia::CAMPOS`). La edición
  admite además `descripcion` y `fotos` (`ReglasDeLaLavanderia::CAMPOS_DE_LA_EDICION`).
- **La descripción** es opcional, de hasta 1000 caracteres (`ReglasDeLaLavanderia::DESCRIPCION_MAXIMA`), el mismo
  largo técnico del comentario de la calificación (`config/pedidos.php`). Se guarda sin los espacios de las orillas, y
  vacía deja el perfil sin descripción (`null`).
- **Las fotos son una lista completa y ordenada** que reemplaza a la anterior, como los servicios y las ventanas:
  `FotosDeLaLavanderia::reemplazarTodas()`, junto a `reemplazarPrincipal()`. Dentro de la transacción de la edición,
  con la lavandería bloqueada:
  - revisa que cada foto que ya tiene exista y venga una sola vez (si no, 422 en `fotos.N`);
  - borra las filas que no vienen;
  - crea las nuevas;
  - reescribe `orden` de 1 a n: la primera es la principal.

  Los archivos quitados se borran cuando la transacción queda guardada, y los nuevos se borran si falla, como la foto
  del registro.
- **Cómo se nombra una foto que ya tiene:** por su lugar en `fotos` de la última lectura (0, la primera), nunca por su
  ruta ni por su llave (perfil-lavanderia-E03; ADR-0016, regla 5). En multipart, Laravel junta primero los textos y
  luego los archivos de la lista; el controlador la ordena por su lugar antes de validar.
- **El máximo** es `FOTOS_MAXIMO_POR_LAVANDERIA` (`config/fotos.php`, `maximo_por_lavanderia`, 5 por omisión), un
  valor técnico que cambia sin tocar el código. La lista va de 1 al máximo: la última foto no se quita, porque el
  registro pide una (E28), y una lista más larga se rechaza con un mensaje que dice el máximo (E30). La app lo recibe en
  `maximo_de_fotos`.
- **La foto sola sigue** (`foto`, E18): reemplaza la principal. Si llegan `foto` y `fotos` juntas, 422 en `fotos`.
- **El panel** (E31) cambia su campo de foto por la lista: `descripcion` es un área de texto y `fotos` un selector de
  varias fotos, ordenables, que agrega al final, en el disco de `config('fotos.disco')`. La página convierte la lista a
  la forma de la app: cada foto que ya tiene, por su lugar en la lista que leyó al abrir, y cada nueva como archivo. La
  guardan las reglas y la edición de la app, y solo si la lista cambió desde que se abrió la página. El error de
  cualquier foto va al selector. Tres cuidados, para que las fotos que ya tiene no estorben al guardar otro dato:
  - **El máximo** de la configuración también frena al selector, que no deja subir más (lo mismo que la app, que no lo
    ofrece). Es la única validación propia del formulario: la regla `max` del selector de Filament corre antes que la
    edición, con el mensaje de `ReglasDeLaLavanderia` ("La lavandería puede tener hasta N fotos."). A una lavandería que
    ya tiene más fotos que el máximo, porque se bajó, el selector le deja las que tiene: la regla de la edición, que
    solo corre si la lista cambió, le pide quedar en el máximo, y lo demás se sigue editando.
  - **El selector no revisa el disco al abrir** (`fetchFileInformation(false)`). Si lo hiciera, Filament quitaría de la
    lista la foto cuyo archivo no encuentra, o que no pudo revisar por una falla momentánea del almacenamiento, y la
    lista parecería cambiada: guardar cualquier otro dato borraría esa foto.
  - **Las fotos que cambiaron después de abrir.** Con la lavandería bloqueada, la edición revisa que las fotos sigan
    siendo las que leyó la página; si cambiaron, por ejemplo desde la app, rechaza la lista en `fotos` y no cambia nada
    (`FotosDeLaLavanderia::FOTOS_QUE_CAMBIARON`). Si la página no cambió las fotos, no las manda y no las toca.
- **En la app** (`app/lavanderia/perfil.tsx`, `src/alta-lavanderias/FormularioDeLaLavanderia.tsx` y `formulario.ts`),
  solo en Perfil y horarios:
  - el campo "Descripción", de varias líneas y opcional;
  - en lugar de la foto única, la lista de fotos en su orden; cada una con "Quitar" si hay más de una y "Mover antes"
    si no es la primera;
  - "Agregar foto", con la misma toma, elección y compresión del registro (`src/alta-lavanderias/foto.ts`), mientras
    haya menos que el máximo que da el servidor. La nueva va al final.

  La edición manda la descripción si cambió y la lista completa si cambió algo de ella, en JSON o, con una foto nueva,
  en multipart. El error de una foto va en la sección de fotos. El registro conserva su campo único de foto.

## El contrato de la API

Para la app (tareas 4.1, 4.2 y 4.3). El mismo texto, con su detalle, está en
`servidor/app/Http/Controllers/Api/LavanderiaDelSocioController.php`.

Todas las rutas llevan `Authorization: Bearer <token>` de una cuenta con el rol de socio lavandería. Sin sesión
responden 401; con otro rol, 403 (`{ "message": "Esta cuenta no tiene acceso." }`).

**La lavandería, como sale en todas las respuestas:**

```json
{
  "id_publico": "01jd8x6m3v2c9q4w5e7r8t0y1z",
  "nombre": "Lavandería Ejemplo",
  "descripcion": "Entregamos el mismo día.",
  "direccion": {
    "calle": "Calle Ejemplo",
    "numero": "123",
    "colonia": "Centro",
    "codigo_postal": "34000",
    "municipio": { "codigo": "10005", "nombre": "Durango", "estado": "Durango" }
  },
  "foto": { "url": "https://…/archivos/fotos/…?expires=…&signature=…", "vence_en": "2026-11-22T18:00:00Z" },
  "fotos": [
    { "url": "https://…/archivos/fotos/…?expires=…&signature=…", "vence_en": "2026-11-22T18:00:00Z" }
  ],
  "maximo_de_fotos": 5,
  "servicios": [
    { "tipo": "estandar", "nombre": "Lavado estándar", "precio": "18.50", "unidad": { "codigo": "kg", "nombre": "kilo" } }
  ],
  "ventanas": [
    { "dia_semana": 4, "hora_inicio": "10:00", "hora_fin": "11:00" }
  ]
}
```

- `municipio` es `null` en una lavandería de prueba sin municipio; `descripcion` es `null` si no tiene.
- `fotos` son todas las del perfil, en su orden; `foto` es la principal, la primera de ellas, o `null` si no tiene.
  `maximo_de_fotos` es el máximo de la configuración (alta-lavanderias-E30).
- `precio` es texto en pesos con dos decimales. `dia_semana` va del 1, el lunes, al 7, el domingo; las horas, en la
  hora local de la plaza.
- No sale el estado de la lavandería: lo que ve suspendida o de baja es DD-104 (E16).

**`POST /api/socio/lavanderias`**: el registro (E01, E02, E05, E06, E09, E10). En `multipart/form-data`, porque lleva
la foto:

| Campo | Regla |
|---|---|
| `nombre` | Obligatorio, texto de hasta 255 |
| `direccion[calle]`, `direccion[numero]`, `direccion[colonia]` | Obligatorios, texto de hasta 255 |
| `direccion[codigo_postal]` | Obligatorio, cinco números |
| `direccion[municipio]` | Obligatorio: el `codigo` de un municipio de `GET /api/catalogos/municipios` |
| `foto` | Obligatoria: imagen de `FOTOS_TIPOS` y de hasta `FOTOS_PESO_MAXIMO_KB` |
| `servicios[N][tipo]`, `servicios[N][precio]` | Al menos uno. Tipo `estandar` o `planchado`; precio en pesos por kilo, sin signo y con hasta dos decimales |
| `ventanas[N][dia_semana]`, `ventanas[N][hora_inicio]`, `ventanas[N][hora_fin]` | Al menos una. Día del 1 al 7; horas `HH:MM`, el fin exactamente una hora después del inicio |

- **201** `{ "lavanderia": { … } }`.
- **409** `{ "message": "Esta cuenta ya tiene una lavandería registrada." }`.
- **422** `{ "message": "…", "errors": { "<campo>": ["…"] } }`, cada error en su campo (`nombre`,
  `direccion.calle`, `foto`, `servicios.0.precio`, `ventanas.1`…). No se consultó la geocodificación ni se subió la
  foto.
- **422** `{ "message": "…", "errors": { "direccion": ["…"] }, "motivo": "fuera_de_la_zona_de_servicio" }`, o con
  `"motivo": "direccion_no_encontrada"`: la dirección queda fuera de las zonas de servicio activas, o la geocodificación
  no la ubica (E10). No se guardó nada.
- **503** `{ "message": "No pudimos ubicar la dirección. Inténtalo más tarde.", "motivo": "geocodificacion_no_disponible" }`:
  el servicio de geocodificación no respondió. No se guardó nada.

**`GET /api/socio/lavanderias`**: las lavanderías de la cuenta (E18, E19). **200** `{ "lavanderias": [ { … } ] }`,
solo las suyas: hoy, a lo más una; vacía si todavía no registra la suya. Así la app sabe, al entrar, si abre el
registro o el perfil, y con qué `id_publico`.

**`GET /api/socio/lavanderias/{id_publico}`**: su lavandería, con su información y sus ventanas actuales (E19).
**200** `{ "lavanderia": { … } }`. **404** `{ "message": "No existe la lavandería." }` si no existe, está borrada o es
de otra cuenta: las tres responden igual (E20).

**`PATCH /api/socio/lavanderias/{id_publico}`**: edita su información, su descripción, sus fotos y sus ventanas (E18,
E20, E21, E22, E25 y E26 a E30). Los mismos campos del registro, con las mismas reglas, más la descripción y la lista de
fotos; solo van los que cambian:

- `nombre`;
- `direccion`, completa (las cinco partes);
- `foto`, que reemplaza a la principal;
- `servicios`, la lista completa: un tipo que no viene deja de ofrecerse;
- `ventanas`, el horario completo de la semana: reemplaza a todas las anteriores;
- `descripcion`, texto de hasta 1000 caracteres; vacía, el perfil queda sin descripción;
- `fotos`, la lista completa y en orden, de 1 a `maximo_de_fotos`; la primera es la principal. Cada elemento es una
  foto que ya tiene, por su lugar en `fotos` de la última lectura (0, la primera), o un archivo nuevo con las reglas de
  la foto del registro. Una foto que no viene se quita y su archivo se borra. No va junto con `foto`. En JSON,
  `"fotos": [2, 0]`; en multipart, `fotos[0]=2`, `fotos[1]=<archivo>`, `fotos[2]=0`.

Sin archivos va en JSON. **Con `foto` o con una foto nueva en `fotos`, en `multipart/form-data` por `POST` con el campo
`_method=PATCH`**, porque PHP solo lee los archivos de un `POST`. La dirección nueva se geocodifica una vez; la misma
que ya tiene no se vuelve a consultar.

- **200** `{ "lavanderia": { … } }`, ya con los cambios.
- **404** como en `GET`: una ajena no se edita y sus datos no cambian. La búsqueda va antes de validar.
- **422** como en el registro; si algo se rechaza, no cambia nada. Los errores de la lista de fotos van en `fotos` (sin
  fotos, más que el máximo, o junto con `foto`) o en su lugar, `fotos.N` (una foto que no tiene o que ya está en la
  lista, o un archivo que no cumple las reglas). Además, `"motivo": "en_otra_plaza"` en `direccion` si la dirección
  nueva cae en una zona de otra plaza (ver la decisión del cambio de plaza).
- **503** como en el registro.

## Risks / Trade-offs

- [DD-053 sigue abierta y define la entrada al registro y el alta] → Se construye sin esperarla lo que no depende de
  ella: el registro, la plaza, el perfil, las ventanas, la edición, la suspensión y la baja. La entrada (E04) y el alta
  (E11, E12) esperan. Si DD-053 decide que el alta la captura la administración sin registro previo en la app, las
  validaciones de E01 y E02 se reusan en el formulario del panel.
- [El estado de la lavandería depende en parte de DD-053] → Suspendida y dada de baja salen del contrato. El modelo ya
  trae los cuatro valores de `lavanderias.estado`; DD-053 decide si un registro de la app pasa por `pendiente`.
- [La plaza depende de que las zonas estén cargadas] → Sin `plazas-y-zonas` terminado, E09 y E10 no se pueden probar.
- [Sin lavanderías reales no se prueba con datos reales] → Se prueba con fábricas y datos sembrados. Las lavanderías
  reales dependen del reclutamiento de EL CLIENTE (DD-021).
- [`perfil-de-lavanderia` crea `lavanderias` por su lado] → Si al implementarse ya trae `estado` o `aprobada_en`, esta
  migración no los repite; lo que vale es la tabla final de `modelo-de-datos.md`.
- [La app nombra cada foto que ya tiene por su lugar en su última lectura, y el servidor lo cuenta sobre la lista del
  momento de guardar] → Si la app y el panel cambian las fotos de la misma lavandería a la vez, la app puede conservar o
  quitar una foto distinta de la que quería, sin error: la app leyó [A, B, C], el panel las reordena a [C, A, B] y la
  lavandería quita C mandando [0, 1], que conserva C y A y quita B. El panel no tiene este riesgo: revisa, con la
  lavandería bloqueada, que las fotos sigan siendo las que leyó. Cerrarlo en la app pide una huella de la lista en la
  lectura (por ejemplo, un hash del orden de las rutas, que no expone ninguna) y un 422 en `fotos` si al guardar ya no
  coincide; cambia el contrato de la API y queda fuera de este cambio.
- [El aviso del selector del panel al pasar del máximo sale en inglés] → El selector de Filament lo arma en el
  navegador con la traducción de Laravel `validation.max.array`, y el servidor no tiene traducciones propias
  (`servidor/lang/` no existe). El mensaje de la regla al guardar sí sale en español. Agregar las traducciones toca el
  panel entero y queda fuera de este cambio.
- [Las horas no caben en el plan] → Las tareas listas suman 48 h, con las 12 h de la descripción y las fotos del perfil
  (tareas 2.4, 3.4 y 4.3), y las bloqueadas 20 h más, contra 15 h del plan para F-B01, F-D01 y F-B06. Se reporta para la
  recalibración de S-006.

## Open Questions

1. **La compresión de la foto.** ADR-0006 deja a la spec el tamaño y la calidad, y no hay dato para fijarlos. Se
   construye con parámetros de configuración; los valores los decide Arthur. **Resuelta el 2026-11-24** (concilio del
   2026-11-24, por delegación de Arthur (C-236, C-245); `acta-2026-11-20-modelo-y-pasarela.md`, decisión 47): se
   ratifican los valores de configuración, el lado mayor de 1600 px y la calidad de 0.7 en la app
   (`movil/src/configuracion/fotos.ts`) y el peso máximo de 5120 KB en el servidor (`servidor/config/fotos.php`). Se
   miden en el Android de Arthur (C-243) sin bloquear nada: medir solo puede mover un número de la configuración.
2. **La librería que toma, elige y comprime la foto** en la app no la nombra ningún ADR. ¿Arthur la considera una
   decisión que necesita ADR? **Resuelta el 2026-11-24** (concilio del 2026-11-24, por delegación de Arthur (C-236,
   C-245); `acta-2026-11-20-modelo-y-pasarela.md`, decisión 47): sin ADR propio. `expo-image-picker` y
   `expo-image-manipulator` son módulos del SDK de Expo, que fija ADR-0002.
3. **La descripción y las fotos del perfil.** El perfil que ve el usuario muestra "Fotos, descripción, servicios,
   precios por kilo, calificación y reseñas" (`NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #5`), pero el
   registro pide una "foto" y ninguna descripción (`:17:Módulo B #1`). ¿Entran por "Edición de información de la
   lavandería" (`:17:Módulo B #6`) o por la "edición de socios" del panel (`:19:Módulo D #1`)? Hay que acordarlo con
   `perfil-de-lavanderia`. El modelo ya tiene `lavanderias.descripcion` y `lavanderias.telefono`, opcionales, y
   `lavanderia_fotos` admite varias fotos; el registro no los pide y este cambio no agrega ningún campo al formulario.
   **Decidida el 2026-11-24** (concilio del 2026-11-24, por delegación de Arthur (C-236, C-245);
   `acta-2026-11-20-modelo-y-pasarela.md`, decisión 44): la descripción, opcional, y las fotos las captura la propia
   lavandería en Perfil y horarios ("Edición de información de la lavandería", `:17:Módulo B #6`), y el panel las
   hereda porque usa las mismas reglas (`:19:Módulo D #1`). El registro sigue pidiendo una sola foto
   (`:17:Módulo B #1`). No necesita migración: `lavanderias.descripcion` y `lavanderia_fotos.orden` ya existen.
   **Resuelta con alta-lavanderias-E26 a E31**, que fijan cómo se escribe la descripción y cómo se agregan, se quitan y
   se ordenan las fotos, y cuál es el máximo. Se escribieron antes que su código, y los implementan las tareas 2.4, 3.4
   y 4.3 (sección "La descripción y las fotos del perfil").
4. **Las pantallas no tienen componente en el design system.** El registro y el perfil y horarios del Módulo B no
   están entre los seis componentes (`vault/design-system/componentes.md`); las imágenes `b01-registro` y `b06-perfil`
   de la Propuesta 04 orientan. La Definition of Ready pide el componente o una tarea para crearlo.
5. **El cambio de dirección a otra plaza.** Si una lavandería edita su dirección y cae en otra plaza, ¿cambia de plaza
   sin más, o eso se hace desde el panel? El contrato no lo dice. **Resuelta el 2026-11-24** (concilio del 2026-11-24,
   por delegación de Arthur (C-236, C-245); `acta-2026-11-20-modelo-y-pasarela.md`, decisión 45): no cambia de plaza.
   La edición se rechaza con el motivo `en_otra_plaza`, desde la app y desde el panel (alta-lavanderias-E25), y una
   lavandería que se muda de plaza se da de baja y se registra con otra cuenta.
6. **Lo que pasa con los pedidos en curso** de una lavandería suspendida o dada de baja, y lo que ve en su app mientras
   está suspendida (E16). Es DD-104.
7. **El cupo por ventana** (BR-17, E23). Es DD-099. Cuando responda, entra como columna de `lavanderia_horarios`
   (`modelo-de-datos.md`).
8. **DD-022.** Si la lavandería puede entregar con su propia gente, ¿el registro tiene que declararlo? Hoy el Módulo B
   #1 no lo pide y no se agrega.
9. **El estado con que nace un registro de la app, mientras DD-053 siga abierta.** El requisito "solo una lavandería con
   alta aparece al usuario y recibe pedidos" apunta a `pendiente` hasta el alta del panel, con `aprobada_en` vacío. Es
   una lectura, no una decisión: la confirma DD-053. E01 se prueba sin afirmar el estado.
10. **La baja usa `estado = 'baja'` y no `deleted_at`.** El modelo tiene las dos cosas en `lavanderias` y no dice cuál
    usa la baja del Módulo D #1. Con el borrado suave de Eloquent, una lavandería dada de baja desaparecería del panel y
    de las relaciones de sus pedidos. ¿Arthur confirma la lectura, y para qué queda `deleted_at` en esta tabla?
    **Resuelta el 2026-11-24** (concilio del 2026-11-24, por delegación de Arthur (C-236, C-245);
    `acta-2026-11-20-modelo-y-pasarela.md`, decisión 46): la baja es `estado = 'baja'` sin borrado suave, y no cambia
    código (alta-lavanderias-E17). `deleted_at` queda sin uso en `lavanderias` y no se quita (ADR-0016, regla 9: las
    migraciones van solo hacia adelante). Queda una asimetría con el repartidor, cuya baja sí llena `deleted_at`
    (`servidor/app/Repartidores/EstadoDelRepartidor.php:88-90`).
11. **Horario y ventanas en `lavanderia_horarios`.** Dos choques con la lectura de "una fila, una ventana de una hora":
    - `agenda-de-recoleccion` lee el horario como un tramo que parte en ventanas de una hora. Con filas de una hora las
      dos lecturas dan las mismas ventanas; pero si `perfil-de-lavanderia` siembra tramos largos (9:00 a 18:00), la
      edición de E22 los rechazaría.
    - E24 (DD-078) distingue un horario de atención de las ventanas, y el modelo no tiene dónde guardar los dos por
      separado. Si DD-078 lo pide, entra como columna o tabla nueva (ADR-0016, regla 9).

    Lo decide Arthur con `perfil-de-lavanderia` y `agenda-de-recoleccion`; este cambio no mueve la tabla.
