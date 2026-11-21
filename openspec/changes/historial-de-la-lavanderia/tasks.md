Cada tarea va en rojo, verde y refactor: primero el test con el identificador del escenario en su nombre, que falla;
luego el código mínimo que lo pone en verde; al final, la limpieza con el test en verde. Tamaños de S-006: S = 2 h,
M = 5 h, L = 12 h.

**Horas:** 8 h listas para entrar y 8 h bloqueadas. El plan de sprints le da 4 h a este cambio (F-B04, S, y F-B05, S).

## 1. Servidor (`servidor/`)

- [ ] 1.1 historial-lavanderia-E01 e historial-lavanderia-E02: historial de pedidos Entregados de la lavandería, solo los suyos y de su plaza — test: `servidor/tests/Feature/HistorialLavanderia/HistorialTest.php` — tamaño S
- [ ] 1.2 historial-lavanderia-E05 e historial-lavanderia-E06: pedidos completados del día y de la semana en la zona horaria de la plaza, solo los suyos — test: `servidor/tests/Feature/HistorialLavanderia/EstadisticasTest.php` — tamaño S

## 2. App (`movil/`)

- [ ] 2.1 historial-lavanderia-E01: pantalla del historial de pedidos de la lavandería — test: `movil/__tests__/historial-lavanderia/historial.test.tsx` — tamaño S
- [ ] 2.2 historial-lavanderia-E05: pantalla de estadísticas de la lavandería — test: `movil/__tests__/historial-lavanderia/estadisticas.test.tsx` — tamaño S

## 3. Bloqueadas: no entran al sprint hasta cerrar su fila

- [ ] 3.1 historial-lavanderia-E03 e historial-lavanderia-E07 (bloqueadas: DD-013, DD-020 y DD-012): monto por pedido e ingreso total — test: `servidor/tests/Feature/HistorialLavanderia/MontosTest.php` — tamaño S
- [ ] 3.2 historial-lavanderia-E03 e historial-lavanderia-E07 (bloqueadas: DD-013, DD-020 y DD-012): mostrar el monto y el ingreso — test: `movil/__tests__/historial-lavanderia/montos.test.tsx` — tamaño S
- [ ] 3.3 historial-lavanderia-E04 e historial-lavanderia-E08 (bloqueadas: DD-052): calificación recibida y promedio — test: `servidor/tests/Feature/HistorialLavanderia/CalificacionesTest.php` — tamaño S
- [ ] 3.4 historial-lavanderia-E04 e historial-lavanderia-E08 (bloqueadas: DD-052): mostrar la calificación y el promedio — test: `movil/__tests__/historial-lavanderia/calificaciones.test.tsx` — tamaño S
