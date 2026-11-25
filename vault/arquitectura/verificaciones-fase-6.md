---
estado: borrador
grado: E
fuente: búsquedas y lecturas de fuentes oficiales del 2026-09-28 (Conekta, Apple Developer, Google Play Console, Google Maps Platform, Firebase, StatCounter), hechas por un subagente de investigación y revisadas por Claude; todo SIN VERIFICAR hasta que Nadir lo confirme; la sección de candidatos, de la investigación del 2026-10-09 sobre construir, adaptar o comprar; la búsqueda del 2026-11-12 sobre compilar para iPhone sin Mac; los datos del 2026-11-13; la búsqueda del 2026-11-15 sobre más opciones de hosting; y la del 2026-11-23 sobre el costo del SDK de mapas de Android (fila 6c)
fecha: 2026-11-23
dueño: Nadir Hadad Navarrete
---
# Verificaciones externas de la Fase 6

Son los datos de fuera que la matriz del stack pedía verificar antes de puntuar
(`matriz-de-criterios-stack.md`, "Verificaciones grado E pendientes"). **Solo cubren proveedores que el
contrato ya obliga a usar y reglas de las tiendas.** No se miró ningún candidato de stack: eso espera a
que Nadir ajuste los pesos.

Todo es **grado E**, consultado el 2026-09-28, `SIN VERIFICAR`. Nada sube a grado C sin que Nadir lo
confirme en acta. Lo de cada fuente está parafraseado.

## Lo que cambia algo

1. **OXXO Pay ya no sería de Conekta, y el contrato lo da por hecho.** → R-028, DD-056.
   - Conekta anunció el 29 de abril de 2024 la venta de OXXO Pay a Digital@FEMSA.
   - Su producto de efectivo actual, Conekta Efectivo, no incluye OXXO entre sus comercios.
   - El contrato dice "Conekta: tarjeta de crédito o débito y OXXO Pay" (`NSV-2026-001-v8.pdf:16:A.3`) y
     que Conekta va a "generar fichas de pago en tiendas OXXO" (`:19`).
2. **La tienda de Apple exige una segunda forma de entrar además de Google.** → BR-14.
   - Si la app ofrece Google para iniciar sesión, debe ofrecer otra opción equivalente que cumpla tres
     condiciones de privacidad. Iniciar sesión con Apple las cumple.
   - Tener además registro con correo y contraseña no crea excepción.
3. **Google Play: una cuenta personal nueva no puede publicar sin una prueba cerrada.** → R-029, DD-017.
   - Hace falta una prueba con al menos 12 testers durante 14 días seguidos, más una revisión de hasta 7
     días.
   - Las cuentas de organización no tienen ese requisito, pero exigen D-U-N-S. Y EL CLIENTE son personas
     físicas (R-003).

## El detalle

