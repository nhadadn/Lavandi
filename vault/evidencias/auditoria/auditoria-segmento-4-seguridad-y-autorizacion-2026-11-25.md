---
estado: vigente
grado: B
fuente: auditoría de solo lectura de arthur/feat/h3-concilio en 9d2497775966ef2d8c0c0f204b2f67cfb8432fbd (PR #4) contra NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3 (medidas de seguridad), ADR-0005, ADR-0018 y ADR-0019; autorización, autenticación, OWASP Top 10 y MASVS revisados por dos subagentes de solo lectura y verificados por muestreo; sin correr pruebas ni pruebas de penetración, por decisión de Nadir Hadad Navarrete en sesión del 2026-11-24
fecha: 2026-11-25
dueño: Nadir Hadad Navarrete
---
# Auditoría Lavandi — Segmento 4: Seguridad y autorización

**Objeto:** rama `arthur/feat/h3-concilio` (PR #4) · SHA `9d24977` · fecha de corte 2026-11-24.

**Qué NO se revisó:**
- No se corrieron pruebas ni pruebas de penetración: todo sale de leer el código.
- El árbol no trae `vendor/` ni `node_modules/`. Lo que depende del comportamiento interno de Filament, Sanctum,
  league/csv o Guzzle (el límite de intentos del login del panel, los valores de CORS por omisión, el mensaje de la
  excepción de Guzzle) se marca "no verificable aquí".
- La configuración real de producción no existe todavía (DD-070).

**Veredicto: LISTO CON RESERVAS.**
- **No hay ningún hallazgo Crítico.** No se encontró fuga de datos entre socios ni entre usuarios, ni una toma de
  cuenta demostrable desde el código.
- Lo que pide el A.3 se cumple en código:
  - comunicaciones cifradas;
  - contraseñas con hash;
  - un socio no ve a otro ni a usuarios que no le pidieron;
  - sin números de tarjeta en la plataforma.
- Las reservas son de endurecimiento:
  - los tokens de sesión no vencen;
  - no hay límite de peticiones en casi ninguna ruta;
  - el panel no tiene segundo factor;
  - Google le da token a la cuenta de administración;
  - el servidor no envía cabeceras de seguridad;
  - que la pasarela simulada esté apagada depende solo de `APP_ENV`.

### Resumen

- **Autorización en un solo componente**, como pide ADR-0019, con cobertura automática.
  - Toda ruta con sesión lleva `auth:sanctum` y `autorizar:<rol>` (registro-E14 y E15).
  - Cada ruta y cada recurso del panel tienen su regla (autorizacion-E01).
  - Un barrido recorre todas las rutas con cada rol y dos plazas (autorizacion-E14).
  - No hay route-model binding: cada controlador resuelve el ULID sobre la consulta ya filtrada.
- **La cuenta bloqueada se rechaza en cada petición**, no solo al iniciar sesión.
- **RLS no existe en PostgreSQL.** El aislamiento equivalente vive en la aplicación:
  - consultas filtradas por dueño y plaza en `Autorizacion`;
  - llaves compuestas `(id, id_plazas)` que impiden en la base mezclar filas de dos plazas (segmento 3).
- **OWASP, sin hallazgos en:** inyección SQL (todo con bindings), XSS almacenado, path traversal, SSRF, asignación
  masiva (lista blanca), CSRF e inyección de fórmulas en la exportación.
- **MASVS:**
  - el token solo vive en SecureStore;
  - no hay `console.log`, WebView ni secretos en `EXPO_PUBLIC_*`;
  - el tráfico sin cifrar está apagado en los perfiles de EAS;
  - la ubicación solo se pide con la app abierta;
  - no hay certificate pinning.

### Hallazgos

| ID | Severidad | Área | Hallazgo | Evidencia (grado + ruta:línea) | Impacto | Recomendación |
|---|---|---|---|---|---|---|
| S4-01 | Media | Autenticación | **Google le da un token de API a la cuenta de administración.** La entrada con correo revisa `usaLaApp` y la de Google no. Si esa cuenta no tenía Google ligado, además anula su contraseña y la deja fuera del panel. El token no vence y no se puede revocar por la API, porque `DELETE api/sesion` le responde 403. Es la tarea 6.2, pendiente | B: `servidor/app/Http/Controllers/Api/SesionConGoogleController.php:21-32` (sin `usaLaApp`); contraste con `SesionController.php:77-81`; `servidor/app/Identidad/EntradaConGoogle.php:44-88,110-114`; `openspec/changes/administracion/tasks.md:47-60` | Un administrador que toca "Continuar con Google" en la app pierde el acceso al panel. El token es inútil en la API (403 en todas las rutas), pero no expira | Rechazar en `EntradaConGoogle` las cuentas que no usan la app, igual que en la entrada con correo |
| S4-02 | Media | Sesión | **Los tokens de Sanctum no vencen** (`expiration => null`), no tienen abilities y no se podan. Cada inicio de sesión crea uno más. Suspender o dar de baja una lavandería no los revoca, y el socio sigue operando sus pedidos | B: `servidor/config/sanctum.php:53`; `SesionController.php:84`; `servidor/routes/console.php:14`; `servidor/app/Lavanderias/EstadoDeLaLavanderia.php` (sin `tokens()`); `servidor/app/Autorizacion/Autorizacion.php:325-340`. DD-104 | Un token filtrado vale indefinidamente. Una lavandería suspendida sigue aceptando y marcando pedidos | Expiración más poda programada; revocar al suspender o dar de baja (DD-104) |
| S4-03 | Media | Límites | **Solo hay límite de peticiones en dos rutas:** el inicio de sesión y la recuperación. No lo tienen el registro, la entrada con Google, el registro público de repartidor (crea cuenta y sube foto de 5 MB sin sesión), los formularios de la landing, la subida de fotos, el webhook ni la API autenticada. Sin `trustProxies`, detrás de un proxy todos comparten una IP, y 5 intentos por minuto bastan para bloquear el inicio de sesión de un correo ajeno | B: `servidor/routes/api.php:36-52`; `servidor/routes/web.php:46-47`; `servidor/app/Providers/AppServiceProvider.php:56-63`; `servidor/bootstrap/app.php:18-27` (sin `throttle:api` ni `trustProxies`) | Creación masiva de cuentas y archivos; denegación del inicio de sesión de un tercero; costo de almacenamiento | `throttle` en las rutas públicas y en la API; `trustProxies` en el despliegue |
| S4-04 | Media | Panel | **El panel de administración no tiene segundo factor.** Ve todos los datos de todas las plazas: pedidos, direcciones, pagos, usuarios. Usa el login de Filament por omisión, cuyo límite interno no se pudo verificar | B: `servidor/app/Providers/Filament/AdminPanelProvider.php` (sin `multiFactor`); `servidor/app/Filament/Auth/IniciarSesion.php:16-28`. A: `NSV-2026-001-v8.pdf:10:Cláusula X` (medidas razonables conforme al estado de la técnica) | Una contraseña de administración filtrada expone todo | 2FA en el panel (Filament lo trae) |
| S4-05 | Media | Configuración | **El servidor no envía cabeceras de seguridad** (HSTS, CSP, X-Frame-Options o frame-ancestors, nosniff). La cookie de sesión no fuerza `Secure` si falta `SESSION_SECURE_COOKIE`, que `.env.example` no define | B: la búsqueda en `servidor/app`, `bootstrap`, `config`, `routes` y `public` no dio nada; `servidor/config/session.php:172`; `servidor/.env.example:44-48` | El panel queda expuesto a clickjacking, y la cookie del panel puede viajar por http antes del 301 | Middleware de cabeceras; `SESSION_SECURE_COOKIE=true` en producción |
| S4-06 | Baja | Pasarela simulada | **Que la pasarela simulada y el checkout de desarrollo estén apagados depende solo de `APP_ENV`.** El código los cierra fuera de `local` y `testing`, pero `.env.example` trae `APP_ENV=local`, `APP_DEBUG=true` y `PASARELA=simulada`. Un servidor público que arranque con esa plantilla deja que el dueño de un pedido apruebe su propio cobro | B: `servidor/app/Cobro/Pasarelas.php:17,41-47`; `servidor/routes/web.php:81-86`; `servidor/app/Http/Controllers/Desarrollo/CheckoutDeDesarrolloController.php:35-68`; `servidor/.env.example:7,9,148` | Pedidos confirmados sin pago si se despliega con la plantilla local | Plantilla de producción aparte; que el arranque falle con `APP_ENV=local` y un `APP_URL` público |
| S4-07 | Baja | Avisos | **`POST api/dispositivos` reasigna un token FCM ya registrado a la cuenta que lo envía** (upsert por `token_fcm`). Quien conozca el token FCM de otra persona le desvía sus avisos | B: `servidor/app/Avisos/TelefonosRegistrados.php:25-37` | Los avisos de un pedido llegan a otro teléfono; requiere conocer el token | Solo reasignar si el token viene del mismo dispositivo o cerrar la sesión de la cuenta anterior |
| S4-08 | Baja | Privacidad | **El repartidor ve las direcciones completas de recogida y entrega de un pedido solo ofrecido**, antes de aceptarlo, y las conserva aunque lo rechace | B: `servidor/app/Autorizacion/Autorizacion.php:353-372`; `servidor/app/Http/Resources/PedidoDelRepartidorResource.php:160-161` | Datos del usuario en manos de quien no tomó el pedido. El A.3 solo nombra al socio lavandería, pero la Cláusula X obliga a cuidar los datos | Mostrar zona o colonia al ofrecer y la dirección completa al aceptar |
| S4-09 | Baja | Pasarela | **El aviso aprobado marca el cobro como pagado sin comparar monto ni moneda** con `pagos.monto`: depende solo de la firma. Además, `eventos_pasarela` guarda el aviso entero y el filtro solo quita número, CVC y vencimiento, no el titular ni el nombre, correo y teléfono del cliente que el servidor manda a Conekta | B: `servidor/app/Cobro/EfectoDelAviso.php:49-66`; `servidor/app/Cobro/SinDatosDeTarjeta.php:17-25`; `servidor/app/Cobro/RecepcionDeAvisos.php:66`; `servidor/app/Cobro/PasarelaConekta.php:268,285-296` | Defensa en profundidad del cobro; datos personales en una bitácora sin limpieza | Comparar monto y moneda en la tarea 3.1 de `pago`; quitar los datos personales al guardar |
| S4-10 | Baja | Enumeración | El registro revela si un correo ya tiene cuenta ("Ese correo ya tiene una cuenta."), lo que anula el Timebox de 200 ms del inicio de sesión | B: `servidor/app/Validacion/CorreoSinCuenta.php:17`; `servidor/app/Http/Requests/RegistroRequest.php:26`; `SesionController.php:55-68` | Permite averiguar quién es cliente | Aceptarlo como costo de la UX o responder igual y avisar por correo |
| S4-11 | Baja | Registro | `report($error)` de la geocodificación puede escribir en el log la URI de Google con `key=` y `address=` del usuario, a través de la excepción previa de Guzzle. **No verificable aquí** sin `vendor/` | B: `servidor/app/Http/Controllers/Api/DireccionesController.php:185`; `LavanderiaDelSocioController.php:257`; `servidor/app/Direcciones/GeocodificadorDeGoogle.php:39-46` | Llave y dirección en los logs | Registrar sin la excepción previa o limpiar la URI |
| S4-12 | Baja | App | La app no exige https en la URL del checkout que recibe. El regreso del pago usa un esquema propio que otra app puede registrar, y el parámetro `pago` no se valida en su formato. El regreso no confirma nada, porque la pantalla le pregunta al servidor | B: `movil/src/pago/api.ts:102-108`; `movil/src/pago/checkout.ts:19-28`; `movil/src/pago/rutas.ts:37-52`; `movil/app/+native-intent.tsx:15-24` | Bajo | Validar https y el formato del ULID |
| S4-13 | Baja | Panel | Las acciones de estado de lavanderías y repartidores (suspender, baja, alta) solo tienen `->visible()`, sin `->authorize()`. Dependen de la puerta del panel y de `canAccess` del recurso. Bloquear usuarios sí lleva `->authorize()` | B: `servidor/app/Filament/Resources/Lavanderias/Actions/AccionesDeEstado.php:35,53,71`; `Repartidores/Actions/AccionesDeEstado.php:49,69,87,105`; contraste con `Usuarios/Actions/AccionesDeBloqueo.php:39,58` | Hoy sin impacto (solo hay un rol en el panel) | Agregar `->authorize()` para que la regla siga en un solo lugar |
| S4-14 | Info | Positivos verificados | Ninguna ruta con sesión sin `autorizar:`; 404 al dato ajeno; cuenta bloqueada rechazada en cada petición; la vinculación con Google revoca tokens y anula la contraseña; recuperación de un solo uso que no revela la cuenta; SQL con bindings; JSON-LD con `JSON_HEX_TAG`; fórmulas escapadas en CSV y XLSX; webhook deduplicado por evento; Conekta en producción rechaza todo aviso hasta la tarea 3.1; token en SecureStore; sin WebView ni `console.log` | B: `servidor/app/Autorizacion/Autorizacion.php:166-180`; `servidor/tests/Feature/Autorizacion/BarridoContraFugasTest.php:86`; `servidor/app/Exportacion/ExportadorDeIndicador.php:136-144,200-218`; `servidor/app/Medicion/InformacionEstructurada.php:57-62`; `servidor/app/Cobro/RecepcionDeAvisos.php:62-69`; `movil/src/sesion/almacen.ts:7-37` | — | — |
| S4-15 | Info | Sin hacer | No hay certificate pinning. `allowBackup` no se declara, pero no hay datos personales fuera de SecureStore. La minificación está apagada en EAS. La llave de Maps para Android va en el manifiesto (como siempre en Android) y su restricción no se ve en el código | B: `movil/app.config.ts:58-60,173-188,205,212` | Bajo | Restringir la llave de Maps en la consola de Google por paquete y huella |

### Matriz RBAC (derivada del código)

| Rol | Recurso | Leer | Crear | Editar | Cambiar estado | Borrar |
|---|---|---|---|---|---|---|
| usuario | Sus pedidos (todas las plazas) | sí (`pedidosVisibles`) | sí | no | no: solo el webhook confirma | no |
| usuario | Sus pagos | sí (`pagosVisibles`) | abrir el checkout | no | no | no |
| usuario | Sus direcciones | sí (`direccionesVisibles`) | sí | no | no | no |
| usuario | Lavanderías visibles | perfil y ventanas | no | no | no | no |
| usuario | Su perfil y su cuenta | sí | — | nombre y foto | no | sí (borrado suave) |
| usuario | Calificación | — | en un pedido propio | no | no | no |
| lavandería | Sus lavanderías (`id_usuarios`) | sí | sí, nace pendiente | sí, campos en lista blanca | no | no |
| lavandería | Pedidos confirmados de su lavandería y su plaza | sí, sin datos del usuario | no | no | aceptar, rechazar, marcar | no |
| repartidor | Su perfil | sí, también pendiente o suspendido | registro público | no | no | no |
| repartidor que opera | Pedidos ofrecidos o aceptados de su plaza | sí, con direcciones completas (S4-08) | no | no | aceptar, rechazar, marcar, evidencia | no |
| administración | API | 403 en todas las rutas | — | — | — | — |
| administración | Panel: lavanderías, repartidores, usuarios, pedidos, reportes, comisiones, avisos, testimonios, logos | todo, todas las plazas | avisos, testimonios, logos | lavanderías, repartidores, portada | suspender, baja, alta, bloquear | testimonios y logos |

**Rutas:** 49 en `servidor/routes/api.php` y 13 en `web.php`. Son públicas 6 de la API (registro, sesión, Google,
registro de repartidor, olvido de contraseña y webhook) y todas las web. Las rutas públicas declaradas en
`Autorizacion::rutasSinSesion` (`servidor/app/Autorizacion/Autorizacion.php:62-143`) coinciden con las reales: no falta
ni sobra ninguna. Toda ruta pública de la API recibe el `id_publico` (ULID), nunca un id entero.

### Aislamiento por tenencia y plaza

| Rol | Cómo se filtra | Evidencia |
|---|---|---|
| Usuario | Por su cuenta (`id_usuarios`); ve todas las plazas a propósito | `Autorizacion.php:289-317`; `servidor/tests/Feature/Autorizacion/UsuarioAisladoTest.php`, `UsuarioTodasLasPlazasTest.php` |
| Lavandería | Pedido confirmado, lavandería de la cuenta y misma plaza que el pedido; el pedido que ve no trae datos del usuario | `Autorizacion.php:330-340`; `servidor/app/Http/Resources/PedidoDeLaLavanderiaResource.php:17`; `servidor/tests/Feature/Autorizacion/LavanderiaAisladaTest.php`, `LavanderiaYSusClientesTest.php`, `SocioOtraPlazaTest.php` |
| Repartidor | Activo y con plaza; pedido de su plaza con asignación ofrecida o aceptada para él; aceptar bloquea la fila | `Autorizacion.php:231-250,353-392`; `servidor/app/Repartidores/RespuestaDelRepartidor.php:45-57`; `servidor/tests/Feature/Autorizacion/RepartidorAsignadoTest.php` |
| Administración | Todo, solo desde el panel | `servidor/app/Models/Usuario.php:134-137`; `servidor/tests/Feature/Autorizacion/PanelSoloAdministracionTest.php` |
| Base de datos | Sin RLS; llaves compuestas con la plaza en las hijas | La búsqueda de `row level security` y `create policy` no dio nada; segmento 3 |

### Medidas de seguridad del contrato (`NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3`)

| Medida | Hoy | Evidencia |
|---|---|---|
| Comunicaciones cifradas (RN-07) | En código: `SoloHttps` (403 en la API, 301 en la web) y tráfico sin cifrar apagado en EAS. Sin desplegar, sin HSTS (S4-05) | `servidor/app/Http/Middleware/SoloHttps.php:29-51`; `movil/app.config.ts:139,171,205` |
| Contraseñas no recuperables (RN-08) | `Hash::make` y cast `hashed`, bcrypt 12 | `servidor/app/Identidad/AltaDeCuenta.php:23`; `servidor/app/Models/Usuario.php:73` |
| Separación por tipo de usuario (RN-09) | Cumple en código, con barrido contra fugas | tabla de arriba |
| Sin números de tarjeta (RN-10) | Checkout redirigido; filtro al guardar los avisos (S4-09) | `servidor/app/Cobro/SinDatosDeTarjeta.php` |

Ninguna se cuenta como PROBADA: NO VERIFICADO, porque no se corrieron las suites.

### Preguntas abiertas

**Para Arthur**
1. ¿Cuándo entra la tarea 6.2 de `administracion` (Google sin token para la administración)? (S4-01)
2. ¿Qué expiración llevarán los tokens? ¿Suspender una lavandería revoca su acceso? (S4-02; DD-104)
3. ¿El plan de despliegue incluye `throttle`, `trustProxies`, cabeceras y una plantilla de producción? (S4-03, S4-05, S4-06)

**Para Nadir**
1. ¿Exiges 2FA en el panel como medida "conforme al estado de la técnica" (Cláusula X)? (S4-04)

**Para EL CLIENTE, vía Nadir**
1. Como responsable del tratamiento: ¿qué datos del usuario ve el repartidor antes de aceptar? (S4-08)

### Comandos ejecutados

```bash
git -c core.longpaths=true worktree add --detach <scratchpad>/h3 9d2497775966ef2d8c0c0f204b2f67cfb8432fbd
git grep -niE 'row level security|enable row|create policy|set_config|current_setting' -- servidor   # sin resultados
grep -cE "Route::(get|post|put|patch|delete)" servidor/routes/api.php servidor/routes/web.php
grep -nE "throttle|RateLimiter" -r servidor/routes servidor/app/Providers servidor/bootstrap
git grep -niE 'Strict-Transport|Content-Security-Policy|X-Frame-Options|nosniff' -- servidor/app servidor/bootstrap servidor/config servidor/routes servidor/public   # sin resultados
# Verificación propia de lo que reportaron los subagentes (muestreo):
sed -n '21,32p' servidor/app/Http/Controllers/Api/SesionConGoogleController.php; grep -rn 'usaLaApp' servidor/app
sed -n '25,37p' servidor/app/Avisos/TelefonosRegistrados.php
grep -n 'tokens()' servidor/app/Lavanderias/EstadoDeLaLavanderia.php   # sin resultados
sed -n '155,163p' servidor/app/Http/Resources/PedidoDelRepartidorResource.php
grep -n 'multiFactor\|twoFactor' servidor/app/Providers/Filament/AdminPanelProvider.php   # sin resultados
sed -n '172p' servidor/config/session.php; sed -n '17,25p' servidor/app/Cobro/SinDatosDeTarjeta.php
sed -n '49,66p' servidor/app/Cobro/EfectoDelAviso.php; sed -n '40,47p' servidor/app/Cobro/Pasarelas.php
grep -n 'MENSAJE' servidor/app/Validacion/CorreoSinCuenta.php
```

Dos subagentes de solo lectura revisaron el mismo SHA: uno la autorización y la autenticación, y otro OWASP y MASVS.
Ninguno encontró nada Crítico ni Alto, y sus afirmaciones principales se verificaron antes de integrarlas.
