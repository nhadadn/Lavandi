## 1. Servidor: tabla, alta, favoritas, zona y aislamiento

Cada tarea va en rojo, verde y refactor: primero el test que falla, con el identificador del escenario en su nombre;
luego el código mínimo; luego la limpieza.

- [x] 1.1 Migración de `direcciones` con sus llaves, el único `(id_direcciones, id_plazas)`, el índice GiST de `ubicacion` y el borrado suave, y el modelo `Direccion` con `$table`, `$primaryKey`, `HasUlids` y `SoftDeletes`; pasa la prueba de esquema de `modelo-de-datos` (modelo-E01 a modelo-E17) — test: `servidor/tests/Feature/Direcciones/MigracionTest.php` — tamaño S
- [x] 1.2 direcciones-E01, direcciones-E02, direcciones-E03: guardar una dirección escrita con colonia obligatoria y una sola geocodificación, con el servicio simulado, y su plaza tomada de la zona que contiene el punto — test: `servidor/tests/Feature/Direcciones/DireccionEscritaTest.php` — tamaño M
- [x] 1.3 direcciones-E04, direcciones-E06, direcciones-E07, direcciones-E08: guardar el punto del mapa sin geocodificar, y favoritas con nombre (`alias`, `es_favorita`) que se reusan sin consulta — test: `servidor/tests/Feature/Direcciones/MapaYFavoritasTest.php` — tamaño S
- [x] 1.4 direcciones-E09, direcciones-E10, direcciones-E11, direcciones-E12: recogida y entrega dentro de una zona de servicio de la plaza de la lavandería, con polígonos de prueba en `zonas` — test: `servidor/tests/Feature/Direcciones/ZonaDeServicioTest.php` — tamaño M
- [x] 1.5 direcciones-E14: una cuenta no ve ni usa las direcciones de otra, pedidas por su `id_publico` — test: `servidor/tests/Feature/Direcciones/AislamientoTest.php` — tamaño S

## 2. App: formulario, mapa y elección

- [x] 2.1 direcciones-E01, direcciones-E02, direcciones-E03: formulario de dirección escrita, con la colonia obligatoria y la salida al mapa si no se encuentra — test: `movil/__tests__/direcciones/formulario.test.tsx` — tamaño S
- [x] 2.2 direcciones-E04, direcciones-E05: marcar la dirección en el mapa con `react-native-maps` sin proveedor — test: `movil/__tests__/direcciones/mapa.test.tsx` — tamaño M
- [x] 2.3 direcciones-E06, direcciones-E08, direcciones-E09, direcciones-E10, direcciones-E11: lista de favoritas, recogida y entrega, y el aviso de fuera de zona — test: `movil/__tests__/direcciones/recogida-y-entrega.test.tsx` — tamaño S

## 3. Bloqueadas: no entran al sprint mientras su fila siga abierta

- [ ] 3.1 direcciones-E13: radio de servicio de la lavandería dentro de su plaza (DD-083) — test: `servidor/tests/Feature/Direcciones/RadioDeServicioTest.php` — tamaño S
- [ ] 3.2 direcciones-E21, direcciones-E23: `GET /api/direcciones/sugerencias` con Google Places detrás de una interfaz simulada en el test; el interruptor `GOOGLE_PLACES_ACTIVO` con `GOOGLE_PLACES_LLAVE` (ADR-0012), que apagado o sin llave responde `"activo": false` sin consultar; solo México, con el municipio y el estado; `throttle:sugerencias` de 30 por minuto por cuenta (DD-072, la llave; costo, fila propuesta en `design.md`) — test: `servidor/tests/Feature/Direcciones/SugerenciasDeCalleTest.php` — tamaño M — vive en `servidor/`
- [ ] 3.3 direcciones-E22, direcciones-E23: `lugar` en `POST /api/direcciones`: con el autocompletado encendido, una sola consulta de detalle con el mismo token y la máscara `location`, en lugar de la geocodificación; apagado, se ignora y se geocodifica (DD-072; costo, fila propuesta) — test: `servidor/tests/Feature/Direcciones/SesionDeSugerenciasTest.php` — tamaño S — vive en `servidor/`
- [ ] 3.4 direcciones-E21, direcciones-E22, direcciones-E23: las sugerencias bajo la calle, con un token UUID v4 por dirección, tres caracteres y 300 ms de espera, la atribución de Google, y sin mandar `lugar` si la persona cambió la calle; con `"activo": false` deja de pedir y la calle queda como texto libre (DD-072; costo, fila propuesta) — test: `movil/__tests__/direcciones/sugerencias-de-calle.test.tsx` — tamaño M — vive en `movil/`
- [ ] 3.5 direcciones-E07, direcciones-E24: guardar el place ID y la fecha del punto (columna nueva hacia adelante, con su fila en `vault/arquitectura/modelo-de-datos.md` antes de la migración, ADR-0016) y pedir el punto por el place ID al usar una dirección de más de 30 días (bloqueada: DD-139) — test: `servidor/tests/Feature/Direcciones/VigenciaDelPuntoTest.php` — tamaño M — vive en `servidor/`

## 4. Servidor: la dirección asistida (C-238)

