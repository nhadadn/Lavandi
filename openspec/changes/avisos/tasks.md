## 0. Servidor: la base de datos

- [x] 0.1 modelo-E01 a modelo-E17 sobre `dispositivos`: migración que la crea con `id_dispositivos`, `id_usuarios` (foránea con índice y `ON DELETE RESTRICT`), `token_fcm` único, `plataforma` con su `CHECK` (`ios`, `android`) y `ultimo_uso_en`; el modelo de Eloquent `Dispositivo` declara su `$table` y su `$primaryKey`, y su `belongsTo` hacia `Usuario` nombra `id_usuarios` — test: `servidor/tests/Feature/Avisos/MigracionTest.php` y la prueba de esquema de `modelo-de-datos` — tamaño S

## 1. Servidor: el canal de Firebase Cloud Messaging

- [x] 1.1 avisos-E01, avisos-E02 y avisos-E16: canal de FCM con el servicio simulado en las pruebas; un fallo del servicio no detiene el cambio de estado y queda en la bitácora; cada mensaje lleva la parte de Android y la de iPhone — test: `servidor/tests/Feature/Avisos/CanalDeAvisosTest.php` — tamaño M

## 2. Servidor: los teléfonos registrados

- [x] 2.1 avisos-E04, avisos-E05, avisos-E06 y avisos-E21: registrar el token en `dispositivos` ligado a la cuenta, con su plataforma; pasarlo a la cuenta que inicia sesión si ya existía; borrarlo al cerrar la sesión y cuando el servicio lo rechaza — test: `servidor/tests/Feature/Avisos/TelefonosRegistradosTest.php` — tamaño S

## 3. Servidor: los momentos y el mensaje de cambio de estado

- [x] 3.1 avisos-E09, avisos-E10, avisos-E11 y avisos-E12: una sola lista de momentos y un aviso por cada uno de los cuatro del contrato, con su texto de configuración, solo a los teléfonos de la cuenta dueña del pedido — test: `servidor/tests/Feature/Avisos/MomentosDelPedidoTest.php` — tamaño S
- [x] 3.2 avisos-E14: cada cambio de estado manda al teléfono del usuario el mensaje con el pedido y su estado nuevo — test: `servidor/tests/Feature/Avisos/CambioDeEstadoTest.php` — tamaño S

## 4. App: recibir los avisos

- [x] 4.1 avisos-E03 y avisos-E17: módulo de mensajería de Firebase en el development build; el token va a la api, con la plataforma del teléfono, al iniciar sesión y al renovarse; el manejador de segundo plano se registra al arrancar; la captura del aviso en Android va a `vault/evidencias/avisos/` — test: `movil/__tests__/avisos/telefono.test.ts` — tamaño M. Hecha sin la captura, que espera las llaves de Firebase (DD-072); Firebase entra solo con `GOOGLE_SERVICES_JSON` y solo en Android (`design.md`, "Decisiones de la implementación de la app")
- [x] 4.2 avisos-E07 y avisos-E08: petición del permiso de avisos, y la app sin errores si el usuario lo niega — test: `movil/__tests__/avisos/permiso.test.tsx` — tamaño S. Se pide al abrirse la sesión del usuario que nunca respondió, sin pantalla propia (`design.md`, punto 6)
- [x] 4.3 avisos-E15: el mensaje de cambio de estado adelanta la consulta del seguimiento abierto — test: `movil/__tests__/avisos/adelanta-la-consulta.test.tsx` — tamaño S

## 5. Bloqueadas: no entran al sprint mientras su fila siga abierta

- [ ] 5.1 avisos-E13: el quinto momento en la lista (DD-011) — test: `servidor/tests/Feature/Avisos/MomentosDelPedidoTest.php` — tamaño S
- [ ] 5.2 avisos-E18: la configuración de iPhone en el development build y la captura del aviso en iPhone (DD-069, DD-017, DD-084) — test: `movil/__tests__/avisos/telefono.test.ts` — tamaño S
- [ ] 5.3 avisos-E19: el aviso a la lavandería de un pedido nuevo (DD-054) — test: `servidor/tests/Feature/Avisos/AvisosASociosTest.php` — tamaño S
- [ ] 5.4 avisos-E20: el aviso al repartidor de un pedido asignado (DD-054, DD-014) — test: `servidor/tests/Feature/Avisos/AvisosASociosTest.php` — tamaño S

Cada tarea va en rojo, verde y refactor: primero el test que falla, con el identificador del escenario en su nombre; luego el código mínimo; luego la limpieza. Tamaños de S-006: S = 2 h, M = 5 h, L = 12 h.

## Horas

- **Listas** (grupos 0 a 4): 2 M y 6 S, **22 h**. Antes de alinear con el modelo eran 20 h; la migración de 0.1 suma 2 h. avisos-E21 entra en la tarea 2.1 sin cambiar su tamaño.
- **Bloqueadas** (grupo 5): 4 S, **8 h**, sin cambio.
- **Contra el plan:** el plan le da 17 h a este cambio (F-T02, L, y F-A17, M). Sin las bloqueadas, se pasa por 5 h (antes, por 3 h); con ellas, por 13 h (antes, por 11 h).
