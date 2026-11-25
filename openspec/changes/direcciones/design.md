## Context

F-A08 es el tercer paso del pedido nuevo: "Ingreso manual o desde mapa. Guardado de direcciones favoritas"
(`NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3`, Módulo A #8). El ANEXO B advierte que cada pedido dispara consultas de
mapa "al elegir la dirección de recolección, al elegir la de entrega" (`:30:ANEXO B, Sección B.5`), y que Google ya no
da crédito gratuito (`:29:B.5`). Lo que el contrato calla está en BR-18 (validación en zona y precisión) y BR-12
(costo de geocodificar). El consumo ya lo acotó `vault/arquitectura/presupuesto-de-mapas.md`, que el ADR-0009 adopta.
Las zonas de servicio las traza y carga `plazas-y-zonas` (BR-02).

**La dirección asistida (C-238).** El 2026-11-21 Arthur pidió "listas desplegables y que se fueran buscando con el Maps
de Google, para facilitar la dirección; obvio, si no viene, facilitarle al usuario escribirla correctamente". No está
fuera del contrato: F-A08 pide "Ingreso manual o desde mapa" sin decir cómo (`NSV-2026-001-v8.pdf:16:ANEXO A, Sección
A.3`, Módulo A #8), y ayudar a escribir la dirección manual es lo mínimo que cumple su criterio (`CLAUDE.md` §2-bis,
punto 4). RN-29 restringe el mapa, no la geocodificación ni el autocompletado (`restricciones-no-negociables.md`). Arthur
aprobó tres piezas: el municipio de una lista con solo los del territorio (`:4:Cláusula I`); la colonia por código
postal, con el catálogo de Correos de México (SEPOMEX); y la calle con Google Places, especificada pero bloqueada hasta
tener la llave (DD-072) y el costo. Son direcciones-E16 a E23.

## Goals / Non-Goals

**Goals:**

- Dos formas de capturar la dirección, escrita o en el mapa, con una geocodificación como máximo en la vida de cada
  dirección.
- Favoritas con nombre, que se reusan sin costo.
- Recogida y entrega dentro de la zona de servicio de la plaza de la lavandería.
- Aislamiento por cuenta, con su prueba.
- La dirección escrita, asistida: municipio de la lista del territorio y colonia por código postal, sin costo por
  consulta y sin bloquear a quien no encuentra la suya (C-238).
- El autocompletado de la calle, especificado y listo para encenderse por configuración cuando se desbloquee.

**Non-Goals:**

- Geocodificación inversa y mapa en el seguimiento.
- Encender el autocompletado de la calle: queda especificado y bloqueado (direcciones-E21 a E23).
- Validar la colonia o el código postal contra el catálogo, o guardar en la dirección una llave a la colonia del
  catálogo: la colonia sigue siendo texto (direcciones-E20).
- Límites geográficos de los municipios, o comparar el punto con el municipio elegido: la zona de servicio ya lo cubre
  (direcciones-E11).
- Editar o borrar favoritas desde el perfil (F-A15).
- El radio de servicio de la lavandería (DD-083).

## Decisions

Las decisiones 1 a 6 aplican el ADR-0009 y las de `presupuesto-de-mapas.md` que adopta. Las 7 a 12 son de la dirección
asistida (C-238) y se sostienen en la **adenda propuesta del ADR-0009** que va al final de este documento: hasta que
Arthur la asiente, las tareas 4.1 a 5.2 no empiezan (`openspec/config.yaml`, reglas del diseño).

1. **Una dirección escrita se geocodifica una sola vez, al guardarse,** con Google Geocoding (ADR-0009, decisión 4;
   presupuesto, decisión 3), y su punto se vuelve a pedir por el place ID al usarla si pasó de 30 días (adenda del
   2026-11-24 de ADR-0009; tarea 3.5, bloqueada por DD-139). El servicio va detrás de una interfaz que los tests simulan, así ningún test gasta
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
7. **El municipio se guarda como llave al catálogo.** La API recibe el `codigo` del municipio, como recibe el del
   servicio en `POST /api/pedidos` (C-235), y la dirección guarda `id_municipios`. La columna de texto `municipio` sale
   de `direcciones`, porque guardar también el nombre repetiría un dato del catálogo, y el ADR-0016 solo admite dos
   copias (la foto del pedido y su estado actual). El estado sale del municipio, sin columna propia (direcciones-E17).
8. **La colonia y el código postal siguen siendo texto en la dirección.** El catálogo solo llena la lista; el servidor
   no los compara con él (direcciones-E20). Así quien no encuentra su colonia, o vive en un código postal que el
   catálogo no trae, guarda igual. La colonia sigue `NOT NULL` (direcciones-E02).
9. **El código postal se pide, pero no se exige.** Es la lectura de C-238 ("el catálogo ayuda, no bloquea") que respeta
   los obligatorios de DD-135, punto 6: calle, número, colonia y municipio. Si viene, son cinco números. Si Arthur lo
   quiere obligatorio, cambian direcciones-E20 y la validación, no las tablas (ver Open Questions).
10. **El texto que se geocodifica lleva el municipio y el estado del catálogo:** "Calle 123, Colonia, 00000 Municipio,
    Estado". Sin el estado, "Matamoros" puede caer en Matamoros, Tamaulipas, fuera del territorio (grado E, de
    conocimiento general). El geocodificador de prueba (direcciones-E15) deja de buscar la palabra "durango" en el texto:
    ubica en la plaza de prueba solo la dirección cuyo municipio del catálogo se llama Durango y es del Estado de
    Durango. Así Gómez Palacio, que también lleva "Durango" en el estado, sigue sin encontrarse, como dice E15. La
    interfaz `Geocodificador` recibe la dirección con su texto y su municipio; Google usa solo el texto.