Empiezan cuando Arthur asiente la adenda del ADR-0009 que propone `design.md` y responda dos de sus Open Questions: si
`municipio` sale de `direcciones` (tarea 4.1) y si el código postal es obligatorio (tarea 4.4). Mismo orden: rojo,
verde, refactor.

- [x] 4.1 Migración del catálogo: `entidades_federativas`, `municipios` y `colonias` con sus llaves, sus únicos de `codigo` y el índice de `colonias.codigo_postal`, en la lista `sin_plaza` de `servidor/config/modelo.php`; en `direcciones`, `id_municipios` opcional con su llave, su índice y `ON DELETE RESTRICT`, y sin `municipio`; los modelos `EntidadFederativa`, `Municipio` y `Colonia` con `$table` y `$primaryKey`; pasa la prueba de esquema de `modelo-de-datos` (modelo-E01 a modelo-E17) — test: `servidor/tests/Feature/Direcciones/MigracionDelCatalogoTest.php` — tamaño S — vive en `servidor/`
- [x] 4.2 direcciones-E16: el comando `direcciones:cargar-catalogo` con el territorio de `servidor/config/territorio.php`: busca las columnas por nombre, convierte de ISO-8859-1, compara el nombre de cada municipio con la configuración y carga todo en una transacción, que se puede repetir; archivos de prueba inventados en `servidor/tests/Fixtures/sepomex/`. Cargar el archivo real en producción espera la licencia (fila propuesta en `design.md`) — test: `servidor/tests/Feature/Direcciones/CargaDelCatalogoTest.php` — tamaño M — vive en `servidor/`
- [x] 4.3 direcciones-E17, direcciones-E19, direcciones-E20: `GET /api/catalogos/municipios` y `GET /api/codigos-postales/{codigo_postal}/colonias`, con el contrato exacto de `design.md` y dentro del grupo de `auth:sanctum` y `autorizar:usuario` — test: `servidor/tests/Feature/Direcciones/CatalogoTest.php` — tamaño S — vive en `servidor/`
- [x] 4.4 direcciones-E17, direcciones-E18, direcciones-E20: en `POST /api/direcciones`, `municipio` por el `codigo` del catálogo, guardado como `id_municipios`, también en la del mapa si viene; `codigo_postal` de cinco números; la colonia sin compararla con el catálogo; todo antes de geocodificar; la respuesta con `municipio` y `estado` del catálogo — test: `servidor/tests/Feature/Direcciones/MunicipioYCodigoPostalTest.php` — tamaño M — vive en `servidor/`
- [x] 4.5 direcciones-E01, direcciones-E02, direcciones-E15: el texto que se geocodifica con el municipio y el estado del catálogo; el geocodificador de prueba decide por el municipio del catálogo, no por la palabra "durango"; `CreacionDePedido` copia el nombre del municipio en `pedido_direcciones`; se ajustan las ayudas de prueba que escriben `municipio` (`DireccionesDePrueba`, `PedidosDePrueba`) y las pruebas de migración y de copia que lo nombran — test: `servidor/tests/Feature/Direcciones/DireccionEscritaTest.php`, `servidor/tests/Feature/Direcciones/GeocodificadorDePruebaTest.php` y `servidor/tests/Feature/Confirmacion/CrearPedidoTest.php` — tamaño S — vive en `servidor/`

## 5. App: la dirección asistida (C-238)

Empiezan con la 4.3 y la 4.4 en verde, y con la pieza de lista que decida Arthur (Open Questions de `design.md`).

- [x] 5.1 direcciones-E01, direcciones-E17, direcciones-E18: el municipio se elige de la lista de `GET /api/catalogos/municipios`, pedida una vez por sesión de la app; manda el `codigo`; el error del campo va junto a él; sin lista, lo dice y la forma del mapa sigue sirviendo; `consultarMunicipios` en `movil/src/direcciones/api.ts` — test: `movil/__tests__/direcciones/municipio.test.tsx` y `movil/__tests__/direcciones/formulario.test.tsx` — tamaño M — vive en `movil/`
- [x] 5.2 direcciones-E02, direcciones-E19, direcciones-E20: el campo de código postal de cinco números; con los cinco, las colonias de `GET /api/codigos-postales/{codigo_postal}/colonias` en una lista, que al elegir escribe la colonia y, sin municipio elegido, elige el suyo; sin colonias, lo dice y deja escribirla; la colonia sigue obligatoria aunque haya código postal; `consultarColonias` en `movil/src/direcciones/api.ts` — test: `movil/__tests__/direcciones/codigo-postal-y-colonia.test.tsx` — tamaño M — vive en `movil/`

## Horas

- **Banda media de S-006:** S = 2 h, M = 5 h.
- **Listas:** 51 h (7 M y 8 S). Antes eran 25 h; la dirección asistida (tareas 4.1 a 5.2: 4 M y 3 S) suma 26 h.
- **Bloqueadas:** 19 h (3 M y 2 S). Antes eran 2 h; el autocompletado (tareas 3.2 a 3.4) suma 12 h y la vigencia del
  punto de Google (tarea 3.5, DD-139) suma 5 h.
- **El plan:** le da 12 h a F-A08. Las listas quedan 39 h por encima (antes, 13). El plan de sprints no tenía la
  dirección asistida, que llegó con C-238.
