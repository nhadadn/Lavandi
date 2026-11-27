## Context

`base-tecnica` deja PostgreSQL con PostGIS en local (ADR-0004), y `modelo-de-datos`, las reglas comunes y la prueba
de esquema (ADR-0016). Este cambio pone la dimensión de plaza antes de que nazcan las primeras filas de operación, las
lavanderías de prueba del hito H2 (la tabla `lavanderias` la crea `perfil-de-lavanderia`). La regla y la definición
de plaza son las de `vault/arquitectura/modelo-multi-plaza.md`: una plaza es un área operativa formada por zonas, no
una unidad política, porque el propio territorio inicial mezcla un estado entero con dos municipios sueltos
(`NSV-2026-001-v8.pdf:4:Cláusula I`). El ANEXO C pone la carga de zonas y la configuración de una ciudad nueva como
trabajo de EL PRESTADOR, sin pantalla (`NSV-2026-001-v8.pdf:38:ANEXO C, Sección C.3`).

## Goals / Non-Goals

**Goals:**

- Ficha de plaza con zona horaria y estado; zonas como polígonos; resolución de un punto.
- Abrir una plaza con un comando y un archivo, sin migraciones.
- Una prueba de esquema que impida crear una tabla de operación sin plaza.

**Non-Goals:**

- Comisión, envío y precios por plaza: nacen con plaza en sus cambios.
- Qué pasa con una dirección fuera de zona al pedir (BR-18): `direcciones`.
- El mapa de cobertura de la landing (F-L07): `landing`.
- Moneda e idiomas por plaza: fuera del contrato (ver `proposal.md`).

## Decisions

1. **Ficha mínima: nombre, zona horaria IANA y estado.** Sale de `modelo-multi-plaza.md`, sin la moneda. Las horas se
   guardan como instantes absolutos y se presentan en la zona de la plaza.
2. **Zonas como polígonos de PostGIS, en SRID 4326.** Sostén: ADR-0004 (PostGIS para radio y zonas) y ADR-0009,
   decisión 5 (la cobertura se dibuja con polígonos propios). BR-02 deja el formato a la spec.
3. **La carga es un comando de `artisan` que lee un archivo GeoJSON versionado** con la ficha y las zonas de una plaza.
   Volver a cargar el mismo archivo actualiza; no duplica. No hay pantalla, porque el Módulo D no la enumera
   (`NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3`).
4. **"No activa" significa que no da servicio al público.** Las "pruebas del recorrido completo de un pedido en la nueva
   ciudad antes de abrirla al público" (`:38:C.3`) se hacen en el entorno de prueba, con la plaza activa y datos de
   prueba (ADR-0012), no con un tercer estado.
5. **La prueba de esquema usa una lista de excepciones a la vista** y no una lista de tablas de operación. Así, una
   tabla nueva sin plaza hace fallar la suite hasta que alguien decida, por escrito, que no es de operación. **La lista
   es una sola:** la de tablas sin plaza de `modelo-de-datos` (`servidor/config/modelo.php`, su diseño, decisión 2).
   modelo-E05 exige que la columna exista; plazas-E09 agrega que sea no nula y con llave foránea a `plazas`. La llave
   compuesta con la plaza en las tablas hijas la verifica modelo-E06.
6. **Las plazas de prueba son ficticias** ("Plaza de prueba 1") y solo se siembran en local y en prueba (ADR-0012).

Ninguna de estas decisiones elige tecnología nueva: siguen ADR-0004, ADR-0009, ADR-0012 y ADR-0016.

## Tablas y migración

Este cambio crea tres tablas, tal como las fija `vault/arquitectura/modelo-de-datos.md` (columna "Nace en"):

| Tabla | Columnas | Llaves, únicos y `CHECK` |
|---|---|---|
| `plazas` | `id_plazas`, `nombre`, `zona_horaria`, `activa` | Llave `id_plazas`. No lleva `id_plazas` como foránea: está en la lista de tablas sin plaza. `zona_horaria` no nula; que sea una zona IANA válida lo valida el código (plazas-E02), porque un `CHECK` no puede consultar la base de husos |
| `zonas` | `id_zonas`, `id_plazas`, `nombre`, `poligono` (`geography(MultiPolygon, 4326)`), `activa` | Llave `id_zonas`. Llave foránea `id_plazas` a `plazas`, no nula, con índice y `ON DELETE RESTRICT`. Índice GiST en `poligono`. El polígono sale de los límites municipales del INEGI (Open Questions); qué cubre cada zona sigue en DD-094 |
| `parametros_plaza` | `id_parametros_plaza`, `id_plazas`, `clave`, `valor` (`jsonb`), `vigente_desde`, `vigente_hasta` | Llave `id_parametros_plaza`. Llave foránea `id_plazas` a `plazas`, no nula, con `ON DELETE RESTRICT`. Único `(id_plazas, clave, vigente_desde)`, que también sirve de índice de la llave foránea. `vigente_desde` y `vigente_hasta` en `timestamptz`. Cada `clave` se valida en el código |