11. **El catálogo se carga con un comando, de un archivo que no se versiona** (ver "La carga del catálogo"). Es el mismo
    patrón que `plazas:cargar`: abrir el territorio es cargar datos, no migrar.
12. **El autocompletado vive detrás de una interfaz y de un interruptor.** La llamada a Places va detrás de una interfaz
    que los tests simulan, como la geocodificación (decisión 1), y solo corre si `GOOGLE_PLACES_ACTIVO` es verdadero y
    hay `GOOGLE_PLACES_LLAVE` en el entorno (ADR-0012). Apagado, la ruta de sugerencias responde `"activo": false` y
    `POST /api/direcciones` ignora la sugerencia: el resto del formulario y de la API no cambia (direcciones-E23).

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
| `calle`, `numero_exterior`, `numero_interior`, `colonia`, `codigo_postal`, `municipio`, `referencias` | Texto. `colonia` es `NOT NULL` (direcciones-E02); `numero_interior` y `referencias`, opcionales. La tarea 4.1 cambia `municipio` por `id_municipios` (ver "La dirección asistida", abajo) |
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

### La dirección asistida: el catálogo y el cambio en `direcciones` (tarea 4.1)

Una segunda migración del cambio, después de `2026_11_21_300000_crear_direcciones.php` y solo hacia adelante, porque la
primera ya corrió en la base de desarrollo. El ADR-0016 (regla 9) dice "una por cambio de OpenSpec"; ya hay precedente
de una segunda en `plazas-y-zonas` (`2026_11_21_110000_agregar_vigencia_valida_a_parametros_plaza.php`) y en
`resumen-y-confirmacion` (`2026_11_21_410000_sembrar_estado_por_pagar.php`). Pasa la prueba de esquema de
`modelo-de-datos` (modelo-E01 a modelo-E17).

**Crea** tres catálogos. Los tres van a la lista `sin_plaza` de `servidor/config/modelo.php`, como `roles` y
`tipos_servicio` (ADR-0016, regla 2): el territorio no es de una plaza, y una plaza nueva no cambia el catálogo. El
comentario de esa lista pasa de "los cuatro catálogos" a "los catálogos".

`entidades_federativas`. Se llama así y no `estados`, para no confundirla con `estados_pedido` ni con las columnas
`estado` de otras tablas.

| Columna | Regla |
|---|---|
| `id_entidades_federativas` | Llave primaria, `bigint` autoincremental |
| `codigo` | Texto de dos números, único: la clave del estado en el archivo de SEPOMEX (`c_estado`; grado E) |
| `nombre` | El nombre que da el archivo (`d_estado`), como "Durango" o "Coahuila de Zaragoza" (grado E) |
| `created_at`, `updated_at` | `timestamptz` (modelo-E17) |

`municipios`

| Columna | Regla |
|---|---|
| `id_municipios` | Llave primaria, `bigint` autoincremental |
| `id_entidades_federativas` | Foránea a `entidades_federativas.id_entidades_federativas`, con índice y `ON DELETE RESTRICT` |
| `codigo` | Texto de cinco números, único: la clave del estado y la del municipio juntas (`c_estado` + `c_mnpio`; grado E). Es como la API nombra al municipio |
| `nombre` | El nombre que da el archivo (`D_mnpio`) |
| `created_at`, `updated_at` | `timestamptz` |

`colonias`. Una fila por asentamiento del archivo, con su código postal. No hay tabla `codigos_postales` aparte: un código
postal tiene varias colonias y puede abarcar más de un municipio (grado E, sin verificar en Durango ni en Coahuila), así
que su municipio no sería único. El código postal es columna de la colonia, con índice.

| Columna | Regla |
|---|---|
| `id_colonias` | Llave primaria, `bigint` autoincremental |
| `id_municipios` | Foránea a `municipios.id_municipios`, con índice y `ON DELETE RESTRICT` |
| `codigo_postal` | Texto de cinco números (`d_codigo`), con índice: así se buscan (direcciones-E19) |
| `nombre` | El nombre del asentamiento (`d_asenta`) |
| `tipo` | El tipo de asentamiento (`d_tipo_asenta`): Colonia, Fraccionamiento, Barrio… (grado E). Distingue dos con el mismo nombre |
| `created_at`, `updated_at` | `timestamptz` |

- **Sin único por nombre en `colonias`:** el archivo puede traer dos asentamientos con el mismo nombre en el mismo
  código postal, de distinto tipo (grado E). La carga reemplaza las colonias en una transacción, así que repetirla no
  duplica (direcciones-E16). Ninguna tabla apunta a `colonias`.
- **Sin `id_publico`:** la API nombra al municipio y al estado por su `codigo`, como a `tipos_servicio` (C-235); a las
  colonias no las nombra, las devuelve por código postal. modelo-E15 no los pide, porque no son tablas de la lista que
  la API expone por identificador.
