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

Ninguna decisión técnica nueva. Las piezas siguen a sus ADRs:

1. **Foto (ADR-0006).** Se guarda con el `Storage` de Laravel en el bucket privado del entorno y se sirve con URL
   firmada. La app la comprime antes de subirla. El tamaño máximo y la calidad quedan como configuración: el ADR los
   deja a la spec y aquí no se fija un número.
2. **Borrado de la cuenta (ADR-0005).** Revoca todos los tokens de Sanctum de la cuenta y deja de aceptar su inicio de
   sesión por cualquiera de los tres caminos. Qué pasa con los datos queda en E16.
3. **Reporte.** Se guarda en `api`, en la tabla `reportes_soporte`. Quien lo reciba al final, la administración o un
   correo de EL CLIENTE (E14), lo leerá de ahí; guardarlo es necesario con cualquiera de las dos respuestas.
4. **Notificaciones.** Lectura mínima del numeral 15: el perfil muestra el estado del permiso de avisos y lleva a
   activarlo. Si Arthur o el cliente leen otra cosa, cambia E08.

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

- [El número de WhatsApp llega después de enviar la app a las tiendas (DD-082)] → si vive en la configuración del
  teléfono, cambiarlo exige otra versión de la app; si vive en el servidor, no. Ver Open Questions.
- [Las tiendas piden algo más al borrar una cuenta] → ver Open Questions; grado E, sin verificar.

## Open Questions

- **Dónde vive el número de WhatsApp: necesita decisión de Arthur.** Propuesta: en la configuración del servidor, que
  la app lee al abrir Soporte, para que DD-082 pueda llegar tarde sin publicar otra versión. Si Arthur lo prefiere en
  la configuración de la app, la tarea 1.3 desaparece.
- **Apple y Google al borrar una cuenta (grado E, `SIN VERIFICAR`).** Conocimiento de Claude, sin fuente consultada
  hoy: Apple pide revocar el token de "Sign in with Apple" al borrar una cuenta que entró con Apple, y Google Play pide
  además una página web donde pedir el borrado. Se verifica antes de implementar E15; si se confirma, necesita ampliar
  ADR-0005, y la página web tendría que caber en lo que el contrato ya pide.
- **BR-25 y BR-09 ya tienen fila:** DD-108 y DD-109.
- **DD-055** (tarjeta guardada: Nadir dijo que sí, solo la referencia, C-198), **DD-079** (si el checkout de la
  pasarela permite reutilizarla, como en pago-E06) y **DD-082** (número de WhatsApp).
- **La plaza de un reporte sin pedido: necesita decisión de Arthur.** `reportes_soporte` lleva `id_plazas`, pero la
  persona no tiene plaza (`usuarios` no la lleva, por el modelo). Con pedido, la plaza es la del pedido. Sin pedido hay
  dos salidas: dejar `id_plazas` vacía, que choca con plazas-E09 ("no nula"), o tomar la plaza de la dirección
  favorita o del último pedido. La tarea 0.1 depende de esta respuesta.
- **La fecha del reporte: resuelta.** Todas las tablas del negocio con llave propia llevan `created_at` y
  `updated_at` en `timestamptz`; la fecha de perfil-usuario-E11 es `reportes_soporte.created_at`.
- **Pantallas sin componente.** Perfil y Ayuda no usan ninguno de los seis componentes de
  `vault/design-system/componentes.md`. La Definition of Ready pide que el componente exista o que la tarea lo cree.
