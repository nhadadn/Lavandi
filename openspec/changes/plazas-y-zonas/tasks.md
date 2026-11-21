Cada tarea va en rojo, verde y refactor: primero el test que falla, luego el código mínimo, luego la limpieza. Una
tarea sin test en verde no se marca. Tamaños de S-006: S = 2 h, M = 5 h, L = 12 h.

## 1. Tablas y ficha de la plaza

- [ ] 1.1 plazas-E03 y la prueba de esquema de `modelo-de-datos` (modelo-E01 a modelo-E17) sobre las tablas nuevas
  (`servidor/`): migración de `plazas`, `zonas` (polígono `geography` con índice GiST, ligada a su plaza) y
  `parametros_plaza`, con las columnas, los únicos y los `CHECK` de `design.md` ("Tablas y migración"); modelos de
  Eloquent `Plaza`, `Zona` y `ParametroPlaza` sobre `App\Models\Modelo`, con su `$table`, su `$primaryKey` y las llaves
  de cada relación. Necesita `modelo-de-datos` y las preguntas abiertas de la llave natural y de `created_at` de
  `design.md` — test: `servidor/tests/Feature/Plazas/MigracionTest.php` y `servidor/tests/Feature/Modelo/` — tamaño S
- [ ] 1.2 plazas-E01, plazas-E02 (`servidor/`): zona horaria obligatoria y válida, y estado de la plaza; hora local de
  un instante — test: `servidor/tests/Feature/Plazas/FichaDePlazaTest.php` — tamaño S

## 2. Zonas y resolución de un punto

- [ ] 2.1 plazas-E04, plazas-E05, plazas-E06, plazas-E07 (`servidor/`): resolución de un punto a zona y plaza activa,
  con las funciones de PostGIS sobre `zonas.poligono`; el usuario sin plaza — test:
  `servidor/tests/Feature/Plazas/ResolucionDePuntoTest.php` — tamaño M

## 3. Abrir una plaza con datos

- [ ] 3.1 plazas-E08 (`servidor/`): comando que carga una plaza y sus zonas desde un archivo GeoJSON, sin duplicar al
  repetirse, con el sembrado de plazas de prueba — test: `servidor/tests/Feature/Plazas/AbrirPlazaConDatosTest.php` —
  tamaño S
- [ ] 3.2 plazas-E09 (`servidor/`): prueba de esquema sobre la lista de tablas sin plaza de `servidor/config/modelo.php`
  (la de `modelo-de-datos`, sin una segunda lista); toda tabla de operación con `id_plazas` no nula y con llave foránea
  a `plazas`, que completa a modelo-E05 — test: `servidor/tests/Feature/Plazas/FilaDeOperacionConPlazaTest.php` —
  tamaño S

## 4. Territorio inicial (bloqueada)

- [ ] 4.1 plazas-E10, plazas-E11 (`servidor/`): archivo del territorio inicial y su carga con el comando de la tarea
  3.1 — test: `servidor/tests/Feature/Plazas/TerritorioInicialTest.php` — tamaño S. **No entra al sprint** mientras
  sigan abiertas las filas DD-093 y DD-094

## Horas

- Listas: 13 h (1.1, 1.2, 2.1, 3.1 y 3.2). Bloqueadas: 2 h (4.1). Total: 15 h.
- El plan da 11 h a este cambio (F-T05, lo que falta; F-T15 va contado en F-T05). Diferencia: +4 h (antes de alinear
  con el modelo de datos eran +2): 2 h de la carga del territorio inicial y 2 h de la migración de las tres tablas con
  sus modelos (tarea 1.1, C-193), que suma `parametros_plaza`.
