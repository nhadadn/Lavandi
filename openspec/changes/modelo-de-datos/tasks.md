Cada tarea se hace en rojo, verde y refactor: primero el test que falla, con el identificador del escenario en su
nombre; luego el código mínimo que lo pone en verde; luego la limpieza. Todas viven en `servidor/` y van después de
`base-tecnica`, antes de la primera tabla del negocio.

## 1. Prueba de esquema

- [ ] 1.1 modelo-E01, modelo-E02, modelo-E03 y modelo-E04: la prueba de esquema que lee los catálogos de PostgreSQL
  después de migrar y revisa los nombres de llaves y columnas, con la lista de excepciones en
  `servidor/config/modelo.php` — test: `servidor/tests/Feature/Modelo/NombresTest.php` — tamaño M
- [ ] 1.2 modelo-E05 y modelo-E06: la regla de la plaza en las tablas de operación y la llave compuesta en las
  hijas, probada con dos tablas de prueba dentro de una transacción — test:
  `servidor/tests/Feature/Modelo/PlazaTest.php` — tamaño S
- [ ] 1.3 modelo-E07, modelo-E08, modelo-E09, modelo-E10, modelo-E13, modelo-E14 y modelo-E17: tipos de dinero, horas y mapas,
  llaves foráneas con índice y `ON DELETE RESTRICT`, ninguna columna de tarjeta y ningún nombre de proveedor de
  pagos — test: `servidor/tests/Feature/Modelo/TiposEIntegridadTest.php` — tamaño S

## 2. Historial y modelos

- [ ] 2.1 modelo-E11 y modelo-E12: la función `impedir_cambio()` y su prueba sobre una tabla de prueba; cada cambio
  que crea `pedidos`, `pedido_eventos`, `pagos` o `reembolsos` le pone su trigger en la misma migración — test:
  `servidor/tests/Feature/Modelo/HistorialTest.php` — tamaño S
- [ ] 2.2 modelo-E15 y modelo-E16: la clase base `App\Models\Modelo`, el identificador público con `HasUlids` y la
  prueba que compara `$table` y `$primaryKey` de cada modelo con la base — test:
  `servidor/tests/Feature/Modelo/ModelosTest.php` — tamaño S

## Horas

- **Este archivo:** 1 tarea M y 4 S = 13 h (tamaños de S-006, banda media: S = 2 h, M = 5 h).
- **El plan:** este cambio no estaba en `vault/formas-de-trabajo/sprints/plan-de-sprints.md`. Sus 13 h se suman al
  hito H0, antes de `registro-e-inicio-de-sesion`; el plan se ajusta al re-proponer los cambios (C-193).
