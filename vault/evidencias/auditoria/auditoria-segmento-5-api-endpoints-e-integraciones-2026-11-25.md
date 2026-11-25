---
estado: vigente
grado: B
fuente: auditoría de solo lectura de arthur/feat/h3-concilio en 9d2497775966ef2d8c0c0f204b2f67cfb8432fbd (PR #4) contra vault/contrato/restricciones-no-negociables.md (RN-06, RN-17, RN-24, RN-28 a RN-32) y los ADR-0005, ADR-0006, ADR-0007, ADR-0010, ADR-0014 y ADR-0019; rutas, cruce con la app e integraciones revisados por tres subagentes de solo lectura y verificados por muestreo; sin correr pruebas, por decisión de Nadir Hadad Navarrete en sesión del 2026-11-24
fecha: 2026-11-25
dueño: Nadir Hadad Navarrete
---
# Auditoría Lavandi — Segmento 5: API, endpoints e integraciones

**Objeto:** rama `arthur/feat/h3-concilio` (PR #4) · SHA `9d24977` · fecha de corte 2026-11-24. El PR #4 seguía en ese
SHA el 2026-11-25 (`gh pr view 4`).

**Qué NO se revisó:**
- **No se corrieron pruebas** (`php artisan test`, `jest`) **ni `php artisan route:list`.** La tabla de endpoints sale
  de leer `servidor/routes/*.php`, los controladores y los Form Requests. La columna de tests dice qué prueba
  **declara** cada ruta: NO VERIFICADO que esté en verde.
- El árbol no trae `vendor/` ni `node_modules/`. Las rutas que registran los paquetes (Filament, Livewire, Sanctum) se
  citan donde el proyecto las declara, no donde el paquete las crea.
- **Ninguna integración se probó contra el servicio real.** No hay llaves en el repositorio, y la configuración de
  producción no existe todavía (DD-070).
- La seguridad de la API ya está en el segmento 4. La paginación y la carga de las consultas, en el segmento 3. Aquí
  solo se citan.

**Veredicto: NO ESTÁ LISTO.**
- **La API está bien hecha:**
  - las 49 rutas de `api.php` validan su entrada;
  - el 404 del dato ajeno es uniforme;
  - la app no hace ninguna llamada a una ruta que no existe.
- **Las integraciones no están listas para producción.** De las cinco tecnologías que nombra el contrato, solo los
  mapas están completos en las dos plataformas:
  - Conekta no confirma ningún pago (S1-01, S1-11);
  - los avisos no existen en iPhone (S1-05);
  - Google Sign-In falta en iPhone;
  - la medición espera el ID de GTM.
- **A eso se suma un problema de producción:** faltan dos paquetes que la configuración de producción da por
  instalados. Sin ellos no se pueden servir las fotos ni los textos legales (S5-01) ni mandar correo (S5-02).

### Resumen

- **Endpoints.**
  - Hay 62 rutas propias: 49 en `api.php` y 13 en `web.php`.
  - Toda ruta con sesión pasa por `auth:sanctum` y `autorizar:<rol>`.
  - Toda ruta que recibe datos tiene Form Request o validación en línea.
  - Cada ruta aparece en al menos un test de Feature, además de dos barridos que recorren todas. NO VERIFICADO en
    verde.
- **Cruce de la app con el servidor.**
  - No hay llamadas huérfanas: la app usa 45 de las 49 rutas de la API.
  - De las 4 que no usa, una es el webhook de la pasarela; las otras tres son GET de detalle sin consumidor.
  - En los cinco cruces que se muestrearon, la forma de los datos coincide.
- **No hay contrato de API compartido ni versionado** (S5-03):
  - sin OpenAPI;
  - sin `/api/v1`;
  - la app copia la forma de cada respuesta a mano en 18 archivos, y sus 42 suites de pruebas simulan el servidor con
    `fetch` falso.
- **No se sustituyó ningún proveedor que nombre el contrato.** No hay Expo Push, OneSignal, otra pasarela ni otro SDK
  de mapas. `vault/cliente/ordenes-de-cambio/` solo tiene `.gitkeep`.
- **Producción, a medias.**
  - El adaptador de S3 y el transporte de Resend no están instalados (S5-01, S5-02).
  - El webhook de Conekta responde 503 siempre (S1-11).
  - FCM está apagado en iPhone (S1-05).
  - Los formularios de la landing no tienen destino (S1-10).

### Hallazgos

| ID | Severidad | Área | Hallazgo | Evidencia (grado + ruta:línea) | Impacto | Recomendación |
|---|---|---|---|---|---|---|
| S5-01 | Alta | Integraciones / producción | **El adaptador de S3 no está instalado y producción lo usa por omisión.** Con `APP_ENV=production`, las fotos van al disco `fotos_s3` y los textos legales a `textos_legales_s3`, los dos con el driver `s3`. Pero `league/flysystem-aws-s3-v3` no está en `composer.json` ni en `composer.lock`: el lock solo trae `league/flysystem` y `league/flysystem-local`, y Laravel lo declara "Required to use the Flysystem S3 driver". Las pruebas no lo detectan porque usan `Storage::fake` | B: `servidor/composer.json:7-14`; `servidor/composer.lock:3133,3217,2517`; `servidor/config/fotos.php:31`; `servidor/config/textos_legales.php:30-32`; `servidor/config/filesystems.php:82,111-112` | En producción fallan subir y ver las fotos de lavanderías, de perfil y de la evidencia de entrega. También fallan el aviso de privacidad y los términos, cuyas direcciones exigen las tiendas para el envío de la semana 14 (RN-39). Bloquea una fecha | `composer require league/flysystem-aws-s3-v3`, y una prueba de arranque que construya el disco de producción sin falsearlo |
| S5-02 | Media | Correo | **ADR-0014 decide mandar el correo por Resend con el transporte oficial de Laravel, pero `resend/resend-php` no está instalado.** Laravel lo declara "Required to enable support for the Resend mail transport". `.env.example` trae `MAIL_MAILER=log` y no menciona la llave de Resend. Con `MAIL_MAILER=resend`, el correo de confirmación del pedido y el de recuperación de contraseña fallarían | B: `servidor/composer.json:7-14`; `servidor/composer.lock:2528`; `servidor/config/mail.php:64-65`; `servidor/config/services.php:21`; `servidor/.env.example:64`. D: `vault/arquitectura/adr/ADR-0014-correo-automatico.md:38` | La confirmación por correo (F-A11, `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3`, Módulo A #11) no sale en producción. Se corrige rápido, pero hoy no está listo | Instalar `resend/resend-php`, documentar `RESEND_API_KEY` en `.env.example` y probar un envío real en el entorno de prueba |
| S5-03 | Media | Contrato de API | **No hay un contrato de API que comparta la app con el servidor, ni versión.** No hay OpenAPI, Scribe ni tipos generados. El prefijo es `/api`, sin `v1`. El contrato vive en el docblock de cada controlador y en `design.md`; tres changes no tienen sección de contrato. La app copia las formas a mano en constantes `CONTRATO_*` (18 archivos), y sus 42 suites reemplazan `global.fetch` con respuestas escritas a mano | B: `servidor/routes/api.php:30-33,96`; `movil/src/configuracion/api.ts:78-123`; 18 archivos con `CONTRATO_` en `movil/src`; 42 archivos con `global.fetch` en `movil/__tests__`; sin sección de contrato en `openspec/changes/{registro-e-inicio-de-sesion,lavanderias-cercanas,agenda-de-recoleccion}/design.md` | Un cambio de forma en el servidor deja en verde las dos suites y rompe la app. Cuando la app ya esté en las tiendas, cada cambio incompatible rompe a quien no la haya actualizado | Antes del envío a tiendas: un esquema de la API (OpenAPI generado o JSON Schema por recurso) con una prueba en cada lado, y una regla de compatibilidad (versión en la ruta o campos solo aditivos) en un ADR |
| S5-04 | Baja | Semántica de errores | **El orden de 404 y 422 no es uniforme.** Rechazar y marcar estado, de la lavandería y del repartidor, validan el cuerpo antes de buscar el pedido: un pedido ajeno con cuerpo malo responde 422. Calificar, checkout y la edición del socio buscan primero, "como en el resto de la API" (autorizacion-E07 y E14). No filtra datos: el 422 sale igual para cualquier pedido | B: `servidor/app/Http/Controllers/Api/PedidosDeLaLavanderiaController.php:110,131`; `servidor/app/Http/Controllers/Api/CalificacionesController.php:43-44` | El cliente tiene que tratar distinto el mismo error según la ruta, y la regla de ADR-0019 queda con excepciones sin escribir | Escribir la excepción en ADR-0019, o mover la validación después de `pedidoVisible` |
| S5-05 | Baja | Correo | **El correo de recuperación se manda dentro de la petición, sin cola.** `EnlaceDeRecuperacion` no implementa `ShouldQueue`, mientras que el de confirmación sí va por cola con reintentos | B: `servidor/app/Notifications/EnlaceDeRecuperacion.php:15`; `servidor/app/Identidad/RecuperacionDeContrasena.php:44` | La respuesta tarda más cuando el correo existe, porque solo entonces se manda: así se puede saber si una cuenta existe midiendo el tiempo, aunque el 200 sea igual. Si el proveedor falla, no hay reintento | `ShouldQueue` en la notificación |
| S5-06 | Baja | Superficie sin consumidor | **Tres GET de detalle no los usa la app:** `direcciones/{direccion}`, `lavanderia/pedidos/{pedido}` y `repartidor/pedidos/{pedido}`. Tienen tests y autorización | B: `servidor/routes/api.php:92-94,155,180-181`; sin coincidencia en `movil/src` ni `movil/app` | Mantener y asegurar rutas que nadie usa. El detalle del repartidor expone direcciones antes de aceptar (S4-08) | Quitar las que ningún escenario necesite, o anotar qué escenario las pide |
| S5-07 | Info | Medición | GA4 y Meta Pixel no tienen código propio: entran como etiquetas dentro de GTM, que se carga solo si `MEDICION_GTM` tiene un ID válido. La verificación de Search Console es por DNS y está pendiente. La app no tiene analítica, y el contrato no la pide: RN-31 es de la landing | B: `servidor/config/medicion.php:24`; `servidor/resources/views/publico/medicion.blade.php`; `routes/web.php:73-74`; `movil/package.json` sin dependencia de analítica. A: `NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2` | A42 no se puede probar sin las cuentas del cliente (DD-118, DD-023) | Pedir el ID de GTM y el acceso a Search Console con los demás insumos de B.10 |
| S5-08 | Info | Positivos verificados | Sin llamadas huérfanas · validación en todas las rutas con entrada · 404 uniforme del dato ajeno · `ResponderEnJson` en todo `/api` · verificación completa del `id_token` de Google (RS256, emisor, audiencia, `exp`, `email_verified`, JWKS en caché) · FCM por cola con reintentos y borrado del token inválido · webhook idempotente por `(pasarela, id_pasarela_evento)` · ninguna llave en el código · mapas sin `provider`, como pide RN-29 · las rutas del checkout simulado solo existen en `local` y `testing` | B: `servidor/bootstrap/app.php:23`; `servidor/app/Acceso/Google/VerificadorDeGoogle.php`; `servidor/app/Avisos/MandarAvisoAlTelefono.php`; `servidor/app/Cobro/RecepcionDeAvisos.php`; `servidor/routes/web.php:81-86` | — | — |

**Ya reportados en otros segmentos y que también son de este:**
- **S1-11:** Conekta, a medias y sin OXXO. El webhook responde 503: `PasarelaConekta::esAutentico` siempre lanza (`servidor/app/Cobro/PasarelaConekta.php:123`) y solo acepta tarjeta (`:263`).
- **S1-05:** FCM y Google en iPhone (`movil/src/avisos/firebase.ts:19-23`; `movil/firebase-nativo.js:21`).
- **S1-10:** los formularios de la landing.
- **S2-03:** el 401 a mitad de sesión.
- **S3-02:** las listas sin paginación.
- **S4-03:** el límite de peticiones en solo dos rutas.

### Endpoints de `servidor/routes/api.php` (prefijo `/api`)

Grupos: **G1** `auth:sanctum` + `autorizar:usuario,lavanderia,repartidor` (`:58`) · **G2** `autorizar:usuario` (`:71`) ·
**G3** `autorizar:lavanderia`, prefijo `socio` (`:137`) · **G4** `autorizar:lavanderia`, prefijo `lavanderia` (`:152`) ·
**G5** `autorizar:repartidor` (`:172`) · **G5o** G5 + `repartidor-opera` (`:178`). "App" dice si `movil/` la llama.
Abreviaturas: `C/` = `servidor/app/Http/Controllers/Api/`, `Rq/` = `servidor/app/Http/Requests/`, `T/` = `servidor/tests/Feature/`.

| # | Método y ruta (`api.php:línea`) | Controlador | Acceso | Validación | Tests declarados (NO VERIFICADO) | App |
|---|---|---|---|---|---|---|
| 1 | POST registro (:36) | C/RegistroController | público, sin límite | Rq/RegistroRequest | T/Registro/RegistroConCorreoTest | sí |
| 2 | POST sesion (:37) | C/SesionController@iniciar | `throttle:inicio-de-sesion` | Rq/InicioDeSesionRequest | T/Registro/SesionTest, LimiteDeIntentosTest | sí |
| 3 | POST sesion/google (:40) | C/SesionConGoogleController | público, sin límite | Rq/SesionConGoogleRequest | T/Registro/GoogleTest | sí |
| 4 | POST repartidor/registro (:43) | C/RepartidorController@registrar | público, sin límite | Rq/RegistroDeRepartidorRequest | T/Repartidor/RegistroTest | sí |
| 5 | POST contrasena/olvido (:46) | C/RecuperacionController | `throttle:recuperacion` | Rq/EnlaceDeRecuperacionRequest | T/Registro/RecuperacionTest | sí |
| 6 | POST pasarela/avisos (:52) | C/AvisosDeLaPasarelaController | público (webhook) | cuerpo crudo; autenticidad en `RecepcionDeAvisos` | T/Pago/SoloElWebhookConfirmaTest, WebhookUnaSolaVezTest | no (webhook) |
| 7 | GET cuenta (:59) | C/CuentaController | G1 | sin entrada | SesionTest, CuentaDeLosRolesDeLaAppTest | sí |
| 8 | DELETE sesion (:60) | C/SesionController@cerrar | G1 | Rq/CierreDeSesionRequest | SesionTest, T/Avisos/TelefonosRegistradosTest | sí |
| 9 | GET catalogos/municipios (:65) | C/CatalogoDelTerritorioController | G1 | sin entrada | T/Direcciones/CatalogoTest | sí |
| 10 | GET codigos-postales/{cp}/colonias (:67) | igual@colonias | G1 | Rq/ColoniasRequest | CatalogoTest | sí |
| 11 | GET lavanderias/cercanas (:74) | C/LavanderiasCercanasController | G2 | Rq/LavanderiasCercanasRequest | T/Cercanas/ConsultaDeCercanasTest, FiltrosTest | sí |
| 12 | GET lavanderias/{ulid} (:77) | C/PerfilDeLavanderiaController | G2 | parámetro | T/PerfilLavanderia/PerfilTest | sí |
| 13 | GET lavanderias/{ulid}/ventanas (:82) | C/VentanasDeLavanderiaController | G2 | Rq/VentanasRequest | T/Agenda/VentanasTest | sí |
| 14 | POST direcciones (:87) | C/DireccionesController@guardar | G2 | Rq/GuardarDireccionRequest | T/Direcciones/DireccionEscritaTest, MapaYFavoritasTest | sí |
| 15 | GET direcciones/favoritas (:88) | igual@favoritas | G2 | sin entrada | MapaYFavoritasTest, AislamientoTest | sí |
| 16 | POST direcciones/recogida-y-entrega (:89) | igual@recogidaYEntrega | G2 | Rq/RecogidaYEntregaRequest | ZonaDeServicioTest | sí |
| 17 | GET direcciones/{ulid} (:92) | igual@mostrar | G2 | parámetro | Direcciones/AislamientoTest | **no** (S5-06) |
| 18 | POST pedidos (:98) | C/PedidosController@crear | G2 | Rq/CrearPedidoRequest (la forma de `ventana` la revisa `Agenda`) | T/Confirmacion/CrearPedidoTest | sí |
| 19 | GET pedidos (:100) | igual@lista | G2 | Rq/ListaDePedidosRequest | T/Historial/ListaDePedidosTest | sí |
| 20 | GET pedidos/{id} (:103) | igual@mostrar | G2 | parámetro | T/Confirmacion/AislamientoTest | sí |
| 21 | POST pedidos/{id}/calificacion (:106) | C/CalificacionesController | G2 | Rq/CalificarPedidoRequest, después del 404 | T/Historial/CalificacionTest | sí |
| 22 | GET perfil (:109) | C/PerfilController@mostrar | G2 | sin entrada | T/PerfilUsuario/PerfilTest | sí |
| 23 | PATCH perfil (:110) | igual@cambiar | G2 | Rq/CambiarPerfilRequest | PerfilTest | sí |
| 24 | POST perfil/foto (:112) | igual@subirFoto | G2 | Rq/SubirFotoDePerfilRequest | FotoDePerfilTest | sí |
| 25 | GET soporte (:116) | C/SoporteController | G2 | sin entrada | NumeroDeSoporteTest | sí |
| 26 | POST soporte/reportes (:117) | C/ReportesDeSoporteController | G2 | Rq/EnviarReporteRequest | ReporteTest | sí |
| 27 | DELETE cuenta (:120) | C/BorradoDeCuentaController | G2 | sin entrada | BorrarCuentaTest | sí |
| 28 | POST dispositivos (:124) | C/DispositivosController | G2 | Rq/RegistroDeTelefonoRequest | TelefonosRegistradosTest | sí |
| 29 | POST pagos/checkout (:128) | C/PagosController@checkout | G2 | Rq/PedirCheckoutRequest, en dos fases | T/Pago/CrearCheckoutTest, PasarelaConektaTest | sí |
| 30 | GET pagos/{id} (:131) | igual@mostrar | G2 | parámetro | PagoRechazadoTest | sí |
| 31 | POST socio/lavanderias (:138) | C/LavanderiaDelSocioController@registrar | G3 | Rq/RegistroDeLavanderiaRequest | T/AltaLavanderias/RegistroTest | sí |
| 32 | GET socio/lavanderias (:139) | igual@lista | G3 | sin entrada | AltaLavanderias/PerfilTest | sí |
| 33 | GET socio/lavanderias/{ulid} (:141) | igual@mostrar | G3 | parámetro | PerfilTest | sí |
| 34 | PATCH socio/lavanderias/{ulid} (:145) | igual@editar | G3 | `Validator::make` en línea, solo sobre los campos que llegan | PerfilTest, DescripcionYFotosTest | sí |
| 35 | GET lavanderia/pedidos (:154) | C/PedidosDeLaLavanderiaController@lista | G4 | sin entrada | T/PedidosLavanderia/ListaTest | sí |
| 36 | GET lavanderia/pedidos/{id} (:155) | igual@mostrar | G4 | parámetro | T/Estados/SinPagoTest | **no** (S5-06) |
| 37 | POST …/{id}/aceptar (:156) | igual@aceptar | G4 | sin cuerpo | AceptarTest | sí |
| 38 | POST …/{id}/rechazar (:158) | igual@rechazar | G4 | Rq/RechazarPedidoRequest, antes del 404 (S5-04) | RechazarTest | sí |
| 39 | POST …/{id}/estado (:161) | igual@marcar | G4 | Rq/MarcarPedidoRequest, antes del 404 (S5-04) | BotonesDeLaLavanderiaTest | sí |
| 40 | GET lavanderia/historial (:164) | C/HistorialDeLaLavanderiaController | G4 | sin entrada | HistorialLavanderia/HistorialTest | sí |
| 41 | GET lavanderia/estadisticas (:166) | igual@estadisticas | G4 | sin entrada | EstadisticasTest | sí |
| 42 | GET repartidor/perfil (:174) | C/RepartidorController@perfil | G5 | sin entrada | Repartidor/RegistroTest, GestionEnElPanelTest | sí |
| 43 | GET repartidor/pedidos (:179) | C/PedidosDelRepartidorController@lista | G5o | sin entrada | PedidosAsignadosTest | sí |
| 44 | GET repartidor/pedidos/{id} (:180) | igual@mostrar | G5o | parámetro | PedidosAsignadosTest | **no** (S5-06) |
| 45 | POST …/{id}/aceptar (:182) | igual@aceptar | G5o | sin cuerpo | AceptarORechazarTest | sí |
| 46 | POST …/{id}/rechazar (:184) | igual@rechazar | G5o | Rq/RechazarPedidoRequest, antes del 404 | AceptarORechazarTest | sí |
| 47 | POST …/{id}/estado (:187) | igual@marcar | G5o | Rq/MarcarPedidoDelRepartidorRequest (la foto solo con "entregado"), antes del 404 | EstadosDelRepartidorTest, EvidenciaDeEntregaTest | sí |
| 48 | GET repartidor/historial (:190) | C/HistorialDelRepartidorController | G5o | sin entrada | HistorialDeEntregasTest | sí |
| 49 | GET repartidor/estadisticas (:192) | igual@estadisticas | G5o | sin entrada | Repartidor/EstadisticasTest | sí |

**Errores comunes:**
- 401 `Unauthenticated.`, siempre en JSON por `ResponderEnJson` (`servidor/bootstrap/app.php:23`).
- 403 `Esta cuenta no tiene acceso.` (`servidor/app/Http/Middleware/Autorizar.php:34`). Con `repartidor-opera`, el 403
  trae el estado del repartidor.
- 404 igual para lo ajeno, lo que no existe y lo que no es ULID (`PedidoNoVisible`, `PagoNoVisible`,
  `DireccionNoVisible`).
- 409 cuando el paso no es el siguiente o el pedido ya fue respondido.
- 503 cuando la pasarela o la geocodificación no están disponibles.

### Rutas de `servidor/routes/web.php` y las que registran los paquetes

| # | Ruta (`web.php:línea`) | Para qué | Validación | Tests declarados |
|---|---|---|---|---|
| 50–51 | GET /contrasena/restablecer/{token} (:23) y POST /contrasena/restablecer (:25) | Página de la contraseña nueva | el `correo` del query solo pasa `is_string` y se pinta escapado con `{{ }}`; el POST, con Rq/ContrasenaNuevaRequest | T/Registro/RecuperacionTest |
| 52 | GET / (:31) | Portada de la landing | — | T/Landing/* |
| 53 | GET /prensa/{archivo} (:38), sin sesión ni CSRF | Logos de prensa | `where [^/]+` y búsqueda en BD | T/Landing/PrensaTest |
| 54–55 | POST /formularios/interes y /formularios/contacto (:46-47) | Formularios de la landing, sin destino (S1-10) | Rq/FormularioDeInteresRequest, Rq/FormularioDeContactoRequest | T/Landing/FormulariosTest, ContactoTest |
| 56–57 | GET /aviso-de-privacidad y /terminos (:54, :57) | Textos legales del cliente (S5-01 en producción) | — | T/TextosLegales/PaginasPublicasTest |
| 58 | GET /borrado-de-cuenta (:66) | Página que pide Google Play | — | T/TextosLegales/BorradoDeCuentaTest |
| 59–60 | GET /sitemap.xml y /robots.txt (:73-74) | Buscadores | — | T/Medicion/BuscadoresTest |
| 61–62 | GET y POST /desarrollo/pasarela/checkout/{orden} (:82, :84) | Checkout simulado; solo en `local` y `testing` (:81) | `validate` en línea | T/Pago/CheckoutDeDesarrolloTest |
| — | `/up` | Salud | — | T/Base/ArranqueTest (`servidor/bootstrap/app.php:17`) |
| — | `/admin` y `/admin/login` (Filament), Livewire, descargas de exportaciones, `sanctum/csrf-cookie`, `storage/{path}` | Panel y paquetes | Declaradas en `Autorizacion::rutasSinSesion()` | barridos `T/Autorizacion/CoberturaDeReglasTest` y `BarridoContraFugasTest` |

### Cruce de la app con el servidor

| Concepto | Resultado | Evidencia |
|---|---|---|
| Cliente HTTP | `fetch` envuelto, con un plazo de 15 s. El token Bearer se pasa en cada llamada; la base sale de `EXPO_PUBLIC_API_URL`, que en preview y producción debe ser https | B: `movil/src/configuracion/api.ts:41-123`; `movil/src/configuracion/entorno.ts:195-225`; `movil/app.config.ts:109-119` |
| Manejo de errores | 422 → `invalido`, 401 → `sin-sesion`, 429 → `demasiados-intentos`; los demás caen en `servidor` y cada endpoint los distingue por su cuenta | B: `movil/src/configuracion/api.ts:115-123` |
| Llamadas huérfanas | **0** | 45 llamadas cruzadas contra `api.php`. La edición con foto va por POST con `_method=PATCH`, documentado en `api.php:144` |
| Rutas sin uso | 4: el webhook y tres GET de detalle (S5-06) | `api.php:52,92,155,180` |
| Forma de los datos (muestreo) | Coinciden en crear pedido, checkout, guardar dirección y estados de lavandería y repartidor. La app ignora campos que el servidor manda de más (`monto_estimado`, `zona_horaria`) | B: `movil/app/pedido/resumen.tsx:107-113` ↔ `servidor/app/Http/Requests/CrearPedidoRequest.php`; `movil/src/pago/api.ts:63` ↔ `PedirCheckoutRequest.php` |
| Prueba de contrato compartida | **No existe** (S5-03) | Sin OpenAPI, JSON Schema, zod ni pact |

### Integraciones externas

| Integración | Lo que pide el contrato | Estado real | Evidencia | Hallazgo |
|---|---|---|---|---|
| Conekta | Tarjeta y OXXO Pay (RN-28; `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3`, Módulo A #10) | **PARCIAL.** El adaptador real llama a la API REST v2 para crear órdenes, consultarlas y conciliar. El webhook no autentica y responde 503. Solo tarjeta. Por omisión, `PASARELA=simulada`. Sin sustitución | B: `servidor/app/Cobro/PasarelaConekta.php:29,123,263`; `servidor/app/Cobro/Pasarelas.php`; `servidor/.env.example:144-155` | S1-01, S1-11 |
| Mapas | Google en Android y Apple en iPhone (RN-29) | **REAL en código, falta la llave.** `react-native-maps` sin `provider`. El panel usa Maps JS | B: `movil/src/cercanas/MapaDeLavanderias.tsx`; `movil/app.config.ts:58-60` | — |
| Geocodificación | No la nombra (decisión técnica) | **REAL, con implementación de prueba.** Se elige por llave y entorno | B: `servidor/app/Direcciones/GeocodificadorSegunEntorno.php` | S4-11 |
| Avisos FCM | Servicio gratuito de Google, en iPhone y Android (RN-06, RN-30) | **PARCIAL.** El servidor va por la API HTTP v1, con OAuth en caché, cola y reintentos. La app, solo en Android | B: `servidor/app/Avisos/Fcm/CanalDeFcm.php`; `movil/src/avisos/firebase.ts:19-23`; `movil/firebase-nativo.js:21` | S1-05, S1-07 |
| Google Sign-In | Opción de registro (RN-17, RN-32) | **REAL en Android.** En iPhone falta el ID de cliente (DD-069). La verificación del `id_token` está completa | B: `servidor/app/Acceso/Google/VerificadorDeGoogle.php`; `movil/src/acceso/google.ts`; `movil/app.config.ts:42-51` | S1-05, S4-01 |
| GTM, GA4, Meta Pixel y Search Console | En la landing (RN-31) | **REAL a través de GTM, falta el ID.** GA4 y Pixel, dentro de GTM. Search Console, pendiente | B: `servidor/config/medicion.php:24` | S5-07 |
| Correo | No lo nombra (ADR-0014: Resend) | **PARCIAL.** La confirmación va por cola; la recuperación, sin cola; el paquete de Resend no está | B: `servidor/.env.example:64`; `servidor/composer.lock:2528` | S5-02, S5-05 |
| SMS | Fuera del alcance base (RN-24) | **AUSENTE, como debe** | Sin coincidencias en `servidor/` ni `movil/` | — |
| Almacenamiento S3 | No lo nombra (ADR-0006) | **ROTO en producción** | B: `servidor/config/fotos.php:31`; `servidor/composer.lock:2517` | S5-01 |
| Formularios de la landing | Secciones 5 y 11 de A.2 | **Sin destino** (DD-119) | B: `servidor/app/Http/Controllers/FormularioDeLaPortadaController.php:17-20` | S1-10 |

**Sustituciones:** ninguna. No hay ninguna Orden de Cambio firmada (`vault/cliente/ordenes-de-cambio/` solo tiene
`.gitkeep`), y ninguna hace falta todavía. DD-079, que propone quitar Conekta, sigue abierta; si se decidiera sin Orden
de Cambio firmada, sería Crítico (CLAUDE.md §2-bis, regla 3).

### Preguntas abiertas

**Para Arthur:**
1. ¿Con qué proveedor S3 o compatible se va a producción, y cuándo entra `league/flysystem-aws-s3-v3`? (S5-01, DD-070)
2. ¿Resend con su paquete o por SMTP? En cualquiera de los dos casos, falta una prueba de envío real (S5-02).
3. ¿Habrá un esquema de la API y una regla de compatibilidad antes del envío a tiendas? (S5-03)
4. ¿Qué escenario necesita los tres GET de detalle que la app no usa? (S5-06)

**Para Nadir:**
5. ¿Ya están pedidos al cliente el ID de GTM, el acceso a Search Console y la cuenta de Conekta? Bloquean A42 y A43-2
   (S5-07, S1-11).

**Para EL CLIENTE:**
6. Las mismas cuentas de la pregunta 5, a su nombre (RN-20; `NSV-2026-001-v8.pdf:34:ANEXO B, Sección B.11`).

### Comandos ejecutados

```bash
git -c core.longpaths=true worktree add --detach <scratchpad>/h3 9d24977
gh pr view 4 -R nhadadn/Lavandi --json headRefOid,updatedAt,state
cat -n servidor/routes/api.php servidor/routes/web.php
grep -n '"name": "league/flysystem\|"name": "resend\|"name": "aws/' servidor/composer.lock
sed -n '2478p;2517p;2528p' servidor/composer.lock
grep -n "env(" servidor/config/fotos.php servidor/config/textos_legales.php
grep -n "allowed_payment_methods\|function esAutentico" servidor/app/Cobro/PasarelaConekta.php
grep -n "class EnlaceDeRecuperacion" servidor/app/Notifications/EnlaceDeRecuperacion.php
sed -n '105,133p' servidor/app/Http/Controllers/Api/PedidosDeLaLavanderiaController.php
sed -n '115,123p' movil/src/configuracion/api.ts
grep -rln "global.fetch\|globalThis.fetch" movil/__tests__ | wc -l     # 42
grep -rln "CONTRATO_" movil/src | wc -l                                # 18
ls -a vault/cliente/ordenes-de-cambio/
```
