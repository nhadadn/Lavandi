## Context

- El contrato fija tres cosas: los cinco estados que ve el usuario (Módulo A, numeral 12), los cuatro botones de la lavandería (Módulo B, numeral 3) y los 60 segundos para que un cambio se vea (A.4.3). Todo lo demás de la máquina de estados está abierto: BR-22, BR-26, BR-44, DD-011, DD-016 y DD-022.
- `arquitectura/c3-api.md` deja el componente `pedido` como `ESPECIFICACIÓN PENDIENTE` y advierte que dibujar la máquina completa sería elegir una lectura del contrato. Este cambio especifica solo lo literal y deja escritos, y bloqueados, los huecos.
- El mecanismo de los 60 segundos ya está decidido: consulta cada 30 segundos con la pantalla visible, más un aviso de FCM al cambiar el estado (ADR-0007). El aviso es del cambio `avisos`.
- Depende de tres cambios anteriores: `resumen-y-confirmacion` crea el pedido confirmado, `pedidos-de-la-lavanderia` lo acepta o lo rechaza, y `autorizacion-por-rol` trae el rol de lavandería.

## Goals / Non-Goals

**Goals:**

- Que la lavandería mueva el pedido con sus botones, en orden, y que el usuario lo vea con los nombres del contrato en 60 segundos o menos.
- Que nadie más que quien tiene el pedido pueda moverlo o consultarlo.
- Que cada cambio quede en el historial con su hora, que es lo que permite cronometrar el criterio A43-3.

**Non-Goals:**

- Los botones del repartidor (cambio `repartidor`), los avisos (cambio `avisos`) y el monitoreo del panel (cambio `administracion`).
- El mapa en el seguimiento (DD-077, fuera del contrato; ADR-0009).
- Decidir lo que el contrato no dice: "En proceso de entrega", quién marca "Entregado", el rechazo y la cancelación.

## Decisions

No hay una decisión técnica nueva que no esté en un ADR. Este cambio aplica:

- **ADR-0007:** la api es la única que autoriza y propaga un cambio de estado; la app solo lo pide. La pantalla de seguimiento consulta cada 30 segundos mientras está visible.
- **ADR-0003 y ADR-0004:** las transiciones y el historial viven en Laravel, sobre PostgreSQL.
- **ADR-0009:** el seguimiento no monta mapa.
- **Autorización en un solo componente** (`CLAUDE.md` §9.5; `arquitectura/c3-api.md`): las reglas de quién mueve y quién consulta cada pedido se agregan ahí, no en el controlador.
- **ADR-0016:** el historial es `pedido_eventos` y el estado actual vive también en `pedidos` (sección siguiente).
- **C-188:** un pedido sin pago acreditado no llega a la lavandería (estados-E23). La regla mira `pedidos.confirmado_en`, no el nombre del estado interno, que sigue en DD-123.

## Tablas y migración

Fuente: `vault/arquitectura/modelo-de-datos.md` ("Nace en") y ADR-0016. Este cambio **no crea tablas ni migraciones.** Usa `pedido_eventos`, que nace en `resumen-y-confirmacion` con sus triggers: cada trigger va en la migración de su tabla (diseño de `modelo-de-datos`, decisión 4).

**`pedido_eventos`** (la usa este cambio). Sus columnas, como en el modelo: `id_pedido_eventos`, `id_pedidos`, `id_plazas`, `id_estados_pedido`, `id_usuarios` (quien hizo el cambio), `motivo`, `ocurrido_en`.

- **Lo que ya protege la base:** los triggers `BEFORE UPDATE` y `BEFORE DELETE` que llaman a `impedir_cambio()`, creados en la migración de `resumen-y-confirmacion`. La base rechaza editar o borrar un evento (modelo-E12); este cambio lo comprueba con estados-E14.
- **Lo que ya trae de `resumen-y-confirmacion`:** la llave `id_pedido_eventos`; la foránea compuesta `(id_pedidos, id_plazas)` → `pedidos(id_pedidos, id_plazas)`; las foráneas `id_estados_pedido` → `estados_pedido.id_estados_pedido` e `id_usuarios` → `usuarios.id_usuarios`, con índice y `ON DELETE RESTRICT`; `ocurrido_en` en `timestamptz`.
- **Cómo la usa este cambio:** cada botón de la lavandería agrega un evento y cambia `pedidos.id_estados_pedido` en la misma transacción (ADR-0016, segunda excepción: el estado actual se guarda también en `pedidos` para que la consulta cada 30 s no recorra el historial). El estado anterior de un cambio es el del evento previo del mismo pedido, en el orden de `ocurrido_en` y de `id_pedido_eventos`; no se guarda dos veces (tercera forma normal). El `ocurrido_en` de cada evento es la hora contra la que se cronometran los 60 s del A.4.3 (estados-E17).