| # | Tema | Dato | Implicación | Confianza | Fuente |
|---|---|---|---|---|---|
| 1 | Google Play, cuentas personales nuevas | Las creadas después del 13 de noviembre de 2023 deben correr una prueba cerrada con al menos 12 testers inscritos sin interrupción durante 14 días, llenar un cuestionario y esperar hasta 7 días de revisión. Las de organización están exentas | Con cuenta personal, la prueba cerrada tiene que empezar **a más tardar a mediados de noviembre** para llegar al envío de la semana 14 | Alta en la regla; media en que no cambie | support.google.com/googleplay/android-developer/answer/14151465 |
| 1b | Google Play, costo y tipo de cuenta | Pago único de 25 USD. Cuenta personal y de organización tienen las mismas funciones; la de organización exige D-U-N-S | La titularidad y el tipo de cuenta cambian el calendario → DD-017 | Alta en costo; baja en plazos, no publicados | support.google.com/googleplay/android-developer/answer/6112435 · /answer/13634885 |
| 2 | Apple Developer Program | 99 USD al año. Como organización exige D-U-N-S, ser entidad legal, facultad para obligarla, sitio web con dominio propio y correo en ese dominio, y doble autenticación. El D-U-N-S tarda hasta 5 días hábiles más 2 de Apple | Una cuenta de organización necesita antes una sociedad, el D-U-N-S y el dominio. Como persona física, publica con el nombre de la persona (R-003) | Alta en requisitos; media en plazos | developer.apple.com/programs/enroll · /support/D-U-N-S |
| 3 | Apple, guía 4.8 | Con inicio de sesión de terceros, hay que ofrecer una opción equivalente que limite los datos a nombre y correo, permita ocultar el correo y no rastree con fines publicitarios sin consentimiento. La excepción por sistema propio solo aplica si es el **único** | Iniciar sesión con Apple entra como **requisito de publicación**, no de alcance (RN-32, BR-14). Si un registro propio bastaría lo decide el revisor | Alta en el texto; media-baja en la interpretación | developer.apple.com/app-store/review/guidelines |
| 4 | Eliminación de cuenta | **Apple:** si la app crea cuentas, debe permitir borrarlas desde la app; desactivar no basta. **Google Play:** borrado dentro de la app **y** un enlace web para pedirlo, declarado en su formulario de datos | Cada módulo que crea cuenta necesita borrado en la app y una página web pública (BR-09). La retención de pedidos y pagos por obligación fiscal se declara en el aviso de privacidad | Alta | developer.apple.com/support/offering-account-deletion-in-your-app · support.google.com/googleplay/android-developer/answer/13327111 |
| 5a | Conekta y OXXO | Venta de OXXO Pay a Digital@FEMSA anunciada el 2024-04-29, con cierre previsto hacia el tercer trimestre de 2024. Hoy Conekta Efectivo cobra en más de 19,000 puntos: BBVA, 7-Eleven, Walmart, Soriana, Farmacias del Ahorro, Benavides, Circle K. **OXXO no aparece** | Ver R-028 y DD-056 | Alta en la venta; media en que OXXO esté fuera hoy: se deduce de la lista, no de una frase que lo excluya | conekta.com/blog (nueva etapa…) · bloomberglinea.com · conekta.com/payments/cash |
| 5a-bis | Conekta Efectivo, cómo funciona | Referencia con monto y vencimiento; el aviso de pago llega por webhook en un máximo de 10 minutos | El pedido necesita un estado "esperando pago" y manejar el vencimiento de la ficha | Alta | developers.conekta.com/docs/cargo-unico-efectivo-direct-api |
| 5b | Conekta, tarjeta desde el teléfono | Tiene un SDK móvil que cifra la tarjeta y la manda directo a Conekta; el número nunca pasa por el servidor propio. Cubre Android nativo, iOS nativo y un framework multiplataforma, con versiones mínimas. **Está en beta** (0.2.0-beta.2, actualizado el 2026-09-03) | La restricción de RN-10 se puede cumplir. Que esté en beta y qué plataformas cubre **pesa en la Fase 6** | Alta en lo documentado; media en su estabilidad | developers.conekta.com/docs/checkout-tokenizer-sdk |
| 5c | Conekta, tarjetas guardadas | Maneja clientes con tarjetas guardadas y permite cobrar después con su identificador sin volver a capturarla | BR-50 y DD-055 son viables. **Cobrar el peso real después de pesar** con la tarjeta guardada es técnicamente posible; falta confirmar condiciones con Conekta | Media: una página dio error | developers.conekta.com (cargo bajo demanda; crear cliente) |
| 5d | Conekta, comisiones publicadas | Tarjeta 3.4 % + 3 MXN + IVA; efectivo 2.6 % + 3 MXN + IVA, más 10 a 13 MXN que el comercio cobra al cliente. Página "válida a partir del 1 de julio de 2024" | **No coincide con el contrato**, que estima tarjeta en 2.9 % + 2.50 MXN y OXXO en 3.9 % (`:30:B.6`). El B.6 es referencial (Cláusula XI), pero el modelo de negocio usa esas cifras | Media: página vieja y con cifras distintas en terceros | conekta.com/pricing |
| 6 | Google Maps Platform | Los mapas nativos en Android e iOS son **gratis sin límite**. Mapas web: 10,000 cargas gratis al mes y después 7 USD por mil. Geocodificación: 10,000 gratis y después 5 USD por mil. Autocompletado: 10,000 gratis y después 2.83 USD por mil. Ya no se menciona el crédito de 200 USD | El mapa de la app no cuesta por carga. Cuestan la geocodificación, el autocompletado y los mapas web del panel (`presupuesto-de-mapas.md`) | Alta en cifras; media en lo del crédito | developers.google.com/maps/billing-and-pricing/pricing |
| 6c | Google Maps, el SDK de Android (consultado el 2026-11-23) | Una carga de mapa **sin map ID** cae en el SKU "Maps SDK", que es "Unlimited"; **con map ID** cae en "Dynamic Maps": 10,000 gratis al mes y después 7 USD por mil. Mover o acercar el mapa no cuenta como carga nueva. Aunque el uso sea gratis, la llave pide un proyecto con facturación activa. La Maps Demo Key, que no pide tarjeta, no lista el SDK de Android entre lo que cubre | El mapa de la app sigue sin costo mientras no use map ID; hoy no lo usa (`movil/src/cercanas/MapaDeLavanderias.tsx:38`, `movil/src/direcciones/MapaParaMarcar.tsx:50`). Para la llave, hay que dar de alta una cuenta de facturación | Alta en el texto | developers.google.com/maps/billing-and-pricing/sku-details/maps · /maps/documentation/android-sdk/usage-and-billing · /maps/demo-key |
| 6b | Apple MapKit | Nativo en iPhone, sin costo aparte de la membresía. Versión web: 250,000 cargas diarias gratis | El mapa en iPhone no genera costo por uso | Media: sale de los foros de Apple | developer.apple.com/maps |
| 7 | Avisos | El servicio de avisos de Google es gratuito. En iPhone necesita una clave que se crea en la cuenta de desarrollador de Apple. En Android 13 o superior la app debe pedir permiso al usuario | Los avisos a iPhone dependen de la cuenta de Apple (B.10, semanas 5 y 6). Ningún paso crítico del pedido puede depender solo del aviso: el usuario puede negarlo | Alta | firebase.google.com/pricing · /docs/cloud-messaging · developer.android.com |
| 8a | Versiones de iOS en México | Agosto de 2026: iOS 26 al menos 57 % e iOS 18 al menos 31 %. Exigir iOS 18 cubre cerca del 90 % | Base para BR-42 | Media-baja: mide tráfico web y falta desglose | gs.statcounter.com (iOS, México) |
| 8b | Versiones de Android en México | Agosto de 2026: Android 11 o superior, al menos 92 %; Android 12 o superior, al menos 87 % | Base para BR-42 | Media | gs.statcounter.com (Android, México) |

