Cada tarea va en rojo, verde y refactor: primero el test con el identificador del escenario en su nombre, que falla;
luego el código mínimo que lo pone en verde; al final, la limpieza con el test en verde. Tamaños de S-006, banda media:
S = 2 h, M = 5 h, L = 12 h. Mientras DD-014 siga abierta, los tests asignan el pedido directo en la base con una
fábrica.

## 0. Tablas y migración (`servidor/`)

- [ ] 0.1 Migración de `repartidores`, `pedido_asignaciones` y `evidencias`, con sus llaves compuestas con la plaza y sus `created_at` y `updated_at` en `timestamptz`; más las llaves foráneas compuestas `(id_repartidores, id_plazas)` → `repartidores(id_repartidores, id_plazas)` de `pedidos` y de `calificaciones`, con `ON DELETE RESTRICT` y su índice; y sus modelos de Eloquent `Repartidor`, `PedidoAsignacion` y `Evidencia`, sobre `App\Models\Modelo`, que sostienen repartidor-E01 a repartidor-E30 (depende de la pregunta abierta de `design.md` sobre la plaza de un registro pendiente) — test: `servidor/tests/Feature/Repartidor/MigracionTest.php` y la prueba de esquema de `modelo-de-datos` (modelo-E01 a modelo-E17) — tamaño M

## 1. Servidor (`servidor/`)

- [ ] 1.1 repartidor-E01, repartidor-E02 y repartidor-E03: registro del repartidor con sus cinco datos y estado pendiente sin acceso a pedidos — test: `servidor/tests/Feature/Repartidor/RegistroTest.php` — tamaño S
- [ ] 1.2 repartidor-E05, repartidor-E06, repartidor-E07, repartidor-E08 y repartidor-E09: recurso de repartidores en el panel de Filament, con alta en una plaza, suspensión, baja y edición, solo para la administración — test: `servidor/tests/Feature/Repartidor/GestionEnElPanelTest.php` — tamaño M
- [ ] 1.3 repartidor-E10, repartidor-E12 y repartidor-E30: API de pedidos asignados, solo los suyos, de su plaza y con el pago acreditado (`pedidos.confirmado_en`), en el componente de autorización — test: `servidor/tests/Feature/Repartidor/PedidosAsignadosTest.php` — tamaño S
- [ ] 1.4 repartidor-E15, repartidor-E16 y repartidor-E17: aceptar, y rechazar con motivo obligatorio, en `pedido_asignaciones` — test: `servidor/tests/Feature/Repartidor/AceptarORechazarTest.php` — tamaño S
- [ ] 1.5 repartidor-E19, repartidor-E20 y repartidor-E21: transiciones Recolectado, En tránsito y Entregado, sin saltos, retrocesos ni cambios de otro repartidor — test: `servidor/tests/Feature/Repartidor/EstadosDelRepartidorTest.php` — tamaño S
- [ ] 1.6 repartidor-E23: guardar la foto de evidencia en el bucket privado, ligada al pedido en `evidencias` — test: `servidor/tests/Feature/Repartidor/EvidenciaDeEntregaTest.php` — tamaño S
- [ ] 1.7 repartidor-E25: historial de entregas completadas, solo las suyas — test: `servidor/tests/Feature/Repartidor/HistorialDeEntregasTest.php` — tamaño S
- [ ] 1.8 repartidor-E28: entregas del día y de la semana en la zona horaria de la plaza — test: `servidor/tests/Feature/Repartidor/EstadisticasTest.php` — tamaño S

## 2. App (`movil/`)