**Tablas de otros cambios que este usa:**

| Tabla | Qué hace aquí | Nace en |
|---|---|---|
| `pedidos` | Lee el estado actual y lo cambia con cada botón; solo pedidos con `confirmado_en` lleno (C-188, estados-E23). Ya tiene su trigger contra el borrado | `resumen-y-confirmacion` |
| `estados_pedido` | Lee los cinco estados del usuario, con `nombre_usuario`, `orden` y `visible_usuario`, y los internos que fije DD-123 | `resumen-y-confirmacion` |
| `lavanderias` | Lee qué lavandería tiene el pedido y su cuenta (`id_usuarios`), para la autorización | `perfil-de-lavanderia`; `alta-de-lavanderias` |
| `usuarios` | Lee quién hizo el cambio y quién es dueño del pedido | `registro-e-inicio-de-sesion` |

## Contrato de la API

Lo agregó el implementador del servidor con la tarea 1.1 (estados-E01 y estados-E11). Es el mismo que está en el
comentario de `servidor/app/Http/Controllers/Api/PedidosController.php`. Ninguna ruta es nueva: el seguimiento consulta
`GET /api/pedidos/{id_publico}`, de `resumen-y-confirmacion`, con su sesión, su autorización y sus errores, que no
cambian (confirmacion-E12: un pedido ajeno responde `404 { "message": "No existe el pedido." }`, y una cuenta sin el
rol de usuario, como la de una lavandería, `403`).

### Lo que agrega al pedido

El pedido, como sale en todas las respuestas (también la de `POST /api/pedidos`), conserva todo lo que ya traía y suma
una sola llave, `seguimiento`:

```json
{
  "id_publico": "01jd8x6m3v2c9q4w5e7r8t0y1z",
  "folio": "7KQ2M9XA",
  "confirmado_en": "2026-12-03T15:30:00Z",
  "estado": { "codigo": "recolectado", "nombre": "Recolectado", "visible_usuario": true },
  "seguimiento": [
    { "codigo": "confirmado", "nombre": "Confirmado", "ocurrido_en": "2026-12-03T09:30:00-06:00" },
    { "codigo": "recolectado", "nombre": "Recolectado", "ocurrido_en": "2026-12-03T10:05:00-06:00" },
    { "codigo": "en_proceso", "nombre": "En proceso", "ocurrido_en": null },
    { "codigo": "listo", "nombre": "Listo", "ocurrido_en": null },
    { "codigo": "entregado", "nombre": "Entregado", "ocurrido_en": null }
  ],
  "…": "el resto del pedido, igual que en resumen-y-confirmacion"
}
```