- **Sin semilla en la migración:** el catálogo es dato del archivo y lo carga el comando (tarea 4.2).
- **Modelos de Eloquent:** `EntidadFederativa`, `Municipio` y `Colonia`, sobre `App\Models\Modelo`, cada uno con su
  `$table` y su `$primaryKey` (modelo-E16).

**Cambia** `direcciones`:

| Columna | Cambio |
|---|---|
| `id_municipios` | **Nueva**, opcional: foránea a `municipios.id_municipios`, con índice y `ON DELETE RESTRICT`. La API la exige en la escrita y la deja opcional en la del mapa, como hoy el municipio (direcciones-E04) |
| `municipio` | **Sale**: su lugar es `id_municipios` (decisión 7). Las filas que ya existen son de prueba, en la base local `nsv_lavanderia` (`vault/meta/trabajo/2026-11-21-arthur-h2-plazas-y-zonas.md:41-43`), y pierden ese texto. **Resuelta: sale** (decisión 29 del acta del 2026-11-20) |
| `codigo_postal` | Sin cambio en la base: texto opcional. La API pide cinco números (direcciones-E20) |
| `colonia` | Sin cambio: texto `NOT NULL` (direcciones-E02) |

**`pedido_direcciones` no cambia.** Su `municipio` sigue siendo texto, porque es la copia del momento (ADR-0016, la
excepción de la foto del pedido). `CreacionDePedido` copia ahí el nombre del municipio del catálogo, en lugar de la
columna que sale. El estado no se copia: la copia no cambia de forma, y `PedidoResource` y el correo de confirmación
siguen igual.

## La carga del catálogo (tarea 4.2)

```
php artisan direcciones:cargar-catalogo storage/app/catalogos/Durango.txt storage/app/catalogos/Coahuila_de_Zaragoza.txt
```

- **Qué entra lo dice `servidor/config/territorio.php`,** no el comando. Es el territorio de la Cláusula I (p. 4):

  ```php
  return [
      // NSV-2026-001-v8.pdf:4:Cláusula I. Cambiarlo es Orden de Cambio (Cláusula IV, p. 8).
      'estados' => [
          '10' => ['nombre' => 'Durango', 'municipios' => '*'],
          '05' => ['nombre' => 'Coahuila de Zaragoza', 'municipios' => ['035' => 'Torreón', '017' => 'Matamoros']],
      ],
  ];
  ```

  Las claves eran grado E, `SIN VERIFICAR` (conocimiento de Claude del catálogo del INEGI). **Verificadas el
  2026-11-21** contra los archivos de Durango y de Coahuila de Zaragoza descargados ese día (grado E, con fecha):
  Durango es `10`, con 39 municipios (`001` Canatlán a `039` Nuevo Ideal); Coahuila de Zaragoza es `05`, Torreón `035` y
  Matamoros `017`. Coinciden con las de arriba, así que `servidor/config/territorio.php` queda así. El comando compara el
  nombre del archivo con el de la configuración, sin acentos ni mayúsculas, y falla si no coinciden: una clave
  equivocada no carga otro municipio en silencio. Compara también el nombre del estado, y falla si el archivo de un
  estado con lista no trae alguno de sus municipios.
- **El formato** (grado E, **verificado el 2026-11-21** en los dos archivos): texto separado por `|`, con fin de línea
  CRLF; la primera línea es la nota legal y la segunda, los nombres de las quince columnas (`d_codigo`, `d_asenta`,
  `d_tipo_asenta`, `D_mnpio`, `d_estado`, `d_ciudad`, `d_CP`, `c_estado`, `c_oficina`, `c_tipo_asenta`, `c_mnpio`,
  `id_asenta_cpcons`, `d_zona`, `c_cve_ciudad`, `c_CP`); la codificación es ISO-8859-1. El comando busca cada columna
  por su nombre, no por su posición; convierte a UTF-8 (un archivo que ya está en UTF-8 se lee como está, para no
  convertirlo dos veces); y si falta una de las que usa, no carga nada y dice cuál (direcciones-E16). Una fila del
  territorio con un código postal o un código de municipio que no son números, o con un nombre vacío, tampoco carga
  nada, y el comando dice la línea.
- **En una transacción:** actualiza o crea los estados y los municipios por su `codigo`; reemplaza las colonias de los
  municipios que cargó. Si algo falla, no cambia nada. Un municipio nunca se borra, porque `direcciones` lo apunta.
- **Al terminar** dice cuántos municipios y colonias cargó de cada estado, muestra la primera línea de cada archivo,
  cuenta las filas que quedaron fuera del territorio y avisa si un estado del territorio no vino en ningún archivo.
- **Asentamientos repetidos.** El archivo de Durango trae 11 asentamientos con el mismo nombre, tipo, código postal y
  municipio, que solo cambian en `id_asenta_cpcons` (por ejemplo, "2 de Octubre", Colonia, 34143, Durango). La carga
  deja una fila por asentamiento del archivo; la lista de colonias de la API no los repite (tarea 4.3).
