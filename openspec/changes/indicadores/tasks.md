Cada tarea sigue rojo, verde, refactor: primero el test con el identificador del escenario, que falla; luego el código
mínimo que lo pone en verde; luego la limpieza. Todas viven en `servidor/`. Tamaños de S-006, banda media: S = 2 h,
M = 5 h.

## 0. Tablas y migración (servidor/)

- [x] 0.1 Migración de `actividad_usuarios`, con su llave primaria compuesta `(id_usuarios, fecha)`, y su acceso desde Eloquent como lo resuelva `registro-e-inicio-de-sesion` para `rol_usuario`, que sostiene indicadores-E06 e indicadores-E07 — test: `servidor/tests/Feature/Indicadores/MigracionTest.php` y la prueba de esquema de `modelo-de-datos` (modelo-E01 a modelo-E17) — tamaño S

## 1. Operaciones (servidor/)

- [x] 1.1 indicadores-E01: página de indicadores con rango y granularidad; pedidos por día en barras y en tabla — test: `servidor/tests/Feature/Indicadores/PedidosTotalesTest.php` — tamaño M
- [x] 1.2 indicadores-E02: pedidos por semana y por mes, cada periodo junto al anterior — test: `servidor/tests/Feature/Indicadores/PedidosTotalesTest.php` — tamaño S
- [x] 1.3 indicadores-E03: el día de un pedido es el de su plaza — test: `servidor/tests/Feature/Indicadores/PedidosTotalesTest.php` — tamaño S
- [x] 1.4 indicadores-E04: dona de activos y completados — test: `servidor/tests/Feature/Indicadores/PedidosPorEstadoTest.php` — tamaño S
- [ ] 1.5 indicadores-E05: cancelados en la dona — test: `servidor/tests/Feature/Indicadores/PedidosPorEstadoTest.php` — tamaño S — bloqueada: DD-016, BR-22

## 2. Usuarios (servidor/)

- [x] 2.1 indicadores-E06: personas distintas por día, semana y mes, leídas de `actividad_usuarios`, en línea de tendencia — test: `servidor/tests/Feature/Indicadores/UsuariosActivosTest.php` — tamaño M
- [ ] 2.2 indicadores-E07: qué escribe en `actividad_usuarios` — test: `servidor/tests/Feature/Indicadores/UsuariosActivosTest.php` — tamaño S — bloqueada: BR-33, DD-111
- [x] 2.3 indicadores-E08: porcentaje que vuelve a pedir, por mes de registro — test: `servidor/tests/Feature/Indicadores/RetencionPorMesDeRegistroTest.php` — tamaño M
- [x] 2.4 indicadores-E09: monto promedio y su tendencia frente al periodo anterior — test: `servidor/tests/Feature/Indicadores/MontoPromedioTest.php` — tamaño S
- [ ] 2.5 indicadores-E10: qué monto cuenta, estimado o final — test: `servidor/tests/Feature/Indicadores/MontoPromedioTest.php` — tamaño S — bloqueada: DD-012

## 3. Socios (servidor/)

- [ ] 3.1 indicadores-E11: lavanderías activas frente a registradas — test: `servidor/tests/Feature/Indicadores/IndicadoresDeSociosTest.php` — tamaño S — bloqueada: DD-083
- [x] 3.2 indicadores-E12: ranking por calificación promedio, con las que no tienen calificaciones al final — test: `servidor/tests/Feature/Indicadores/IndicadoresDeSociosTest.php` — tamaño S
- [x] 3.3 indicadores-E13: semáforo con dos cortes de configuración y su texto — test: `servidor/tests/Feature/Indicadores/IndicadoresDeSociosTest.php` — tamaño S
- [ ] 3.4 indicadores-E14: cargar los valores de los cortes — test: `servidor/tests/Feature/Indicadores/IndicadoresDeSociosTest.php` — tamaño S — bloqueada: BR-33, DD-114
- [x] 3.5 indicadores-E15: entregas completadas por repartidor — test: `servidor/tests/Feature/Indicadores/IndicadoresDeSociosTest.php` — tamaño S

## 4. Financiero (servidor/)

- [x] 4.1 indicadores-E16: ingresos brutos por periodo, con el mismo cálculo del reporte financiero — test: `servidor/tests/Feature/Indicadores/IngresosYComisionesTest.php` — tamaño M
- [ ] 4.2 indicadores-E17: agrupación de las barras — test: `servidor/tests/Feature/Indicadores/IngresosYComisionesTest.php` — tamaño S — bloqueada: BR-31, DD-115
- [ ] 4.3 indicadores-E18: comisiones por socio y en total — test: `servidor/tests/Feature/Indicadores/IngresosYComisionesTest.php` — tamaño S — bloqueada: DD-020, DD-013

## 5. Geográfico (servidor/)

- [x] 5.1 indicadores-E19: agregados por colonia en el servidor y su componente de mapa, sin geocodificación inversa — test: `servidor/tests/Feature/Indicadores/MapaDeCalorTest.php` — tamaño M
- [x] 5.2 indicadores-E20: zonas de servicio pintadas con su número de pedidos (PostGIS) — test: `servidor/tests/Feature/Indicadores/MapaDeCalorTest.php` — tamaño M

## 6. El panel (servidor/)

- [x] 6.1 indicadores-E21: refresco automático con intervalo de configuración — test: `servidor/tests/Feature/Indicadores/PanelEnTiempoRealTest.php` — tamaño S
- [x] 6.2 indicadores-E22: el valor del intervalo, 30 s por omisión (adenda del 2026-11-22 de ADR-0007, decisión 33; cierra DD-116) — test: `servidor/tests/Feature/Indicadores/PanelEnTiempoRealTest.php` — tamaño S
- [x] 6.3 indicadores-E23: cada indicador con las cifras de la base, sin cifras de ejemplo en el código — test: `servidor/tests/Feature/Indicadores/DatosRealesTest.php` — tamaño S
- [x] 6.4 indicadores-E24: lo que no tiene plaza se lee en la zona horaria de referencia de la configuración — test: `servidor/tests/Feature/Indicadores/RetencionPorMesDeRegistroTest.php`, `servidor/tests/Feature/Indicadores/PedidosTotalesTest.php` y `servidor/tests/Feature/Administracion/ConciliacionTest.php` — tamaño S

## Horas

- **Este archivo:** 54 h sin bloqueo y 14 h bloqueadas, 68 h en total. La 6.4 (S) es nueva: indicadores-E24, de la
  pregunta 8 que resolvió el concilio del 2026-11-24 (C-245; `acta-2026-11-20-modelo-y-pasarela.md`, decisión 60).
- **Contra la versión anterior (66 h; 52 sin bloqueo y 14 bloqueadas):** 2 h más, de la zona horaria de lo que no
  tiene plaza (6.4, S).
- **Contra el plan:** el plan de sprints le da 57 h (F-I01 a F-I11). La diferencia pasa de 9 h a 11 h.
