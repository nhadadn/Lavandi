## Context

- El Módulo D vive en el panel web hecho con Filament (ADR-0003; DD-040 cerrada por C-140). La app trae además una
  vista reducida para el administrador (`c2-contenedores.md`), en su lectura mínima: el aviso con el botón que abre
  el panel (administracion-E25; Open Questions 1, resuelta).
- Cubre F-D03 a F-D07. F-D01 y F-D02 viven en `alta-de-lavanderias` y en `repartidor`.
- Se apoya en piezas de otros cambios, que llegan antes en el plan: las plazas y zonas, el rol de administración y la
  entrada al panel (ADR-0005), los estados del pedido, el pago confirmado por el webhook de la pasarela (ADR-0010), los
  dispositivos registrados para avisos (ADR-0008) y las direcciones con coordenadas (ADR-0009).
- Lo que más falta es del cliente: el modelo del dinero (DD-013) y la comisión (DD-020).
- La pasarela es Conekta, la del contrato, mientras el cliente no firme el cambio (DD-079, C-192, C-216). Las tablas
  de este cambio no nombran proveedor; la conciliación es con Conekta, por la interfaz neutral del cobro (segunda
  adenda del 2026-11-22 de ADR-0010, que cerró DD-112), y no espera la firma.
- Las tablas siguen `vault/arquitectura/modelo-de-datos.md` y ADR-0016 (C-193).

## Goals / Non-Goals

**Goals:**

- Las cinco funciones con lo mínimo que cumple el A.4.3: cuatro módulos funcionales y datos reales de pedidos y
  usuarios activos.
- Las comisiones configurables desde ya, sin valores de fábrica, para que DD-020 solo llene datos.
- Una sola fuente para ingresos y comisiones, que usan también `indicadores` y `exportacion`.

**Non-Goals:**

- Mover dinero: ni liquidar ni dispersar a los socios (`NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3`, Módulo C).
- Rastrear al repartidor en el mapa.
- Dar de alta, editar o borrar usuarios desde el panel. La cuenta de administración se crea con un comando, sin
  pantalla (administracion-E26; "La cuenta de administración", abajo).
- Consultas rápidas o avisos nativos de la administración en la app: la app solo abre el panel (administracion-E25).
- Asignar repartidores (DD-014, cambio `repartidor`).

## Decisions

No hay decisión técnica nueva. Se aplican los ADR vigentes:

- **Panel:** recursos y páginas de Filament, con sesión web y rol de administración (ADR-0003, ADR-0005).
- **Refresco del monitoreo:** consulta periódica de 30 s (ADR-0007).
- **Mapa del monitoreo:** Google Maps JavaScript, con plugin de Filament o componente propio (ADR-0009).
- **Avisos masivos:** FCM desde `api` con `laravel-notification-channels/fcm` (ADR-0008).
- **Ingresos:** solo cuentan los pagos que la pasarela confirmó por webhook, los de `pagos.estado = 'pagado'`
  (ADR-0010).
- **Plaza y horas:** toda fila de operación lleva su plaza; las horas se guardan como instante absoluto y se presentan
  en la zona de la plaza (ADR-0004, `modelo-multi-plaza.md`).
- **La administración en la app** (administracion-E25; Open Questions 1): la vista del navegador del sistema dentro de
  la app, con `expo-web-browser`, la misma del checkout (adenda del 2026-11-22 de ADR-0010, decisión 32).
- **La cuenta de administración** (administracion-E26; Open Questions 6): un comando de Artisan, sin pantalla, como
  `plazas:cargar` (decisión 68 de `acta-2026-11-20-modelo-y-pasarela.md`, C-245). Ver "La cuenta de administración",
  abajo.

**Lecturas del contrato que hizo el especificador.** No son decisiones técnicas. Confirmadas por el concilio del
2026-11-24, como Claude por delegación de Arthur (C-245; `acta-2026-11-20-modelo-y-pasarela.md`, decisión 57), 3 de 3,
las cinco como están. Son lecturas literales del Módulo D #3 a #6 (`NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3`) y del
A.5 (`NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5`), y las sostienen las pruebas
`servidor/tests/Feature/Administracion/MonitoreoDePedidosTest.php:56` (1), `ConfiguracionDeComisionesTest.php:128` (2),
`BloqueoDeUsuariosTest.php:202` (3), `ReportesFinancierosTest.php:65` (4) y `MapaDelMonitoreoTest.php:215` (5):

1. **Pedido activo** es el confirmado que no se ha entregado ni cancelado. El A.5 separa activos, completados y
   cancelados (`NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5`).
2. **"Por socio o global"**: el porcentaje propio de un socio prevalece sobre el global.
3. **"Bloqueo"** incluye levantarlo.
4. **"Ingresos"** es lo cobrado con pago confirmado; las comisiones van aparte, como las enumera el Módulo D #6.
5. **El mapa** pone un marcador en la dirección de recolección y otro en la de entrega si es distinta (BR-30).

Con C-188, "confirmado" es pagado: el pedido llena `confirmado_en` solo cuando la pasarela confirma el pago. Un pedido
sin pago acreditado no está activo y no entra al monitoreo (lectura 1). El rechazado sigue en el monitoreo hasta que
respondan DD-103 y DD-016, de EL CLIENTE ("Monitoreo de pedidos", abajo).

## Tablas y migración

Una sola migración, hacia adelante (ADR-0016, regla 9), en la tarea 0.1. Pasa la prueba de esquema de
`modelo-de-datos` (modelo-E01 a modelo-E17). Ninguna tabla de este cambio lleva el trigger `impedir_cambio()`, que es
solo de `pedidos`, `pedido_eventos`, `pagos` y `reembolsos`. Todas las llaves foráneas llevan su índice y
`ON DELETE RESTRICT`.

**Crea:**