- **La fuente** (grado E): Correos de México, Catálogo Nacional de Códigos Postales, descarga por estado en
  `https://www.correosdemexico.gob.mx/SSLServicios/ConsultaCP/CodigoPostal_Exportar.aspx`, en formato TXT. **Consultada
  y descargada el 2026-11-21** por el implementador del servidor; la página decía "Última Actualización de
  Información: Noviembre 20 de 2026". Cómo se consigue:
  - **A mano:** en esa página se elige el estado, se marca "TXT" y se oprime "Descargar". Llega un archivo
    `Durango.txt` o `Coahuila de Zaragoza.txt`.
  - **Sin navegador:** la página es un formulario de ASP.NET. Se pide la página con GET y se guardan sus cookies y sus
    campos ocultos `__VIEWSTATE`, `__VIEWSTATEGENERATOR` y `__EVENTVALIDATION`; luego se manda con POST a la misma
    dirección con esos campos, `cboEdo` con la clave del estado (`10` o `05`), `rblTipo=txt`, y `btnDescarga.x` y
    `btnDescarga.y` con cualquier número (es un botón de imagen). La respuesta es el archivo
    (`Content-Disposition: attachment`).
  - **Dónde vive:** fuera del repositorio, en la máquina de cada entorno, o en `servidor/storage/app/catalogos/`, que git
    ignora. El comando recibe la ruta.
  - Los archivos del 2026-11-21: Durango, 39 municipios y 7,149 asentamientos; Coahuila de Zaragoza, 38 municipios y
    4,489 asentamientos, de los que entran Torreón (566) y Matamoros (203). La carga se repite cuando SEPOMEX lo
    actualice.
- **La licencia** (grado E, **verificada el 2026-11-21** en la primera línea de los dos archivos), literal: "El Catálogo
  Nacional de Códigos Postales, es elaborado por Correos de México y se proporciona en forma gratuita para uso
  particular, no estando permitida su comercialización, total o parcial, ni su distribución a terceros bajo ningún
  concepto." Confirma lo que se temía. Por eso:
  - **No se versiona.** Vive en `servidor/storage/app/catalogos/`, que git ignora (`servidor/storage/app/.gitignore:1`).
  - **No entra en lo que se cede**, que es solo el código (`NSV-2026-001-v8.pdf:10:Cláusula IX`): cada entorno lo
    descarga.
  - **Cargarlo en producción espera que Nadir verifique la licencia** (fila propuesta en Open Questions). Los tests usan
    archivos inventados con el mismo formato, en `servidor/tests/Fixtures/sepomex/`, con municipios reales y colonias de
    prueba.
  - Si la licencia no lo permite, la lista de municipios puede salir del catálogo de claves del INEGI (grado E, sin
    verificar sus términos), y la lista de colonias queda fuera hasta encontrar otra fuente.

## Contrato de la API

Lo agregó el implementador del servidor con las tareas 1.2 a 1.5. El contrato exacto, con cada cuerpo, respuesta y
error, está en el comentario de `servidor/app/Http/Controllers/Api/DireccionesController.php`; esta tabla es el
índice. Todas las rutas llevan `auth:sanctum` y `autorizar:usuario`, y la API identifica cada dirección por su
`id_publico`.

| Ruta | Para qué | Escenarios |
|---|---|---|
| `POST /api/direcciones` | Guarda una dirección escrita (`"origen": "escrita"`, se geocodifica una vez) o marcada en el mapa (`"origen": "mapa"`, con el pin). 201 con la dirección; 422 con el campo o con el motivo `direccion_no_encontrada`; 503 con `geocodificacion_no_disponible` | E01 a E04, E06 |
| `GET /api/direcciones/favoritas` | Las favoritas de la cuenta, con su punto guardado | E06 a E08, E14 |
| `GET /api/direcciones/{id_publico}` | Una dirección de la cuenta; la de otra cuenta, una borrada o una que no existe responden 404 igual | E14 |
| `POST /api/direcciones/recogida-y-entrega` | Valida la recogida y la entrega para una lavandería. 200 con las dos; 422 con `motivos` por dirección: `fuera_de_la_zona_de_servicio` o `fuera_de_la_plaza_de_la_lavanderia`; 404 si la lavandería o una dirección no es visible | E07 a E12, E14 |
| `GET /api/catalogos/municipios` | **Nueva.** La lista de municipios del territorio, con su estado | E17 |
| `GET /api/codigos-postales/{codigo_postal}/colonias` | **Nueva.** Las colonias de un código postal; vacía si no está en el catálogo | E19, E20 |
| `POST /api/direcciones` (cambio) | `municipio` pasa a ser el código del catálogo y `codigo_postal`, cinco números; la dirección sale con `estado` | E01, E17, E18, E20 |
| `GET /api/direcciones/sugerencias` | **Nueva, bloqueada.** Las sugerencias de calle de Places; apagada, `"activo": false` | E21, E23 |
| `POST /api/direcciones` (cambio bloqueado) | Acepta `lugar`, la sugerencia elegida con su token de sesión | E22, E23 |

Lo que sigue es el contrato exacto de lo nuevo. El implementador lo copia al comentario del controlador, como el de las
rutas de arriba. Las rutas nuevas van dentro del grupo `auth:sanctum` y `autorizar:usuario` de `servidor/routes/api.php`:
la prueba `tests/Feature/Registro/AutorizacionTest.php` rechaza una ruta sin los dos. Responden 401 y 403 como las demás.

### `GET /api/catalogos/municipios` (direcciones-E17)

```
200  {
       "municipios": [
         {
           "codigo": "10005",                          el que la app manda en POST /api/direcciones
           "nombre": "Durango",
           "estado": { "codigo": "10", "nombre": "Durango" }
         },
         {
           "codigo": "05035",
           "nombre": "Torreón",
           "estado": { "codigo": "05", "nombre": "Coahuila de Zaragoza" }
         }
       ]
     }
```