| Campo | Qué es |
|---|---|
| `estado` | Sin cambio. El estado actual: su código en `estados_pedido`, el nombre que ve el usuario (`nombre_usuario`) y si lo ve (`visible_usuario`). En Recolectado, En proceso y Listo, el nombre es el mismo del botón que marcó la lavandería (estados-E11) |
| `seguimiento` | Nueva. Los estados de `estados_pedido` con `visible_usuario` verdadero, en su `orden`: los cinco del contrato, de Confirmado a Entregado (Módulo A #12; estados-E01). Cada uno con su `codigo`, su `nombre` para el usuario y `ocurrido_en`: la hora en que el pedido llegó a él, en ISO 8601 con el desfase de la hora de la plaza, de su evento más reciente en ese estado, o `null` si no ha llegado (estados-E24). Es la misma lista para todo pedido, también en `por_pagar`, donde ninguno trae hora |

**Cómo pinta la app el seguimiento (estados-E02).** Ubica el estado actual en `seguimiento` por `estado.codigo`: los
anteriores son los cumplidos y los siguientes, los pendientes. Con `estado.visible_usuario` en `false` (`por_pagar`), el
pedido todavía no entra al flujo y ningún paso es el actual.

**`en_entrega` y `aceptado`: resuelto con la tarea 2.1.** Los dos se siembran con `visible_usuario` falso, el nombre
y el orden del paso en que los ve el usuario (Listo y Confirmado), así que `seguimiento` sigue con exactamente los cinco
(estados-E01). La API del usuario presenta el paso: con el pedido en `en_entrega`, `estado` sale como
`{ "codigo": "listo", "nombre": "Listo", "visible_usuario": true }`, y con `aceptado`, como Confirmado. La app ubica el
paso actual igual que antes, por `estado.codigo`. La regla está en la adenda propuesta a ADR-0021, en el `design.md` de
`pedidos-de-la-lavanderia`.

**"En proceso de entrega" se ve como Listo: lectura provisional, no respuesta.** Es lo que aplica hoy el código, por
ADR-0021 (decisión 26 del acta del 2026-11-20, C-236): `en_entrega` se siembra con el nombre y el orden de Listo, y
`App\Http\Resources\PedidoResource::estadoParaElUsuario()` lo presenta con el código de ese paso. No resuelve
estados-E12, que sigue `SIN RESPUESTA` (DD-022 y DD-011): lo que ve el usuario con "En proceso de entrega" lo pueden
cambiar esas dos. Por eso no se prueba como E12 sino bajo estados-E04
(`servidor/tests/Feature/Estados/BotonesDeLaLavanderiaTest.php`, "estados-E04: en En proceso de entrega, el usuario
sigue viendo uno de los cinco: Listo"). Si la respuesta pide otro de los cinco, cambian el nombre y el orden sembrados
de `en_entrega`, en una migración hacia adelante, y esa prueba pasa a E12.

### Los botones de la lavandería

Lo agregó el implementador del servidor con las tareas 2.1, 3.1 y 3.2. Las rutas de la lavandería, su sesión, su
autorización, la forma del pedido y sus errores comunes están en el `design.md` de `pedidos-de-la-lavanderia`, "Contrato
de la API", y en el comentario de `servidor/app/Http/Controllers/Api/PedidosDeLaLavanderiaController.php`. Lo propio de
este cambio:

```
POST /api/lavanderia/pedidos/{id_publico}/estado
{ "estado": "recolectado" }          uno de: "recolectado", "en_proceso", "listo", "en_entrega"

200  { "pedido": { ...el pedido de la lavandería, en su estado nuevo, con su "siguiente"... } }
409  { "message": "Ese no es el paso siguiente del pedido.", "pedido": { ...el pedido como está... } }
422  { "message": "Ese no es un botón de la lavandería.", "errors": { "estado": ["Ese no es un botón de la lavandería."] } }
404  { "message": "No existe el pedido." }

GET /api/lavanderia/pedidos/{id_publico}
200  { "pedido": { ... } }            el pedido de la lavandería, en cualquier estado
404  { "message": "No existe el pedido." }
```

| Botón (Módulo B #3) | `estado` | Desde | El usuario lo ve como |
|---|---|---|---|
| Recolectado | `recolectado` | `aceptado` | Recolectado (estados-E11) |
| En proceso | `en_proceso` | `recolectado` | En proceso (estados-E11) |
| Listo | `listo` | `en_proceso` | Listo (estados-E11) |
| En proceso de entrega | `en_entrega` | `listo` | Listo (ADR-0021) |

- **El paso siguiente.** El pedido de la lavandería trae `siguiente`, `{ "estado": "…", "boton": "…" }` o `null`: es el
  único botón que la app habilita (estados-E06). Desde `en_entrega` es `null`: Entregado espera DD-022 (estados-E13).
- **Quién lo mueve.** Otra lavandería, de la misma plaza o de otra, recibe 404 (estados-E07); una cuenta de usuario,
  403 (estados-E08), y ninguna ruta suya cambia el estado; un pedido no aceptado o rechazado responde 409 (estados-E09);
  uno sin pago acreditado, 404, y no se agrega ningún evento (estados-E23).
- **El historial.** Cada botón agrega un evento a `pedido_eventos` con el estado nuevo, `ocurrido_en` y la cuenta de la
  lavandería en `id_usuarios`, en la misma transacción que `pedidos.id_estados_pedido`, y publica
  `App\Pedidos\EstadoDelPedidoCambiado` ya guardado (estados-E14; avisos-E14).

## Risks / Trade-offs

- **[Entregado no tiene quién lo marque en este hito]** → Los botones de la lavandería terminan en "En proceso de entrega"; "Entregado" es un botón del repartidor (cambio `repartidor`, hito H8), o de la lavandería si DD-022 lo resuelve así. Al cerrar H6, el recorrido en local llega hasta Listo o En proceso de entrega, no hasta Entregado. Hay que decirlo antes de prometer el recorrido completo.
- **[A43-3 queda a la mitad]** → En este hito solo se acredita el cambio que hace la lavandería; el del repartidor se acredita en `repartidor`.
- **[Un pedido rechazado o cancelado sin estado visible]** → Mientras no se decida (estados-E19 y E20), la pantalla no puede mostrar nada para él. El pedido rechazado al menos no se mueve (estados-E09).
- **[Red lenta]** → La consulta cada 30 segundos deja margen hasta 60. Si en las pruebas algún cambio tarda más, se reabre ADR-0007, según su criterio de reversión.
- **[iPhone]** → Sin cuenta de Apple no se prueba en iPhone (DD-069). Se acredita primero en Android.

## Open Questions

- **La máquina de estados: necesita ADR. Es DD-123** (Arthur, límite 2027-01-04). La propuesta es guardar el estado operativo (los botones de cada socio) y derivar el que ve el usuario con `estados_pedido` (`nombre_usuario` y `visible_usuario`). No está en ningún ADR y el C3 la dejó pendiente a propósito. Con DD-123 se fijan también el estado interno en que el pedido espera su pago y los de "aceptado" y "rechazado" de `pedidos-de-la-lavanderia`.
- **Cuándo está Confirmado.** C-188 lo respondió: cuando la pasarela confirma el pago (numerales 10 y 11), con `pedidos.confirmado_en`, y antes de que la lavandería lo acepte. Si la aceptación tuviera que ser condición, cambia estados-E01 (BR-22).
- **Corregir un botón tocado por error.** El contrato no lo prevé y la api rechaza los regresos (estados-E05). Si la operación lo necesita, se decide como posible Orden de Cambio (fila propuesta en el reporte).
- **La hora de cada estado en la pantalla: resuelta.** Sí, la de cada estado cumplido (DD-135, punto 4; decisión 25 del acta del 2026-11-20, por delegación C-236). Es estados-E24.
- **La consulta periódica en `movil/`: resuelta.** Un temporizador propio, sin dependencia nueva: `useFocusEffect` de
  expo-router para consultar solo con la pantalla visible y `AppState` de React Native para pausar con la app al fondo;
  al volver a la pantalla o al traer la app al frente consulta de inmediato (estados-E15 y E16). Vive en
  `movil/src/estados/useConsultaPeriodica.ts`, que comparte con la lista de la lavandería. Se decidió por delegación de Arthur (C-236), con la tarea 4.2; TanStack Query
  u otra biblioteca no entran mientras no hagan falta.
- **Dónde viven los flujos de Maestro y cómo se cronometra estados-E17: resuelta** con la tarea 4.3, por delegación de
  Arthur (C-236). La recomendación, que es la que quedó:
  - **Dónde.** Cada flujo, en `movil/.maestro/<cambio>/`; lo que usa y no se corre solo, en `pasos/`, porque Maestro
    corre como prueba cada archivo de la carpeta que recibe, pero no los de sus subcarpetas. La preparación y el script
    de las corridas van junto al flujo, en PowerShell, como `herramientas/apk-local/compilar.ps1`.
  - **Maestro.** La CLI 2.12.1 del release oficial (`cli-2.12.1` de `mobile-dev-inc/maestro`), con su SHA-256
    comprobado, fuera del repositorio, en `%LOCALAPPDATA%\lavandi-android\maestro`, con el JDK 17. Se fija la versión
    para que las corridas se puedan repetir; se cambia a propósito, no al actualizar.
  - **Cada corrida empieza igual.** El flujo borra los datos de la app, pasa la bienvenida e inicia sesión por la
    pantalla, sin inyectar la sesión; concede la ubicación al abrir para que el inicio no lo detenga con el diálogo. El
    pedido nace por el camino real, por la API, con la pasarela simulada y la aceptación de la lavandería, uno por
    corrida (`preparar-pedido.ps1`).
  - **El seguimiento se abre con el enlace de la app**, `<APP_IDENTIFIER>://pedido/seguimiento/<id_publico>`: el
    esquema de los enlaces es el identificador de la app (`app.config.ts`). Así el flujo no depende del historial.
  - **El cronómetro.** Empieza en la máquina, justo antes de que salga el botón de la lavandería por la API con su token,
    y termina cuando Maestro lee "Recolectado, estado actual" en la etiqueta de accesibilidad del paso. Así incluye lo
    que tarda el servidor en guardar y lo que tarda Maestro en leer la pantalla: puede pasarse un poco del real, nunca
    quedarse corto. Falla si pasa de 60 000 ms, y la espera tiene un tope de 60 s. Se lee la etiqueta del paso y no el
    texto suelto porque los cinco nombres siempre están en la pantalla; la insignia del estado actual se busca arriba
    de los pasos, porque en la jerarquía que lee Maestro en Android su nombre no queda dentro de `estado-actual`.
  - **La evidencia.** La salida de Maestro de cada corrida y un `resumen.csv` van al vault, en
    `vault/evidencias/estados/`. El `.gitignore` deja pasar los `.log` de `vault/evidencias/`, porque el registro de
    Maestro trae los tiempos; el registro completo del teléfono no se versiona.
  - **Lo que queda fuera.** El flujo marca unos 3 s después de la primera consulta, así que cada corrida cae cerca del
    peor caso de la consulta cada 30 s. No se agrega una espera al azar para variar la fase: alargaría la prueba sin
    cambiar el peor caso. Los 10 cambios en teléfonos reales que pide la trazabilidad de A43-3 son otra prueba.
