Cada tarea sigue rojo, verde, refactor: primero el test con el identificador del escenario, que falla; luego el código
mínimo que lo pone en verde; luego la limpieza. Todas viven en `servidor/`, salvo la 6.1, que vive en `movil/`. Tamaños
de S-006, banda media: S = 2 h, M = 5 h.

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

## 6. La administración en la app (movil/ y servidor/)

- [x] 6.1 administracion-E25: el aviso de la cuenta de administración con el botón que abre el panel en expo-web-browser — test: `movil/__tests__/administracion/abrir-el-panel.test.tsx` — tamaño S
- [ ] 6.2 administracion-E25 y registro-E33: POST /api/sesion/google responde 403 sin token a una cuenta de administración — test: `servidor/tests/Feature/Registro/CuentaDeLosRolesDeLaAppTest.php` — tamaño S

La 6.2 vive en `servidor/`. Hoy `POST /api/sesion/google` no pregunta a `Autorizacion::usaLaApp()` y le da token a la
cuenta de administración; si su Google no estaba ligado, `EntradaConGoogle` además le anula la contraseña y le borra
los tokens antes de ligarlo (registro-E18), y el panel solo entra con contraseña. El rechazo va antes de ligar y de
cerrar el acceso anterior, para que la cuenta quede intacta (design.md, Open Questions 1). La app ya lee ese 403 como la
cuenta sin acceso: no hay que tocarla.

La revocación que la app pide de ese token tampoco sirve: `DELETE /api/sesion` está detrás de
`autorizar:usuario,lavanderia,repartidor` (`servidor/routes/api.php:58-60`) y a la administración le responde 403 sin
borrar nada (`servidor/tests/Feature/Registro/CuentaDeLosRolesDeLaAppTest.php:56-58`). Con `'expiration' => null`
(`servidor/config/sanctum.php:53`), el token queda vigente en `personal_access_tokens`. La 6.2 deja sin efecto también
los tokens que la administración ya recibió así, por ejemplo borrándolos de esa tabla.

La 6.2 no venía en la decisión 56 de `acta-2026-11-20-modelo-y-pasarela.md`, que dice que la cuenta "sigue sin token":
es así con la contraseña, no por Google. Entra al plan como consecuencia de administracion-E25 por ese camino; el acta
todavía no la asienta como complemento de la 56.

## 7. La cuenta de administración (servidor/)

- [x] 7.1 administracion-E26: `php artisan administracion:crear-cuenta {correo}`, que pide la contraseña dos veces con la entrada oculta, la guarda con hash y crea la cuenta con solo el rol de administración en cualquier entorno, también en producción; rechaza sin cambiar nada un correo no válido, una contraseña corta o que no coincide y un correo que ya tiene cuenta, de cualquier rol o borrada (decisión 68 de `acta-2026-11-20-modelo-y-pasarela.md`, C-245; design.md, Open Questions 6) — test: `servidor/tests/Feature/Administracion/CuentaDeAdministracionTest.php` — tamaño S

Correr el comando en el entorno de prueba y en producción no es tarea de código: espera el alojamiento (DD-070) y el
correo que dé EL CLIENTE, por medio de Nadir.

## Horas

- **Este archivo:** 51 h sin bloqueo y 21 h bloqueadas, 72 h en total. La decisión 68 de
  `acta-2026-11-20-modelo-y-pasarela.md` (C-245) agregó la 7.1 (S), 2 h sin bloqueo.
- **Contra la versión anterior (70 h; 49 sin bloqueo y 21 bloqueadas):** entran las 2 h de la 7.1, la cuenta de
  administración con un comando.
- **Antes (70 h):** la decisión 56 (C-245) agregó la 6.1 (S) y la 6.2 (S), 4 h sin bloqueo. **Contra la versión de
  entonces (66 h; 45 sin bloqueo y 21 bloqueadas):** entraron las 2 h de la 6.1 y las 2 h de la 6.2, el 403 de
  `POST /api/sesion/google` que la 6.1 daba por hecho.
- **Antes (66 h; 36 sin bloqueo y 30 bloqueadas):** la decisión 34 (segunda adenda del 2026-11-22 de ADR-0010) cerró
  DD-112: 0.2 (S), 4.4 (M) y 4.5 (S), 9 h, dejaron de estar bloqueadas; el total no cambió.
- **Antes de eso (62 h; 41 sin bloqueo y 21 bloqueadas):** entraron la migración de `comisiones` y `avisos_masivos`
  (0.1, S) y la de `movimientos_pasarela` (0.2, S), y 4.4 y 4.5 se bloquearon por DD-112 hasta la decisión 34.
- **Contra el plan:** el plan de sprints le da 39 h (F-D03 a F-D07). La diferencia pasa de 31 h a 33 h; antes había
  pasado de 27 h a 31 h.
