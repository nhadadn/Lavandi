Cada tarea sigue rojo, verde, refactor: primero el test con el identificador del escenario, que falla; luego el código
mínimo que lo pone en verde; luego la limpieza. Todas viven en `servidor/`. Tamaños de S-006: S = 2 h, M = 5 h.

**Horas:** 21 en total, contra 5 del plan (F-T14, contada como una sola M). La diferencia son los 14 exportadores, uno
por reporte: 15 h. La tarea 1.3 está parcialmente bloqueada por los indicadores y reportes que esperan respuesta. El
modelo de datos no le suma horas: este cambio no crea tablas.

## 1. Descarga en CSV y en Excel (servidor/)

- [ ] 1.1 exportacion-E01 y exportacion-E02: exportador de pedidos totales en CSV y XLSX, con la exportación de Filament (depende de la pregunta abierta 3 de `design.md`, sobre `exports` y `notifications`) — test: `servidor/tests/Feature/Exportacion/DescargaCsvYExcelTest.php` — tamaño M
- [ ] 1.2 exportacion-E03: exportadores de los indicadores de operaciones y de usuarios (F-I01 a F-I05), con las cifras del panel — test: `servidor/tests/Feature/Exportacion/TodosLosReportesTest.php` — tamaño M
- [ ] 1.3 exportacion-E03: exportadores de los indicadores de socios, dinero y geografía (F-I06 a F-I11) y de los tres reportes financieros — test: `servidor/tests/Feature/Exportacion/TodosLosReportesTest.php` — tamaño M — parcialmente bloqueada: DD-083, DD-020, DD-013 y DD-115, a través de sus indicadores; la conciliación, por DD-112

## 2. Rango de fechas (servidor/)

- [ ] 2.1 exportacion-E04, exportacion-E05 y exportacion-E06: rango con los dos extremos incluidos, sin tope de duración y rechazo del rango al revés — test: `servidor/tests/Feature/Exportacion/RangoDeFechasTest.php` — tamaño S

## 3. Escritura del archivo (servidor/)

- [ ] 3.1 exportacion-E07, exportacion-E08 y exportacion-E09: acentos y eñes legibles en Excel, y escape de fórmulas solo en celdas de texto — test: `servidor/tests/Feature/Exportacion/EscrituraDelArchivoTest.php` — tamaño S

## 4. Segundo plano (servidor/)

- [ ] 4.1 exportacion-E10 y exportacion-E11: generación en cola, aviso en el panel y descarga solo para quien la pidió — test: `servidor/tests/Feature/Exportacion/GeneracionEnSegundoPlanoTest.php` — tamaño S
