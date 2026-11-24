## 1. Servidor: ventanas y validación

Cada tarea va en rojo, verde y refactor: primero el test que falla, con el identificador del escenario en su nombre;
luego el código mínimo; luego la limpieza. Horas: listas 12 (M + S + M); bloqueadas 11; el plan le da 5 a F-A07.

- [x] 1.1 agenda-E01, agenda-E02, agenda-E03, agenda-E04: ventanas de una hora dentro del horario sembrado en `lavanderia_horarios`, en la hora local de la plaza (`plazas.zona_horaria`), y día sin servicio — test: `servidor/tests/Feature/Agenda/VentanasTest.php` — tamaño M
- [x] 1.2 agenda-E09, agenda-E11, agenda-E12: solo ventanas futuras, anticipación y horizonte leídos de dos claves de `parametros_plaza`, y validación de una ventana elegida con la misma lógica — test: `servidor/tests/Feature/Agenda/ValidarVentanaTest.php` — tamaño S

## 2. App: selector de fecha y hora

- [x] 2.1 agenda-E04, agenda-E08: pantalla de fecha y hora con el componente 2 (disponible, seleccionada con señal además del color, día sin servicio) que lleva la elección al resumen — test: `movil/__tests__/agenda/selector.test.tsx` — tamaño M

## 3. Bloqueadas: no entran al sprint mientras su fila siga abierta

- [ ] 3.1 agenda-E05: cupo por ventana en el servidor (DD-099, BR-17) — test: `servidor/tests/Feature/Agenda/CupoTest.php` — tamaño M
- [ ] 3.2 agenda-E05: ventana sin cupo tachada y visible en la app (DD-099, BR-17) — test: `movil/__tests__/agenda/sin-cupo.test.tsx` — tamaño S
- [ ] 3.3 agenda-E06, agenda-E07: pedidos con la lavandería cerrada y días festivos, como configuración de la plaza (DD-078) — test: `servidor/tests/Feature/Agenda/NocheYFestivosTest.php` — tamaño S
- [ ] 3.4 agenda-E10: primera ventana propuesta en la app (DD-078) — test: `movil/__tests__/agenda/primera-ventana.test.tsx` — tamaño S