1. `comisiones` (E11 a E16 y E19):
   - Columnas: `id_comisiones`, `id_plazas`, `id_lavanderias`, `porcentaje`, `vigente_desde`, `vigente_hasta`.
   - Llaves foráneas: `id_plazas` → `plazas.id_plazas`; `(id_lavanderias, id_plazas)` →
     `lavanderias(id_lavanderias, id_plazas)`, compuesta con la plaza.
   - `id_lavanderias` e `id_plazas` vacíos son la comisión global, una sola para todo el sistema (E11); con valor, la
     propia de esa lavandería, con su plaza (E12). El `CHECK` `comisiones_global_o_propia_check` no deja otra
     combinación. **Así quedó con la migración `2026_11_26_100000_hacer_global_la_comision_global`**, hacia adelante:
     la tarea 0.1 la había hecho global de cada plaza (Open Questions 4, ya resuelta). Por eso `comisiones` está en la
     lista de "plaza opcional" de la prueba de esquema de `modelo-de-datos`.
   - `porcentaje` es `numeric(5,2)`, con `CHECK` de 0 a 100 (E13).
   - `vigente_desde` y `vigente_hasta` son `timestamptz`, con `CHECK` de que la vigencia termina después de empezar
     (la regla general de integridad del modelo: "ventana que termina después de empezar").
     Un cambio de porcentaje es una fila nueva y la anterior queda como historial (`modelo-de-datos.md`); qué pasa con
     los pedidos ya cobrados es DD-020 (E16).
   - La migración no siembra ningún porcentaje: los captura EL CLIENTE.
   - A lo más una fila abierta (sin `vigente_hasta`): una global en todo el sistema y una propia por lavandería, con
     dos índices únicos parciales (`comisiones_global_abierta_unique` y `comisiones_propia_abierta_unique`). Lo agregó
     el implementador con la tarea 0.1: sin él, dos cambios a la vez dejarían dos porcentajes vigentes.
   - La migración que la hizo global convierte lo que hubiera sin inventar ningún porcentaje: las globales de cada
     plaza pasan a la global del sistema con su historial; de las abiertas queda la que se fijó al último, y las demás
     se cierran en el instante en que empezó esa (una que empezó en ese mismo instante se borra, porque nunca rigió
     sola).
2. `avisos_masivos` (E23 y E24):
   - Columnas: `id_avisos_masivos`, `id_plazas`, `segmento`, `titulo`, `mensaje`, `id_usuarios`, `enviado_en`,
     `destinatarios`.
   - Llaves foráneas: `id_plazas` → `plazas.id_plazas`; `id_usuarios` → `usuarios.id_usuarios`, la cuenta de la
     administración que lo envió.
   - `id_plazas` es **opcional**: vacío es un aviso global (E23). Por eso `avisos_masivos` entra a la lista de "plaza
     opcional" de la prueba de esquema de `modelo-de-datos`. Con valor, su llave foránea a `plazas` se revisa como en
     cualquier otra tabla.
   - `segmento` es `jsonb`; sus criterios son DD-113 (E24).
   - `enviado_en` es `timestamptz`. `destinatarios` guarda a cuántos dispositivos se mandó (propuesta: el modelo no
     dice su tipo).
   - **Cómo quedó en la tarea 0.1:** `titulo` es `varchar(255)` y `mensaje` es `text`, los dos no nulos; `id_usuarios`
     y `enviado_en` (cuándo lo envió la administración) son no nulos; `destinatarios` es `integer`, vacío hasta que el
     envío lo cuente (tarea 5.1), con `CHECK` de no negativo; `segmento` es `jsonb` opcional, vacío sin segmento.

Las dos tablas llevan `created_at` y `updated_at` en `timestamptz`, como todas las tablas del negocio con llave propia.

**Crea, en la tarea 0.2,** con su propia migración hacia adelante (`2026_11_25_100000_crear_movimientos_pasarela`):

3. `movimientos_pasarela` (E20 a E22), con la forma que fija la segunda adenda del 2026-11-22 de ADR-0010 (decisiones 6
   y 7, que cierran DD-112): los pagos que acreditó la pasarela, leídos de su API por la interfaz neutral del cobro.
   - Columnas: `id_movimientos_pasarela`, `pasarela`, `id_pasarela_orden`, `monto`, `comision`, `pagado_en`, y
     `created_at` y `updated_at` en `timestamptz`.
   - Una fila por orden: único `(pasarela, id_pasarela_orden)`. Leer dos veces el mismo periodo actualiza la fila, no la
     repite.
   - `pasarela` lleva el mismo `CHECK` que `pagos` (`conekta` o `simulada`); `monto` es `numeric(12,2)` mayor que cero;
     `comision` es `numeric(12,2)` opcional, no negativa: la que retiene la pasarela, si la trae (E22, DD-013);
     `pagado_en` es `timestamptz` no nulo, la fecha del pago en la pasarela.
   - **Sin plaza propia y sin llave foránea:** la cuenta de la pasarela es una para todas las plazas y la plaza de un
     movimiento es la del pedido con el que cuadra, por `(pasarela, id_pasarela_orden)` con `pagos`. Un movimiento sin
     pedido es justo una diferencia (E21), así que no puede apuntar a `pagos`. Ya estaba en la lista `sin_plaza` de
     `servidor/config/modelo.php`; ahora lleva escrita esa razón (pregunta abierta 10, resuelta).
   - Sin el trigger `impedir_cambio()`: no es historial del pedido ni del dinero de la plataforma; es la copia de lo que
     dice la pasarela.
   - No nombra a ningún proveedor ni guarda datos de tarjeta (modelo-E13 y modelo-E14). Su modelo es
     `App\Models\MovimientoPasarela`.

**Los modelos de Eloquent** (`Comision` y `AvisoMasivo`) heredan de `App\Models\Modelo` y declaran su `$table` y su
`$primaryKey` (modelo-E16).

**Solo usa** tablas que crean otros cambios:

