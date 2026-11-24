Cada tarea sigue rojo, verde, refactor: primero el test con el identificador del escenario, que falla; luego el código
mínimo que lo pone en verde; luego la limpieza. Todas viven en `servidor/`. Tamaños de S-006, banda media: S = 2 h,
M = 5 h.

## 0. Tablas y migración (servidor/)

- [x] 0.1 Migración de `comisiones` y `avisos_masivos`, con la llave compuesta con la plaza hacia `lavanderias`, el `CHECK` del porcentaje, `avisos_masivos.id_plazas` opcional (lista de "plaza opcional") y `created_at` y `updated_at` en `timestamptz`, y sus modelos de Eloquent `Comision` y `AvisoMasivo`, sobre `App\Models\Modelo`, que sostienen administracion-E11 a administracion-E16, administracion-E19, administracion-E23 y administracion-E24 — test: `servidor/tests/Feature/Administracion/MigracionTest.php` y la prueba de esquema de `modelo-de-datos` (modelo-E01 a modelo-E17) — tamaño S
- [x] 0.2 Migración de `movimientos_pasarela` y su modelo de Eloquent `MovimientoPasarela`, con la forma que fija la segunda adenda del 2026-11-22 de ADR-0010 (decisiones 6 y 7, que cierran DD-112): una fila por orden, sin plaza propia (lista "sin plaza" de `config/modelo.php`, con su razón), para administracion-E20 a administracion-E22 — test: `servidor/tests/Feature/Administracion/MigracionTest.php` y la prueba de esquema de `modelo-de-datos` (modelo-E01 a modelo-E17) — tamaño S

## 1. Gestión de usuarios (servidor/)

- [x] 1.1 administracion-E01: ficha del usuario con su historial de pedidos, de solo lectura — test: `servidor/tests/Feature/Administracion/GestionDeUsuariosTest.php` — tamaño S
- [ ] 1.2 administracion-E02: filtro de usuarios activos del periodo, sobre `actividad_usuarios` — test: `servidor/tests/Feature/Administracion/GestionDeUsuariosTest.php` — tamaño S — bloqueada: BR-33, DD-111
- [x] 1.3 administracion-E03 y administracion-E04: bloquear y levantar el bloqueo en `usuarios.estado`; se revocan los tokens de Sanctum y se rechaza la entrada por correo, Google y Apple — test: `servidor/tests/Feature/Administracion/BloqueoDeUsuariosTest.php` — tamaño M
- [ ] 1.4 administracion-E05: bloqueo con un pedido activo — test: `servidor/tests/Feature/Administracion/BloqueoDeUsuariosTest.php` — tamaño S — bloqueada: DD-016

## 2. Monitoreo de pedidos (servidor/)

- [x] 2.1 administracion-E06: tabla de los pedidos activos de todas las plazas, con folio, estado y plaza — test: `servidor/tests/Feature/Administracion/MonitoreoDePedidosTest.php` — tamaño M
- [x] 2.2 administracion-E07: refresco cada 30 s sin recargar (ADR-0007) — test: `servidor/tests/Feature/Administracion/MonitoreoDePedidosTest.php` — tamaño S
- [ ] 2.3 administracion-E08: el pedido cancelado sale del monitoreo — test: `servidor/tests/Feature/Administracion/MonitoreoDePedidosTest.php` — tamaño S — bloqueada: DD-016, BR-22
- [x] 2.4 administracion-E09 y administracion-E10: mapa con un marcador en la recolección y otro en la entrega si es distinta (Google Maps JavaScript, ADR-0009) — test: `servidor/tests/Feature/Administracion/MapaDelMonitoreoTest.php` — tamaño M

## 3. Comisiones (servidor/)

- [x] 3.1 administracion-E11, administracion-E12 y administracion-E13: porcentaje global, uno para todas las plazas (migración `2026_11_26_100000_hacer_global_la_comision_global`), y propio por socio en `comisiones`, sin valores de fábrica y entre 0 y 100 — test: `servidor/tests/Feature/Administracion/ConfiguracionDeComisionesTest.php` — tamaño M
- [ ] 3.2 administracion-E14: comisión de un socio repartidor — test: `servidor/tests/Feature/Administracion/ConfiguracionDeComisionesTest.php` — tamaño S — bloqueada: DD-019
- [ ] 3.3 administracion-E15 y administracion-E16: cálculo de la comisión de cada pedido y efecto de un cambio de porcentaje — test: `servidor/tests/Feature/Administracion/ComisionDelPedidoTest.php` — tamaño S — bloqueada: DD-020, DD-013

## 4. Reportes financieros (servidor/)

- [x] 4.1 administracion-E17: ingresos del periodo con los pagos que la pasarela confirmó por webhook, en la hora de la plaza — test: `servidor/tests/Feature/Administracion/ReportesFinancierosTest.php` — tamaño M
- [ ] 4.2 administracion-E18: reembolsos y ajustes en los ingresos — test: `servidor/tests/Feature/Administracion/ReportesFinancierosTest.php` — tamaño S — bloqueada: DD-012, DD-016
- [ ] 4.3 administracion-E19: comisiones del periodo por socio y en total — test: `servidor/tests/Feature/Administracion/ReportesFinancierosTest.php` — tamaño S — bloqueada: DD-020
- [x] 4.4 administracion-E20: lectura de los movimientos de la pasarela del periodo, por la operación nueva de la interfaz neutral del cobro, y marca de los conciliados (pasarela simulada), según la segunda adenda del 2026-11-22 de ADR-0010 (decisiones 6 y 7, que cierran DD-112) — test: `servidor/tests/Feature/Administracion/ConciliacionTest.php` — tamaño M
- [x] 4.5 administracion-E21: los tres tipos de diferencia en el reporte, según la segunda adenda del 2026-11-22 de ADR-0010 (que cierra DD-112) — test: `servidor/tests/Feature/Administracion/ConciliacionTest.php` — tamaño S
- [ ] 4.6 administracion-E22: comisión que retiene la pasarela — test: `servidor/tests/Feature/Administracion/ConciliacionTest.php` — tamaño S — bloqueada: DD-013

## 5. Avisos masivos (servidor/)

- [x] 5.1 administracion-E23: aviso global por FCM, en cola, solo a cuentas con dispositivo registrado, guardado en `avisos_masivos` (FCM simulado) — test: `servidor/tests/Feature/Administracion/AvisosMasivosTest.php` — tamaño M
- [ ] 5.2 administracion-E24: aviso a un segmento — test: `servidor/tests/Feature/Administracion/AvisosMasivosTest.php` — tamaño M — bloqueada: BR-32, DD-113

## Horas

- **Este archivo:** 45 h sin bloqueo y 21 h bloqueadas, 66 h en total. La decisión 34 (segunda adenda del 2026-11-22 de
  ADR-0010) cerró DD-112: 0.2 (S), 4.4 (M) y 4.5 (S), 9 h, dejan de estar bloqueadas.
- **Contra la versión anterior (66 h; 36 sin bloqueo y 30 bloqueadas):** el total no cambia; 9 h pasan de bloqueadas a
  sin bloqueo.
- **Antes (62 h; 41 sin bloqueo y 21 bloqueadas):** entraron la migración de `comisiones` y `avisos_masivos` (0.1, S) y
  la de `movimientos_pasarela` (0.2, S), y 4.4 y 4.5 se bloquearon por DD-112 hasta la decisión 34.
- **Contra el plan:** el plan de sprints le da 39 h (F-D03 a F-D07). La diferencia pasa de 23 h a 27 h.
