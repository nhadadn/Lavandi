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
- **El mensaje de cambio de estado sin aviso visible.** ADR-0008 separa los avisos "al usuario, en los momentos" del mensaje "al cambiar de estado, para el refresco". Este cambio lee que, en los cambios que no son momento, el mensaje no se muestra como aviso. Conviene asentarlo en ADR-0008.
- **En qué pantalla se pide el permiso.** El mock A17 lo explica con una pantalla propia; el contrato no lo dice. Es decisión de pantalla de Arthur.
- **Quién escribe y aprueba los textos de los avisos** (BR-24): fila propuesta en el reporte.