| Tabla | Para qué | La crea |
|---|---|---|
| `usuarios` | La lista y la ficha (E01); el bloqueo escribe `estado = 'bloqueado'` y `bloqueado_en`, y levantarlo vuelve a `activo` (E03 y E04) | `registro-e-inicio-de-sesion` |
| `rol_usuario`, `roles` y `cuentas_externas` | La entrada al panel con rol de administración; rechazar la entrada con Google y Apple de una cuenta bloqueada (E03) | `registro-e-inicio-de-sesion` |
| `personal_access_tokens` | Revocar los tokens de Sanctum al bloquear (E03). Es del framework | `base-tecnica` |
| `actividad_usuarios` | Los usuarios activos del periodo (E02, bloqueado por DD-111) | `indicadores`, que llega después en el plan |
| `pedidos` y `estados_pedido` | El historial del usuario (E01) y los pedidos activos (E06 a E08) | `resumen-y-confirmacion` |
| `pedido_eventos` | Los cambios de estado que refresca el monitoreo (E07) | `resumen-y-confirmacion` (base) y `estados-del-pedido` |
| `pedido_direcciones` | Los marcadores de recolección y de entrega, de su `ubicacion` (E09 y E10) | `resumen-y-confirmacion` |
| `lavanderias` | El porcentaje propio por socio (E12) y la lavandería del historial (E01) | `perfil-de-lavanderia` y `alta-de-lavanderias` |
| `plazas` | La plaza de cada pedido y la `zona_horaria` de los reportes (E06 y E17) | `plazas-y-zonas` |
| `pagos` | Los ingresos: `estado = 'pagado'`, `monto` y `pagado_en` (E17) | `pago` |
| `reembolsos` | Los reembolsos en los ingresos (E18, bloqueado) | `pago` |
| `dispositivos` | Los `token_fcm` a los que va un aviso masivo (E23) | `avisos` |
| `repartidores` | La comisión de un repartidor (E14, bloqueado por DD-019) | `repartidor` |

## El panel

Lo implementó el implementador del servidor, tarea por tarea. Todo vive en el panel de Filament, `/admin`, solo con el
rol de administración (ADR-0005; autorizacion-E03). Cada página pregunta al componente único de autorización
(`App\Autorizacion\Autorizacion`, ADR-0019) qué filas ve la cuenta, y una cuenta de otro rol recibe 403.

**Usuarios** (`/admin/usuarios`; tarea 1.1, administracion-E01):