- **`parametros_plaza` nace vacía.** Este cambio no lee ni escribe ninguna clave. Las claves que el modelo prevé (el
  envío, DD-019 y DD-025; la anticipación y el horizonte de la agenda, DD-099; el texto de la cancelación, DD-016) las
  agrega y las valida el cambio que las usa (`agenda-de-recoleccion`, `resumen-y-confirmacion`), cuando su fila de la
  deuda se responda.
- **Usa, de otro cambio:** `usuarios` (`registro-e-inicio-de-sesion`), solo para plazas-E07: la cuenta no tiene
  `id_plazas`.
- **Modelos de Eloquent:** `Plaza`, `Zona` y `ParametroPlaza`, sobre `App\Models\Modelo`, cada uno con su `$table` y su
  `$primaryKey`, y las relaciones con `id_plazas` nombrada. El polígono se lee y se consulta con las funciones de
  PostGIS, sin paquete de tipos espaciales (ver Open Questions).
- **La migración pasa la prueba de esquema de `modelo-de-datos`** (modelo-E01 a modelo-E17). Ninguna de estas tablas
  lleva el trigger `impedir_cambio()`, que es solo de `pedidos`, `pedido_eventos`, `pagos` y `reembolsos`.
- **`created_at` y `updated_at`:** las tablas de este cambio los llevan, en `timestamptz` (modelo-E17; ADR-0016, regla 7).

## Risks / Trade-offs

- [La zona horaria de Matamoros, Coahuila] → en la base de husos, `America/Matamoros` es Matamoros, **Tamaulipas**,
  con horario fronterizo. Para la Comarca Lagunera el candidato es `America/Monterrey` (grado E, `SIN VERIFICAR`). Se
  verifica antes de escribir el archivo del territorio.
- [Polígonos municipales pesados] → las zonas salen de los límites municipales del INEGI (Open Questions). Las
  consultas se apoyan en el índice espacial, y lo que va al teléfono y al panel ya sale simplificado al leerlo
  (`ZonasDeLaCobertura`, `PedidosPorZona`). El archivo se simplifica al prepararlo, antes de que lo lea el comando, y
  como un conjunto: el borde que comparten dos municipios vecinos (los de Durango entre sí, Gómez Palacio con Torreón,
  Torreón con Matamoros) queda idéntico, vértice por vértice, en las dos zonas. Simplificar cada municipio por separado,
  aun con `ST_SimplifyPreserveTopology`, puede mover ese borde distinto de cada lado y dejar encimados, que plazas-E12
  rechaza, y huecos, que dejan sin plaza un domicilio válido sin que nada lo avise. Simplificar también mueve el borde exterior
  del territorio hasta la tolerancia, por eso la tolerancia se mide con los datos reales y el archivo preparado se
  compara con los límites originales: ninguna zona se mete en un municipio fuera del TERRITORIO INICIAL
  (`NSV-2026-001-v8.pdf:4:Cláusula I`).
- [Zonas encimadas] → la carga rechaza dos zonas que comparten área, de cualquier plaza (plazas-E12, C-234). El chequeo
  es en el plano y la resolución en la esfera: con bordes que comparten vértices coinciden; con un borde largo de
  vértices distintos puede quedar un encimado de metros (anotado en `AperturaDePlaza`).
- [El criterio de O-26 queda a medias] → plazas-E08 prueba la carga sin migraciones, pero el criterio completo pide el
  recorrido entero en la plaza nueva. Se completa con un escenario en `estados-del-pedido` o en `publicacion`.

## Open Questions

- **DD-093: plazas del territorio inicial.** ¿Una plaza o dos? C-123 habla de arrancar "en ambas" (Durango y
  La Laguna), pero falta decir qué municipios van en cada una: Gómez Palacio y Lerdo son de Durango y de la Comarca
  Lagunera (`:4:Cláusula I`). Bloquea plazas-E10 y plazas-E11.
