---
estado: vigente
grado: B
fuente: auditoría de solo lectura de arthur/feat/h3-concilio en 9d2497775966ef2d8c0c0f204b2f67cfb8432fbd (PR #4) contra ADR-0002, ADR-0003, ADR-0007, ADR-0017, ADR-0018 y ADR-0019 y CLAUDE.md §5 y §9; servidor/ y movil/ revisados por dos subagentes de solo lectura y verificados por muestreo; verificar_marca.py y verificar_secretos.py corridos en ese SHA; sin correr las suites, por decisión de Nadir Hadad Navarrete en sesión del 2026-11-24
fecha: 2026-11-25
dueño: Nadir Hadad Navarrete
---
# Auditoría Lavandi — Segmento 2: Arquitectura y calidad de código

**Objeto:** rama `arthur/feat/h3-concilio` (PR #4) · SHA `9d24977` · fecha de corte 2026-11-24 (el informe se cerró el
2026-11-25).

**Qué NO se revisó:**
- Las suites no se corrieron. `tsc`, ESLint y Pint tampoco se corrieron aquí: se reporta lo que configura la CI.
- La seguridad a fondo es del segmento 4; la base de datos, del 3.
- El barrido de clases sin referencia en `servidor/app` quedó parcial: cubrió hasta `Filament/Pages`.

**Veredicto: LISTO CON RESERVAS.**
- El código sigue lo que declaran los ADR. Es consistente entre módulos y tiene buena disciplina de tipado,
  configuración y transacciones. CLAUDE.md §5 y §9 se cumplen en marca, dominio y secretos.
- Las reservas:
  - el análisis estático del servidor nunca corre;
  - el checkout llama a Conekta dentro de una transacción con bloqueo y sin llave de idempotencia;
  - la app no trata la sesión vencida;
  - dos documentos de arquitectura (ADR-0003 y las specs del Módulo D) ya no dicen lo que hay.

### Resumen

- **Servidor.**
  - Laravel organizado por dominio: controladores delgados, Form Requests, Resources y una clase de caso de uso por
    operación (`CreacionDePedido`, `CambioDeEstado`, `RespuestaDelRepartidor`).
  - Eventos después del commit.
  - Transacciones y bloqueos en las carreras críticas.
  - Sin `env(` fuera de `config/` y sin `catch` vacíos.
- **App.**
  - expo-router, con rutas protegidas por rol.
  - Un solo cliente HTTP con URL de configuración, plazo de 15 s y validación de las respuestas en tiempo de ejecución.
  - TypeScript `strict`, 0 `any`, 0 `@ts-ignore` y 0 colores literales fuera del tema.
- **CLAUDE.md §5 y §9.**
  - `verificar_marca.py`: 0 apariciones.
  - `verificar_secretos.py`: 0. Una búsqueda más amplia (llaves de Google, de Stripe y privadas, `APP_KEY`) tampoco
    encontró nada.
  - Nombre, identificador y dominio salen del entorno.
  - La autorización vive en un solo componente. La excepción es una regla de visibilidad en un scope del modelo
    (S2-06).
- **Lo más importante a corregir:**
  - el análisis estático del servidor (S2-01);
  - el checkout con HTTP dentro de una transacción (S2-02);
  - el 401 a mitad de sesión en la app (S2-03).

### Hallazgos

| ID | Severidad | Área | Hallazgo | Evidencia (grado + ruta:línea) | Impacto | Recomendación |
|---|---|---|---|---|---|---|
| S2-01 | Media | Calidad del servidor | **El análisis estático del servidor nunca corre.** El paso de PHPStan en la CI depende de que exista `servidor/phpstan.neon`, que no existe, y PHPStan no está en `require-dev`. Ningún archivo declara `strict_types` (0 de 358) | B: `.github/workflows/ci.yml:91-94`; `servidor/composer.json` (`require-dev`); `git grep -l 'declare(strict_types=1)' -- servidor/app` = 0 | 26 115 líneas de PHP solo las revisan Pint (estilo) y las pruebas. Los errores de tipo y las llamadas a métodos inexistentes en ramas sin test no los detecta nadie | Agregar Larastan con `phpstan.neon` en un nivel bajo y subirlo; la CI ya está lista para correrlo |
| S2-02 | Media | Consistencia del cobro | **El checkout llama a Conekta (hasta 25 s) dentro de una transacción con `lockForUpdate` sobre el pago, sin llave de idempotencia.** Si la respuesta se pierde por timeout, la transacción se revierte y la orden ya existe en Conekta. Mientras tanto, el bloqueo y la conexión a la base quedan retenidos | B: `servidor/app/Cobro/CheckoutDelCobro.php:31-48`; `servidor/app/Cobro/PasarelaConekta.php:335-341` (timeouts sin cabecera de idempotencia; la búsqueda de `idempot` no da nada) | Órdenes huérfanas o duplicadas en la pasarela; bloqueos largos bajo carga (segmento 3) | Crear la orden fuera de la transacción, o con un estado intermedio y una llave de idempotencia |
| S2-03 | Media | App: sesión | **Un 401 a mitad de sesión no cierra la sesión.** El cliente lo distingue (`sin-sesion`), pero las pantallas lo convierten en "Algo salió mal" y la persona sigue con un token revocado hasta reiniciar la app. Solo al arrancar se trata como sesión cerrada | B: `movil/src/configuracion/api.ts:118-119`; `movil/src/cercanas/textos.ts:83-85` (`mensajeDeLaFalla`); `movil/src/sesion/ProveedorDeSesion.tsx:57` | Pantallas en error sin salida tras una revocación, por ejemplo después de un bloqueo de F-D03 o un borrado de cuenta | Un manejo central del 401 que llame a `cerrar()` y lleve al inicio de sesión |
| S2-04 | Media | App: estado | **El pedido en curso no se vacía al cerrar sesión.** Vive en un store a nivel de módulo, fuera de React, y solo se vacía al confirmar. Otra cuenta en el mismo teléfono hereda el pedido a medias | B: `movil/src/pedido/pedidoEnCurso.ts:76,172`; único llamado en `movil/app/pedido/confirmacion/[id].tsx:40`; `cerrar()` no lo llama | Fuga de datos entre cuentas en un teléfono compartido (lo amplía el segmento 4) | Vaciarlo en `cerrar()` y al cambiar de cuenta |
| S2-05 | Media | Documentación de arquitectura | **Dos ADR ya no describen el código.** (a) ADR-0003 dice que "hay SDK oficial de Conekta", pero la integración va por HTTP directo, sin el paquete. (b) ADR-0002 y ADR-0003 describen la "vista reducida de administración" de C-140, que C-247 revocó; el ADR que pide la decisión 69 no existe | B: `vault/arquitectura/adr/ADR-0003-backend-y-lenguaje.md:53,74-76,88`; `servidor/composer.json` sin `conekta`; `PasarelaConekta.php:335`; `ADR-0002-framework-movil.md:81-82`. C: C-247 | La regla del proyecto es que sin ADR la decisión no existe (`CLAUDE.md` §5) | Adenda de ADR-0003 (HTTP directo) y el ADR de la decisión 69 antes de su código |
| S2-06 | Baja | Autorización | **Una regla de visibilidad vive fuera de `Autorizacion`:** qué lavanderías ve el usuario (ni suspendidas ni de baja, y de plaza activa) es un scope del modelo, usado en 5 lugares. Además, el comentario de `lavanderiasDeLaCuenta` dice "en su plaza" y la consulta no filtra por plaza | B: `servidor/app/Models/Lavanderia.php:151-156`; usos en `PedidosController.php:220`, `DireccionesController.php:230`, `PerfilDeLavanderiaController.php:68`, `VentanasDeLavanderiaController.php:64`, `Lavanderias/ConsultaDeCercanas.php:52`; `servidor/app/Autorizacion/Autorizacion.php:407-417`. D: ADR-0019 | Es una regla de catálogo más que de tenencia, pero el método pide que se revise una sola clase (`CLAUDE.md` §9.5) | Moverla a `Autorizacion` o registrar en ADR-0019 que las reglas de catálogo viven en scopes; corregir el comentario |
| S2-07 | Baja | Acoplamiento en el servidor | **Hay dependencias que van al revés.** Algunas excepciones de dominio arman Resources HTTP; una regla de dominio lee una constante de un Form Request; un cálculo de dominio usa una clase de Filament; las páginas de Filament usan Requests y Resources de la API; tres enums de dominio implementan contratos de Filament | B: `servidor/app/Pedidos/CambioDeEstadoNoPermitido.php:5-6,40-43`; `Repartidores/RespuestaNoPermitida.php:5`; `Lavanderias/ReglasDeLaLavanderia.php:6,64`; `Pedidos/MarcadoresDelMonitoreo.php:5,79`; `Filament/Resources/Lavanderias/Pages/EditLavanderia.php:8,169`; `Indicadores/Semaforo.php:5-6` | Encarece mover el Módulo D a la app (decisión 69) y cualquier cambio de la API | Dejar el dominio sin dependencias de HTTP ni de Filament; que los adaptadores vivan en las capas de afuera |
| S2-08 | Baja | Duplicación | **Lógica repetida.** En el servidor: estadísticas e historial de lavandería y de repartidor; la búsqueda por ULID (6 veces); la lavandería visible (4); la ventana en hora de la plaza (3 resources, aunque existe `Agenda/Ventana::toArray`). En la app: historial, estadísticas y rechazo casi iguales entre lavandería y repartidor; unos 12 hooks repiten el mismo bloque de lectura; 3 interfaces de foto; dos "Cerrar sesión", el del usuario sin confirmación | B: `servidor/app/Pedidos/EstadisticasDeLaLavanderia.php:31-59` y `Repartidores/EstadisticasDelRepartidor.php:32-60`; `PedidoResource.php:45-47`, `Agenda/Ventana.php:53-65`; `movil/app/inicio.tsx:93-115` y `movil/src/sesion/CerrarSesion.tsx:26-31`; `movil/src/pedidos-lavanderia/useRechazo.ts` y `movil/src/repartidor/useRechazoDelRepartidor.ts` | Cada cambio se hace dos veces. Hoy el usuario y los socios ven un comportamiento distinto al cerrar sesión | Extraer lo común. El cierre de sesión, con un solo componente |
| S2-09 | Baja | Errores | **Errores sin red de seguridad.** En la app no hay ErrorBoundary, 28 pantallas con error no ofrecen reintentar y cuatro `.catch` silenciosos no avisan a la persona. En el servidor, `withExceptions` está vacío: el formato de error depende de cada excepción, y los 404 se arman de tres maneras | B: la búsqueda de `ErrorBoundary` y `componentDidCatch` en `movil/app` y `movil/src` no da nada; `movil/src/acceso/BotonDelPanel.tsx:17`; `movil/src/textos-legales/EnlacesLegales.tsx:25`; `servidor/bootstrap/app.php:28-30` | Un error de render cierra la app sin pantalla de recuperación | ErrorBoundary de expo-router en el layout raíz; reintentar en las pantallas de lectura; un render central de errores de la API |
| S2-10 | Baja | Interfaces | FCM y la verificación de Google son clases concretas sin interfaz y se prueban solo con `Http::fake`. La pasarela y la geocodificación sí tienen interfaz | B: `servidor/app/Avisos/Fcm/CanalDeFcm.php`; `servidor/app/Acceso/Google/VerificadorDeGoogle.php`; contraste con `servidor/app/Cobro/Pasarela.php:20` | Las pruebas quedan atadas al formato HTTP del proveedor | Interfaz más implementación, como en la pasarela |
| S2-11 | Baja | Consistencia del servidor | **Algunos controladores no siguen el patrón.** La entrada con correo tiene la lógica en el controlador, cuando la de Google tiene su clase. Hay validación inline en la API y consultas con joins dentro de controladores. Crear un pedido no es idempotente (un doble toque crea dos) | B: `servidor/app/Http/Controllers/Api/SesionController.php:49-104`; `LavanderiaDelSocioController.php:187`; `CatalogoDelTerritorioController.php:82-113`; `servidor/app/Pedidos/CreacionDePedido.php:82` | Menor | Aplicar el mismo patrón; llave de idempotencia en la creación del pedido |
| S2-12 | Info | Estados | Los estados del dominio son constantes de texto en los modelos, no enums de PHP: solo hay 5 enums | B: `servidor/app/Models/EstadoPedido.php:26-46`; `Models/Pago.php:25-57` | Sin seguridad de tipos en la máquina de estados | Valorarlo junto con S2-01 |
| S2-13 | Info | Restos | Queda el comando de ejemplo `inspire`; `composer.json` conserva `post-create-project-cmd` con SQLite y `minimum-stability: dev`; la herramienta de pruebas `app/Esquema/` (unas 850 líneas) vive en `app/` | B: `servidor/routes/console.php:8-10`; `servidor/composer.json`; `servidor/app/Esquema/` | Limpieza | Quitar el ejemplo y mover `Esquema/` a `tests/` |
| S2-14 | Info | Positivos verificados | Transacciones y bloqueos en las carreras críticas: aceptar un pedido, el webhook duplicado, la confirmación con folio y el cambio de estado. Cliente HTTP único en la app. `strict`, 0 `any`. Tiempo real conforme a ADR-0007: consulta cada 30 s que se pausa en segundo plano o fuera de la pantalla, y el aviso la adelanta | B: `servidor/app/Repartidores/RespuestaDelRepartidor.php:43-50`; `Cobro/RecepcionDeAvisos.php:62,80-85`; `Pedidos/CambioDeEstado.php:215-217`; `movil/src/configuracion/api.ts:52-104`; `movil/tsconfig.json:4`; `movil/src/estados/useConsultaPeriodica.ts:21,44,76-90` | — | — |

### Patrón real contra lo declarado

| Tema | Lo que declara | Lo que hay | ¿Coincide? |
|---|---|---|---|
| Backend | Laravel con Sanctum para la app (ADR-0003) | Laravel 13; `auth:sanctum` en la API (`servidor/routes/api.php`) | Sí |
| Panel | Filament (ADR-0003); el Módulo D completo en la app (decisión 69, C-247) | Todo el Módulo D en Filament; en la app, solo un botón que abre el panel | No con C-247 (S2-05; segmento 1, S1-04) |
| Pasarela | "SDK oficial de Conekta" (ADR-0003:88) | HTTP directo detrás de la interfaz `Pasarela`, más una simulada | No (S2-05) |
| Tiempo real | Consulta cada 30 s más aviso FCM (ADR-0007) | `useConsultaPeriodica` a 30 s; FCM adelanta el seguimiento del usuario | Sí (solo para el usuario: DD-054) |
| Autorización | Una clase y `autorizar:<rol>`; 404 al dato ajeno; Policy adaptadora (ADR-0019) | `servidor/app/Autorizacion/Autorizacion.php` (647 líneas); middleware `Autorizar.php:32-34`; `canAccessPanel` delega (`Usuario.php:134-137`); Filament delega en `canAccess`, consultas y acciones | Sí, salvo el scope de S2-06 |
| App | React Native con Expo y TypeScript (ADR-0002) | Expo SDK 58, TS 6, `strict` | Sí |
| Navegación | expo-router, pantallas en `movil/app/` (ADR-0017) | `Stack.Protected` por rol en `movil/app/_layout.tsx:62,80,83,86`; layouts por módulo | Sí |
| Sesión | Token de Sanctum guardado de forma segura (ADR-0018) | `expo-secure-store` en `movil/src/sesion/almacen.ts` | Sí; falta el 401 (S2-03) |
| Tokens de diseño | `theme.ts` generado (ADR-0013) | 0 hex, 0 `fontSize` literal fuera de `src/tema/` | Sí |

### CLAUDE.md §5 y §9

| Regla | Resultado | Evidencia |
|---|---|---|
| §5 y §9.3: marca y dominio, siempre de configuración | Cumple | `verificar_marca.py`: 0; `movil/app.config.ts:153,157,167,174` (nombre, scheme e identificadores del entorno); producción se detiene si el identificador trae `{{MARCA}}` (`:125-134`). En `servidor/.env.example:6` sigue `APP_NAME="{{MARCA}}"` como marcador |
| §9.4: ningún secreto versionado | Cumple | `verificar_secretos.py`: 0; búsqueda propia sin resultados (comandos abajo) |
| §9.5: la autorización en un solo componente, con rol, tenencia y plaza | Cumple con una excepción | S2-06 |
| §9.1: test primero, con el ID del escenario | 387 de 498 escenarios con test declarado (segmento 1) | NO VERIFICADO: no se corrieron las suites |

### Tamaño

| Servidor (`servidor/app`) | | App (`movil/app` y `movil/src`) | |
|---|---|---|---|
| Archivos PHP | 358 · 26 115 líneas | Archivos TS/TSX | 198 · unas 17 500 líneas |
| Más grande | `Autorizacion/Autorizacion.php` (647) | Más grande | `src/repartidor/api.ts` (499) |
| Siguientes | `Esquema/RevisorDeEsquema.php` (562), `Cobro/PasarelaConekta.php` (379), `Direcciones/CargaDelCatalogo.php` (361), `Models/Pedido.php` (347) | Siguientes | `app/pedido/direccion-nueva.tsx` (456, con 12 `useState`), `src/piezas/Selector.tsx` (396), `src/alta-lavanderias/FormularioDeLaLavanderia.tsx` (393) |
| TODO/FIXME | 0 (los 7 resultados de la búsqueda son la palabra "TODOS") | TODO/FIXME | 0 |

### Preguntas abiertas

**Para Arthur**
1. ¿Por qué no hay `phpstan.neon` si la CI ya trae el paso? ¿Se agrega antes de seguir? (S2-01)
2. ¿Se mueve la creación de la orden de Conekta fuera de la transacción antes de la tarea 3.3 de `pago`? (S2-02)
3. ¿El ADR de la decisión 69 va antes de tocar el código del Módulo D? (S2-05)

**Para Nadir**
1. ¿Exiges como condición del PR que el análisis estático corra en la CI? (S2-01)

### Comandos ejecutados

```bash
git -c core.longpaths=true worktree add --detach <scratchpad>/h3 9d2497775966ef2d8c0c0f204b2f67cfb8432fbd
python -I herramientas/ci/verificar_marca.py      # 0 apariciones de la marca o el dominio en el código
python -I herramientas/ci/verificar_secretos.py   # 0 posibles secretos versionados
git grep -nIE 'AIza[0-9A-Za-z_-]{30,}|sk_(live|test)_[0-9A-Za-z]{10,}|-----BEGIN [A-Z ]*PRIVATE KEY|"private_key"|xox[bp]-|ghp_[0-9A-Za-z]{30,}|base64:[A-Za-z0-9+/]{40,}=?' -- movil servidor herramientas .github
grep -nE 'KEY|SECRET|TOKEN|PASSWORD' servidor/.env.example movil/.env.example
git grep -nE "['\"](com|mx)\.[a-z0-9]+\.[a-z0-9.]+['\"]" -- movil/app.config.ts movil/eas.json servidor/config
git grep -l 'declare(strict_types=1)' -- 'servidor/app/*.php' | wc -l
git grep -nE 'TODO|FIXME' -- servidor/app movil/app movil/src
cat movil/tsconfig.json; grep -nE 'pint|tsc|eslint|phpstan' .github/workflows/ci.yml
# Verificación propia de lo que reportaron los subagentes (muestreo):
sed -n '112,124p' movil/src/configuracion/api.ts; sed -n '80,86p' movil/src/cercanas/textos.ts
git grep -n 'sin-sesion\|vaciarPedidoEnCurso\|ErrorBoundary' -- movil/src movil/app
sed -n '93,115p' movil/app/inicio.tsx
sed -n '149,157p' servidor/app/Models/Lavanderia.php
sed -n '28,50p' servidor/app/Cobro/CheckoutDelCobro.php
grep -niE 'idempot' servidor/app/Cobro/PasarelaConekta.php; sed -n '332,342p' servidor/app/Cobro/PasarelaConekta.php
sed -n '28,30p' servidor/bootstrap/app.php; sed -n '5,6p;40,43p' servidor/app/Pedidos/CambioDeEstadoNoPermitido.php
```

Dos subagentes de solo lectura, uno del servidor y otro de la app, trabajaron sobre el mismo SHA. Sus hallazgos se
integraron aquí después de verificar sus afirmaciones principales. Bajé de Alta a Media la severidad que el subagente
de la app propuso para el 401: no rompe el contrato ni los datos, y la sesión se corrige al reiniciar.