- **La lista:** las cuentas con el rol de usuario, con su correo, su nombre y su estado (Activo o Bloqueado), con
  búsqueda por correo y por nombre. No trae las cuentas de las lavanderías (se gestionan con su lavandería, Módulo D #1)
  ni las de la administración. La da `Autorizacion::usuariosDelPanel()`.
- **La ficha** (`/admin/usuarios/{id_publico}`): la cuenta (correo, nombre, teléfono y estado) y su historial de
  pedidos, una tabla con folio, fecha, lavandería, estado y monto, del más reciente al más antiguo. Los pedidos los da
  `Autorizacion::pedidosDelPanel()`: los confirmados; uno que espera su pago no tiene folio y no entra (C-188).
  - **Fecha:** la de la recolección en la hora de la plaza, como en el historial del usuario
    (`historial-y-calificacion`, decisión 1), escrita DD/MM/AAAA.
  - **Estado:** el operativo (ADR-0021 y su adenda): Confirmado, Aceptado, Recolectado, En proceso, Listo, En proceso
    de entrega, Entregado o Rechazado. La administración distingue lo que el usuario ve igual.
  - **Monto:** el final si ya se pesó y, si no, el estimado, en pesos. Hoy los dos nacen vacíos (DD-012 y DD-010), y la
    ficha dice "Sin monto" hasta que se respondan. No es el cobro: lo cobrado vive en `pagos` y llega con `pago`.
- **Solo lectura:** sin alta, edición ni borrado (fuera del contrato). El filtro de usuarios activos del periodo espera
  DD-111 (E02).

**Bloquear y levantar el bloqueo** (tarea 1.3, administracion-E03 y E04), con un botón en cada fila de la lista y en
la cabecera de la ficha, con confirmación. "Bloquear" solo aparece en una cuenta activa y "Levantar el bloqueo" solo en
una bloqueada.

- **Bloquear** (`App\Identidad\BloqueoDeCuenta`) escribe `usuarios.estado = 'bloqueado'` con la hora en `bloqueado_en`
  y revoca todos los tokens de Sanctum de la cuenta: la siguiente petición de la app con cualquiera de sus sesiones
  responde `401`. No toca sus teléfonos registrados, sus direcciones ni sus pedidos (E05 espera DD-016).
- **Mientras esté bloqueada**, el componente no le abre sesión por ninguna vía (`Autorizacion::puedeIniciarSesion()`):
  - `POST /api/sesion` con la contraseña correcta responde
    `422 { "message": "Esta cuenta está bloqueada.", "errors": { "correo": ["Esta cuenta está bloqueada."] } }`, sin
    token. Con otra contraseña, la respuesta de siempre ("Las credenciales no son válidas."): no se dice que la cuenta
    existe a quien no sabe su contraseña.
  - `POST /api/sesion/google` responde el mismo mensaje en `errors.id_token`, ya ligada la cuenta de Google o por su
    correo, y no liga nada ni anula la contraseña.
  - La entrada con Apple no existe todavía (DD-069): cuando llegue, pregunta al mismo método.
  - Si le quedara un token, `Autorizacion::permite()` lo rechaza con `403` en toda ruta, y una cuenta de administración
    bloqueada tampoco entra al panel.
- **Levantar el bloqueo** la deja en `activo`, con `bloqueado_en` vacío, y la persona vuelve a entrar con su contraseña
  o con Google.
- **Quién bloquea a quién:** solo la administración, y solo a las cuentas de usuario de la lista
  (`Autorizacion::puedeBloquear()`). Las de los socios se suspenden con su lavandería o su repartidor (Módulo D #1 y
  #2).

**Monitoreo de pedidos** (`/admin/monitoreo`; tarea 2.1, administracion-E06): una tabla con los pedidos activos de
todas las plazas, con su folio (con búsqueda), su estado y su plaza, de la recolección más próxima a la más lejana. Es
de solo lectura. Los pedidos los da `Autorizacion::pedidosActivosDelPanel()`, y cuáles están activos,
`Pedido::activosDelMonitoreo()`:

- **Activos** (lectura 1): los confirmados que no se han entregado ni cancelado, en `confirmado`, `aceptado`,
  `recolectado`, `en_proceso`, `listo`, `en_entrega` o `rechazado`. El estado sale con su nombre operativo, como en la
  ficha del usuario.
- **El rechazado se queda** mientras DD-103 y DD-016 no digan qué pasa con él: está pagado y no se entregó ni se canceló.
  Si esas filas lo llevan a un estado de cancelación, sale por E08.
- **No entran** el entregado, el que espera su pago (C-188) ni un estado que se agregue después, como `cancelado`,
  mientras no se nombre en el modelo (E08 espera DD-016 y BR-22).
- **El mapa** (tarea 2.4, administracion-E09 y E10): ver "El mapa del monitoreo", abajo.
- **El refresco** (tarea 2.2, administracion-E07): la tabla se vuelve a consultar sola cada 30 segundos mientras la
  página está abierta (`wire:poll.30s` de Livewire, con `poll()` de Filament; ADR-0007), sin recargar la página. Un
  cambio de estado que hace la lavandería, un pedido que se entrega o uno que se confirma se ven en el siguiente
  refresco, a lo más 30 segundos después.

**El mapa del monitoreo** (tarea 2.4, administracion-E09 y E10; lectura 5; BR-30), arriba de la tabla, en la misma
página (`App\Filament\Pages\MonitoreoDePedidos`):

- **Qué pinta:** los mismos pedidos activos de la tabla, que da `Autorizacion::pedidosActivosDelPanel()`, con un
  marcador en la dirección de recolección de cada uno y otro en la de entrega cuando es distinta
  (`App\Pedidos\MarcadoresDelMonitoreo`). Cada marcador lleva su letra en el mapa, R en la recolección y E en la entrega,
  con una leyenda debajo; al elegirlo, su ventana dice el folio, el estado operativo (como la tabla), si es la
  recolección o la entrega y la plaza, como texto. Ningún repartidor: su posición está fuera del contrato (proposal.md).
- **Cuándo la entrega es distinta** (lectura del implementador): cuando su copia en `pedido_direcciones` salió de otra
  dirección guardada que la de la recolección (`id_direcciones`). Sin otra dirección de entrega, la app copia la misma
  dirección dos veces (direcciones-E09), y el pedido lleva un solo marcador, en la recolección, que dice "Recolección y
  entrega". Dos direcciones guardadas distintas en el mismo punto llevan dos marcadores, uno encima del otro.
- **De dónde salen los puntos:** de la `ubicacion` que se guardó con cada dirección, leída con PostGIS
  (`PedidoDireccion::conCoordenadas()`). El mapa no geocodifica nada (`presupuesto-de-mapas.md`, decisión 3).
- **El mapa es Google Maps JavaScript** (ADR-0009, decisión 3) y sigue el camino del mapa de calor de los indicadores,
  con las mismas piezas (`App\Filament\MapaDelPanel`): la llave `GOOGLE_MAPS_PANEL_LLAVE`, el aviso sin llave ("El mapa
  no está disponible porque falta la llave de Google Maps del panel. Los pedidos están en la tabla."), el contenedor con
  `wire:ignore` y el cargador, que trae la API una sola vez por página.
- **Una carga de mapa por visita** (`presupuesto-de-mapas.md`: "una sesión por turno de trabajo"): el mapa se crea una
  vez al abrir la página. El refresco de 30 segundos de la tabla vuelve a dibujar la página y con ella los datos del mapa
  (el atributo `data-mapa-datos`), pero no su contenedor; el script vuelve a poner los marcadores sobre el mismo mapa y
  Livewire no le manda el script otra vez. No hay un segundo sondeo: el mapa y la tabla salen de la misma consulta
  periódica (ADR-0007), así que nunca difieren.
- **Lo que hace el script al refrescar** (lectura del implementador, para que el refresco no estorbe): el mapa se
  encuadra solo la primera vez que hay pedidos; después no mueve lo que la administración ve. Si tenía abierto un
  marcador que sigue en el mapa, lo deja abierto con sus datos nuevos; si su pedido salió, lo cierra.
- **El marcador es `google.maps.Marker`** (lectura del implementador). Google lo marcó como obsoleto en la versión 3.56
  (febrero de 2024) a favor de `AdvancedMarkerElement`, pero "at this time, google.maps.Marker is not scheduled to be
  discontinued" y avisará con al menos 12 meses (grado E, `https://developers.google.com/maps/deprecations`, consultado
  el 2026-11-22, `SIN VERIFICAR` en el navegador). `AdvancedMarkerElement` pide además un identificador de mapa creado
  en la consola de Google Cloud de cada entorno: otra variable y otro trámite. Si Google pone fecha de retiro, se cambia
  el marcador dentro de la vista del mapa, con ese identificador; el resto no cambia.
- **Quién lo ve:** la página ya pregunta al componente (`puedeEntrarAlPanel()`), y los marcadores salen de los pedidos
  que el componente le da a la cuenta; a una de otro rol no le da ninguno.

**Comisiones** (tarea 3.1, administracion-E11 a E13), dos páginas en el grupo "Comisiones" del menú. Las reglas y la
vigencia viven en `App\Comisiones\PorcentajesDeComision`, no en las páginas:

- **Comisión global** (`/admin/comision-global`): un solo porcentaje para todo el sistema, con su valor vigente ("Sin
  porcentaje" si no hay), desde cuándo rige y el botón "Fijar porcentaje". Aplica a toda lavandería, de cualquier
  plaza, sin porcentaje propio (Open Questions 4). Lo fija solo la administración
  (`Autorizacion::puedeFijarLaComisionGlobal()`), que es una para todas las plazas: el contrato no nombra una
  administración por plaza (`autorizacion-por-rol`, Non-Goals).
- **Comisión por lavandería** (`/admin/comision-por-lavanderia`): una fila por lavandería de todas las plazas
  (`Autorizacion::lavanderiasDelPanel()`), con su porcentaje propio ("Sin porcentaje propio") y el que le aplica, con
  de dónde sale: "7.25 % (propio)", "10.00 % (global)" o "Sin porcentaje". Los botones "Fijar porcentaje
  propio" y "Quitar porcentaje propio" (solo si tiene uno; al quitarlo le vuelve a aplicar el global).
- **Sin valores de fábrica** (E11): la tabla nace vacía; sin porcentaje guardado no aplica ninguno.
- **El rango** (E13): de 0 a 100, con a lo más dos decimales. El formulario lo rechaza con su mensaje ("El porcentaje va
  de 0 a 100.") y el vigente no cambia; `PorcentajesDeComision` valida lo mismo, y la base tiene su `CHECK`.
- **La vigencia:** fijar un porcentaje cierra el vigente en ese instante (`vigente_hasta`) y abre una fila nueva; el
  anterior queda como historial y `queAplica()` responde para cualquier instante. Quitar el propio lo cierra igual. Un
  cambio en el mismo segundo que el anterior lo reemplaza, porque ese no llegó a regir. Cuál se usa para un pedido ya
  cobrado lo decide DD-020 (E15 y E16).
- El porcentaje de un socio repartidor espera DD-019 (E14).

**Ingresos** (`/admin/ingresos`; tarea 4.1, administracion-E17), en el grupo "Reportes financieros" del menú. Es un
tablero de Filament con sus filtros, como el de indicadores (`App\Filament\Pages\ReporteDeIngresos`):

- **El periodo:** la administración elige el primer y el último día, los dos incluidos, y si lo ve por día, semana o mes,
  con las mismas reglas del rango de los indicadores (`App\Indicadores\Rango`): por semana o por mes se ajusta a
  periodos completos, y sin filtros es el mes en curso hasta hoy.
- **Lo que muestra:** los ingresos de todo el periodo, en pesos, con cuántos pagos confirmados son, y una tabla con los
  de cada periodo (periodo, pagos confirmados e ingresos). Es de solo lectura y no se refresca sola: el contrato no lo
  pide para un reporte, y las cifras cambian al mover los filtros o al recargar.
- **Qué cuenta (lectura 4):** cada pago en `pagado`, el estado que solo pone el aviso auténtico de la pasarela
  (`App\Cobro\RecepcionDeAvisos`; ADR-0010, decisión 1). No cuentan una orden creada y sin confirmar, un intento
  rechazado (`fallido`) ni un cobro vencido o cancelado. Entra cualquier `tipo`: hoy solo existe el cobro; el ajuste de
  DD-012 entraría como lo cobrado que es, y qué hacer con él lo dice administracion-E18.
- **Los montos y DD-012 (lectura del implementador):** el ingreso es `pagos.monto`, lo que la pasarela cobró, no el
  monto estimado ni el final del pedido. Qué monto se cobra lo fija el modelo de cobro (pago-E14, DD-012): este cálculo
  no lo afirma ni depende de esa respuesta. Los reembolsos no se descuentan todavía (administracion-E18, bloqueado por
  DD-012 y DD-016): son ingresos brutos.
- **La fecha:** la de la confirmación del pago (`pagos.pagado_en`), en la zona horaria de la plaza del pedido
  (`modelo-multi-plaza.md`): un pago de las 23:30 del 31 de marzo cuenta en marzo aunque en UTC ya sea abril.
- **Una sola fuente:** `App\Finanzas\Ingresos` calcula el total y cada periodo; el indicador de ingresos brutos
  (`indicadores`, indicadores-E16) y las descargas (`exportacion`) leen de ahí, así que nunca difieren.
- **Quién lo ve:** `Autorizacion::puedeVerLosReportesFinancieros()`, solo la administración; la página y sus dos widgets
  le preguntan, y un widget montado por otra cuenta responde 403. Cuentan los pagos de todas las plazas.
- Las comisiones del periodo esperan la base de cálculo (administracion-E19, DD-020) y la conciliación vive en su propia
  página.
- **La descarga:** el botón "Descargar" de la página baja la tabla en CSV y en Excel, con el total del periodo al final
  (`exportacion`, tarea 1.3).

**Conciliación con la pasarela** (`/admin/conciliacion`; tareas 4.4 y 4.5, administracion-E20 y E21), en el grupo
"Reportes financieros" (`App\Filament\Pages\Conciliacion`), con la segunda adenda del 2026-11-22 de ADR-0010
(decisiones 6 y 7, que cierran DD-112):

- **Conciliar:** el botón "Conciliar un periodo" pide el primer y el último día, los dos incluidos; uno al revés se
  rechaza con "La fecha de fin no puede ser anterior a la de inicio.". Al conciliar, el servidor lee de la pasarela sus
  pagos del periodo y los guarda; la tabla muestra el resultado. Antes de conciliar, la tabla no muestra nada: sin leer
  la pasarela, todo pago parecería sin movimiento. No hay tarea programada que lea la pasarela (decisión 7).
- **La operación nueva de la interfaz neutral** (`Pasarela::pagosDelPeriodo()`): los pagos que la pasarela acreditó
  entre dos instantes, cada uno con el id de su orden, el monto, la comisión si la trae y la fecha del pago
  (`App\Cobro\MovimientoDePasarela`), autenticada con la llave del entorno (pago-E17). La implementan la pasarela simulada
  y, con la sección 3 de `pago`, la de Conekta: sus órdenes pagadas, de la más nueva hacia atrás, hasta pasar el
  inicio, con el corte por fecha en el servidor (grado E, `SIN VERIFICAR`; se comprueba en su sandbox).
- **Qué se le pide a la pasarela** (lectura del implementador): un día más a cada lado del periodo, en UTC, para que
  entren los pagos de cualquier plaza sea cual sea su zona horaria; lo que devuelva fuera de ese lapso no se guarda.
- **Cómo se guarda:** `App\Finanzas\ConciliacionConLaPasarela` hace un `upsert` en `movimientos_pasarela` por
  `(pasarela, id_pasarela_orden)`: leer dos veces el mismo periodo vuelve a pedirle a la pasarela y actualiza las filas,
  sin repetir ninguna. La comisión se guarda como llega, pero no se muestra ni se descuenta: quién la absorbe es DD-013
  (administracion-E22).
- **El cruce:** por la orden de la pasarela, `(pasarela, id_pasarela_orden)`, entre los pagos que la plataforma tiene
  como pagados en el periodo, los mismos que cuentan como ingreso (`App\Finanzas\Ingresos`, por el día de su
  confirmación en la hora de su plaza), y los movimientos ya guardados. La plaza de un movimiento es la del pedido con el
  que cuadra. Cada fila queda con uno de cuatro resultados (`App\Finanzas\ResultadoDeConciliacion`):
  - **Conciliado** (E20): el pago tiene su movimiento, con el mismo monto. Los montos se comparan como los guarda la
    base, con dos decimales.
  - **Monto que no coincide** (E21): la misma orden, con un monto en la plataforma y otro en la pasarela; van los dos.
  - **Pedido pagado sin movimiento** (E21): la plataforma lo tiene pagado y la pasarela no tiene su movimiento.
  - **Movimiento sin pedido** (E21): un movimiento del periodo que no cuadra con ningún pago pagado de la plataforma; sin
    folio ni plaza. **Lectura del implementador:** también lo es el de una orden cuyo cobro la plataforma no tiene como
    pagado, porque su aviso no llegó: no hay pedido pagado con el que cuadre. La spec nombra solo tres diferencias, y
    este caso es el primero.
- **El día de cada fila** (lectura del implementador): el de un pago, el de su confirmación en la hora de su plaza, como
  en los ingresos. Un pago y su movimiento cuadran aunque la pasarela feche el pago unos segundos antes de que llegue su
  aviso, y cuentan en el periodo del pago, sin diferencia en ninguno de los dos. Un movimiento sin pedido no tiene plaza,
  así que su día es el de su fecha en la zona horaria de referencia de lo que no tiene plaza
  (`app.zona_horaria_sin_plaza`; indicadores-E24), como el "hoy" de los indicadores
  (`openspec/changes/indicadores/design.md`, pregunta 8).
- **La tabla:** una fila por pago o movimiento, de la más antigua a la más nueva, con el resultado (una insignia con su
  texto), la orden en la pasarela, el folio, la plaza, la fecha del pago (en la hora de la plaza) y los dos montos, el
  de la plataforma y el de la pasarela; arriba, el periodo y cuántos hay de cada resultado. Es de solo lectura.
- **Sin pasarela** (sin llave, o en un entorno sin pasarela): la página dice "No se pudo leer la pasarela" y no guarda
  nada.
- **La descarga:** después de conciliar aparece "Descargar", que baja en CSV y en Excel las filas de la tabla del periodo
  conciliado, sin volver a leer la pasarela (`exportacion`, tarea 1.3).
- **Quién concilia:** `Autorizacion::puedeVerLosReportesFinancieros()`, solo la administración; la página y el botón le
  preguntan.
- **La pasarela simulada lleva sus libros:** cada aviso de aprobado que manda es un pago que acreditó, con el monto de su
  orden, y los lee `pagosDelPeriodo()`. Viven en la caché de la aplicación, no en la base, porque son de la pasarela, no
  de la plataforma: así duran entre peticiones en local (la página de checkout de desarrollo y `pago:simular-aviso`
  acreditan, y la conciliación los encuentra). En las pruebas, la caché es de memoria. Las pruebas pueden acreditar un
  pago con otro monto o quitar uno (`acreditar()` y `quitarPago()`), para simular las diferencias.

**Avisos masivos** (`/admin/avisos-masivos`; tarea 5.1, administracion-E23), en el menú como "Avisos masivos":

- **La lista:** los avisos enviados, del más reciente al más antiguo, con su título, su texto, cuándo y quién lo mandó
  y a cuántos teléfonos salió ("En cola" mientras no se cuentan). Es de solo lectura: un aviso enviado no se edita ni se
  borra. La da `Autorizacion::avisosMasivosDelPanel()`.
- **Escribir aviso:** título (hasta 255 caracteres, como la columna) y texto (hasta 1000), los dos obligatorios. El tope
  del texto es del implementador, para que el mensaje quepa en lo que FCM acepta por mensaje (grado E, `SIN VERIFICAR`:
  4096 bytes). Al guardarlo, la fila de `avisos_masivos` queda con la cuenta que lo mandó, `enviado_en` y sin plaza ni
  segmento: es global. El formulario no ofrece segmentos mientras DD-113 no los defina (E24).
- **El envío va en cola** (`App\Avisos\MandarAvisoMasivo`): el panel no espera a Google. El trabajo pregunta al
  componente qué teléfonos reciben el aviso (`Autorizacion::telefonosQueRecibenElAvisoMasivo()`), guarda a cuántos se
  manda en `destinatarios` y pone en cola un envío por teléfono (`App\Avisos\MandarAvisoMasivoAlTelefono`). Corre una
  sola vez, para no mandar el aviso dos veces.
- **Un envío por teléfono, con la lista de tokens de `dispositivos`** (pregunta abierta 3, para el aviso global): FCM
  HTTP v1, que ya usa el servidor (adenda de ADR-0008), manda a un token por petición. No se usan temas de FCM: la app
  tendría que suscribirse a ellos, y eso es de `movil/`. Si DD-113 define segmentos, la decisión se revisa con ellos.
- **Cada envío** sigue las reglas del aviso del pedido: vuelve a preguntar al componente si el teléfono todavía recibe
  el aviso, un token que FCM rechaza se borra (avisos-E06) y un fallo de Google se reintenta con las esperas de
  `avisos.esperas` (trait `App\Avisos\EsperasDeReintento`, que ahora comparten los dos envíos).
- **El mensaje** (`App\Avisos\MensajeMasivo`) es un aviso visible, con la parte de Android y la de iPhone, con el título
  y el texto; en `data` lleva solo `tipo: aviso_masivo`, porque no hay pedido que consultar.
- **Quién lo recibe** (lectura del implementador): los teléfonos registrados de todas las cuentas de la app que pueden
  usarla. No una cuenta bloqueada desde el panel, ni la de un repartidor dado de baja, ni una borrada: no pueden iniciar
  sesión (`Autorizacion::puedeIniciarSesion()`). Una cuenta sin teléfono no genera ningún envío. A quien apagó los
  avisos, el servidor se lo manda igual, como a todo teléfono registrado, y el sistema operativo lo detiene (pregunta
  abierta 5, resuelta).

## La cuenta de administración

`php artisan administracion:crear-cuenta {correo}` (`App\Console\Commands\CrearCuentaDeAdministracion`; tarea 7.1,
administracion-E26), con la decisión 68 de `acta-2026-11-20-modelo-y-pasarela.md` (C-245), que cierra la pregunta
abierta 6:

- **Por qué un comando:** el panel es la "Pantalla de control reservada al dueño del negocio"
  (`NSV-2026-001-v8.pdf:44:ANEXO D, Sección D.1`), y sin una cuenta de administración nadie entra a él ni ve sus datos
  (`NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3`). Fuera de local no había cómo crearla: las cuentas de desarrollo
  (`CuentasDeDesarrolloSeeder`) solo se siembran en local (ADR-0012). No lleva pantalla, porque el Módulo D #3 da
  consulta, historial y bloqueo, no alta; es el mismo camino que abrir una plaza con `plazas:cargar`. Dar de alta
  usuarios desde el panel sigue fuera del contrato (Non-Goals).
- **Qué pide:** el correo, como argumento; la contraseña, dos veces y con la entrada oculta, sin respaldo visible: si la
  terminal no la puede ocultar, falla en lugar de mostrarla. La pregunta se arma a mano y no con `secret()`, que le
  recorta los espacios del principio y del final; la app (`POST /api/sesion`) y el panel la comparan tal como se
  escribe. Nunca la recibe como argumento ni la escribe en la salida. Sin una terminal que pregunte
  (`--no-interaction`), falla.
- **Qué revisa, antes de crear nada:** el correo primero, con las reglas del registro (`App\Validacion\CorreoSinCuenta`:
  sin distinguir mayúsculas y contando las cuentas borradas), y solo después pide la contraseña; luego, la contraseña,
  con `App\Validacion\Contrasena` (ADR-0018, decisión 2: 8 caracteres como mínimo), y que las dos coincidan. Cada rechazo
  dice su motivo y no cambia nada. Si otra cuenta toma el correo mientras se escribe la contraseña, el único sobre
  `lower(correo)` detiene el alta, la transacción no deja nada y sale el mismo mensaje del correo ocupado, sin el error
  de la base, que traería el SQL con el hash.
- **Qué crea:** en una transacción, la cuenta con el correo normalizado, la contraseña cifrada con `Hash::make`, como
  `App\Identidad\AltaDeCuenta` (registro-E05), y solo el rol de administración. Nace activa. Con ella entra al panel
  (autorizacion-E03) y, si inicia sesión en la app con su correo y su contraseña, recibe 403 sin token (registro-E33).
  Por Google todavía recibe token y, si su Google no estaba ligado, se le anula la contraseña, hasta la tarea 6.2. Qué
  puede hacer la cuenta lo decide después `App\Autorizacion\Autorizacion` (ADR-0019); quien corre el comando ya tiene la
  terminal del servidor.
- **Corre en cualquier entorno, también en producción:** no lleva la guarda de solo-local de `CuentasDeDesarrolloSeeder`,
  que no cambia.
- **Qué no hace:** no le da el rol a una cuenta que ya existe, de ningún rol, para no decidir "una cuenta, dos roles"
  (DD-053). No manda correo, invitación ni enlace: nada sale hacia EL CLIENTE. No hay ruta HTTP ni página de Filament
  para crear administradores, ni subroles ni niveles de administración. No decide cuántas cuentas habrá ni quién las
  tiene.
- **Cuándo se corre:** en el entorno de prueba y en producción, cuando estén el alojamiento (DD-070) y el correo que dé
  EL CLIENTE, por medio de Nadir. No hay ningún correo real en el repositorio, en el `.env` ni en los sembrados.

## Risks / Trade-offs

- [El plugin de mapas de Filament está en beta] → se hace como componente propio (ADR-0009). Si el mapa sin código a
  la medida pasa de 8 h, se reabre el ADR-0003, según su criterio de reversión.
- [DD-079: el cliente firma el cambio de Conekta por Stripe o Mercado Pago (C-192, C-216)] → la conciliación se hace
  con Conekta, por la interfaz neutral del cobro (DD-112, cerrada); si se firma el cambio, la lectura de la elegida
  entra como otra implementación de la misma interfaz, con su ADR. No se construye nada
  de Stripe ni de Mercado Pago mientras tanto. El
  resto del cambio no depende del proveedor: lee `pagos`, que no lo nombra.
- [DD-056: el efectivo sin OXXO] → la conciliación de los pagos en efectivo espera a que se cierre.
- [La comisión no tiene base (DD-020)] → el porcentaje se configura desde ya; el cálculo, el reporte de comisiones y
  el indicador F-I10 quedan bloqueados.
- [Un aviso global a muchos dispositivos] → el envío va en cola para no detener el panel. Cuántos destinos acepta FCM
  por lote es grado E, `SIN VERIFICAR`.
- [Bloquear a alguien con un pedido activo] → el bloqueo solo cierra la entrada y no cambia el estado de ningún
  pedido. Lo demás lo dice DD-016 (administracion-E05).
- [La app lee todo 403 de `POST /api/sesion` y de `POST /api/sesion/google` como la cuenta sin acceso a la app]
  (`movil/src/acceso/api.ts`, administracion-E25) → el servidor tiene otro 403 en esas rutas: el de
  `App\Http\Middleware\SoloHttps` (`servidor/app/Http/Middleware/SoloHttps.php:36`), a toda petición de la API que
  llega por http con `solo_https` encendido. Con una compilación o un proxy mal configurados, cualquier cuenta vería el
  aviso de la administración y "Abrir el panel" en vez de "Algo salió mal". Es el mismo criterio que ya sigue
  `ProveedorDeSesion` con el 403 de `GET /api/cuenta`, y solo pasa con una mala configuración. Se queda como riesgo
  conocido; si se quiere distinguir, el servidor tendría que mandar un motivo propio en ese 403.

## Open Questions

1. **Qué entra en la vista reducida de administración de la app** (C-140). Pregunta para Arthur; no se diseña aquí.
   **Resuelta** por el concilio del 2026-11-24, por delegación de Arthur (C-245; `acta-2026-11-20-modelo-y-pasarela.md`,
   decisión 56, que complementa la 31), 3 de 3, con la opción b, la mínima (administracion-E25, tareas 6.1 y 6.2):
   - Una cuenta de administración que inicia sesión en la app no recibe token: el 403 de `POST /api/sesion`
     (registro-E33) no cambia en el servidor, y con esa respuesta la app no guarda nada.
   - **Hueco, hasta la tarea 6.2:** por Google el servidor sí le da token. `SesionConGoogleController` y
     `EntradaConGoogle::entrar()` no preguntan a `Autorizacion::usaLaApp()`; solo `SesionController` lo hace. Peor: si
     su Google no estaba ligado, `EntradaConGoogle::cerrarElAccesoAnterior()` (registro-E18) le anula la contraseña y le
     borra los tokens antes de ligarlo, y el panel entra solo con contraseña (`App\Filament\Auth\IniciarSesion`): la
     cuenta no vuelve a entrar al panel hasta que recupere su contraseña. La app no guarda ese token, pide revocarlo y
     muestra el aviso con el botón (registro-E33), pero el daño ya está hecho en el servidor. Y la revocación falla:
     `DELETE /api/sesion` está detrás de `autorizar:usuario,lavanderia,repartidor` (`servidor/routes/api.php:58-60`), a
     la administración le responde 403 y el token no se borra
     (`servidor/tests/Feature/Registro/CuentaDeLosRolesDeLaAppTest.php:56-58`); con `'expiration' => null`
     (`servidor/config/sanctum.php:53`), queda vigente en `personal_access_tokens`. La 6.2 responde 403 sin token,
     antes de ligar Google y de anular la contraseña, para que la cuenta quede intacta, y deja sin efecto los tokens que
     la administración ya recibió así; su línea en registro-E33 y su prueba entran con ella. La 6.2 no venía en la
     decisión 56, que dice "sigue sin token": el acta todavía no la asienta como su complemento.
   - La app le muestra el aviso de que la administración entra por el panel, con un botón, "Abrir el panel", que lo
     abre en el navegador del sistema dentro de la app, con `expo-web-browser`, el mismo del checkout (adenda del
     2026-11-22 de ADR-0010, decisión 32). Va en el inicio de sesión y en el registro, que también entra con Google
     (`movil/src/acceso/BotonDelPanel.tsx`).
   - La dirección del panel sale de la configuración de la app, `EXPO_PUBLIC_PANEL_URL`, nunca del código; sin ella, el
     aviso sale sin el botón.
   - Sin datos ni rutas nuevas en el servidor; lo único que cambia ahí es el 403 de Google de la 6.2. Se conserva la
     "vista en app" de C-140 en su lectura mínima; las consultas rápidas y los avisos nativos (opción c, que agranda sin
     cita) no entran. La opción a revocaba C-140.
2. **De dónde salen los movimientos de la pasarela para conciliar: resuelta** por la segunda adenda del 2026-11-22 de
   ADR-0010 (decisiones 6 y 7, asentada en la decisión 34 de `acta-2026-11-20-modelo-y-pasarela.md`, que cierra
   DD-112): de la API de la pasarela, por la misma interfaz neutral del cobro, cuando la administración concilia un
   periodo; se guardan en `movimientos_pasarela`, una fila por orden. Desbloquea E20 y E21 (tareas 0.2, 4.4 y 4.5). E22
   sigue esperando DD-013. Con Conekta, la lectura real se comprueba en su sandbox con la sección 3 de `pago`.
3. **Avisos masivos: temas de FCM o lista de tokens.** Condiciona cómo se arman los segmentos. Necesita ADR, o
   ampliar el ADR-0008. **Para el aviso global, la tarea 5.1 usa la lista de tokens** de `dispositivos`, un envío por
   teléfono, con el canal de FCM HTTP v1 que ya está en la adenda de ADR-0008 (sección "El panel", "Avisos masivos").
   Para los segmentos sigue abierta, con DD-113.
4. **El porcentaje global es uno solo para todo el sistema: resuelta.** Queda global por el texto del contrato ("por
   socio o global", `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3`, Módulo D #5), decisión 39 de
   `acta-2026-11-20-modelo-y-pasarela.md` (C-236). La tarea 0.1 lo había hecho global de cada plaza, siguiendo el
   modelo de datos (ADR-0016, C-193); la migración `2026_11_26_100000_hacer_global_la_comision_global` deja
   `id_plazas` vacía en la global y convierte lo que hubiera ("Tablas y migración"). E11 lo prueba en todas las
   plazas.
5. **Un aviso masivo y la preferencia de avisos del perfil** (F-A15, cambio `perfil-y-soporte`): si quien apagó los
   avisos recibe uno de mantenimiento. Depende de cómo defina ese cambio "notificaciones". **Resuelta** por el concilio
   del 2026-11-24, por delegación de Arthur (C-245; `acta-2026-11-20-modelo-y-pasarela.md`, decisión 58), 3 de 3, con
   la opción a, sin código: el servidor no guarda ninguna preferencia de avisos; el aviso masivo va a todo teléfono
   registrado y el sistema operativo lo detiene si la persona apagó los avisos. El A #15 dice solo "notificaciones"
   (`NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3`, Módulo A #15) y el perfil solo refleja el permiso del sistema
   (`perfil-y-soporte/design.md:70-78`, Decisions, punto 4). Una preferencia por tipo de aviso sería posible Orden de
   Cambio, que decide Nadir (`perfil-y-soporte/proposal.md:27`; `NSV-2026-001-v8.pdf:8:Cláusula IV`).
6. **La entrada al panel con rol de administración** (ADR-0005) se espera de `autorizacion-por-rol` o de
   `alta-de-lavanderias`, que llegan antes (sprints 11 y 12). Si ninguno la trae, hay que agregarla aquí.
   **Resuelta** por el concilio del 2026-11-24, por delegación de Arthur (C-245; `acta-2026-11-20-modelo-y-pasarela.md`,
   decisión 68), 3 de 3. La entrada llegó con esos dos cambios (autorizacion-E03,
   `servidor/tests/Feature/Autorizacion/PanelSoloAdministracionTest.php`), pero ninguno crea la cuenta de
   administración fuera de local: `CuentasDeDesarrolloSeeder` solo siembra en local. Se agregó aquí, con el comando
   `administracion:crear-cuenta` (administracion-E26, tarea 7.1; "La cuenta de administración", arriba).
7. **`actividad_usuarios` nace después.** La crea `indicadores` (sprints 24 a 29) y la lee administracion-E02 (sprint
   21). E02 ya está bloqueado por DD-111; cuando se libere, necesita esa tabla.
8. **El aviso global y la plaza: resuelta.** `avisos_masivos.id_plazas` es opcional y la tabla entra a la lista de
   "plaza opcional" de la prueba de esquema de `modelo-de-datos`.
9. **La comisión del repartidor (E14).** `comisiones` solo tiene `id_lavanderias`. Si DD-019 dice que la plataforma le
   retiene algo al repartidor, falta una columna en el modelo; entraría como opcional, hacia adelante (ADR-0016,
   regla 9).
10. **La plaza de `movimientos_pasarela`: resuelta** por la segunda adenda del 2026-11-22 de ADR-0010 (decisión 7): la
    tabla no lleva plaza propia, y la de un movimiento es la del pedido con el que cuadra. Ya estaba en la lista de
    tablas sin plaza de `servidor/config/modelo.php`; la tarea 0.2 le escribió la razón.
