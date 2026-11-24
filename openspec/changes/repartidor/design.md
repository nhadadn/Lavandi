## Context

- Es el cambio grande del hito H8: el Módulo C completo y la gestión de repartidores del Módulo D.
- Se apoya en lo que ya existe para entonces:
  - la máquina de estados de `estados-del-pedido`;
  - el componente de autorización único de `autorizacion-por-rol` (`CLAUDE.md` §9.5);
  - la cámara de `perfil-y-soporte`.
- Lo sostienen ADRs vigentes: el panel en Filament (ADR-0003); el inicio de sesión con correo y contraseña para todos
  los roles (ADR-0005); fotos y evidencias en bucket privado (ADR-0006); la consulta cada 30 s (ADR-0007).
- El contrato lo acota: "versión operativa mínima" y "Su alcance es el expresamente descrito"
  (`NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3`).
- **Cobro antes de recolectar (C-188).** Arthur: "no se va a recolectar si no ha sido pagado"
  (`vault/meta/actas/acta-2026-11-19-normativa-y-arranque.md`). El orden lo sostiene el contrato: resumen con precio
  estimado (#9), pago (#10) y confirmación con folio (#11) (`NSV-2026-001-v8.pdf:16-17:ANEXO A, Sección A.3`). Aquí
  significa que un pedido sin pago acreditado no se le ofrece al repartidor (E30).
- Las tablas siguen `vault/arquitectura/modelo-de-datos.md` y ADR-0016 (C-193).

## Goals / Non-Goals

**Goals:**

- Dejar implementables, sin respuestas pendientes:
  - repartidor-E01 a E03 y E05 a E12;
  - E15 a E17, E19 a E21, E23, E25, E28 y E30.
- Para probar sin DD-014, los tests asignan el pedido directo en la base, con una fábrica.

**Non-Goals:**

- Todo lo que el Módulo C excluye (ver la propuesta).
- Ubicación del teléfono, en primer o en segundo plano: ninguna función del Módulo C la usa.
- Modelar documentos del repartidor. Si un día entran por Orden de Cambio, van en una entidad aparte, como deja
  previsto `vault/arquitectura/c3-api.md`.
- Lo que bloquean DD-014, DD-053, DD-054, DD-022, DD-011, DD-019, DD-013, DD-052 y BR-29.

## Decisions

No hay decisión técnica nueva. Cinco lecturas que fijan los escenarios; Arthur las aprueba con la spec:

1. **"Alta" es autorizar un registro pendiente.** El repartidor se registra en el Módulo C (#1), y el alta, la
   validación y la autorización "corresponden a EL CLIENTE". Por eso el alta del Módulo D (#2) autoriza un registro que
   ya existe. Alternativa: que la administración también cree repartidores desde el panel. No se descarta, pero no
   hace falta para cumplir los dos numerales.
2. **La plaza se fija al dar de alta.** Toda fila de operación lleva su plaza (`CLAUDE.md` §9.5 y
   `vault/arquitectura/modelo-multi-plaza.md`). La administración elige la plaza al autorizar. Alternativa: que el
   repartidor la elija al registrarse. **Choca con el modelo:** `repartidores.id_plazas` es no nula (plazas-E09), y un
   registro `pendiente` todavía no tiene plaza. Ver Open Questions.
3. **El tipo de vehículo es texto libre.** Una lista de opciones obligaría a inventar sus valores.
4. **Los cinco datos del registro son obligatorios.** El contrato lista lo que el registro lleva y EL CLIENTE lo
   necesita completo para validar fuera de la plataforma.
5. **"Del día" y "de la semana"** se cuentan en la zona horaria de la plaza (`modelo-multi-plaza.md`). La semana va de
   lunes a domingo. Una entrega es "completada" cuando el repartidor marca Entregado.

**El tiempo real de la lista** usa la consulta cada 30 s del ADR-0007. El ADR la escribe para la pantalla del pedido
del usuario y para el panel; aquí se aplica igual a la lista del socio. No hay mecanismo nuevo.

**El pago acreditado** se lee de `pedidos.confirmado_en`: con C-188, el pedido pasa a Confirmado y lo llena solo
cuando la pasarela confirma el pago (`modelo-de-datos.md`, tabla `pedidos`). La lista, el detalle y la aceptación del
repartidor filtran por esa columna, sin depender del estado interno previo al pago que fije DD-123.

## Tablas y migración

Es una sola migración, hacia adelante (ADR-0016, regla 9), en la tarea 0.1. Pasa la prueba de esquema de
`modelo-de-datos` (modelo-E01 a modelo-E17). Ninguna de las tres tablas lleva el trigger `impedir_cambio()`, que es
solo de `pedidos`, `pedido_eventos`, `pagos` y `reembolsos`. Todas las llaves foráneas llevan su índice y
`ON DELETE RESTRICT` (modelo-E09 y modelo-E10).

**Crea:**

1. `repartidores` (E01 a E09):
   - Columnas: `id_repartidores`, `id_publico`, `id_plazas`, `id_usuarios`, `tipo_vehiculo`, `estado`, `aprobado_en`,
     `deleted_at`.
   - Llave primaria `id_repartidores`. `id_publico` es un ULID único (modelo-E15) y es el que usa la API.
   - Llaves foráneas: `id_plazas` → `plazas.id_plazas`; `id_usuarios` → `usuarios.id_usuarios`.
   - Únicos: `(id_repartidores, id_plazas)`, para las llaves compuestas de sus hijas; `id_usuarios`, porque una cuenta
     es a lo más un repartidor (el diagrama del modelo).
   - `CHECK` de `estado`: `pendiente`, `activo`, `suspendido`, `baja`.
   - `aprobado_en` y `deleted_at` son `timestamptz`. La baja es borrado suave: las entregas se conservan (E07).
   - Nombre, teléfono, correo y foto viven en `usuarios` (`nombre`, `telefono`, `correo`, `foto_ruta`), no aquí.
   - **Sin columnas de documentos:** la verificación documental está excluida
     (`NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3`, Módulo C). Si entra por Orden de Cambio, es una tabla nueva,
     `documentos_repartidor`, sin tocar esta.
2. `pedido_asignaciones` (E10 a E18 y E30):
   - Columnas: `id_pedido_asignaciones`, `id_pedidos`, `id_plazas`, `id_repartidores`, `estado`, `motivo`,
     `respondido_en`.
   - Llaves foráneas compuestas con la plaza: `(id_pedidos, id_plazas)` → `pedidos(id_pedidos, id_plazas)` y
     `(id_repartidores, id_plazas)` → `repartidores(id_repartidores, id_plazas)`. La base rechaza ofrecer un pedido de
     una plaza a un repartidor de otra (modelo-E06).
   - `CHECK` de `estado`: `ofrecido`, `aceptado`, `rechazado`. `respondido_en` es `timestamptz`.
   - Sin único: cada intento es una fila. El rechazo guarda `motivo` y `respondido_en` (E16); que el motivo no venga
     vacío lo valida la API (E17).
   - Quién crea la fila `ofrecido` es DD-014 (E13). Mientras, los tests la siembran con una fábrica.
3. `evidencias` (E23 y E24):
   - Columnas: `id_evidencias`, `id_pedidos`, `id_plazas`, `tipo`, `ruta`, `id_usuarios`, `tomada_en`.
   - Llaves foráneas: `(id_pedidos, id_plazas)` → `pedidos(id_pedidos, id_plazas)`; `id_usuarios` →
     `usuarios.id_usuarios`, quien la tomó.
   - `CHECK` de `tipo`: nació con `recoleccion` y `entrega`; la migración `2026_11_26_300000_quitar_del_esquema_lo_que_no_tiene_respaldo`, hacia adelante, deja solo
     `entrega` (E23): la foto al recolectar es la propuesta de DD-110, sin respuesta (E24). `tomada_en` es
     `timestamptz`.
   - En la base solo va `ruta`; la foto vive en S3 (ADR-0006). Si es obligatoria y en qué momentos: DD-110 (E24).

**Agrega dos llaves foráneas a tablas que nacen antes**, cuando `repartidores` todavía no existe. Las dos son
compuestas, `(id_repartidores, id_plazas)` → `repartidores(id_repartidores, id_plazas)`, con `ON DELETE RESTRICT` y su
índice. Esta migración no cambia las columnas ni sus datos:

- en `pedidos`, que crea `resumen-y-confirmacion`;
- en `calificaciones`, que crea `perfil-de-lavanderia` con `id_repartidores` opcional y sin llave foránea. Con la columna
  vacía, la llave no se revisa, así que las calificaciones de lavandería no cambian.

**Fechas de creación.** Las tres tablas llevan `created_at` y `updated_at` en `timestamptz`, como todas las tablas del
negocio con llave propia. `pedido_asignaciones.created_at` es la hora en que se ofreció el pedido; `respondido_en`, la
hora de la respuesta.

**Los modelos de Eloquent** (`Repartidor`, `PedidoAsignacion` y `Evidencia`) heredan de `App\Models\Modelo` y declaran
su `$table` y su `$primaryKey` (modelo-E16). `Repartidor` usa el ULID público (modelo-E15).

**Solo usa** tablas que crean otros cambios:

| Tabla | Para qué | La crea |
|---|---|---|
| `usuarios`, `rol_usuario`, `roles` | El registro crea la cuenta con el rol `repartidor` y sus datos (E01, E02); la administración los edita (E08); la baja cierra la entrada (E07) | `registro-e-inicio-de-sesion` |
| `plazas` | La plaza del repartidor y la zona horaria de sus estadísticas (E05, E28) | `plazas-y-zonas` |
| `pedidos` | La lista y el detalle (E10 a E12); al aceptar se escribe `id_repartidores` (E15); cada botón cambia `id_estados_pedido` (E19); `confirmado_en` dice si el pago está acreditado (E30) | `resumen-y-confirmacion`; `confirmado_en` lo llena `pago` |
| `pedido_eventos` | Cada botón agrega su evento con `id_usuarios` y `ocurrido_en` (E19); el de Entregado da la fecha del historial y de las estadísticas (E25, E28) | `resumen-y-confirmacion` (base) y `estados-del-pedido` |
| `estados_pedido` | Recolectado, En tránsito y Entregado. En tránsito es un estado interno que fija DD-123 | `resumen-y-confirmacion` |
| `pedido_direcciones` | La dirección de recogida y la de entrega en la lista (E10) | `resumen-y-confirmacion` |
| `lavanderias` | El nombre de la lavandería en la lista (E10) | `perfil-de-lavanderia` |
| `calificaciones` | La calificación recibida, con `id_repartidores` (E27, bloqueado por DD-052). Esta migración le pone la llave foránea a esa columna | `perfil-de-lavanderia` (base) y `historial-y-calificacion` |
| `dispositivos` | El aviso de asignación (E14, bloqueado por DD-054) | `avisos` |

## Implementación en el servidor

Lo que se decidió al implementar las tareas 0.1 y 1.1 a 1.8, por delegación de Arthur (C-236), con lo mínimo que
cumple cada escenario. Ninguna decisión agrega una librería ni un servicio externo, ni decide una fila abierta.

### La migración (tarea 0.1)

- **Archivo:** `servidor/database/migrations/2026_11_23_100000_crear_repartidores_asignaciones_y_evidencias.php`. Pasa
  la prueba de esquema (modelo-E01 a E17) y la prueba propia, `servidor/tests/Feature/Repartidor/MigracionTest.php`.
- **`repartidores`:** como en "Tablas y migración", con la plaza vacía solo en `pendiente`
  (`repartidores_plaza_check`), la salida (a) de Open Questions. Además:
  - `id_usuarios` es única, porque una cuenta es a lo más un repartidor (el diagrama del modelo);
  - `estado` vale `pendiente` por omisión, que es como nace el registro de la app (E01);
  - `deleted_at` es la baja (E07), con `estado = 'baja'`.
- **`pedido_asignaciones`:** dos `CHECK` además del de `estado`, para que la base no guarde una respuesta a medias:
  `respondido_en` vacía solo si la asignación sigue `ofrecido`, y un `rechazado` lleva `motivo` no vacío (E16 y E17). La
  API valida el motivo antes (E17); el `CHECK` es la red de abajo.
- **Las llaves de `pedidos` y `calificaciones`:** la compuesta `(id_repartidores, id_plazas)`, con su índice y
  `ON DELETE RESTRICT`. Tres pruebas de cambios anteriores daban por hecho que la llave no existía y se ajustaron sin
  bajar lo que revisan: la lista de llaves de `pedidos` en `tests/Feature/Confirmacion/MigracionTest.php` suma la nueva,
  y las calificaciones de repartidor de `tests/Feature/PerfilLavanderia/MigracionTest.php` apuntan a un repartidor que
  existe en su plaza en lugar del número 7.
- **Los modelos:** `App\Models\Repartidor` (con su ULID, su borrado suave y sus relaciones `plaza`, `cuenta`,
  `asignaciones` y `pedidos`), `App\Models\PedidoAsignacion` y `App\Models\Evidencia`, que toman la plaza de su pedido
  (`HeredaLaPlazaDelPedido`). `Evidencia` esconde `ruta` del JSON. `Pedido` suma `repartidor`, `asignaciones` y
  `evidencias`, y `Rol` suma `REPARTIDOR`.
- **Las fábricas:** `RepartidorFactory` (activo en una plaza, con `pendiente()`, `suspendido()` y `deBaja()`) y
  `PedidoAsignacionFactory`, que crea la oferta directo en la base mientras DD-014 siga abierta (ver "La asignación").

### La asignación espera DD-014

**No hay operación ni ruta que asigne un pedido a un repartidor.** E13 está bloqueado: quién asigna, desde qué pantalla
y con qué regla es DD-014, de EL CLIENTE. Ninguno de los escenarios que entran (E10 a E12, E15 a E21, E23, E25, E28 y
E30) necesita una operación de asignar: necesitan una oferta que ya existe. Por eso las pruebas y el sembrado de
desarrollo la crean con `PedidoAsignacionFactory`, con la fila `ofrecido` directo en la base, como pide `tasks.md`.

Cuando DD-014 responda, la operación nace como un servicio interno de `servidor/app/Repartidores/` que crea la fila
`ofrecido`, y **quién lo llama** (una acción del panel en el Módulo D, o una de la lavandería en el Módulo B) es esa
respuesta, con su cita. Lo que pasa con un pedido rechazado (E18) también espera DD-014.

### El registro y el acceso (tarea 1.1)

- **Una ruta pública propia,** `POST /api/repartidor/registro`, aparte de `POST /api/registro` del usuario, que ignora
  cualquier rol (registro-E01). Es la recomendación de DD-053 ("lavandería y repartidor, desde su propia entrada y con
  aprobación en el Módulo D; una cuenta, un rol en la v1"); **la entrada en la app y los roles de la cuenta siguen
  esperando DD-053** (E04). Se sumó a la lista de rutas públicas de `tests/Feature/Registro/AutorizacionTest.php`.
- **El registro abre la sesión,** como el del usuario: la app necesita el token para mostrar la espera y consultar,
  con `GET /api/repartidor/perfil`, si ya tiene alta. El repartidor pendiente o suspendido también inicia sesión con su
  correo y su contraseña; lo que no puede es ver ni mover pedidos (E03 y E06). El de baja no inicia sesión (E07).
- **El código:** `App\Repartidores\RegistroDeRepartidor` crea la cuenta con el rol de repartidor, su nombre, su
  teléfono, su contraseña con hash y su foto, y la fila `pendiente`, en una transacción; si algo falla, borra la foto.
  Las reglas viven en `App\Http\Requests\RegistroDeRepartidorRequest`, y la edición del panel (E08) usa las mismas. El
  correo sin cuenta es la regla `App\Validacion\CorreoSinCuenta`, que ahora comparte con el registro del usuario.
- **El teléfono** se guarda como lo escribió la persona, con la regla técnica mínima de 10 a 15 dígitos, con espacios,
  guiones, paréntesis y un `+` al frente. El contrato no fija su formato.
- **La foto** va al almacenamiento privado de fotos, en la carpeta de la cuenta (`usuarios/<id_publico>/`), como la foto
  de perfil de cualquier cuenta (`App\Identidad\FotoDePerfil`, ADR-0006), con las reglas de `App\Fotos\ReglasDeLaFoto`.
- **Quién opera** lo decide `Autorizacion::repartidorQueOpera()`: solo un repartidor activo, con su plaza y no dado de
  baja. Las rutas de pedidos, historial y estadísticas llevan el middleware `repartidor-opera`
  (`App\Http\Middleware\RepartidorQueOpera`), que pregunta ahí y responde 403 con el estado del repartidor
  (`App\Repartidores\RepartidorSinAcceso`). El rol `repartidor` se sumó a `Autorizacion::usaLaApp()` y al grupo de la
  cuenta, la sesión y el catálogo de `routes/api.php`.

### El panel (tarea 1.2)

El recurso de repartidores de Filament vive en `servidor/app/Filament/Resources/Repartidores/`, como el de lavanderías.

- **Quién entra:** solo la administración (`Autorizacion::puedeEntrarAlPanel()`), y el recurso toma sus repartidores de
  `Autorizacion::repartidoresDelPanel()`: todos, de todas las plazas y en cualquier estado, también los pendientes sin
  plaza y los de baja; ninguno para otra cuenta (E09). No usa Policies de Filament (ADR-0019).
- **La lista** muestra nombre, correo, teléfono, tipo de vehículo, estado (Pendiente, Activo, Suspendido, Dado de baja) y
  plaza. No hay alta desde cero ni borrado.
- **Las acciones** van en la lista y en la página de cada uno, y su regla vive en `App\Repartidores\EstadoDelRepartidor`,
  con la fila bloqueada:
  - **Dar de alta** (E05): de `pendiente` a `activo`, con la plaza que elige la administración entre las plazas activas
    y la hora en `aprobado_en`.
  - **Suspender** y **levantar la suspensión** (E06): de `activo` a `suspendido` y de regreso, en su misma plaza.
  - **Dar de baja** (E07): de `activo` o `suspendido` a `baja`, con `deleted_at`, y revoca todos sus tokens. La cuenta
    no se borra. `Autorizacion::puedeIniciarSesion()` ya no le abre sesión por ninguna vía: `POST /api/sesion` responde
    el 422 de la cuenta bloqueada ("Esta cuenta está bloqueada."). Sus entregas se quedan, con el pedido ligado a él
    (`Pedido::repartidor()` lo encuentra aunque esté de baja). Ninguna acción deshace la baja.
  - **Un pendiente no se da de baja:** no tiene plaza, y la base solo admite la plaza vacía en `pendiente`
    (`repartidores_plaza_check`). Ver Open Questions.
- **La edición** (E08) son los cinco datos del registro, con sus mismas reglas (`RegistroDeRepartidorRequest::
  reglasDeLosDatos()`), en `App\Repartidores\EdicionDeRepartidor`. El correo no choca con el de su propia cuenta
  (`CorreoSinCuenta` con `salvo`). La foto solo cambia si se sube una nueva, y reemplaza a la anterior con
  `App\Identidad\FotoDePerfil`. La contraseña no se edita. El repartidor ve el cambio en `GET /api/repartidor/perfil`.
- **Lo que pasa con sus pedidos en curso** al suspenderlo o darlo de baja no lo fija la spec: no se tocan.

### Los pedidos asignados (tarea 1.3)

- **De quién son** lo decide `Autorizacion::pedidosDelRepartidor()`, en una sola consulta: los pedidos con el pago
  acreditado (`confirmado_en`, E30), de la plaza del repartidor, con una oferta suya `ofrecido` o `aceptado`, y que no
  tomó otro repartidor (`pedidos.id_repartidores` vacía o la suya). Uno de otro repartidor, de otra plaza, que él
  rechazó o sin pago responde el mismo 404 que uno que no existe (E12; ADR-0019, decisión 3). Toda acción del
  repartidor sobre un pedido parte de ahí.
- **La lista** son los que están en curso, de `confirmado` a `en_entrega` (`Pedido::activosDelRepartidor()`): también
  el que la lavandería todavía no responde, porque la oferta puede llegar antes. Nunca uno entregado, que va al
  historial (E25), ni uno que la lavandería rechazó. De la recolección más próxima a la más lejana.
- **El pedido** lleva su folio, su estado, su oferta, la ventana, la recogida y la entrega (la copia del pedido, como la
  ve el usuario) y la lavandería con su dirección (`App\Http\Resources\PedidoDelRepartidorResource`). No lleva datos de
  la cuenta que hizo el pedido: E10 no los pide, y cuáles ve el repartidor no lo fija la spec. Tampoco montos (E26,
  DD-019 y DD-013).
- **El tiempo real** es la consulta cada 30 s de la app (ADR-0007): el servidor responde la lista de ese momento, y un
  pedido recién ofrecido aparece en la siguiente consulta (E11).

### Aceptar o rechazar (tarea 1.4)

- **La respuesta vive en `pedido_asignaciones`**, en `App\Repartidores\RespuestaDelRepartidor`, con el pedido y la oferta
  bloqueados: aceptar deja la oferta `aceptado` con su hora y escribe `pedidos.id_repartidores` (E15); rechazar la deja
  `rechazado` con su motivo y su hora (E16). **Ninguna cambia el estado del pedido ni agrega un evento a su historial:**
  la oferta no es un estado del pedido (ADR-0021).
- **El pedido aceptado es de quien lo aceptó:** a otro repartidor con una oferta del mismo pedido deja de aparecerle, y
  aceptarlo le responde 404. Si dos aceptan a la vez, el bloqueo deja pasar al primero; el segundo recibe 409 "El pedido
  ya lo tomó otro repartidor.". Esto no decide DD-014: la regla de a quién se ofrece sigue abierta; solo impide que un
  pedido tenga dos repartidores a la vez en `pedidos.id_repartidores`.
- **Se acepta aunque la lavandería todavía no responda el pedido**, porque la oferta puede llegar antes; sus botones
  esperan a que la lavandería lo acepte (ver la adenda propuesta a ADR-0021).
- **Una oferta respondida no se vuelve a responder:** aceptar dos veces, o rechazar después de aceptar, responde 409 "La
  oferta ya fue respondida.". Deshacer una aceptación no lo pide la spec.
- **El motivo** usa la regla del rechazo de la lavandería (`App\Http\Requests\RechazarPedidoRequest`): obligatorio, texto,
  sin los espacios de las orillas y de hasta `pedidos.motivo_de_rechazo_maximo` caracteres (500), y se valida antes de
  buscar el pedido (E17).
- **Después del rechazo** el pedido sale de su lista y pedirlo le responde 404 ("deja de estar asignado a él"). Qué pasa
  con el pedido rechazado (se reasigna, se avisa o se detiene) espera DD-014 (E18): no se toca.

### Los botones del repartidor (tarea 1.5)

- **La misma operación que la lavandería:** `App\Pedidos\CambioDeEstado::marcarComoRepartidor()`, con su propia tabla
  (la adenda propuesta de abajo). Cada botón cambia `pedidos.id_estados_pedido` y agrega su evento a `pedido_eventos`,
  con la cuenta del repartidor y su hora, en una transacción con el pedido bloqueado, y publica
  `EstadoDelPedidoCambiado` (E19).
- **Solo en el pedido que aceptó:** el pedido tiene que ser suyo (`pedidos.id_repartidores`) y su oferta, `aceptado`.
  Con la oferta todavía `ofrecido`, responde 409 "Primero acepta el pedido." (E20). Uno de otro repartidor ni siquiera
  se encuentra: 404 (E21).
- **Entre Recolectado y En tránsito van los botones de la lavandería,** En proceso y Listo (Módulo B #3): en esos dos el
  repartidor no tiene botón (`siguiente` en `null`). E19 lo prueba así, con la lavandería en medio.
- **Lo que no se decide aquí:** cómo ve el usuario Recolectado, En tránsito y Entregado del repartidor, y si el pedido
  lleva un viaje de repartidor o dos (E22, DD-022 y DD-011). Hoy el usuario los ve como ya lo fija ADR-0021: Recolectado,
  Listo (por `en_entrega`) y Entregado.
- **`EstadosDePrueba::entregarComoElRepartidor()`** dejó de escribir el estado a mano: ahora usa
  `marcarComoRepartidor()`, con un repartidor de la plaza del pedido que recibe la oferta (con su fábrica) y la acepta
  con `RespuestaDelRepartidor`. El evento de Entregado queda con la cuenta de ese repartidor en lugar de vacío. La suite
  completa sigue en verde con el cambio.

### La evidencia de entrega (tarea 1.6)

- **Va con el botón Entregado,** en la misma petición `POST …/estado`, en multipart: `estado=entregado` y `foto`. Un solo
  paso para la app, y el cambio a Entregado y la evidencia quedan juntos: `App\Repartidores\EvidenciaDeEntrega` guarda la
  foto, marca Entregado y crea la fila de `evidencias` en una transacción; si Entregado no es el paso siguiente (409) o
  algo falla, borra el archivo y no queda la fila.
- **Dónde queda:** en el almacenamiento privado de fotos del entorno (`config/fotos.php`, ADR-0006), en la carpeta del
  pedido, `evidencias/<id_publico>/`; en la base, solo la ruta, con `tipo = 'entrega'`, quién la tomó (`id_usuarios`, la
  cuenta del repartidor) y cuándo (`tomada_en`). Las reglas de la foto son las de toda foto (`App\Fotos\ReglasDeLaFoto`).
- **Sin URL pública:** ninguna respuesta trae la ruta ni una URL; el pedido del repartidor solo dice que tiene evidencia
  (`evidencias`, con su tipo y su hora). El disco de fotos no sirve un archivo sin URL firmada. Quién puede ver la foto, y
  desde dónde, no lo fija la spec.
- **Opcional y solo al entregar:** sin foto, Entregado pasa igual; con Recolectado o En tránsito, la foto se rechaza con
  422. Si es obligatoria, si también se toma al recolectar y si es foto o firma espera BR-29 y DD-110 (E24). La tabla
  solo admite `tipo = 'entrega'`: si DD-110 pide la de recolección, el valor entra con su migración.

### El historial (tarea 1.7)

- **Qué es una entrega suya** lo decide `Autorizacion::entregasDelRepartidor()`: un pedido suyo
  (`pedidosDelRepartidor()`, con `pedidos.id_repartidores` en él) en Entregado, cuyo paso a Entregado marcó él (el
  evento de Entregado con su cuenta). Es la lectura 5: "una entrega es completada cuando el repartidor marca Entregado".
  Si DD-022 deja que la lavandería marque Entregado con su gente, esa entrega no cuenta para el repartidor.
- **Cada entrega** lleva su folio y la fecha en que la entregó, en la zona horaria de la plaza, con la misma forma que el
  historial de la lavandería (`App\Http\Resources\PedidoEntregadoResource`). De la más reciente a la más antigua.
- **Sin monto ni calificación recibida:** esperan DD-019 y DD-013 (E26) y DD-052 (E27). Entran como campos nuevos.

### Las estadísticas (tarea 1.8)

- **Cuentan las mismas entregas del historial** (`Autorizacion::entregasDelRepartidor()`), por el instante en que él
  marcó Entregado, en `App\Repartidores\EstadisticasDelRepartidor`, con la misma consulta que las de la lavandería
  (`App\Pedidos\EstadisticasDeLaLavanderia`).
- **"Del día" y "de la semana"** se leen en la zona horaria de la plaza (lectura 5): una entrega a las 19:30 del jueves en
  la plaza es del jueves, aunque en UTC ya sea el viernes. La semana va de lunes a domingo e incluye hoy.
- **Sin ingreso total acumulado:** espera DD-019 y DD-013 (E29). Entra como un campo nuevo.

### Adenda propuesta a ADR-0021: las transiciones del repartidor

Propuesta por Claude por delegación de Arthur (C-236), con la tarea 1.5. No edita el ADR: Arthur la asienta o la cambia
al revisar. Completa lo que la adenda del 2026-11-22 dejó abierto ("Desde dónde marca el repartidor su Recolectado lo
fija `repartidor`"). Lo que esperan DD-022 (entregar sin repartidor), DD-011 (uno o dos viajes), DD-014 (asignación) y
DD-016 (cancelación) **no** se decide aquí.

1. **Las transiciones del repartidor,** en la tabla `DEL_REPARTIDOR` de `App\Pedidos\CambioDeEstado` (ADR-0021,
   decisión 4):

   | Desde | Botón del repartidor | Llega a |
   |---|---|---|
   | `aceptado` | Recolectado | `recolectado` |
   | `listo` | En tránsito | `en_entrega` |
   | `en_entrega` | Entregado | `entregado` |

2. **Recolectado sale de `aceptado`, no de `confirmado`** (la decisión 2 original decía `confirmado` para los dos
   socios). Si el repartidor pudiera recolectar un pedido `confirmado`, la lavandería ya no podría aceptarlo ni
   rechazarlo (Módulo B #2): su tabla no tiene esas acciones desde `recolectado`. Es la misma regla que su adenda le
   puso a la lavandería: sin aceptar no hay botones. El repartidor puede aceptar la oferta antes de que la lavandería
   responda, pero su botón aparece hasta que ella acepte.
3. **Solo mueve el pedido el repartidor que lo aceptó:** `pedidos.id_repartidores` es el suyo y su oferta en
   `pedido_asignaciones` está `aceptado`. La oferta no es un estado del pedido: aceptarla o rechazarla no agrega evento.
4. **Dos pasos los comparten la lavandería y el repartidor,** sin cambiar la tabla de ella: Recolectado (desde
   `aceptado`) y `en_entrega` ("En proceso de entrega" de ella, "En tránsito" de él), como ya decía la decisión 2. Lo
   marca quien llegue primero; si la lavandería ya marcó En proceso de entrega, el siguiente botón del repartidor es
   Entregado. Si DD-022 decide que la lavandería entrega con su gente, su Entregado es una fila nueva en su tabla, sin
   tocar esta.
5. **Lo que ve el usuario no cambia** (adenda del 2026-11-22, punto 4): Recolectado, Listo por `en_entrega` y Entregado.
   Si DD-011 decide dos viajes de repartidor (recoger y entregar), la oferta del segundo viaje es otra fila de
   `pedido_asignaciones` y `pedidos.id_repartidores` pasa al segundo; esa regla se escribe con E22.

### Cómo se prueba en local

1. `php artisan migrate --seed` (o `php artisan db:seed` si ya está migrada). En local, el sembrado agrega
   `Database\Seeders\RepartidorDeDesarrolloSeeder`, que solo corre con `APP_ENV=local` y necesita las plazas y las
   lavanderías de prueba.
2. `php artisan serve` y la app apuntando a ese servidor.
3. Las cuentas, todas con la contraseña de las cuentas de prueba, `contrasena-de-prueba` (`UsuarioFactory::CONTRASENA`),
   guardada con hash:

| Cuenta | Rol | Para qué |
|---|---|---|
| `repartidor@ejemplo.com` | Repartidor, activo en la plaza de prueba de Durango | Entra a la app del Módulo C con tres pedidos ofrecidos |
| `usuario.de.prueba@ejemplo.com` | Usuario | La cuenta que hizo los tres pedidos de prueba |
| `lavanderia.norte@ejemplo.com` | Socio lavandería, con la contraseña `lavanderia-de-prueba` | Mueve los pedidos de la lavandería de prueba Durango Norte |
| `admin@ejemplo.com` | Administración | Da de alta, suspende, da de baja y edita repartidores en `/admin/repartidores` |

Los tres pedidos son de la lavandería de prueba Durango Norte, en la primera ventana que ofrece la agenda: uno que la
lavandería todavía no responde, uno que ya aceptó y uno listo para el reparto. Nacen por el camino real (el resumen
aceptado, el pago con la pasarela simulada, cuyo cobro confirma el pedido, y los botones de la lavandería) sin mandar
avisos ni correos. El pago necesita `PASARELA=simulada` y `PASARELA_SIMULADA_LLAVE` en el `.env`
(`openspec/changes/pago/design.md`); sin ellas, se siembra el repartidor sin pedidos. La oferta
se crea directo en la base (DD-014). Sembrar dos veces no los duplica. Ninguna de estas cuentas se siembra en el
entorno de prueba ni en producción.

## Contrato de la API

El contrato con que se hará la app del Módulo C (tareas 2.1 a 2.7). Todo va bajo `/api`, en JSON salvo donde se dice
multipart. Los instantes van en UTC (`…Z`); la ventana, con el desfase de la plaza, como en el pedido del usuario.

**Sesión y acceso.** Salvo el registro, toda ruta lleva `Authorization: Bearer <token>` y pasa por el componente de
autorización (`auth:sanctum` y `autorizar:repartidor`). Las de pedidos, historial y estadísticas, además, solo con un
repartidor que opera (`repartidor-opera`). Las respuestas de error comunes:

| Código | Cuerpo | Cuándo |
|---|---|---|
| 401 | `{ "message": "Unauthenticated." }` | Sin sesión, o con un token revocado (al darlo de baja, E07) |
| 403 | `{ "message": "Esta cuenta no tiene acceso." }` | La cuenta no tiene el rol de repartidor (E09) |
| 403 | `{ "message": "Tu registro espera la autorización de la administración.", "estado": "pendiente" }` | Pendiente de alta (E03) |
| 403 | `{ "message": "Tu cuenta de repartidor está suspendida.", "estado": "suspendido" }` | Suspendido (E06) |
| 404 | `{ "message": "No existe el pedido." }` | El pedido no existe, no es un ULID, no se le ofreció a él, lo rechazó, es de otra plaza o no tiene el pago acreditado: todos igual y sin datos (ADR-0019, decisión 3; E12 y E30) |

La cuenta y la sesión son las de toda cuenta de la app: `POST /api/sesion` (inicia sesión con correo y contraseña;
`usuario.rol` es `repartidor`), `GET /api/cuenta` y `DELETE /api/sesion`. Un repartidor pendiente o suspendido inicia
sesión; uno dado de baja no: con su contraseña correcta, `POST /api/sesion` responde
`422 { "message": "Esta cuenta está bloqueada.", "errors": { "correo": ["Esta cuenta está bloqueada."] } }`, sin token
(E07).

### El repartidor

Como sale en el registro y en el perfil:

```json
{
  "id_publico": "01jd8x6m3v2c9q4w5e7r8t0y1z",
  "nombre": "Rubén Repartidor",
  "telefono": "618 123 4567",
  "correo": "ruben@ejemplo.com",
  "foto": { "url": "https://…", "vence_en": "2026-12-01T19:00:00Z" },
  "tipo_vehiculo": "Motocicleta",
  "estado": "pendiente",
  "puede_operar": false,
  "plaza": null
}
```

- `foto`: URL firmada que vence (ADR-0006), o `null` sin foto. La ruta del archivo nunca sale.
- `estado`: `pendiente`, `activo` o `suspendido`. `puede_operar` es `true` solo si está activo y con su plaza.
- `plaza`: `{ "nombre": "…" }`, la del alta; `null` mientras está pendiente.

### `POST /api/repartidor/registro` (E01 y E02)

Sin sesión, en multipart. Campos, todos obligatorios: `nombre` (texto, hasta 255), `telefono` (de 10 a 15 dígitos, con
espacios, guiones, paréntesis y `+`), `correo` (sin cuenta; se guarda en minúsculas), `password` (con el mínimo de
`config/acceso.php`, 8 por omisión), `foto` (imagen de los tipos y del peso de `config/fotos.php`, ya comprimida por la
app) y `tipo_vehiculo` (texto libre, hasta 100). Cualquier otro campo, como un estado, una plaza o un rol, se ignora.

- **201** `{ "token": "…", "usuario": { "id_publico": "…", "correo": "…", "rol": "repartidor" }, "repartidor": { … } }`,
  con `repartidor.estado` en `pendiente` y `puede_operar` en `false`: la app muestra que espera la autorización de EL
  CLIENTE.
- **422** `{ "message": "…", "errors": { "<campo>": ["…"] } }`. No se crea ninguna cuenta ni se guarda la foto. Los
  mensajes: "Escribe tu nombre.", "Escribe tu teléfono.", "El teléfono no es válido.", "Escribe tu correo.", "El correo
  no es válido.", "Ese correo ya tiene una cuenta.", "Escribe una contraseña.", "La contraseña debe tener al menos 8
  caracteres.", "Toma o elige tu foto de perfil.", "La foto debe ser una imagen: jpg, jpeg, png, webp.", "La foto pesa
  más de 5120 KB." y "Escribe tu tipo de vehículo.".

### `GET /api/repartidor/perfil` (E01 y E08)

En cualquier estado. **200** `{ "repartidor": { … } }`. La app lo consulta para saber si ya tiene alta: con
`puede_operar` en `true`, abre sus pedidos. También trae lo que la administración le editó en el panel (E08).

### El pedido

Como sale en la lista, en el pedido y en las respuestas de aceptar, rechazar y los botones:

```json
{
  "id_publico": "01jd8x6m3v2c9q4w5e7r8t0y1z",
  "folio": "7KQ2M9XA",
  "confirmado_en": "2026-12-03T15:30:00Z",
  "estado": { "codigo": "listo", "nombre": "Listo" },
  "asignacion": { "estado": "ofrecido", "ofrecido_en": "2026-12-03T15:40:00Z", "respondido_en": null },
  "por_responder": true,
  "siguiente": null,
  "zona_horaria": "America/Mexico_City",
  "ventana": {
    "inicio": "2026-12-03T11:00:00-06:00",
    "fin": "2026-12-03T12:00:00-06:00",
    "fecha": "2026-12-03",
    "hora_inicio": "11:00",
    "hora_fin": "12:00"
  },
  "recogida": {
    "alias": "Casa", "calle": "Calle de prueba", "numero_exterior": "123", "numero_interior": "4",
    "colonia": "Colonia de prueba", "codigo_postal": "34000", "municipio": "Durango",
    "referencias": "Portón verde", "latitud": 24.042, "longitud": -104.655
  },
  "entrega": { "…": "la misma forma que recogida" },
  "lavanderia": {
    "id_publico": "01jd…", "nombre": "Lavandería …", "calle": "…", "numero": "…", "colonia": "…", "codigo_postal": "…"
  },
  "evidencias": [ { "tipo": "entrega", "tomada_en": "2026-12-03T22:45:00Z" } ]
}
```

- `estado.codigo` es el estado operativo del pedido (ADR-0021): `confirmado`, `aceptado` (la lavandería lo aceptó),
  `recolectado`, `en_proceso`, `listo`, `en_entrega`, `entregado` o `rechazado`. `estado.nombre` es como lo nombra la
  app del repartidor: el del usuario, salvo `en_entrega`, que es **"En tránsito"**.
- `asignacion` es la oferta de este repartidor: `ofrecido` mientras no responde, `aceptado` cuando la aceptó.
  `por_responder` es `true` solo en `ofrecido`: la app ofrece Aceptar y Rechazar.
- `recogida` y `entrega` son la copia del pedido; sin entrega distinta, las dos son la misma dirección.
- No lleva datos de la cuenta que hizo el pedido, llaves internas ni montos (E26 espera DD-019 y DD-013).

### `GET /api/repartidor/pedidos` (E10 a E12 y E30)

La lista en tiempo real; la app la consulta cada 30 s con la pantalla visible (ADR-0007). **200**
`{ "pedidos": [ { …el pedido… } ] }`: los que se le ofrecieron y los que aceptó, de `confirmado` a `en_entrega`. Nunca uno
entregado (va al historial), rechazado por la lavandería, que él rechazó, que tomó otro repartidor, de otra plaza o sin
pago acreditado. De la recolección más próxima a la más lejana; con la misma, del más viejo al más nuevo. Sin paginación;
vacía si no hay.

### `GET /api/repartidor/pedidos/{id_publico}` (E12 y E30)

Un pedido suyo en cualquier estado, también uno que ya entregó. **200** `{ "pedido": { … } }` · **404** como arriba.

### `POST /api/repartidor/pedidos/{id_publico}/aceptar` (E15)

Sin cuerpo. **200** `{ "pedido": { … } }`, con `asignacion.estado` en `aceptado`, su `respondido_en` y `por_responder` en
`false`; el estado del pedido no cambia. **409** `{ "message": "…", "pedido": { …como está… } }`, sin cambiar nada, con
uno de: "La oferta ya fue respondida.", "El pedido ya no está en curso." o "El pedido ya lo tomó otro repartidor.".

### `POST /api/repartidor/pedidos/{id_publico}/rechazar` (E16 y E17)

`{ "motivo": "Mi moto está en el taller." }`: obligatorio, texto, sin los espacios de las orillas, de hasta 500
caracteres (`pedidos.motivo_de_rechazo_maximo`).

- **200** `{ "asignacion": { "pedido": "<id_publico>", "estado": "rechazado", "motivo": "…", "respondido_en": "…Z" } }`.
  El pedido deja de estar asignado a él: sale de su lista y pedirlo responde 404. Qué pasa con él espera DD-014 (E18).
- **422** `{ "message": "Escribe el motivo del rechazo.", "errors": { "motivo": ["Escribe el motivo del rechazo."] } }`
  sin motivo, vacío, de puros espacios o que no es texto; "El motivo no puede pasar de 500 caracteres." si pasa del
  máximo. Se valida antes de buscar el pedido. La oferta sigue `ofrecido`.
- **409** "La oferta ya fue respondida." si ya la aceptó.

### `POST /api/repartidor/pedidos/{id_publico}/estado` (E19 a E21)

`{ "estado": "recolectado" }`: obligatorio, el estado al que lleva el botón, uno de `recolectado` (Recolectado),
`en_entrega` (En tránsito) o `entregado` (Entregado). Solo se marca el paso siguiente, el que trae `siguiente`, en un
pedido que aceptó: Recolectado desde `aceptado` (la lavandería ya lo aceptó), En tránsito desde `listo` y Entregado
desde `en_entrega`.

**Con la foto de evidencia (E23):** solo con `entregado`, en multipart, `estado=entregado` y `foto` (opcional; imagen de
los tipos y del peso de `config/fotos.php`). La respuesta trae `evidencias: [{ "tipo": "entrega", "tomada_en": "…Z" }]`,
sin ruta ni URL. Los 422 de la foto: "La foto de evidencia se toma al marcar Entregado." (con otro botón), "La foto debe
ser una imagen: jpg, jpeg, png, webp." y "La foto pesa más de 5120 KB.". Con cualquier error no se guarda nada.

- **200** `{ "pedido": { … } }`, en su estado nuevo y con su `siguiente`. Se guarda el evento con su hora y la cuenta
  del repartidor.
- **422** `{ "message": "Ese no es un botón del repartidor.", "errors": { "estado": ["Ese no es un botón del repartidor."] } }`
  sin estado o con uno que no es de los tres (`confirmado`, `aceptado`, `en_proceso`, `listo`…). Se valida antes de buscar
  el pedido.
- **409** `{ "message": "Ese no es el paso siguiente del pedido.", "pedido": { … } }` en un salto, un regreso, el mismo
  botón otra vez, o un pedido que la lavandería todavía no acepta; `"Primero acepta el pedido."` si su oferta sigue
  `ofrecido`. El pedido no cambia.

**`siguiente`** en el pedido: `{ "estado": "recolectado", "boton": "Recolectado" }`, `{ "estado": "en_entrega", "boton":
"En tránsito" }` o `{ "estado": "entregado", "boton": "Entregado" }`; `null` si no aceptó la oferta, si la lavandería
todavía no acepta el pedido, entre Recolectado y Listo (le toca a la lavandería) y después de Entregado.

### `GET /api/repartidor/historial` (E25)

**200**:

```json
{
  "pedidos": [
    { "id_publico": "01jd8x6m3v2c9q4w5e7r8t0y1z", "folio": "7KQ2M9XA", "entregado_en": "2026-12-04T01:30:00Z", "fecha": "2026-12-03" }
  ]
}
```

Sus entregas completadas, las que él marcó Entregado: ni una en curso ni la de otro repartidor. `entregado_en` es el
instante en UTC; `fecha`, ese instante en la fecha de la plaza. De la más reciente a la más antigua; sin paginación; vacía
si no hay. El detalle de cada una es `GET /api/repartidor/pedidos/{id_publico}`. Sin monto ni calificación (E26, E27).

### `GET /api/repartidor/estadisticas` (E28)

**200** `{ "entregas_del_dia": 2, "entregas_de_la_semana": 5 }`. Las mismas entregas del historial, por el instante en
que marcó Entregado. "Del día" es hoy y "de la semana", de lunes a domingo, los dos en la zona horaria de su plaza. Sin
entregas, los dos en 0. Sin ingreso total acumulado (E29, DD-019 y DD-013).

### Lo que falta en el contrato, y espera su fila

| Qué | Escenario | Espera |
|---|---|---|
| Quién asigna el pedido y desde qué pantalla; qué pasa con el que se rechaza | E13, E18 | DD-014 |
| La entrada al registro en la app y los roles de la cuenta | E04 | DD-053 |
| El aviso al teléfono de un pedido asignado con la app cerrada | E14 | DD-054 |
| Cómo ve el usuario Recolectado, En tránsito y Entregado del repartidor; uno o dos viajes | E22 | DD-022, DD-011 |
| Si la evidencia es obligatoria, en qué momentos, y si es foto o firma | E24 | BR-29, DD-110 |
| El monto por entrega y el ingreso total acumulado | E26, E29 | DD-019, DD-013 |
| La calificación recibida por entrega | E27 | DD-052 |

## Implementación en la app

Lo que se decidió al implementar las tareas 2.1 a 2.7 en `movil/`, por delegación de Arthur (C-236), con lo mínimo que
cumple cada escenario y el "Contrato de la API" de arriba, sin tocar `servidor/`. Ninguna decisión agrega una librería,
un permiso ni un cambio en la configuración de EAS, ni decide una fila abierta. Cada pantalla se prueba en iPhone y en
Android, con la app real y el servidor simulado con este contrato (`movil/__tests__/apoyo/repartidor.ts`), en
`movil/__tests__/repartidor/`.

### Las rutas y el módulo

| Ruta | Pantalla | Escenarios |
|---|---|---|
| `/registro-de-repartidor` | El registro, sin sesión (`app/registro-de-repartidor.tsx`) | E01, E02 |
| `/repartidor/espera` | La espera del alta, o de que le quiten la suspensión | E01, E03 |
| `/repartidor/pedidos` | Los pedidos asignados, la primera del módulo | E10, E11, E15, E19, E20 |
| `/repartidor/pedidos/rechazo/[id]` | El rechazo con motivo | E16, E17 |
| `/repartidor/pedidos/entrega/[id]` | La entrega con la foto de evidencia | E23 |
| `/repartidor/historial` | El historial de entregas | E25 |
| `/repartidor/estadisticas` | Las estadísticas | E28 |

- **El rol `repartidor` tiene su módulo** (`src/sesion/modulos.ts`, registro-E33): su primera pantalla son sus pedidos, y
  sus pantallas solo se ven con una cuenta de repartidor (`app/_layout.tsx`).
- **Al entrar, el módulo pide `GET /api/repartidor/perfil`** (`src/repartidor/ProveedorDelRepartidor.tsx`). Con
  `puede_operar` abre sus pedidos; sin él, la única pantalla es la espera, y la app no pide sus pedidos (E03). Si el
  perfil no llega, sin escenario propio, el mensaje general de la app con "Intentar de nuevo" y "Cerrar sesión".
- **Si una ruta de pedidos, historial o estadísticas responde 403** (lo suspendieron con la app abierta, E06), la app
  vuelve a pedir el perfil y el módulo lo lleva a la espera; la consulta cada 30 s se detiene con la pantalla.

### El registro y la espera (tarea 2.1)

- **El registro va fuera del módulo, sin sesión,** junto al registro y el inicio de sesión del usuario: con la sesión
  abierta lleva al módulo de la cuenta. **No tiene enlace** desde la bienvenida ni desde el inicio de sesión: es DD-053,
  de Nadir (E04). "Regresar" lleva al inicio de sesión.
- **Los datos:** nombre, teléfono (teclado de teléfono; el formato lo revisa el servidor), correo, contraseña (ADR-0005),
  foto de perfil y tipo de vehículo (texto libre, lectura 3), con los títulos de la imagen c01-registro. La contraseña no
  está en el mock: la pide ADR-0005.
- **La foto** se toma con la cámara o se elige de la galería y se comprime con `COMPRESION_DE_FOTOS`, como la de la
  lavandería, con la pieza nueva `src/piezas/CampoDeFoto.tsx`. Viaja con el registro, en multipart.
- **El 201 abre la sesión** con `useEntrada`, como el registro del usuario, y el módulo muestra la espera. Cada 422 va
  junto a su campo (`useEntrada` recibe los campos de la pantalla) y no se repite arriba del botón.
- **La espera** dice "Tu registro espera la autorización de la administración." o "Tu cuenta de repartidor está
  suspendida.", los mismos mensajes del servidor. **"Revisar de nuevo"** vuelve a pedir el perfil: con el alta, abre sus
  pedidos. No se consulta sola: ADR-0007 pide la consulta periódica para la lista, no para esta pantalla.

### Los pedidos asignados (tareas 2.2 a 2.4)

- **La tarjeta es el componente 5 del design system** (`src/piezas/TarjetaDePedidoDeRepartidor.tsx`): folio, estado en
  insignia cuadrada con el nombre del servidor ("En tránsito" en `en_entrega`) y los colores del canvas, la recolección en
  la hora de la plaza, "Recoger en", "Entregar en" ("La misma de la recogida" si el servidor manda la misma) y la
  lavandería con su dirección. Del canvas no entran el pin (sin librería de íconos ni de SVG), la distancia (sin
  ubicación) ni el acumulado del día (E29).
- **La consulta cada 30 s** es la de la lavandería (`src/estados/useConsultaPeriodica.ts`), y la lista compartida entre la
  lista, el rechazo y la entrega pasó a `src/estados/listaCompartida.tsx`, que también usa la lavandería sin cambiar lo
  que hace.
- **Aceptar y Rechazar** solo en la oferta `ofrecido`, con la nota del rechazo. Al aceptar, la tarjeta dice "Aceptaste
  este pedido." y ofrece el botón de su paso siguiente; si la lavandería todavía no acepta el pedido, el botón espera.
  **El rechazo** es la pantalla del de la lavandería, con su misma regla del motivo.
- **Un solo botón, el de `siguiente`,** mandado con `POST …/estado`. El 409 ("La oferta ya fue respondida.", "Primero
  acepta el pedido." o "Ese no es el paso siguiente del pedido.") muestra el mensaje del servidor y vuelve a consultar.
  El código de los botones entró con la tarea 2.3, porque E15 pide habilitarlos al aceptar; la tarea 2.4 son sus pruebas,
  que se corrieron en rojo antes de ese código.

### La evidencia de entrega (tarea 2.5)

- **"Entregado" abre la entrega** en lugar de mandarse desde la tarjeta: el pedido, la foto de evidencia y "Marcar
  entregado", que manda el botón y la foto juntos, en multipart, como pide el contrato de arriba.
- **Solo la cámara:** la evidencia es de lo que pasó en la entrega, no una foto de la galería.
- **Sin foto se marca igual, en JSON:** si la evidencia es obligatoria espera BR-29 y DD-110 (E24); la app hace lo que hoy
  acepta el servidor.

### El historial y las estadísticas (tareas 2.6 y 2.7)

- **Se abren desde sus pedidos,** con "Historial de entregas" y "Mis estadísticas" bajo el título, como las entradas de la
  lavandería mientras no exista una barra inferior.
- **El historial** usa la tarjeta del historial de la lavandería (`TarjetaDePedidoEntregado`): folio y "Entregado el …"
  en la fecha de la plaza, como los da el servidor. Sin monto ni calificación (E26 y E27).
- **Las estadísticas** usan la tarjeta de cifras que se sacó de las de la lavandería (`src/piezas/TarjetaDeCifras.tsx`):
  "Entregas de hoy" y "Entregas de esta semana", con la nota de que la semana va de lunes a domingo. Sin ingreso
  acumulado (E29) ni el desglose por día del mock c05 (fuera del contrato).

### Lo que la app no hace todavía

- **El enlace al registro** (E04, DD-053), **el aviso de asignación** (E14, DD-054), **los montos** (E26 y E29, DD-019 y
  DD-013) y **la calificación recibida** (E27, DD-052): sin huecos ni datos de relleno.
- **Un perfil del repartidor en la app** para ver lo que le editó la administración (E08): ninguna tarea de `movil/` lo
  pide; hoy solo lo trae `GET /api/repartidor/perfil`.
- **El texto del permiso de la cámara** (`src/alta-lavanderias/permisos-de-la-foto.json`, que `app.config.ts` lleva al
  Info.plist) habla de la foto de perfil y de la de la lavandería, no de la evidencia de entrega. Cambiarlo es un cambio
  de la configuración nativa que pide una compilación nueva: se le propone a Arthur y no se hizo.

### Cómo se prueba en el emulador

Con el servidor sembrado como dice "Cómo se prueba en local", la app apuntando a él y la sesión de
`repartidor@ejemplo.com` (`contrasena-de-prueba`):

1. **Sus pedidos:** entra a "Pedidos asignados" con los tres pedidos ofrecidos, cada uno con Aceptar y Rechazar. La
   lista se pone al día sola cada 30 s con la pantalla a la vista.
2. **Aceptar:** en el que la lavandería ya aceptó aparece "Recolectado"; en el listo para el reparto, "En tránsito"; en
   el que la lavandería no responde, ningún botón hasta que `lavanderia.norte@ejemplo.com` lo acepte.
3. **Rechazar:** pide el motivo; sin él, el botón dice "Escribe el motivo". El pedido sale de la lista.
4. **Entregar:** "En tránsito" y luego "Entregado", que abre la entrega: "Tomar foto" abre la cámara del emulador y
   "Marcar entregado" lo manda con la foto.
5. **Historial y estadísticas:** "Historial de entregas" y "Mis estadísticas", bajo el título.
6. **La espera:** con `admin@ejemplo.com`, suspender al repartidor en `/admin/repartidores`; a los 30 s la lista lo
   lleva a la espera, y "Revisar de nuevo" lo regresa al quitarle la suspensión.
7. **El registro:** no tiene enlace (DD-053). Se abre con el enlace de la app, `<esquema>://registro-de-repartidor`,
   donde el esquema es el `APP_IDENTIFIER` de la configuración; por ejemplo en Android,
   `adb shell am start -a android.intent.action.VIEW -d "<esquema>://registro-de-repartidor"`, sin sesión abierta.

## Risks / Trade-offs

- [El contrato guarda "evidencias de entrega" (p. 20), pero ninguna pantalla del Módulo C las captura, y su alcance es
  "el expresamente descrito" (p. 18)] → ADR-0006 ya ubica las evidencias en el Módulo C. Aquí entra lo mínimo: una foto
  opcional al marcar Entregado. Si es obligatoria, o si también va al recolectar, es BR-29 (E24).
- [DD-014 sigue abierta en el sprint 19] → la lista, la aceptación y los estados se construyen con asignaciones
  sembradas, pero el recorrido real no corre, y A43-2 tampoco: su fila en la trazabilidad ya marca DD-014 como bloqueo.
- [DD-022 cierra con "la lavandería entrega con su gente"] → el repartidor solo participa en algunos pedidos. No
  cambia E10 a E21, pero sí cambia E22.

## Open Questions

- **Las cinco lecturas de Decisions:** Arthur las confirma o las cambia al aprobar la spec.
- **Dónde se asigna el repartidor** (DD-014). Si es en el panel, es una acción nueva del Módulo D, que no la lista. Si
  es la lavandería desde su módulo, es una acción nueva del Módulo B. Las dos necesitan su cita al cerrarse DD-014.
- **BR-29 es DD-110.**
- **DD-053, DD-054, DD-022, DD-011, DD-019, DD-013 y DD-052.**
- **La plaza de un registro pendiente: respondida con la salida (a).** La lectura 2 fija la plaza al dar de alta, así
  que un registro `pendiente` no la tiene; plazas-E09 y el modelo piden `id_plazas` no nula. Dos salidas: (a) que
  `id_plazas` quede vacía solo en `pendiente`, con un `CHECK` que la exija en los demás estados, y que `repartidores`
  entre a la lista de "plaza opcional" de la prueba de esquema de `modelo-de-datos`, como `avisos_masivos`; (b) la
  alternativa de la lectura 2: el repartidor elige su plaza al registrarse y la administración
  la confirma al darlo de alta, lo que agrega un sexto dato a E01 y E02. **Quedó (a):** ya la traían
  `vault/arquitectura/modelo-de-datos.md` ("`id_plazas` vacía solo si `estado` es `pendiente`") y la lista
  `plaza_opcional` de `servidor/config/modelo.php`, y no cambia los cinco datos de E01 y E02. La tarea 0.1 la
  implementa (sección "Implementación en el servidor").
- **Un registro pendiente que EL CLIENTE no autoriza** (al implementar la tarea 1.2). El contrato pide alta, baja,
  suspensión y edición (Módulo D #2), y la spec da de baja a un repartidor activo (E07), sin decir qué pasa con un
  registro que nunca se autoriza. Hoy se queda `pendiente`, sin acceso a pedidos: la base no admite la baja sin plaza.
  Si Arthur quiere que también se pueda dar de baja, la salida es que el `CHECK` admita la plaza vacía en `baja` cuando
  `aprobado_en` está vacía, con su escenario.
- **En tránsito** no es uno de los cinco estados del usuario: es un estado interno de `estados_pedido` que fija el ADR
  de la máquina de estados (DD-123, límite 2027-01-04). E19 y E20 lo necesitan antes del sprint 18.
- **Pantallas sin componente.** El componente 5 de `vault/design-system/componentes.md` cubre la tarjeta de pedido
  del repartidor. El registro, el historial y las estadísticas no tienen componente; tampoco el recurso del panel.
  **En la app** se armaron con las piezas y los tokens, como las pantallas de la lavandería sin componente (ADR-0013),
  sin estilos sueltos: el campo de foto y la tarjeta de cifras son piezas nuevas de `movil/src/piezas/`, y el historial
  usa la tarjeta del de la lavandería. Si se quieren en el design system, entran con su cita.
