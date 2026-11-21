Cada tarea se hace en rojo, verde y refactor: primero el test que falla, con el identificador del escenario en su
nombre; luego el código mínimo que lo pone en verde; luego la limpieza. Una tarea vive en `servidor/` o en `movil/`,
nunca en las dos.

## 1. Servidor: cuenta, contraseña y sesión (sprint 01)

- [ ] 1.1 La parte de la base de registro-E02 (el correo único) y de registro-E16 (una cuenta de Google, una sola
  cuenta), y la prueba de esquema de `modelo-de-datos` (modelo-E01 a modelo-E17) sobre las tablas nuevas: migración de
  `roles` con su semilla, `usuarios`, `rol_usuario` y `cuentas_externas`, con las columnas, los únicos y los `CHECK` de
  `design.md` ("Tablas y migración"); modelos de Eloquent `Rol`, `Usuario` y `CuentaExterna` sobre
  `App\Models\Modelo`, con su `$table`, su `$primaryKey` y las llaves de cada relación; el proveedor de
  `config/auth.php` apunta a `Usuario`. Necesita `modelo-de-datos` y la pregunta abierta 11 de `design.md` — test:
  `servidor/tests/Feature/Registro/MigracionTest.php` y `servidor/tests/Feature/Modelo/` — tamaño S
- [ ] 1.2 registro-E01, registro-E02, registro-E03, registro-E05, registro-E06, registro-E07, registro-E08,
  registro-E10 y registro-E12: registro con correo y contraseña para el rol de usuario, con hash; correo repetido y
  datos inválidos rechazados; inicio de sesión con token de Sanctum, consulta de la propia cuenta y cierre de sesión.
  Incluye la fábrica de cuentas para los tests — test: `servidor/tests/Feature/Registro/RegistroConCorreoTest.php` y
  `servidor/tests/Feature/Registro/SesionTest.php` — tamaño M
- [ ] 1.3 registro-E14 y registro-E15: el componente único de autorización con el rol de usuario; toda ruta protegida
  pasa por él y una cuenta de usuario no entra al panel — test:
  `servidor/tests/Feature/Registro/AutorizacionTest.php` — tamaño S

## 2. App: registro, inicio de sesión y sesión (sprint 01)

- [ ] 2.1 registro-E04, registro-E09, registro-E11, registro-E13 y registro-E28: pantallas de registro y de inicio de
  sesión contra la API, con los errores a la vista y los enlaces legales desde la configuración; la sesión que se
  conserva al volver a abrir la app y se borra al cerrarla. Incluye las piezas de formulario con los tokens (ADR-0013).
  Necesita las preguntas abiertas 1 y 10 de `design.md` — test: `movil/__tests__/registro/pantallas.test.tsx` y
  `movil/__tests__/registro/sesion.test.ts` — tamaño M

## 3. Google y recuperación de la contraseña (sprint 02)

- [ ] 3.1 registro-E16 y registro-E17: el servidor valida el `idToken` de Google contra el cliente configurado y crea
  o encuentra la cuenta — test: `servidor/tests/Feature/Registro/GoogleTest.php` — tamaño S
- [ ] 3.2 registro-E19 y registro-E20: el botón de Google en las pantallas de entrada, con
  `react-native-nitro-google-signin`; en Android no se ofrece Apple — test: `movil/__tests__/registro/google.test.tsx`
  — tamaño M
- [ ] 3.3 registro-E22, registro-E23, registro-E24 y registro-E25: el enlace que vence, el correo capturado en local y
  la contraseña nueva. Necesita la pregunta abierta 2 de `design.md` — test:
  `servidor/tests/Feature/Registro/RecuperacionTest.php` — tamaño M
- [ ] 3.4 registro-E26: la pantalla "¿Olvidaste tu contraseña?" — test:
  `movil/__tests__/registro/recuperacion.test.tsx` — tamaño S

## 4. Bloqueadas: no entran a un sprint hasta que se responda su fila

- [ ] 4.1 registro-E18: Google con un correo que ya tiene cuenta con contraseña. Bloqueada por DD-091 — test: `servidor/tests/Feature/Registro/GoogleTest.php` — tamaño S
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

## Horas

| Grupo | Tareas | Horas |
|---|---|---|
| Sprint 01 (grupos 1 y 2) | 2 M y 2 S | 14 |
| Sprint 02 (grupo 3) | 2 M y 2 S | 14 |
| **Que se pueden hacer hoy** | | **28** |
| Bloqueadas (grupo 4) | 6 S | 12 |

Tamaños de S-006, banda media: S = 2 h, M = 5 h, L = 12 h.

- **El plan:** 22 h en los sprints 01 y 02. Con la hora de la spec en el sprint 00 y la que sigue en el sprint 03, el
  registro suma 24 h en el hito H1 (`vault/formas-de-trabajo/sprints/plan-de-sprints.md`).
- **Diferencia:** 6 h más que los sprints 01 y 02 (antes de alinear con el modelo de datos eran 4). Salen del
  componente de autorización (tarea 1.3), que el plan no contaba aparte; del botón de Google en la app como M (tarea
  3.2), por ser terreno nuevo, y de la migración de las cuatro tablas con sus modelos (tarea 1.1, +2 h, C-193).
- **El sprint 01 queda en 14 h** contra las 11 de un sprint: no cabe entero.
- **Las 12 h bloqueadas no están en el plan,** salvo Apple, que `sprint-01.md` deja para el sprint 02 "cuando haya
  cuenta".
