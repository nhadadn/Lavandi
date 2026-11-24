Cada tarea se hace en rojo, verde y refactor: primero el test que falla, con el identificador del escenario en su
nombre; luego el código mínimo que lo pone en verde; luego la limpieza con el test en verde. Tamaños de S-006: S = 2 h,
M = 5 h, L = 12 h.

## 0. Base de datos en el servidor

- [x] 0.1 modelo-E01 a modelo-E17 sobre `lavanderias`: migración que la completa con `estado` (`CHECK` de `pendiente`, `activa`, `suspendida` y `baja`, obligatorio, por omisión `activa`) y `aprobada_en` (opcional), y llena `aprobada_en` en las filas que ya existen; el modelo `Lavanderia` (de `perfil-de-lavanderia`) recibe los dos campos, sin cambiar su `$table` ni su `$primaryKey` — test: `servidor/tests/Feature/AltaLavanderias/MigracionTest.php` y la prueba de esquema de `modelo-de-datos` — tamaño S

## 1. Registro de la lavandería en el servidor

- [x] 1.1 alta-lavanderias-E01, alta-lavanderias-E02: registro completo y registro incompleto, con la foto en el almacenamiento S3 privado — test: `servidor/tests/Feature/AltaLavanderias/RegistroTest.php` — tamaño M
- [x] 1.2 alta-lavanderias-E05, alta-lavanderias-E06: los tres tipos de servicio con su precio por kilo y el rechazo de cualquier otro tipo — test: `servidor/tests/Feature/AltaLavanderias/ServiciosYPreciosTest.php` — tamaño S
- [x] 1.3 alta-lavanderias-E09, alta-lavanderias-E10: plaza según la dirección, con la geocodificación simulada, y rechazo fuera de las zonas — test: `servidor/tests/Feature/AltaLavanderias/PlazaPorDireccionTest.php` — tamaño S

## 2. Perfil y ventanas en el servidor

- [x] 2.1 alta-lavanderias-E18, alta-lavanderias-E20: la lavandería edita su información y no puede editar la de otra — test: `servidor/tests/Feature/AltaLavanderias/PerfilTest.php` — tamaño S
- [x] 2.2 alta-lavanderias-E21, alta-lavanderias-E22: ventanas de una hora por día de la semana y rechazo de las que no duran una hora — test: `servidor/tests/Feature/AltaLavanderias/VentanasTest.php` — tamaño S

## 3. Gestión de lavanderías en el panel de Filament

- [x] 3.1 alta-lavanderias-E13: lista de lavanderías con su estado y su plaza, y edición con las validaciones del registro — test: `servidor/tests/Feature/AltaLavanderias/PanelEdicionTest.php` — tamaño M
- [x] 3.2 alta-lavanderias-E14, alta-lavanderias-E15: suspender (`estado = 'suspendida'`) y levantar la suspensión (de vuelta a `activa` si `aprobada_en` tiene fecha, a `pendiente` si no), con su efecto en la búsqueda y en los pedidos nuevos — test: `servidor/tests/Feature/AltaLavanderias/PanelSuspensionTest.php` — tamaño S
- [x] 3.3 alta-lavanderias-E17: baja (`estado = 'baja'`, sin `deleted_at`) que conserva la lavandería y sus pedidos en la base — test: `servidor/tests/Feature/AltaLavanderias/PanelBajaTest.php` — tamaño S

## 4. Pantallas del Módulo B en la app

- [x] 4.1 alta-lavanderias-E03: pantalla de registro, con la foto tomada o elegida y comprimida con los parámetros de configuración — test: `movil/__tests__/alta-lavanderias/registro.test.tsx` — tamaño M
- [x] 4.2 alta-lavanderias-E19: pantalla de perfil y horarios, con las ventanas de disponibilidad y los errores del servidor por campo — test: `movil/__tests__/alta-lavanderias/perfil-y-horarios.test.tsx` — tamaño M

## 5. Bloqueadas: no entran al sprint mientras su fila siga abierta

- [ ] 5.1 alta-lavanderias-E04: entrada al registro de lavandería, bloqueada por DD-053 — test: `movil/__tests__/alta-lavanderias/entrada.test.tsx` — tamaño S
- [ ] 5.2 alta-lavanderias-E11, alta-lavanderias-E12: alta en el panel y lavandería registrada sin alta, bloqueadas por DD-053 — test: `servidor/tests/Feature/AltaLavanderias/PanelAltaTest.php` — tamaño M
- [ ] 5.3 alta-lavanderias-E07: precio del lavado en seco en su unidad, bloqueada por DD-010 y BR-16 — test: `servidor/tests/Feature/AltaLavanderias/ServiciosYPreciosTest.php` — tamaño S
- [ ] 5.4 alta-lavanderias-E08: reglas del precio, bloqueada por DD-010 — test: `servidor/tests/Feature/AltaLavanderias/ServiciosYPreciosTest.php` — tamaño S
- [ ] 5.5 alta-lavanderias-E16: pedidos en curso al suspender o dar de baja, bloqueada por DD-104 — test: `servidor/tests/Feature/AltaLavanderias/PanelSuspensionTest.php` — tamaño M
- [ ] 5.6 alta-lavanderias-E23: cupo por ventana, bloqueada por BR-17 y DD-099 — test: `servidor/tests/Feature/AltaLavanderias/VentanasTest.php` — tamaño S
- [ ] 5.7 alta-lavanderias-E24: ventanas fuera del horario de atención, bloqueada por DD-078 — test: `servidor/tests/Feature/AltaLavanderias/VentanasTest.php` — tamaño S

## Horas

- **Listas para el sprint** (grupos 0 a 4): 4 tareas M y 7 tareas S, **34 h**. Antes de alinear con el modelo eran
  32 h; la migración de 0.1 suma 2 h.
- **Bloqueadas** (grupo 5), tamaño tentativo: 2 M y 5 S, **20 h**, sin cambio.
- **Contra el plan:** el plan de sprints le da **15 h** a este cambio (F-B01, F-D01 y F-B06, tres M). Las listas pasan
  el plan por 19 h (antes, por 17 h); con las bloqueadas, por 39 h (antes, por 37 h).
