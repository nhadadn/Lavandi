Cada tarea se hace en rojo, verde y refactor: primero el test que falla, con el identificador del escenario en su
nombre; luego el código mínimo que lo pone en verde; luego la limpieza. Una tarea vive en `servidor/` o en `movil/`,
nunca en las dos.

## 1. Servidor: cuenta, contraseña y sesión (sprint 01)

- [x] 1.1 La parte de la base de registro-E02 (el correo único) y de registro-E16 (una cuenta de Google, una sola
  cuenta), y la prueba de esquema de `modelo-de-datos` (modelo-E01 a modelo-E17) sobre las tablas nuevas: migración de
  `roles` con su semilla, `usuarios`, `rol_usuario` y `cuentas_externas`, con las columnas, los únicos y los `CHECK` de
  `design.md` ("Tablas y migración"); modelos de Eloquent `Rol`, `Usuario` y `CuentaExterna` sobre
  `App\Models\Modelo`, con su `$table`, su `$primaryKey` y las llaves de cada relación; el proveedor de
  `config/auth.php` apunta a `Usuario`. Necesita `modelo-de-datos` y la pregunta abierta 11 de `design.md` — test:
  `servidor/tests/Feature/Registro/MigracionTest.php` y `servidor/tests/Feature/Modelo/` — tamaño S
- [x] 1.2 registro-E01, registro-E02, registro-E03, registro-E05, registro-E06, registro-E07, registro-E08,
  registro-E10 y registro-E12: registro con correo y contraseña para el rol de usuario, con hash; correo repetido y
  datos inválidos rechazados; inicio de sesión con token de Sanctum, consulta de la propia cuenta y cierre de sesión.
  Incluye la fábrica de cuentas para los tests — test: `servidor/tests/Feature/Registro/RegistroConCorreoTest.php` y
  `servidor/tests/Feature/Registro/SesionTest.php` — tamaño M
- [x] 1.3 registro-E14 y registro-E15: el componente único de autorización con el rol de usuario; toda ruta protegida
  pasa por él y una cuenta de usuario no entra al panel — test:
  `servidor/tests/Feature/Registro/AutorizacionTest.php` — tamaño S
- [x] 1.4 registro-E30: el límite de intentos del inicio de sesión, con el valor en configuración (ADR-0018) — test:
  `servidor/tests/Feature/Registro/LimiteDeIntentosTest.php` — tamaño S

## 2. App: registro, inicio de sesión y sesión (sprint 01)

- [x] 2.1 registro-E04, registro-E09, registro-E11, registro-E13 y registro-E28: pantallas de registro y de inicio de
  sesión contra la API, con los errores a la vista y los enlaces legales desde la configuración; la sesión que se
  conserva al volver a abrir la app y se borra al cerrarla; el inicio provisional con "Cerrar sesión" (C-225). Incluye
  las piezas de formulario con los tokens (ADR-0013), con expo-router (ADR-0017) y el token en el almacenamiento
  cifrado (ADR-0018) — test: `movil/__tests__/registro/pantallas.test.tsx` y
  `movil/__tests__/registro/sesion.test.ts` — tamaño M
- [x] 2.2 registro-E32: al primer arranque después de instalar, la app borra del almacén cifrado la sesión y la marca
  de la bienvenida (C-229) — test: `movil/__tests__/registro/reinstalacion.test.ts` — tamaño S
- [x] 2.3 Ajuste visual de las pantallas de registro, inicio de sesión y recuperación a las imágenes de
  `mocks/Lavandi - Propuesta 04/Usuario/` (C-230), sin lo que no tiene cita y sin cambiar ningún escenario — test:
  los de las tareas 2.1, 3.2 y 3.4, en verde — tamaño S

## 3. Google y recuperación de la contraseña (sprint 02)

- [x] 3.1 registro-E16 y registro-E17: el servidor valida el `idToken` de Google contra el cliente configurado y crea
  o encuentra la cuenta — test: `servidor/tests/Feature/Registro/GoogleTest.php` — tamaño S
- [x] 3.2 registro-E19 y registro-E20: el botón de Google en las pantallas de entrada, con
  `react-native-nitro-google-signin`; en Android no se ofrece Apple — test: `movil/__tests__/registro/google.test.tsx`
  — tamaño M
- [x] 3.3 registro-E22, registro-E23, registro-E24, registro-E25 y registro-E31: el enlace que vence, en una página web
  del servidor, el correo capturado en local, la contraseña nueva que cierra las sesiones y el límite de peticiones
  (ADR-0018) — test:
  `servidor/tests/Feature/Registro/RecuperacionTest.php` — tamaño M