- [ ] 2.1 repartidor-E01, repartidor-E02 y repartidor-E03: pantalla de registro con foto y pantalla de espera de autorización — test: `movil/__tests__/repartidor/registro.test.tsx` — tamaño S
- [ ] 2.2 repartidor-E10 y repartidor-E11: lista de pedidos asignados con consulta cada 30 s mientras está abierta — test: `movil/__tests__/repartidor/pedidos-asignados.test.tsx` — tamaño S
- [ ] 2.3 repartidor-E15, repartidor-E16 y repartidor-E17: aceptar, y rechazar con motivo obligatorio — test: `movil/__tests__/repartidor/aceptar-o-rechazar.test.tsx` — tamaño S
- [ ] 2.4 repartidor-E19 y repartidor-E20: botones Recolectado, En tránsito y Entregado, en orden — test: `movil/__tests__/repartidor/estados.test.tsx` — tamaño S
- [ ] 2.5 repartidor-E23: foto de evidencia al marcar Entregado — test: `movil/__tests__/repartidor/evidencia.test.tsx` — tamaño S
- [ ] 2.6 repartidor-E25: pantalla del historial de entregas — test: `movil/__tests__/repartidor/historial.test.tsx` — tamaño S
- [ ] 2.7 repartidor-E28: pantalla de estadísticas — test: `movil/__tests__/repartidor/estadisticas.test.tsx` — tamaño S

## 3. Bloqueadas: no entran al sprint hasta cerrar su fila

- [ ] 3.1 repartidor-E04 (bloqueada: DD-053): roles de la cuenta del repartidor — test: `servidor/tests/Feature/Repartidor/EntradaDelRolTest.php` — tamaño S
- [ ] 3.2 repartidor-E04 (bloqueada: DD-053): entrada al registro del repartidor en la app — test: `movil/__tests__/repartidor/entrada-del-rol.test.tsx` — tamaño S
- [ ] 3.3 repartidor-E13 (bloqueada: DD-014): asignar un pedido a un repartidor, a mano y sin algoritmo — test: `servidor/tests/Feature/Repartidor/AsignacionTest.php` — tamaño M
- [ ] 3.4 repartidor-E14 (bloqueada: DD-054): aviso FCM al repartidor cuando se le asigna un pedido — test: `servidor/tests/Feature/Repartidor/AvisoDeAsignacionTest.php` — tamaño S
- [ ] 3.5 repartidor-E14 (bloqueada: DD-054): recibir el aviso y abrir la lista — test: `movil/__tests__/repartidor/aviso-de-asignacion.test.tsx` — tamaño S
- [ ] 3.6 repartidor-E18 (bloqueada: DD-014): qué pasa con el pedido rechazado — test: `servidor/tests/Feature/Repartidor/PedidoRechazadoTest.php` — tamaño S
- [ ] 3.7 repartidor-E22 (bloqueada: DD-022 y DD-011): mapear los estados del repartidor a los cinco del usuario en 60 s o menos — test: `servidor/tests/Feature/Repartidor/ReflejoEnElUsuarioTest.php` — tamaño S
- [ ] 3.8 repartidor-E24 (bloqueada: BR-29, DD-110): evidencia obligatoria y sus momentos — test: `servidor/tests/Feature/Repartidor/EvidenciaObligatoriaTest.php` — tamaño S
- [ ] 3.9 repartidor-E26 y repartidor-E29 (bloqueadas: DD-019 y DD-013): monto por entrega e ingreso total acumulado — test: `servidor/tests/Feature/Repartidor/MontosTest.php` — tamaño S
- [ ] 3.10 repartidor-E26 y repartidor-E29 (bloqueadas: DD-019 y DD-013): mostrar el monto y el ingreso — test: `movil/__tests__/repartidor/montos.test.tsx` — tamaño S
- [ ] 3.11 repartidor-E27 (bloqueada: DD-052): calificación recibida por entrega — test: `servidor/tests/Feature/Repartidor/CalificacionRecibidaTest.php` — tamaño S
- [ ] 3.12 repartidor-E27 (bloqueada: DD-052): mostrar la calificación recibida — test: `movil/__tests__/repartidor/calificacion-recibida.test.tsx` — tamaño S

## Horas

- **Este archivo:** 38 h listas para entrar y 27 h bloqueadas, 65 h en total.
- **Contra la versión anterior (60 h):** 5 h más, de la migración y los modelos (0.1, M). repartidor-E30 entra en la
  tarea 1.3 sin cambiar su tamaño: es un filtro más sobre la misma consulta.
- **Contra el plan:** el plan de sprints le da 21 h a este cambio (F-C01, S; F-C02, M; F-C03, M; F-C04, S; F-C05, S;
  F-D02, M). La diferencia pasa de 39 h a 44 h.