- Todos los municipios del catálogo, ordenados por el nombre del estado y luego por el del municipio. Los códigos y
  nombres del ejemplo son grado E: los da el archivo.
- No depende de las plazas ni de las zonas: es el territorio del contrato. Que una dirección de un municipio de la lista
  quede fuera de toda zona de servicio lo resuelve direcciones-E11.
- Sin catálogo cargado: `200 { "municipios": [] }`. La app lo dice, y la dirección escrita no se puede guardar; la
  marcada en el mapa sí, porque ahí el municipio es opcional.

### `GET /api/codigos-postales/{codigo_postal}/colonias` (direcciones-E19 y E20)

```
200  {
       "codigo_postal": "34000",
       "colonias": [
         {
           "nombre": "Zona Centro",
           "tipo": "Colonia",
           "municipio": { "codigo": "10005", "nombre": "Durango" }
         }
       ]
     }

200  { "codigo_postal": "99999", "colonias": [] }
     El código postal no está en el catálogo. No es un error: la persona escribe la colonia a mano (direcciones-E20).

422  { "message": "El código postal son cinco números.",
       "errors": { "codigo_postal": ["El código postal son cinco números."] } }
```

- Las colonias van ordenadas por nombre. Cada una trae su municipio, para que la app lo elija si la persona todavía no
  eligió uno (direcciones-E19).