## Lo que no se confirmó

- Si hoy existe alguna vía para cobrar en OXXO a través de Conekta. **Es lo primero que hay que preguntarle a
  Conekta**, por escrito.
- Las condiciones de Conekta para cobrar sin el cliente presente y con 3D Secure.
- La frase explícita de Google sobre el fin del crédito de 200 USD.
- El desglose completo de versiones viejas de iOS y Android.

## Lo que sigue pendiente de verificar, a propósito

**Precios de servidor, base de datos y almacenamiento** contra el rango de RN-22. Son datos de
candidatos concretos, y el método no deja mirar candidatos antes de fijar los pesos.

## Investigación del 2026-10-09: datos de candidatos (no leer antes de fijar los pesos)

> **Esta sección nombra frameworks concretos.** Salió de la investigación de construir, adaptar o comprar
> (`planteamiento-fase-6.md`, paso 4), que no buscaba candidatos pero los encontró. El método manda fijar los pesos
> antes de mirar candidatos; esta sección se lee en el paso 6, no antes. Grado E, `SIN VERIFICAR`, consultado el
> 2026-10-09.

| Dato | Puerta que toca | Fuente |
|---|---|---|
| En React Native, `react-native-maps` usa el mapa de Apple en iPhone y el de Google en Android sin configuración extra | G-2 (RN-29) | https://github.com/react-native-maps/react-native-maps/blob/master/docs/installation.md |
| En Expo, `expo-maps` hace lo mismo, pero sigue en versión alfa | G-2 | https://docs.expo.dev/versions/latest/sdk/maps/ |
| En Flutter, el mapa de Apple depende de `apple_maps_flutter`: solo iOS, se declara versión preliminar y su última versión tiene unos 20 meses | G-2 | https://pub.dev/packages/apple_maps_flutter |
| Conekta no tiene SDK oficial de Flutter; los complementos de terceros están desactualizados. Hay SDK nativos de Android e iOS, y ConektaJS tokeniza en web | G-3 (RN-28) | https://developers.conekta.com/tutorials/card · https://pub.dev/packages/flutter_conekta/score |
| FlutterFlow trae como mapa nativo el de Google en las dos plataformas; exportar el código es de un solo sentido y solo en planes de pago | G-2, G-5 | https://www.flutterflow.io/pricing |
| Supabase: plan gratuito que se pausa tras una semana sin uso; Pro desde 25 dólares al mes. Se puede alojar uno mismo | G-4 (RN-22) | https://supabase.com/pricing |