- **DD-094: zonas del territorio inicial (BR-02).** Sigue abierta en su otra mitad, que es de EL CLIENTE, vía Nadir:
  ¿cada zona es el municipio completo o un área menor dentro de él, y quién la aprueba, en un caso o en el otro?
  Recomendación del concilio: si una zona es un área menor, se traza a mano sobre la base del INEGI y la aprueba quien
  EL CLIENTE indique, vía Nadir. Una recomendación no es la decisión. La fuente del polígono ya está resuelta (ver "La
  fuente de los límites oficiales", abajo). Bloquea plazas-E10 y plazas-E11, junto con DD-093, y el mapa de cobertura
  de la landing (F-L07).
- **Zonas encimadas.** Resuelta: la carga las rechaza (plazas-E12; DD-134, C-234).
- **Tipos espaciales en Eloquent.** Sin paquete, las consultas usan las funciones de PostGIS directamente y no hace
  falta ADR. Si se quiere un paquete de tipos espaciales, necesita ADR.
- **La fuente de los límites oficiales.** Resuelta en su parte técnica: los polígonos de las zonas salen de los
  límites municipales del Marco Geoestadístico del INEGI, simplificados al preparar el archivo. Encaja con un
  territorio que el contrato define por municipios, "La totalidad de los municipios que integran el Estado de Durango"
  y "Únicamente los municipios de Torreón y Matamoros, Coahuila de Zaragoza" (`NSV-2026-001-v8.pdf:4:Cláusula I`), y
  con el mapa de cobertura, que muestra "las zonas de servicio del TERRITORIO INICIAL"
  (`NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2`).
  - **El archivo.** El GeoJSON versionado que lee el comando (decisión 3) se prepara a partir de esos límites: en
    longitud y latitud, SRID 4326 (decisión 2), reproyectado si la fuente viene en otra proyección, y simplificado como
    un conjunto, con los bordes que comparten los municipios vecinos idénticos y la tolerancia que se mida con los
    datos reales (Risks, "Polígonos municipales pesados"). `AperturaDePlaza` guarda cada polígono como viene, sin
    simplificarlo, y rechaza los encimados: el archivo preparado pasa plazas-E12 y ninguna de sus zonas se mete en un
    municipio fuera del TERRITORIO INICIAL.
  - **La licencia.** Grado E, `SIN VERIFICAR`: los "Términos de Libre Uso de la Información del INEGI"
    (https://www.inegi.org.mx/inegi/terminos.html, consultados el 2026-11-24) dicen que se puede "explotar
    comercialmente la información", y piden darle el crédito al INEGI ("Fuente: INEGI, nombre del producto de donde se
    extrae la información") y "notificar al usuario final de cualquier análisis o transformación que haga a la
    información", como la simplificación. Se verifica, con su fecha de consulta, antes de cargar el archivo. Siguen
    abiertos quién la verifica para el uso comercial (la licencia de SEPOMEX la verifica Nadir, DD-138) y dónde va el
    crédito si se confirma: el mapa de cobertura de la landing (F-L07) y el mapa de los indicadores del panel.
  - **Quién la decidió.** Es la parte que DD-094 asigna a "Arthur (la fuente de datos)"
    (`vault/meta/deuda-de-decision.md:105`); la decidió el concilio de agentes del 2026-11-24, por delegación de Arthur
    (C-236, ampliada por C-245), y la asienta la decisión 62 del acta del 2026-11-20
    (`vault/meta/actas/acta-2026-11-20-modelo-y-pasarela.md`; `vault/evidencias/concilio/concilio-2026-11-24.md`,
    paquete P07). No desbloquea la tarea 4.1: siguen DD-093 y la otra mitad de DD-094.
- **Con qué reconoce la carga una plaza que ya existe.** plazas-E08 pide que volver a cargar el mismo archivo no
  duplique. Para eso la carga necesita una llave natural, por ejemplo `nombre` único en `plazas` y
  `(id_plazas, nombre)` único en `zonas`. **Resuelta:** el modelo de datos aprobado ya los trae (ADR-0016, C-193;
  `vault/arquitectura/modelo-de-datos.md`, filas `plazas` y `zonas`).
- **`zonas.activa`.** Resuelta: una zona que no está activa no da servicio, y al recargar se desactiva la que ya no
  viene (plazas-E13 y E14; DD-134, C-234).
- **Las marcas `created_at` y `updated_at`.** Resuelta: van en toda tabla del negocio, en `timestamptz` (modelo-E17; ADR-0016, regla 7).
