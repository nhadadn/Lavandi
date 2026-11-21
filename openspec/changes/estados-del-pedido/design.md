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
- **La hora de cada estado en la pantalla.** El mock A12 muestra una línea de tiempo con horas; el contrato no la nombra. Es una decisión de pantalla de Arthur.
- **Una biblioteca para la consulta periódica en `movil/`** (por ejemplo, TanStack Query) o un temporizador propio. Es una dependencia nueva y la decide Arthur.
- **Dónde viven los flujos de Maestro.** No hay convención escrita; las tareas usan `movil/.maestro/estados/`.