## Búsqueda del 2026-11-12: compilar para iPhone sin Mac

> Grado E, `SIN VERIFICAR`, consultado el 2026-11-12. La pregunta vino de Arthur (C-137): el equipo no tiene Mac, y
> Xcode solo corre en macOS. Nombra frameworks concretos, igual que la sección anterior: cuenta en el paso 6.

| Dato | Qué toca | Fuente |
|---|---|---|
| Con Expo, EAS Build compila el binario de iPhone, firmado, en máquinas de Expo: no hace falta Mac ni Xcode local. EAS Submit lo sube a App Store Connect y a TestFlight desde Windows o Linux | G-1, R-025; la prueba temprana | https://docs.expo.dev/submit/ios · https://docs.expo.dev/tutorial/eas/ios-production-build.md · https://blog.openreplay.com/build-ios-app-without-mac/ |
| `--auto-submit` sube la versión a TestFlight para pruebas internas, pero no la manda a revisión de la App Store | Calendario de publicación | https://docs.expo.dev/tutorial/eas/ios-production-build.md |
| Sin Mac no hay simulador de iPhone: se prueba en un iPhone físico, con una versión de desarrollo o por TestFlight | La prueba temprana; hace falta un iPhone | https://docs.expo.dev/submit/testflight.md |
| Para Capacitor, la compilación de iPhone sin Mac depende de terceros con Macs en la nube: Capawesome Cloud, Capgo Build y Codemagic. Las fuentes son sobre todo de los mismos proveedores | G-1, R-025 | https://capawesome.io/solutions/build-without-mac/ · https://capgo.app/docs/builder/ · https://capgo.app/blog/comparing-ci-cd-platforms-for-ios-apps/ |
| Toda ruta sin Mac necesita una cuenta del Apple Developer Program, de unos 99 USD al año según una sola fuente. Firmar y repartir por TestFlight la exigen | DD-017, DD-069 | https://blog.openreplay.com/build-ios-app-without-mac/ |

**Lo que no se verificó:** las condiciones actuales de cada servicio (minutos de compilación, precios, versión de
Xcode), el precio vigente del Apple Developer Program y si depurar fallas nativas de iPhone sin Mac resulta práctico.
Como plan de respaldo existe rentar un Mac en la nube.

## Datos de candidatos del 2026-11-12 (paso 6)

> Grado E, `SIN VERIFICAR`, consultados el 2026-11-12 por dos subagentes de investigación y revisados por Claude. Son
> la base de los puntajes de `ADR-0002`, `ADR-0003` y `ADR-0004`. Los costos en MXN son cálculos con el FIX de Banxico
> del 2026-11-12 (18.6188 MXN por USD), no cotizaciones.

### App móvil