- [x] 3.4 registro-E26: la pantalla "¿Olvidaste tu contraseña?" — test:
  `movil/__tests__/registro/recuperacion.test.tsx` — tamaño S
- [x] 3.5 registro-E18: Google con un correo que ya tiene cuenta con contraseña se liga si Google verificó el correo
  (C-224; era la 4.1, bloqueada por DD-091) — test: `servidor/tests/Feature/Registro/GoogleTest.php` — tamaño S
- [x] 3.6 registro-E16 y registro-E17: el servidor rechaza el `idToken` cuyo correo Google no verificó (C-228,
  DD-128 cerrada) — test: `servidor/tests/Feature/Registro/GoogleTest.php` — tamaño S

- [x] 3.7 registro-E18 (`servidor/`): al ligar Google a la cuenta que ya existía, se cierran sus sesiones y se anula
  su contraseña (DD-132, decisión 23 de `acta-2026-11-20-modelo-y-pasarela`) — test:
  `servidor/tests/Feature/Registro/GoogleTest.php` — tamaño S
- [x] 3.8 Pantallas de acceso con lo que respondió DD-133 y DD-131, punto 1 (`movil/`): el logotipo de Google en su
  botón, el alto de 52 del botón principal, el borde de los controles y el foco de 3 desde `tokens.json`, y las fuentes
  de la marca con `expo-font` (adenda de ADR-0013) — test: `movil/__tests__/registro/aspecto.test.tsx` y
  `movil/__tests__/tema/` — tamaño S

## 4. Bloqueadas: no entran a un sprint hasta que se responda su fila

- [ ] 4.2 registro-E21: el servidor valida el token de identidad de Apple con `socialiteproviders/apple`. Bloqueada por
  DD-069 — test: `servidor/tests/Feature/Registro/AppleTest.php` — tamaño S
- [ ] 4.3 registro-E21: el botón de Apple en iPhone, con `expo-apple-authentication`. Bloqueada por DD-069 — test:
  `movil/__tests__/registro/apple.test.tsx` — tamaño S
- [ ] 4.4 registro-E27: el transporte real del correo en prueba y producción. Bloqueada por DD-071, DD-070 y DD-023 —
  test: `servidor/tests/Feature/Registro/CorreoTest.php` — tamaño S
- [ ] 4.5 registro-E29: la constancia de aceptación en el servidor, si EL CLIENTE la pide. Bloqueada por DD-080 —
  test: `servidor/tests/Feature/Registro/DocumentosLegalesTest.php` — tamaño S
- [ ] 4.6 registro-E29: la aceptación en la pantalla de registro, si EL CLIENTE la pide. Bloqueada por DD-080 — test:
  `movil/__tests__/registro/documentos-legales.test.tsx` — tamaño S

- [x] 3.9 registro-E33: la cuenta y el cierre de la sesión para toda cuenta con un rol de la app (decisión 31) — test: `servidor/tests/Feature/Registro/CuentaDeLosRolesDeLaAppTest.php` — tamaño S

## Horas

| Grupo | Tareas | Horas |
|---|---|---|
| Sprint 01 (grupos 1 y 2) | 2 M y 3 S | 16 |
| Sprint 02 (grupo 3) | 2 M y 3 S | 16 |
| **Que se pueden hacer hoy** | | **32** |
| Bloqueadas (grupo 4) | 5 S | 10 |

Tamaños de S-006, banda media: S = 2 h, M = 5 h, L = 12 h.

- **El plan:** 22 h en los sprints 01 y 02. Con la hora de la spec en el sprint 00 y la que sigue en el sprint 03, el
  registro suma 24 h en el hito H1 (`vault/formas-de-trabajo/sprints/plan-de-sprints.md`).
- **Diferencia:** 6 h más que los sprints 01 y 02 (antes de alinear con el modelo de datos eran 4). Salen del
  componente de autorización (tarea 1.3), que el plan no contaba aparte; del botón de Google en la app como M (tarea
  3.2), por ser terreno nuevo, y de la migración de las cuatro tablas con sus modelos (tarea 1.1, +2 h, C-193).
- **El 2026-11-20 se sumaron 4 h:** la tarea 1.4, del límite de intentos (C-223), y la 3.5, que era la 4.1 bloqueada
  y C-224 desbloqueó. La 3.3 suma registro-E31 sin cambiar de tamaño.
- **El sprint 01 queda en 16 h** contra las 11 de un sprint: no cabe entero.
- **Las 10 h bloqueadas no están en el plan,** salvo Apple, que `sprint-01.md` deja para el sprint 02 "cuando haya
  cuenta".
