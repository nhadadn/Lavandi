## 1. Servidor: tabla, alta, favoritas, zona y aislamiento

Cada tarea va en rojo, verde y refactor: primero el test que falla, con el identificador del escenario en su nombre;
luego el código mínimo; luego la limpieza.

- [ ] 1.1 Migración de `direcciones` con sus llaves, el único `(id_direcciones, id_plazas)`, el índice GiST de `ubicacion` y el borrado suave, y el modelo `Direccion` con `$table`, `$primaryKey`, `HasUlids` y `SoftDeletes`; pasa la prueba de esquema de `modelo-de-datos` (modelo-E01 a modelo-E17) — test: `servidor/tests/Feature/Direcciones/MigracionTest.php` — tamaño S
- [ ] 1.2 direcciones-E01, direcciones-E02, direcciones-E03: guardar una dirección escrita con colonia obligatoria y una sola geocodificación, con el servicio simulado, y su plaza tomada de la zona que contiene el punto — test: `servidor/tests/Feature/Direcciones/DireccionEscritaTest.php` — tamaño M
- [ ] 1.3 direcciones-E04, direcciones-E06, direcciones-E07, direcciones-E08: guardar el punto del mapa sin geocodificar, y favoritas con nombre (`alias`, `es_favorita`) que se reusan sin consulta — test: `servidor/tests/Feature/Direcciones/MapaYFavoritasTest.php` — tamaño S
- [ ] 1.4 direcciones-E09, direcciones-E10, direcciones-E11, direcciones-E12: recogida y entrega dentro de una zona de servicio de la plaza de la lavandería, con polígonos de prueba en `zonas` — test: `servidor/tests/Feature/Direcciones/ZonaDeServicioTest.php` — tamaño M
- [ ] 1.5 direcciones-E14: una cuenta no ve ni usa las direcciones de otra, pedidas por su `id_publico` — test: `servidor/tests/Feature/Direcciones/AislamientoTest.php` — tamaño S

## 2. App: formulario, mapa y elección

- [ ] 2.1 direcciones-E01, direcciones-E02, direcciones-E03: formulario de dirección escrita, con la colonia obligatoria y la salida al mapa si no se encuentra — test: `movil/__tests__/direcciones/formulario.test.tsx` — tamaño S
- [ ] 2.2 direcciones-E04, direcciones-E05: marcar la dirección en el mapa con `react-native-maps` sin proveedor — test: `movil/__tests__/direcciones/mapa.test.tsx` — tamaño M
- [ ] 2.3 direcciones-E06, direcciones-E08, direcciones-E09, direcciones-E10, direcciones-E11: lista de favoritas, recogida y entrega, y el aviso de fuera de zona — test: `movil/__tests__/direcciones/recogida-y-entrega.test.tsx` — tamaño S

## 3. Bloqueadas: no entran al sprint mientras su fila siga abierta

- [ ] 3.1 direcciones-E13: radio de servicio de la lavandería dentro de su plaza (DD-083) — test: `servidor/tests/Feature/Direcciones/RadioDeServicioTest.php` — tamaño S

## Horas

- **Banda media de S-006:** S = 2 h, M = 5 h.
- **Listas:** 25 h (3 M y 5 S). Antes eran 23 h; la migración (tarea 1.1, S) suma 2 h.
- **Bloqueadas:** 2 h (1 S), sin cambio.
- **El plan:** le da 12 h a F-A08. Las listas quedan 13 h por encima (antes, 11).