| Dato | Qué toca | Fuente |
|---|---|---|
| Expo SDK 58 (2026-10-15) trae React Native 0.88. `expo-maps` pasó de alfa a estable en la 58.0.0: usa Apple Maps en iOS y Google en Android, pero exige iOS 17 o más | G-2; BR-42 | https://raw.githubusercontent.com/expo/expo/main/packages/expo-maps/CHANGELOG.md · https://docs.expo.dev/versions/latest/sdk/maps/ |
| `react-native-maps` 1.29.11 (2026-09-27), MIT: sin `provider` usa MapKit en iOS y Google en Android | G-2 | https://github.com/react-native-maps/react-native-maps/releases |
| Capacitor: `@capacitor/google-maps` usa Google también en iOS, así que no cumple G-2. La única opción con MapKit es `@capgo/capacitor-native-map`, creada el 2026-10-18, con 2 estrellas y licencia MPL-2.0 | G-2 | https://capacitorjs.com/docs/apis/google-maps · https://github.com/Cap-go/capacitor-native-map |
| Flutter: `apple_maps_flutter` 1.4.0 lleva unos 21 meses sin cambios y se declara "developers preview" | G-2 | https://pub.dev/packages/apple_maps_flutter |
| .NET MAUI y Kotlin Multiplatform: para compilar iPhone exigen Mac con Xcode | Equipo sin Mac (C-137) | https://learn.microsoft.com/en-us/dotnet/maui/ios/pair-to-mac · https://kotlinlang.org/docs/multiplatform/quickstart.html |
| Conekta: SDK nativos `conekta-components-compose` 1.2.0 (Android, Compose, minSdk 24) y `conekta-components-ios` 1.2.0 (iOS 15+). El envoltorio oficial para React Native, `conekta-elements-react-native`, sigue en la 0.0.1 (2026-03-03) y no funciona en Expo Go | G-3; ADR-0010 | https://github.com/conekta/conekta-elements-react-native · https://github.com/conekta/conekta-components-ios · https://repo1.maven.org/maven2/io/conekta/conekta-components-compose/maven-metadata.xml |
| Conekta tiene un checkout redirigido (`HostedPayment`) que devuelve una URL y confirma el pago por webhook, y funciona desde cualquier framework. La API directa de tokens es solo para cuentas con PCI | G-3; ADR-0010 | https://developers.conekta.com/docs/componente-de-pago.md · https://developers.conekta.com/docs/checkout-tokenizer-sdk.md |
| **OXXO.** La lista vigente de redes de Conekta Efectivo (actualizada el 2026-08-03) no incluye OXXO. El tutorial "Pagos con OXXO Pay" sigue publicado con la misma fecha. En 2024 Conekta acordó vender los activos de OXXO PAY a Digital@FEMSA. Coincide con R-028 y DD-056: hay que confirmarlo con Conekta | G-3; DD-056 | https://developers.conekta.com/tutorials/oxxopay · https://www.contxto.com/en/payments/conekta-to-sell-oxxo-pay-assets-to-digitalfemsa-in-2024/ |
| Avisos: `@react-native-firebase/messaging` 27.1.1 funciona en Expo con development build | ADR-0008 | https://rnfirebase.io/ |
| Inicio de sesión: la versión gratuita de `@react-native-google-signin/google-signin` 17.0.0 se apoya en el SDK de Android en desuso; la recomendada es de pago | ADR-0005 | https://react-native-google-signin.github.io/docs/install |
| EAS: el plan gratuito da 15 compilaciones de iOS y 15 de Android, con cola de baja prioridad. El plan Starter cuesta 19 USD al mes. EAS Submit viene incluido | ADR-0012 | https://expo.dev/pricing |

### Backend