- Solo hay colonias de los municipios del territorio, porque son las únicas que se cargan.
- **Precisiones del implementador (tarea 4.3), sin cambiar la forma:** con el mismo nombre, van por tipo y luego por el
  nombre del municipio; un código postal puede traer colonias de dos municipios; y un asentamiento que el archivo
  repite con el mismo nombre, tipo y municipio sale una sola vez (ver "Asentamientos repetidos" en "La carga del
  catálogo"). La ruta no lleva restricción en `{codigo_postal}`, para que uno que no son cinco números responda 422 y
  no 404. El contrato exacto quedó en el comentario de
  `servidor/app/Http/Controllers/Api/CatalogoDelTerritorioController.php`.

### Qué cambia en `POST /api/direcciones` (direcciones-E01, E17, E18 y E20)

Solo cambian dos campos y la respuesta; lo demás queda como está en el comentario del controlador.

```
{
  "origen": "escrita",
  "calle": "Calle Ejemplo",          obligatorio en la escrita, como hoy
  "numero_exterior": "123",          obligatorio en la escrita, como hoy
  "numero_interior": "4",            opcional
  "codigo_postal": "34000",          opcional; si viene, cinco números (antes, texto libre)
  "colonia": "Zona Centro",          obligatorio, texto libre: de la lista o a mano, como hoy
  "municipio": "10005",              el código de un municipio de GET /api/catalogos/municipios (antes, texto libre).
                                     Obligatorio en la escrita; opcional en la del mapa, pero si viene, del catálogo
  "referencias": "Portón gris",      opcional
  "es_favorita": true, "alias": "Casa"   como hoy
}
```

Los errores nuevos. Los dos se validan antes de geocodificar, así que no gastan ninguna consulta:

```
422  { "message": "Elige un municipio de la lista.",
       "errors": { "municipio": ["Elige un municipio de la lista."] } }
     El código no está en el catálogo: fuera del territorio, como Saltillo, o inventado (direcciones-E18).

422  { "message": "El código postal son cinco números.",
       "errors": { "codigo_postal": ["El código postal son cinco números."] } }
```

**Precisión del implementador (tarea 4.4), sin cambiar la forma:** `municipio` y `codigo_postal` viajan como texto,
para no perder el cero de "05035" o de un código postal que empiece con cero. Un número JSON, o cualquier valor que no
sea texto, recibe el mismo error de su campo: "Elige un municipio de la lista." o "El código postal son cinco números.".

La colonia y el código postal no se comparan con el catálogo (direcciones-E20). La dirección, en todas las respuestas,
cambia así:

```
"municipio": "Durango" | null,       el nombre del municipio del catálogo (antes, el texto que escribió la persona)
"estado": "Durango" | null,          nuevo: el estado de ese municipio (direcciones-E17)
```

El campo `municipio` sigue siendo texto en la respuesta, así que la app y el perfil lo leen igual. El texto que se
geocodifica es "Calle Ejemplo 123, Zona Centro, 34000 Durango, Durango" (decisión 10).

### `GET /api/direcciones/sugerencias` (direcciones-E21 y E23; bloqueada)

```
GET /api/direcciones/sugerencias?texto=Calle%20Eje&municipio=10005&sesion=6f1c2a9e-3b7d-4c5e-9a1f-2d8b7e4c6a10

     texto      obligatorio, de 3 a 255 caracteres
     municipio  obligatorio, el código de un municipio del catálogo
     sesion     obligatorio, un UUID v4: el token de la sesión de esa dirección

200  { "activo": true,
       "sugerencias": [
         { "id_lugar": "ChIJ…", "principal": "Calle Ejemplo 123", "secundario": "Zona Centro, Durango, Dgo., México" }
       ] }
     Hasta cinco, en el orden de Places.

200  { "activo": false, "sugerencias": [] }
     Apagado por configuración, o encendido sin llave. No se consultó a Places. La app deja de pedir sugerencias para
     esa dirección (direcciones-E23).

422  { "message": "...", "errors": { "texto": ["..."] } }        falta un campo o no es válido; no se consultó a Places
429  la cuenta pasó de 30 peticiones por minuto (throttle:sugerencias)
503  { "message": "No pudimos sugerir la calle. Escríbela.", "motivo": "autocompletado_no_disponible" }
     Places no respondió. La app sigue con la calle como texto libre.
```

El servidor pide a Places las sugerencias solo en México, con el texto más el municipio y el estado elegidos. Los
parámetros exactos de Places (`includedRegionCodes`, la máscara de campos) se verifican al desbloquear (grado E).

### Qué agrega a `POST /api/direcciones` el autocompletado (direcciones-E22 y E23; bloqueado)

```
"lugar": {                              opcional, solo en la escrita
  "id_lugar": "ChIJ…",                  el de la sugerencia elegida
  "sesion": "6f1c2a9e-3b7d-4c5e-9a1f-2d8b7e4c6a10"   el mismo token de sus sugerencias
}
```

- **Encendido:** el servidor pide a Places el detalle de ese lugar con el mismo token y la máscara de campos `location`,
  y guarda la dirección con ese punto. No geocodifica. Si Places no da el punto, responde con los motivos de hoy, para
  que la app no cambie: 422 `direccion_no_encontrada` si no lo encontró, o 503 `geocodificacion_no_disponible` si no
  respondió. No hace una segunda consulta de pago.
- **Apagado:** el servidor ignora `lugar` y geocodifica como en direcciones-E01.
- La app no manda `lugar` si la persona cambió la calle después de elegir la sugerencia (direcciones-E22).

## Qué cambia en el formulario de la app (tareas 5.1, 5.2 y 3.4)

`movil/app/pedido/direccion-nueva.tsx`, en sus dos formas, escrita y en el mapa:

- **Municipio (tarea 5.1):** deja de ser campo de texto. Al abrir el formulario, la app pide
  `GET /api/catalogos/municipios` una vez por sesión de la app y ofrece la lista para elegir uno. Manda su `codigo`. El
  error del campo, "Elige un municipio de la lista.", va junto a él, como los demás (direcciones-E17 y E18). Si la lista
  no llega, lo dice; la forma del mapa sigue sirviendo.
- **Código postal (tarea 5.2):** campo nuevo, de cinco números, antes de la colonia. Al completar los cinco, la app pide
  `GET /api/codigos-postales/{codigo_postal}/colonias`.
- **Colonia (tarea 5.2):** sigue siendo campo de texto, obligatorio. Con colonias en la respuesta, la app las ofrece en
  una lista; elegir una escribe su nombre en el campo y, si no había municipio elegido, elige el suyo (direcciones-E19).
  Sin colonias, lo dice junto al campo y deja escribirla (direcciones-E20). La persona siempre puede escribirla a mano.
- **Calle (tarea 3.4, bloqueada):** con el autocompletado encendido, muestra las sugerencias bajo el campo
  (direcciones-E21 a E23). Mientras siga bloqueada, la calle es el campo de texto de hoy.
- `movil/src/direcciones/api.ts`: `SenasDeLaDireccion` lleva `codigo_postal`, y `municipio` guarda el código elegido.
  `Direccion` agrega `estado`. Dos consultas nuevas, `consultarMunicipios` y `consultarColonias`, que nunca lanzan y
  devuelven un `Resultado`, como las demás.
- El comentario de la pantalla, que hoy deja fuera "el código postal (DD-135, punto 6) y el autocompletado", se corrige
  con C-238.

**Pregunta abierta:** con qué pieza se elige de una lista. No hay selector en `vault/design-system/componentes.md` ni en
`movil/src/piezas/`. No la diseña esta spec (ver Open Questions).

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
- [La licencia del catálogo de SEPOMEX no permite usarlo en el producto] (grado E, sin verificar) → No se versiona y la
  carga en producción espera a Nadir. Si no se puede, el municipio sale de otra fuente y la colonia vuelve a ser solo
  texto, como hoy: direcciones-E20 ya cubre ese camino.
- [Sin cargar el catálogo, la lista de municipios sale vacía] y la dirección escrita no se puede guardar, en
  desarrollo también → La carga es un paso de la instalación de cada entorno, junto a `plazas:cargar`. La dirección
  marcada en el mapa sigue sirviendo.
- [Un nombre de municipio que se repite en otro estado] ("Matamoros" también es de Tamaulipas) → El texto que se
  geocodifica lleva el estado (decisión 10), y el municipio se elige por código, no por nombre.
- [Las condiciones de Google limitan guardar el punto que dan Geocoding y Places] (grado E, `SIN VERIFICAR`: de memoria,
  los términos de cada servicio permiten guardar la latitud y la longitud de forma temporal, hasta 30 días; fuente:
  `https://cloud.google.com/maps-platform/terms/maps-service-terms`, sin consulta en esta sesión) → Si se confirma,
  toca la decisión 4 del ADR-0009 y la 3 de `presupuesto-de-mapas.md` ("una dirección se geocodifica una sola vez en su
  vida"), no solo el autocompletado. El punto marcado en el mapa no sale de Google, así que no le aplica. Hay que
  verificarlo antes de producción. **Decidida la opción b el 2026-11-24** (adenda del 2026-11-24 de ADR-0009): cada
  dirección geocodificada por Google guarda su place ID y la fecha de su punto, y pasados 30 días el punto se vuelve a
  pedir por el place ID al usarla (direcciones-E07 y E24). Siguen abiertos en DD-139 la verificación de los términos
  firmados y el trato de las copias del punto (`pedido_direcciones.ubicacion` y `lavanderias.ubicacion`).
- [Las sesiones de sugerencias que no terminan en una dirección guardada se cobran por petición] (grado E) → Tres
  caracteres antes de pedir, 300 ms de espera entre teclas, 30 peticiones por minuto por cuenta, y la app deja de pedir
  al recibir `"activo": false`. Si el primer mes pasa del nivel gratuito, se apaga por configuración (adenda, criterio
  de reversión).
- [Las sugerencias de Places mostradas sin un mapa de Google piden la atribución de Google] (grado E) → Va con las
  sugerencias (direcciones-E21); su forma exacta se verifica al desbloquear.

## Open Questions

- **Desde dónde se geocodifica.** El ADR-0009 dice "una vez por dirección al guardarla", pero no si la consulta sale de
  `api` o de la app. Esta spec la pone en `api`: la llave no viaja en la app y el conteo de consultas vive en un solo
  lugar. DD-135, punto 6, lo decidió así ("la geocodificación en el servidor, con un ADR corto"; Claude, por delegación
  de Arthur, C-236). **Falta asentarlo en el ADR-0009:** va como precisión de la decisión 4 en la adenda propuesta de
  abajo.
- **La calle sugerida al soltar el pin** que dibuja el mock `a08-direccion.html` supone geocodificación inversa, que el
  presupuesto pone en cero. No entra. Si se quiere, **necesita ADR**, con su costo.
- **Autocompletado de direcciones** (pendiente de `presupuesto-de-mapas.md`; BR-12). Lo responde la adenda propuesta:
  listas propias para el municipio y la colonia, y Places para la calle, apagado hasta desbloquearse.
- **Si `municipio` sale de `direcciones` o se queda.** Esta spec la quita (decisión 7) porque no hay datos reales. La
  otra opción es dejarla vacía en las filas nuevas y quitarla después, que deja una columna muerta. **Resuelta: sale** (decisión 29 del acta del 2026-11-20, por
  delegación C-236).
- **Si el código postal es obligatorio.** Esta spec lo pide sin exigirlo (decisión 9). C-238 dice que "pasa a pedirse";
  exigirlo dejaría sin guardar a quien no sabe su código postal, contra "el catálogo ayuda, no bloquea". **Resuelta: se pide y no se exige**
  (decisión 29).
- **Con qué pieza se elige de una lista** el municipio y la colonia. No hay selector en el design system ni en las
  piezas de la app, y el mock orienta pero no lo define. **Resuelta (decisión 29):** una pieza nueva,
  `Selector`, en `movil/src/piezas/`: un campo que abre una hoja con un buscador y la lista, sin dependencias nuevas
  (`Modal`, `TextInput` y `FlatList` de React Native). La crea la tarea 5.1.
- **Filas abiertas por este diseño:** DD-137, DD-138 y DD-139, en `vault/meta/deuda/`:
  - **El costo del autocompletado de la calle.** ¿Se acepta pagar Places por cada sesión de sugerencias, a nombre de EL
    CLIENTE, que es quien contrata el servicio (RN-20)? La contesta Nadir con EL CLIENTE. Bloquea direcciones-E21 a E23.
  - **La licencia del catálogo de SEPOMEX.** ¿Se puede usar en el producto? La verifica Nadir. Bloquea la carga en
    producción, no los escenarios.
  - **Cuánto tiempo se puede guardar el punto de Google.** La verifica Arthur contra los términos vigentes. Puede tocar
    la decisión 4 del ADR-0009. **Decidida la opción b el 2026-11-24** (adenda del 2026-11-24 de ADR-0009): el place ID
    y la fecha del punto, que se vuelve a pedir pasados 30 días (tarea 3.5). Siguen abiertos en DD-139 la verificación
    de los términos firmados y el trato de las copias del punto.
- **DD-083:** si la lavandería atiende toda su plaza o un radio (direcciones-E13).
- **¿El TERRITORIO INICIAL es una plaza o dos?** Pendiente de Nadir en `modelo-multi-plaza.md`; es DD-093.
- **`id_plazas` de una dirección fuera de toda zona.** El modelo dice que la plaza sale de la zona que contiene el
  punto, pero no qué pasa si no cae en ninguna. direcciones-E11 deja que esa dirección exista y la rechaza al elegirla,
  así que esta spec deja la columna vacía en ese caso; la foránea compuesta de `pedido_direcciones` impide usarla en un
  pedido. Si Arthur prefiere rechazarla al guardar, `id_plazas` pasa a `NOT NULL` y cambia el momento del rechazo de
  direcciones-E11. **Resuelta en la tarea 1.1, ya hecha:** la columna queda vacía y el rechazo es al elegirla.
- **`created_at` y `updated_at`:** las tablas de este cambio los llevan, en `timestamptz` (modelo-E17; ADR-0016, regla 7).

## Adenda del 2026-11-21 al ADR-0009

> Asentada en `vault/arquitectura/adr/ADR-0009-mapas.md` (decisión 29 del acta del 2026-11-20, por delegación
> C-236). Las tareas 4.1 a 5.2 ya pueden empezar; las 3.2 a 3.4 esperan DD-137.

**Contexto.**

- Arthur pidió la dirección asistida y aprobó sus tres piezas (C-238). F-A08 pide "Ingreso manual o desde mapa" sin
  decir cómo (`NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3`, Módulo A #8).
- RN-29 restringe el mapa, no la geocodificación ni el autocompletado. RN-23 pide el consumo de mapas acotado por diseño
  (`NSV-2026-001-v8.pdf:29-30:ANEXO B, Sección B.5`).
- `presupuesto-de-mapas.md` dejó pendiente "si el autocompletado de direcciones usa un servicio de pago o una lista
  propia", para este ADR.
- DD-135, punto 6, decidió que la geocodificación sale del servidor, "con un ADR corto" (C-236).

**Decisión.**

- **4, precisión.** La geocodificación de la decisión 4 la hace el servidor (`api`), nunca la app. La llave no viaja en
  la app y las consultas de pago se cuentan en un solo lugar. El texto que se geocodifica lleva el municipio y el estado
  del catálogo.
- **6. El municipio y la colonia se asisten con listas propias, sin costo por consulta.** Los municipios del territorio
  y las colonias por código postal, del Catálogo Nacional de Códigos Postales de Correos de México, cargados en la base
  con un comando. El municipio se elige de la lista y la API rechaza uno que no esté en ella. La colonia y el código
  postal siguen siendo texto: el catálogo los sugiere y no los exige.
- **7. La calle se autocompleta con Google Places (la API nueva), desde el servidor, apagado por omisión.** Se enciende
  en cada entorno con `GOOGLE_PLACES_ACTIVO` y `GOOGLE_PLACES_LLAVE` (ADR-0012). Una vez construidas las tareas 3.2 a
  3.4, encenderlo o apagarlo no pide otra versión de la app ni cambia el resto de la API.
  - **Una sesión por dirección.** La app crea un token, un UUID v4, al empezar a escribir la calle de una dirección
    nueva. Lo manda en cada petición de sugerencias y al guardar.
  - **La sesión termina en una sola consulta de detalle,** al guardar, con el mismo token y la máscara de campos
    `location`. Esa consulta sustituye a la geocodificación de esa dirección, así que una dirección sigue costando una
    sola consulta de pago al guardarse.
  - **Menos peticiones:** tres caracteres antes de pedir, 300 ms de espera entre teclas, hasta cinco sugerencias, 30
    peticiones por minuto por cuenta, y la app deja de pedir cuando el servidor responde que está apagado.
  - **Solo México,** con el municipio y el estado elegidos.
  - **Con la atribución de Google** que pidan sus condiciones (grado E).

**Alternativas para la calle.**

| Alternativa | Qué pasa con ella |
|---|---|
| Places desde la app, con la llave en la app | Se descarta. La llave de un servicio web viaja en cada teléfono y se restringe peor que en el servidor (grado E). El conteo de consultas queda repartido. Contradice la precisión de la decisión 4 |
| Un catálogo propio de calles | Se descarta. SEPOMEX no trae calles. El de vialidades del INEGI (grado E, sin verificar) pesa más y hay que mantenerlo y cruzarlo con los números |
| Sin autocompletado | No se descarta: es lo que queda mientras esté apagado, y la dirección escrita se sigue pudiendo guardar |

**Costo** (grado E, `SIN VERIFICAR`; precios en dólares):

- **De `verificaciones-fase-6.md`, fila 6, consultado el 2026-09-28** en
  `https://developers.google.com/maps/billing-and-pricing/pricing`:
  - geocodificación: 10,000 gratis al mes y después 5 USD por mil;
  - autocompletado: 10,000 gratis y después 2.83 USD por mil.
- **De memoria de Claude, sin consulta en esta sesión,** en esa página y en
  `https://developers.google.com/maps/documentation/places/web-service/session-pricing`:
  - el detalle de un lugar con solo campos básicos, como `location`, se cobra en el nivel más bajo, con un precio
    cercano al de la geocodificación;
  - las sugerencias de una sesión que termina en una consulta de detalle no se cobran aparte;
  - las de una sesión que no termina se cobran por petición.

**Consumo por dirección nueva escrita:**

| Evento de pago | Apagado | Encendido, eligió una sugerencia | Encendido, escribió sin elegir |
|---|---|---|---|
| Geocodificación | 1 | 0 | 1 |
| Detalle de Places | 0 | 1 | 0 |
| Peticiones de sugerencias que se cobran | 0 | 0 (E) | Las que pidió (E) |

Esta nota no estima el gasto en pesos. Para eso falta el número de direcciones nuevas al mes, que es dato del negocio
(regla 3 de evidencia).

**Quién decide qué.** Arthur asienta la adenda, que es técnica. Encender el autocompletado en producción es gasto de un
servicio contratado a nombre de EL CLIENTE (RN-20): lo acepta Nadir con EL CLIENTE, en la fila que propone este diseño.

**Criterio de reversión.** Si en el primer mes con el autocompletado encendido las sugerencias pasan del nivel
gratuito, se apaga por configuración, sin tocar nada más. Si la licencia de SEPOMEX no permite usar el catálogo, la
colonia vuelve a ser solo texto y el municipio sale de otra fuente.

**Costo estimado de revertir.** Bajo: el autocompletado es un interruptor, y la colonia sin lista es el formulario de
hoy.
