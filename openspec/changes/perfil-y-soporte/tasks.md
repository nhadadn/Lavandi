Cada tarea va en rojo, verde y refactor: primero el test con el identificador del escenario en su nombre, que falla;
luego el código mínimo que lo pone en verde; al final, la limpieza con el test en verde. Tamaños de S-006, banda media:
S = 2 h, M = 5 h, L = 12 h.

## 0. Tablas y migración (`servidor/`)

- [ ] 0.1 Migración de `reportes_soporte`, con `created_at` y `updated_at` en `timestamptz`, y su modelo de Eloquent `ReporteSoporte`, sobre `App\Models\Modelo`, con su `$table` y su `$primaryKey` declarados, que sostiene perfil-usuario-E11 a perfil-usuario-E13 (depende de la pregunta abierta de `design.md` sobre la plaza de un reporte sin pedido) — test: `servidor/tests/Feature/PerfilUsuario/MigracionTest.php` y la prueba de esquema de `modelo-de-datos` (modelo-E01 a modelo-E17) — tamaño S

## 1. Servidor (`servidor/`)

- [ ] 1.1 perfil-usuario-E01, perfil-usuario-E02 y perfil-usuario-E06: API del perfil para leer y cambiar el nombre, sin guardar ni devolver números de tarjeta — test: `servidor/tests/Feature/PerfilUsuario/PerfilTest.php` — tamaño S
- [ ] 1.2 perfil-usuario-E03 y perfil-usuario-E04: subida de la foto al bucket privado, URL firmada y rechazo de archivos inválidos o más grandes que el máximo configurado — test: `servidor/tests/Feature/PerfilUsuario/FotoDePerfilTest.php` — tamaño S
- [ ] 1.3 perfil-usuario-E09 y perfil-usuario-E10: el número de soporte como dato de configuración que lee la app, vacío si no existe (depende de la pregunta abierta de `design.md`) — test: `servidor/tests/Feature/PerfilUsuario/NumeroDeSoporteTest.php` — tamaño S
- [ ] 1.4 perfil-usuario-E11, perfil-usuario-E12 y perfil-usuario-E13: guardar el reporte en `reportes_soporte` con descripción obligatoria y pedido propio opcional — test: `servidor/tests/Feature/PerfilUsuario/ReporteTest.php` — tamaño S
- [ ] 1.5 perfil-usuario-E15: borrar la cuenta, revocar todos sus tokens y rechazar su inicio de sesión por correo, Google y Apple — test: `servidor/tests/Feature/PerfilUsuario/BorrarCuentaTest.php` — tamaño S

## 2. App (`movil/`)

- [ ] 2.1 perfil-usuario-E01, perfil-usuario-E02 y perfil-usuario-E05: pantalla "Mi perfil" con el nombre editable y las direcciones guardadas — test: `movil/__tests__/perfil-usuario/perfil.test.tsx` — tamaño S
- [ ] 2.2 perfil-usuario-E03 y perfil-usuario-E04: foto desde la cámara o la galería, comprimida antes de subirla — test: `movil/__tests__/perfil-usuario/foto-de-perfil.test.tsx` — tamaño M
- [ ] 2.3 perfil-usuario-E08: estado del permiso de avisos y acceso a activarlo — test: `movil/__tests__/perfil-usuario/notificaciones.test.tsx` — tamaño S
- [ ] 2.4 perfil-usuario-E09 y perfil-usuario-E10: abrir el chat de WhatsApp con el número de la configuración, o mostrarlo como no disponible — test: `movil/__tests__/perfil-usuario/whatsapp.test.tsx` — tamaño S
- [ ] 2.5 perfil-usuario-E11 y perfil-usuario-E12: formulario de reporte con confirmación sin tiempo de respuesta — test: `movil/__tests__/perfil-usuario/reporte.test.tsx` — tamaño S
- [ ] 2.6 perfil-usuario-E15: borrar la cuenta con confirmación previa y volver a la bienvenida — test: `movil/__tests__/perfil-usuario/borrar-cuenta.test.tsx` — tamaño S

## 3. Bloqueadas: no entran al sprint hasta cerrar su fila

- [ ] 3.0 perfil-usuario-E07 (bloqueada: DD-055 y DD-079, como pago-E06: C-198 dijo que sí; falta si el checkout permite reutilizar la tarjeta): migración de `metodos_pago_guardados` con las columnas de C-192 (`pasarela`, `id_pasarela_cliente`, `id_pasarela_fuente`) y su modelo de Eloquent — test: `servidor/tests/Feature/PerfilUsuario/MigracionTest.php` y la prueba de esquema de `modelo-de-datos` (modelo-E01 a modelo-E17) — tamaño S
- [ ] 3.1 perfil-usuario-E07 (bloqueada: DD-055 y DD-079): guardar y eliminar la referencia de la tarjeta que da la pasarela — test: `servidor/tests/Feature/PerfilUsuario/TarjetaGuardadaTest.php` — tamaño S
- [ ] 3.2 perfil-usuario-E07 (bloqueada: DD-055): métodos de pago en el perfil — test: `movil/__tests__/perfil-usuario/metodos-de-pago.test.tsx` — tamaño S
- [ ] 3.3 perfil-usuario-E14 (bloqueada: BR-25, DD-108): entregar el reporte a quien lo reciba y responder a la persona — test: `servidor/tests/Feature/PerfilUsuario/DestinoDelReporteTest.php` — tamaño S
- [ ] 3.4 perfil-usuario-E16 (bloqueada: BR-09, DD-109): borrar o conservar los datos de la cuenta y resolver el pedido en curso — test: `servidor/tests/Feature/PerfilUsuario/DatosDeCuentaBorradaTest.php` — tamaño S

## Horas

- **Este archivo:** 27 h listas para entrar y 10 h bloqueadas, 37 h en total.
- **Contra la versión anterior (33 h):** 4 h más. La migración de `reportes_soporte` (0.1, S) entra a las listas; la de
  `metodos_pago_guardados` (3.0, S) entra a las bloqueadas, por DD-055.
- **Contra el plan:** el plan de sprints le da 7 h a este cambio (F-A15, M, y F-A16, S). La diferencia pasa de 26 h a
  30 h. Borrar la cuenta (4 h de las listas) no estaba en el inventario ni en el plan.