| Dato | Qué toca | Fuente |
|---|---|---|
| Laravel 13.42.0 al 2026-11-10 | ADR-0003 | https://packagist.org/packages/laravel/framework |
| Filament 5.11.2 (2026-11-07), MIT. Exporta a CSV y XLSX de fábrica, con colas y lotes de trabajos. Los mapas solo con plugins: `cheesegrits/filament-google-maps`, en beta, y Pinpoint | ADR-0003; ADR-0009 | https://filamentphp.com/docs/5.x/actions/export · https://filamentphp.com/plugins/cheesegrits-google-maps |
| `conekta/conekta-php` 9.0.0 (2026-07-10), oficial. Los webhooks reintentan 13 veces en unas 24 h. **La firma de los webhooks no está confirmada**: hay que validarla en el sandbox | ADR-0010 | https://packagist.org/packages/conekta/conekta-php · https://developers.conekta.com/resources/webhooks |
| `laravel-notification-channels/fcm` 6.2.0 y `kreait/firebase-php` 8.6.0. FCM no tiene costo | ADR-0008 | https://packagist.org/packages/laravel-notification-channels/fcm · https://firebase.google.com/pricing |
| `socialiteproviders/apple` 6.1.0 (2026-09-19) valida el token de identidad de las apps nativas | ADR-0005 | https://packagist.org/packages/socialiteproviders/apple |
| Laravel Reverb 1.13.0, MIT, en el propio servidor sin costo. No tiene cliente oficial para React Native | ADR-0007 | https://packagist.org/packages/laravel/reverb |
| Costo de arranque: Forge con DigitalOcean sale en unos 1,180 MXN al mes; Laravel Cloud, en unos 750 a 1,030; Supabase Pro, en unos 465 más el panel; NestJS con DigitalOcean, en unos 863. La base de datos administrada de 1 GiB en DigitalOcean cuesta 15.15 USD con PostgreSQL o con MySQL | G-4; DD-070 | https://www.digitalocean.com/pricing/managed-databases · https://laravel.com/cloud/pricing · https://supabase.com/pricing.md · https://www.banxico.org.mx/tipcamb/tipCamMIAction.do?idioma=sp |
| Almacenamiento: Cloudflare R2 cuesta 0.015 USD por GB al mes, con 10 GB gratis y descarga sin costo. DigitalOcean Spaces cuesta 5 USD al mes con 250 GiB | ADR-0006 | https://developers.cloudflare.com/r2/pricing/ · https://www.digitalocean.com/pricing/spaces-object-storage |
| Correo: Amazon SES cuesta 0.16 USD por cada mil correos para las cuentas nuevas desde el 2026-07-21. Resend tiene 3,000 gratis al mes | ADR-0014 | https://aws.amazon.com/ses/pricing/ · https://resend.com/pricing |

## Datos del 2026-11-13 (para los ADR-0005, 0011, 0012 y 0014, y para DD-070)

> Grado E, `SIN VERIFICAR`, consultados el 2026-11-13 por un subagente de investigación y revisados por Claude.

| Dato | Qué toca | Fuente |
|---|---|---|
| **PostGIS en Laravel Cloud: no confirmado.** Su Postgres sin servidor "admite una amplia gama de extensiones", pero solo nombra `pgvector`. Neon, que está debajo, sí trae PostGIS (3.5.7 en PG17 y 3.6.4 en PG18) | DD-070; ADR-0004 | https://laravel.com/cloud/docs/resources/databases/postgres · https://neon.com/docs/extensions/pg-extensions |
| **PostGIS en DigitalOcean:** sí, en clusters Standard Edition de PG14 a PG18. Los Advanced Edition no lo traen | DD-070; ADR-0004 | https://docs.digitalocean.com/products/databases/postgresql/details/supported-extensions/ |
| Laravel Cloud: planes Starter de 5 USD, Growth de 20 y Business de 200, más consumo. Entornos ilimitados, con apagado automático cuando no se usan. Buckets sobre R2 a 0.02 USD por GB al mes. **Las aplicaciones no se transfieren a otra organización**, y la facturación puede ir a nombre de una persona o de una empresa | DD-070; ADR-0012 | https://laravel.com/cloud/docs/pricing · https://laravel.com/cloud/docs/applications.md · https://laravel.com/cloud/docs/organizations.md |
| Google quitó el inicio de sesión antiguo de Android de `play-services-auth` 22.0.0 (2026-08-26) y manda usar Credential Manager | ADR-0005 | https://developers.google.com/android/guides/releases |
| `react-native-nitro-google-signin` 2.3.1, MIT, usa Credential Manager y funciona con Expo 49 o superior. Universal Sign In cuesta 79 USD al año (personal) o 249 (equipo). `expo-auth-session` ya no es viable con Google en Android, porque Google no acepta esquemas de URI propios | ADR-0005 | https://registry.npmjs.org/react-native-nitro-google-signin/latest · https://universal-sign-in.com/ · https://developers.google.com/identity/protocols/oauth2/native-app · https://docs.expo.dev/guides/google-authentication/ |
| Laravel 13 trae transporte oficial para Resend y para Amazon SES. El plan gratuito de Resend da 100 correos al día y 3,000 al mes; al pasarse responde 429 y no reintenta. **SES exige salir del sandbox**, por región, y AWS responde en hasta 96 horas | ADR-0014; DD-071 | https://laravel.com/docs/13.x/mail · https://resend.com/docs/knowledge-base/account-quotas-and-limits · https://docs.aws.amazon.com/ses/latest/dg/request-production-access.html |
| **Tailwind Plus:** no se pudo leer su licencia sin iniciar sesión. Según fragmentos de su FAQ, permite "un sitio a la medida para un solo cliente" y prohíbe revenderlo o derivar plantillas. **ThemeForest, licencia Regular:** se puede crear un producto final para un cliente y transferírselo | ADR-0011 | https://tailwindcss.com/plus/license · https://themeforest.net/licenses/terms/regular |

