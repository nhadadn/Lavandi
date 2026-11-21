## Context

F-A08 es el tercer paso del pedido nuevo: "Ingreso manual o desde mapa. Guardado de direcciones favoritas"
(`NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3`, Módulo A #8). El ANEXO B advierte que cada pedido dispara consultas de
mapa "al elegir la dirección de recolección, al elegir la de entrega" (`:30:ANEXO B, Sección B.5`), y que Google ya no
da crédito gratuito (`:29:B.5`). Lo que el contrato calla está en BR-18 (validación en zona y precisión) y BR-12
(costo de geocodificar). El consumo ya lo acotó `vault/arquitectura/presupuesto-de-mapas.md`, que el ADR-0009 adopta.
Las zonas de servicio las traza y carga `plazas-y-zonas` (BR-02).

## Goals / Non-Goals

**Goals:**

- Dos formas de capturar la dirección, escrita o en el mapa, con una geocodificación como máximo en la vida de cada
  dirección.
- Favoritas con nombre, que se reusan sin costo.
- Recogida y entrega dentro de la zona de servicio de la plaza de la lavandería.
- Aislamiento por cuenta, con su prueba.

**Non-Goals:**

- Autocompletado de direcciones, geocodificación inversa y mapa en el seguimiento.
- Editar o borrar favoritas desde el perfil (F-A15).
- El radio de servicio de la lavandería (DD-083).

## Decisions

No hay decisión técnica nueva: se aplica el ADR-0009 y las decisiones de `presupuesto-de-mapas.md` que adopta.

1. **Una dirección escrita se geocodifica una sola vez, al guardarse,** con Google Geocoding (ADR-0009, decisión 4;
   presupuesto, decisión 3). El servicio va detrás de una interfaz que los tests simulan, así ningún test gasta
   consultas ni necesita llave.
2. **Una dirección marcada en el mapa no se geocodifica:** el pin ya da el punto. La colonia y las señas las escribe el
   usuario (presupuesto, decisiones 3 y 4: geocodificación inversa en cero).
3. **La colonia es campo obligatorio,** no se deduce del mapa (presupuesto, decisión 4). Alimenta después el mapa de
   calor por colonia (F-I11).
4. **El mapa es `react-native-maps` sin `provider`:** Apple en iPhone, Google en Android (ADR-0009, decisión 1).
5. **La zona se comprueba en la base,** con el punto de la dirección contra los polígonos de las zonas de la plaza
   (PostGIS, ADR-0004). Que la plaza de la dirección sea la de la lavandería sale de que toda fila de operación lleva su
   plaza (`modelo-multi-plaza.md`; `CLAUDE.md` §9.5).
6. **El aislamiento vive en el componente de autorización** que nace en H1 con el rol de usuario (`CLAUDE.md` §9.5). Una
   dirección ajena responde igual que una que no existe, para no revelar que existe.

## Tablas y migración

**Crea** `direcciones`, con una migración que pasa la prueba de esquema de `modelo-de-datos` (modelo-E01 a modelo-E17).
Las columnas son las de `vault/arquitectura/modelo-de-datos.md`:

| Columna | Regla |
|---|---|
| `id_direcciones` | Llave primaria, `bigint` autoincremental |
| `id_publico` | ULID, único. La API identifica la dirección por él, nunca por `id_direcciones` (modelo-E15) |
| `id_usuarios` | Foránea a `usuarios.id_usuarios`, con índice y `ON DELETE RESTRICT` |
| `id_plazas` | Foránea a `plazas.id_plazas`, con índice y `ON DELETE RESTRICT`. Sale de la zona que contiene el punto, al guardar. Si el punto no cae en ninguna zona activa, queda vacía (ver Open Questions) |
| `alias` | El nombre de la favorita ("Casa"); opcional en las que no son favoritas |
| `calle`, `numero_exterior`, `numero_interior`, `colonia`, `codigo_postal`, `municipio`, `referencias` | Texto. `colonia` es `NOT NULL` (direcciones-E02); `numero_interior` y `referencias`, opcionales |
| `ubicacion` | `geography(Point, 4326)`, con índice GiST (ADR-0004) |
| `es_favorita` | `boolean`, falso por omisión |
| `deleted_at` | `timestamptz`. Borrado suave: una dirección que ya usó un pedido no se borra (ADR-0016, regla 6) |

- **Únicos:** `id_publico`; y `(id_direcciones, id_plazas)`, para que la foránea compuesta de `pedido_direcciones`
  (`resumen-y-confirmacion`) haga que la base rechace una dirección de otra plaza, como pide direcciones-E12
  (ADR-0016, regla 4).
- **Sin trigger:** `direcciones` no está entre las tablas del historial; se borra en suave.
- **Modelo de Eloquent:** `Direccion`, sobre la clase base `App\Models\Modelo`, con `$table = 'direcciones'`,
  `$primaryKey = 'id_direcciones'`, `HasUlids` para `id_publico` y `SoftDeletes`. Es el modelo que usa de ejemplo
  modelo-E16.

**Usa, sin crearlas:**

| Tabla | Para qué | La crea |
|---|---|---|
| `usuarios` | La dueña de cada dirección | `registro-e-inicio-de-sesion` |
| `plazas`, `zonas` | La plaza de la dirección y la zona de servicio (`zonas.poligono`, `zonas.activa`) | `plazas-y-zonas` |
| `lavanderias` | La plaza de la lavandería elegida (`id_plazas`), para direcciones-E12 | `perfil-de-lavanderia` |

**Lo que no escribe:** el pedido. La recogida y la entrega elegidas viajan en la app hasta el resumen, como la ventana
de `agenda-de-recoleccion`, y el servidor solo las valida. La copia en `pedido_direcciones` la hace
`resumen-y-confirmacion`, que va después en el plan (sprint 09). Por eso direcciones-E07 a direcciones-E10 se prueban
aquí con la validación de la API y la elección en la app; que la dirección quede en el pedido se prueba en
`resumen-y-confirmacion`.

## Risks / Trade-offs

- [Direcciones mal geocodificadas en colonias sin número claro] → Si la geocodificación no encuentra la dirección, la
  app ofrece marcarla en el mapa (direcciones-E03); el punto del pin manda.
- [Las zonas de `plazas-y-zonas` todavía no existen al implementar] → Los tests siembran polígonos de prueba; las zonas
  reales se cargan como datos.
- [Gasto de geocodificación] → Una consulta por dirección nueva; cero por favorita y por pedido repetido. Si el primer
  mes pasa del nivel gratuito, se revisa según el criterio de reversión del ADR-0009.
- [El territorio inicial puede ser una plaza o dos] (`modelo-multi-plaza.md`, pendiente de Nadir) → No cambia el
  mecanismo de direcciones-E12; cambia qué direcciones caen en la misma plaza, por ejemplo Gómez Palacio con una
  lavandería de Torreón.

## Open Questions

- **Desde dónde se geocodifica.** El ADR-0009 dice "una vez por dirección al guardarla", pero no si la consulta sale de
  `api` o de la app. Esta spec la pone en `api`: la llave no viaja en la app y el conteo de consultas vive en un solo
  lugar. **Necesita precisarse en el ADR-0009** antes de implementar; si se decide la app, cambian direcciones-E01, E03
  y E07.
- **La calle sugerida al soltar el pin** que dibuja el mock `a08-direccion.html` supone geocodificación inversa, que el
  presupuesto pone en cero. No entra. Si se quiere, **necesita ADR**, con su costo.
- **Autocompletado de direcciones** (pendiente de `presupuesto-de-mapas.md`; BR-12). No entra. Si se quiere, **necesita
  ADR**: servicio de pago o lista propia.
- **DD-083:** si la lavandería atiende toda su plaza o un radio (direcciones-E13).
- **¿El TERRITORIO INICIAL es una plaza o dos?** Pendiente de Nadir en `modelo-multi-plaza.md`; es DD-093.
- **`id_plazas` de una dirección fuera de toda zona.** El modelo dice que la plaza sale de la zona que contiene el
  punto, pero no qué pasa si no cae en ninguna. direcciones-E11 deja que esa dirección exista y la rechaza al elegirla,
  así que esta spec deja la columna vacía en ese caso; la foránea compuesta de `pedido_direcciones` impide usarla en un
  pedido. Si Arthur prefiere rechazarla al guardar, `id_plazas` pasa a `NOT NULL` y cambia el momento del rechazo de
  direcciones-E11. **Lo decide Arthur antes de la tarea 1.1.**
- **`created_at` y `updated_at`:** las tablas de este cambio los llevan, en `timestamptz` (modelo-E17; ADR-0016, regla 7).
