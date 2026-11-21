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
    llena con la hora en que corre, porque esas lavanderías ya estaban activas.
- **Lo que ya trae de `perfil-de-lavanderia`:** la llave `id_lavanderias`; `id_publico` (ULID, único); las foráneas
  `id_plazas` → `plazas.id_plazas` e `id_usuarios` → `usuarios.id_usuarios`, con índice y `ON DELETE RESTRICT`; el único
  `(id_lavanderias, id_plazas)` para las llaves compuestas de sus hijas; el índice GiST de `ubicacion`, y `deleted_at`.
- **Cómo usa los estados este cambio:**
  - Solo una lavandería `activa` aparece al usuario y recibe pedidos nuevos (E12, E14, E17).
  - Suspender (E14) pone `suspendida`. Levantar la suspensión (E15) regresa a `activa` si `aprobada_en` tiene fecha y a
    `pendiente` si no: "el estado que tenía antes" sale de la fila, sin otra columna.
  - Dar de baja (E17) pone `baja` y no borra la fila, ni en suave: `deleted_at` queda vacío, para que el panel la siga
    listando con su estado y sus pedidos la sigan encontrando. Es la lectura de este diseño (pregunta abierta 10).
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
- [Las horas no caben en el plan] → Las tareas listas suman 34 h y las bloqueadas 20 h más, contra 15 h del plan para
  F-B01, F-D01 y F-B06. Se reporta para la recalibración de S-006.

## Open Questions

1. **La compresión de la foto.** ADR-0006 deja a la spec el tamaño y la calidad, y no hay dato para fijarlos. Se
   construye con parámetros de configuración; los valores los decide Arthur.
2. **La librería que toma, elige y comprime la foto** en la app no la nombra ningún ADR. ¿Arthur la considera una
   decisión que necesita ADR?
3. **La descripción y las fotos del perfil.** El perfil que ve el usuario muestra "Fotos, descripción, servicios,
   precios por kilo, calificación y reseñas" (`NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #5`), pero el
   registro pide una "foto" y ninguna descripción (`:17:Módulo B #1`). ¿Entran por "Edición de información de la
   lavandería" (`:17:Módulo B #6`) o por la "edición de socios" del panel (`:19:Módulo D #1`)? Hay que acordarlo con
   `perfil-de-lavanderia`. El modelo ya tiene `lavanderias.descripcion` y `lavanderias.telefono`, opcionales, y
   `lavanderia_fotos` admite varias fotos; el registro no los pide y este cambio no agrega ningún campo al formulario.
4. **Las pantallas no tienen componente en el design system.** El registro y el perfil y horarios del Módulo B no
   están entre los seis componentes (`vault/design-system/componentes.md`); las imágenes `b01-registro` y `b06-perfil`
   de la Propuesta 04 orientan. La Definition of Ready pide el componente o una tarea para crearlo.
5. **El cambio de dirección a otra plaza.** Si una lavandería edita su dirección y cae en otra plaza, ¿cambia de plaza
   sin más, o eso se hace desde el panel? El contrato no lo dice.
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
11. **Horario y ventanas en `lavanderia_horarios`.** Dos choques con la lectura de "una fila, una ventana de una hora":
    - `agenda-de-recoleccion` lee el horario como un tramo que parte en ventanas de una hora. Con filas de una hora las
      dos lecturas dan las mismas ventanas; pero si `perfil-de-lavanderia` siembra tramos largos (9:00 a 18:00), la
      edición de E22 los rechazaría.
    - E24 (DD-078) distingue un horario de atención de las ventanas, y el modelo no tiene dónde guardar los dos por
      separado. Si DD-078 lo pide, entra como columna o tabla nueva (ADR-0016, regla 9).

    Lo decide Arthur con `perfil-de-lavanderia` y `agenda-de-recoleccion`; este cambio no mueve la tabla.