## Datos del 2026-11-15: más opciones de hosting (para DD-070)

> Grado E, `SIN VERIFICAR`, consultados por Claude el 2026-11-15, a pedido de Arthur, que administra sus otros
> sistemas en el cPanel de GoDaddy (C-159). Las conversiones usan el FIX del 2026-11-12 (18.6188 MXN por USD). Los
> precios bajos son de promoción y se pagan por adelantado a 24 o 36 meses; no se confirmó si incluyen IVA.

| Dato | Qué toca | Fuente |
|---|---|---|
| **GoDaddy cPanel compartido:** su tabla de componentes solo lista MySQL. En un plan compartido no se instalan extensiones, así que no hay PostGIS. Las colas de Laravel correrían con cron, sin Supervisor (foros, no documentación de GoDaddy) | DD-070; reabriría el ADR-0004 | https://www.godaddy.com/help/which-components-does-my-hosting-support-5614 · https://laracasts.com/discuss/channels/laravel/laravel-queuework-setup-in-background-cpanel |
| **GoDaddy VPS:** acceso root; desde 8.99 USD al mes (unos 167 MXN) en promoción a 36 meses. PostgreSQL y PostGIS se instalan a mano | DD-070 | https://www.godaddy.com/hosting/vps-hosting |
| **Hostinger VPS KVM 2:** 2 vCPU, 8 GB de RAM y 100 GB NVMe; MX$154.99 al mes en promoción a 24 meses, renueva a MX$273.99 | DD-070 | https://www.hostinger.com/mx/vps/docker/kong |
| **Vultr** tiene centro de datos en la Ciudad de México y PostgreSQL administrado desde unos 15 USD al mes (estimación de terceros, sin región). PostGIS sin confirmar | DD-070 | https://blogs.vultr.com/Diecinueve-Vultrs-19th-Cloud-Location-is-in-Mexico-City · https://www.vultr.com/products/managed-databases/ |
| **Paneles para un VPS:** Forge, unos 12 USD al mes; Ploi, unos 8 USD, con plan gratis de un servidor y un sitio; Coolify, gratis y de código abierto (Apache 2.0) | DD-070 | https://benjamincrozat.com/laravel-forge · https://toolradar.com/tools/ploi · https://lumadock.com/tutorials/deploy-laravel-on-coolify |
| **Oracle Cloud Always Free:** un servidor completo gratis, pero Oracle recupera las instancias ociosas (CPU debajo de 20% en 7 días). Hay reportes, sin confirmar, de que recortó el plan a la mitad en junio de 2026. Sirve para prueba, no para producción | ADR-0012 (entorno de prueba) | https://docs.oracle.com/iaas/Content/FreeTier/resourceref.htm · https://linuxiac.com/oracle-quietly-cuts-free-tier-ampere-a1-resources-in-half/ |
| **Descartados:** Vercel (su plan gratis es solo para uso no comercial y no soporta PHP de forma oficial); Render gratis (el servidor se duerme a los 15 minutos y la base de datos caduca a los 30 días); Hetzner (subió precios dos veces en 2026 y no está claro qué vende en Estados Unidos) | DD-070 | https://vercel.com/docs/plans/hobby · https://render.com/docs/free · https://www.bitdoze.com/es/hetzner-cloud-precios-2026/ |
| **Next.js en lugar de Laravel:** no trae base de datos, colas, tareas programadas ni correo; cada pieza se arma aparte. Puntaje propuesto con la matriz vigente: 65, contra 96 de Laravel. No se abrió ADR, porque Arthur no cambió la decisión (C-160) | ADR-0003 | https://payloadcms.com/docs/v3/jobs-queue/overview.md · https://npmjs.com/package/conekta |
