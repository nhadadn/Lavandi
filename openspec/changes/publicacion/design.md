## Context

- ADR-0012 fija tres entornos y a nombre de quién va cada cuenta: local y prueba pueden arrancar en cuentas de EL
  PRESTADOR; producción va siempre en cuentas de EL CLIENTE, con Arthur como colaborador revocable. **Mientras DD-070
  siga abierta, solo existe el entorno local** (C-154).
- ADR-0002: la app se compila y se envía a las tiendas en la nube, con EAS Build y EAS Submit, sin Mac.
- ADR-0008: un proyecto de Firebase por entorno; en iPhone los avisos necesitan la clave de la cuenta de Apple.
- ADR-0009: la llave de Google Maps de Android va restringida a la app y es una por entorno.
- ADR-0010: el pago se da por bueno solo con el webhook de la pasarela; la firma se valida en su modo de prueba antes de
  producción. ADR-0010 sigue vigente y nombra a Conekta, que es la pasarela en producción mientras el cliente no firme
  el cambio (`CLAUDE.md` §2-bis, punto 3). Stripe y Mercado Pago son candidatas (C-192); el cliente dio un OK expreso y
  la firma sigue pendiente (C-216, DD-079). Si se firma, el ADR de la elegida reemplaza a ADR-0010.
- Los trámites del B.10 no han empezado (DD-084) y no se sabe si las cuentas de las tiendas serán de persona física o de
  sociedad (DD-017). Grado E: una cuenta personal nueva de Google Play pide una prueba cerrada de 14 días con 12
  personas antes de publicar (R-029, `vault/arquitectura/verificaciones-fase-6.md`, fila 1).
- En las dos semanas antes del envío no entran funciones nuevas (`vault/formas-de-trabajo/sprint-y-sesion-diaria.md`).

## Goals / Non-Goals

**Goals:**

- Prueba y producción existen y no se mezclan.
- Todo viaja cifrado (SEG-1).
- Compilaciones firmadas, enviadas y liberadas a la vez en las dos tiendas (A43-1).
- La pasarela cobra en producción (A43-4): Conekta, o la elegida si se firma el cambio (DD-079).
- El hito alterno del pago 2 tiene dónde acreditarse (`NSV-2026-001-v8.pdf:7:Cláusula IV`).

**Non-Goals:**

- Los tiempos y los criterios de revisión de las tiendas, que no dependen de EL PRESTADOR.
- Más de 2 intentos de corrección de rechazos sin cargo (`NSV-2026-001-v8.pdf:23:ANEXO A, Sección A.7`).
- El mantenimiento posterior (`NSV-2026-001-v8.pdf:40:ANEXO C, Sección C.5`), incluida la vigilancia de disponibilidad.
- Abrir o pagar las cuentas del cliente.
- Funciones nuevas.

## Decisions

No hay decisión técnica nueva: el cambio aplica ADR-0002, ADR-0004, ADR-0008, ADR-0009, ADR-0010 y ADR-0012, sin
repetirlos.

**Cómo quedó implementado.** Las lecturas de abajo las tomó Claude por delegación de Arthur (C-236), con la mínima que
cumple cada escenario; se pueden rehacer sin tocar los requisitos.

- **Configuración por entorno (tarea 1.1, 2026-11-22):** lo que pide publicacion-E01 ya lo habían dejado `pago`
  (`PASARELA` y su llave, pago-E17), `avisos` (`FCM_CREDENCIALES`, la ruta a la cuenta de servicio del proyecto de
  Firebase del entorno), `registro-e-inicio-de-sesion`, `direcciones` e `indicadores` (`GOOGLE_CLIENTES_OAUTH`,
  `GOOGLE_GEOCODIFICACION_LLAVE` y `GOOGLE_MAPS_PANEL_LLAVE`) y el correo de Laravel (`MAIL_*`). No hizo falta código: la
  prueba `servidor/tests/Feature/Publicacion/ConfiguracionPorEntornoTest.php` lo fija. Simula el `.env` de cada entorno
  poniendo las variables en el proceso y volviendo a leer la configuración: el aviso va al proyecto de Firebase del
  entorno, la orden de pago lleva la llave del entorno, en producción no cobra la pasarela simulada, y cada credencial
  cambia con su variable. Revisa además que ninguna llave de Google, cliente de OAuth, llave privada ni llave de Conekta
  esté escrita en el código, y que nada fuera de `config/` lea el entorno, porque con la configuración en caché, como en
  producción, `env()` ya no lee nada. Nació en verde; con la ruta de Firebase escrita a mano en `config/avisos.php`, dos
  de sus pruebas fallan.
- **La pasarela de prueba y la de producción:** Conekta llega con la sección 3 de `openspec/changes/pago/tasks.md`. Sus
  llaves de modo de prueba y de producción entran por el mismo camino, con sus variables en el `.env` de cada entorno;
  hasta entonces, en prueba y en producción el pago responde que no está disponible. La parte de la pasarela de
  publicacion-E01 se termina de acreditar con esa tarea.
- **Solo https (tarea 2.1, 2026-11-22):** el middleware global `App\Http\Middleware\SoloHttps`, que implementó la
  tarea 3.2 de `medicion-de-la-landing` (medicion-E13) y aquí se prueba en el panel y en la API. Se enciende con
  `app.solo_https`, verdadero en todo entorno que no sea `local` ni `testing`. Por http, una página del panel o de la
  landing recibe una redirección 301 a la misma dirección por https, en el dominio de `APP_URL` (publicacion-E04); una
  ruta de la API recibe 403 con solo `{"message": "La API solo atiende por https."}`, sin redirección (publicacion-E05):
  una app que siguiera la redirección volvería a mandar por http su token o su contraseña, y el 403 hace visible el error
  de configuración en lugar de esconderlo. En los dos casos la petición no se procesa: no abre sesión, no crea token y no
  guarda el aviso de la pasarela. La prueba recorre la tabla del enrutador, así que una ruta nueva entra sola. Si el
  alojamiento de DD-070 termina el https en un proxy, ese proxy se declara de confianza al desplegar (`trustProxies`);
  `SoloHttps` va después de `TrustProxies` para respetarlo.

## Decisiones de la implementación de la app

Las tomó Claude por delegación de Arthur (C-236) al implementar las tareas 2.2, 3.1, 3.2, 3.3 y 4.2, el 2026-11-22, en
la rama `feat/avisos-y-publicacion-movil`. Ninguna sale del contrato ni de la spec, y ninguna necesitó una cuenta.

### 1. Cada perfil de EAS se nombra a sí mismo (tarea 2.2)

- `movil/eas.json` tiene los perfiles `preview` y `production`. Cada uno deja su nombre en `PERFIL_DE_COMPILACION`, con
  su `env`, que EAS aplica al leer `app.config.ts` en la máquina y en la nube. Es la forma que documenta Expo para las
  variantes de una app; no depende de una variable propia de EAS.
- Sin `PERFIL_DE_COMPILACION`, la compilación es la de la máquina: el APK local (`herramientas/apk-local/compilar.ps1`) y
  Expo Go siguen como estaban. Un nombre que no es de `eas.json` detiene la configuración.

### 2. Solo https en `preview` y `production` (tarea 2.2, publicacion-E06)

- **La API:** con perfil, `EXPO_PUBLIC_API_URL` tiene que ser https o la configuración se detiene y lo dice. La
  dirección no va en `eas.json`: es la variable del entorno de EAS de cada perfil (punto 3).
- **Android:** `usesCleartextTraffic` en `false`, con `expo-build-properties`. Sin perfil no se toca: el APK local lo
  pone en `true` para hablar con el servidor de la máquina, como antes.
- **iPhone:** `NSAppTransportSecurity` sin cargas arbitrarias ni a la red local. Hacía falta: la plantilla de Expo
  deja `NSAllowsArbitraryLoads` en `true` y una excepción para la máquina local, y con eso una compilación de las tiendas
  habría permitido el tráfico sin cifrar.

### 3. Las variables de cada entorno de EAS (tarea 3.1, publicacion-E07)

`preview` toma las variables del entorno `preview` de EAS, y `production` las del entorno `production` (el campo
`environment` de cada perfil). `eas.json` no trae ninguna: ni la API, ni el identificador, ni el archivo de Firebase, ni
la llave de mapas (`movil/__tests__/publicacion/perfiles-de-compilacion.test.ts`). Se dan de alta con `eas env:create`
en la cuenta de cada entorno (ADR-0012): la de prueba puede ser la de EL PRESTADOR; la de producción es la del cliente.

| Variable | Tipo | Visibilidad en EAS | Qué lleva |
|---|---|---|---|
| `EXPO_PUBLIC_NOMBRE_APP` | texto | texto plano | El nombre del producto; hoy `{{MARCA}}` (DD-023) |
| `EXPO_PUBLIC_API_URL` | texto | texto plano | La API del entorno, por https (publicacion-E06); el entorno de prueba espera DD-070 |
| `APP_IDENTIFIER` | texto | texto plano | El identificador de la app; el definitivo espera DD-088 (publicacion-E08) |
| `GOOGLE_SERVICES_JSON` | archivo | secreta | El `google-services.json` del proyecto de Firebase del entorno (ADR-0008; DD-072) |
| `GOOGLE_MAPS_ANDROID_API_KEY` | texto | secreta | La llave de Google Maps de Android del entorno, restringida a la app (ADR-0009) |
| `EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID`, `EXPO_PUBLIC_GOOGLE_IOS_CLIENT_ID` | texto | texto plano | Los clientes OAuth de Google del entorno (ADR-0005; DD-072) |
| `EXPO_PUBLIC_AVISO_DE_PRIVACIDAD_URL`, `EXPO_PUBLIC_TERMINOS_URL` | texto | texto plano | Las páginas legales del servidor del entorno (DD-080) |

- **Las `EXPO_PUBLIC_*` no son secretas:** van dentro de la app y cualquiera las puede leer de ella.
- **Una variable secreta no se lee en la máquina** al preparar la compilación, solo en el servidor de EAS (grado E,
  `SIN VERIFICAR`, documentación de variables de entorno de EAS, consultada de memoria el 2026-11-22). Por eso
  `app.config.ts` no se detiene si falta el archivo de Firebase o la llave de mapas: en la máquina faltan, y en la nube
  están.
- **`preview` es la compilación interna** (`distribution: internal`) y en Android sale un APK que se instala sin tienda,
  como pide ADR-0012. TestFlight, en iPhone, llega con la tarea 3.4 (DD-017, DD-084).
- **Pendiente, sin cuenta todavía:** el identificador del proyecto de EAS (`extra.eas.projectId`) y su dueño (`owner`),
  que da `eas init` cuando exista la cuenta de Expo de cada entorno. Como cambian de una cuenta a otra, irán también
  desde el entorno.

### 4. El marcador detiene la compilación de producción (tarea 3.2, publicacion-E08)

- El identificador sigue saliendo de `APP_IDENTIFIER`, el mismo en iPhone y Android (base-E05).
- Con `production`, si `APP_IDENTIFIER` trae el marcador `{{MARCA}}`, en cualquier forma de escribirlo, la
  configuración se detiene con "Falta el identificador de la app", antes de que EAS suba nada.
- **Recomendación:** que el entorno `production` de EAS lleve `APP_IDENTIFIER={{MARCA}}` hasta que DD-088 dé el
  definitivo. Así ninguna compilación de producción sale con un identificador provisional.
- En `preview` y en local no se revisa: ahí el identificador es uno neutro de desarrollo, como el de `.env.example`.
  Que producción no lleve uno de esos neutros no lo pide el escenario, y queda en manos de quien llene su entorno.

### 5. La compilación de producción de Android (tarea 3.3, publicacion-E09)

- **Para Google Play, un paquete de aplicación** (`buildType: app-bundle`), firmado con la llave de subida que guarda
  EAS (`credentialsSource: remote`). La llave nunca entra al repositorio: `.gitignore` ya excluye `*.jks` y
  `*.keystore`, y `herramientas/ci/verificar_secretos.py` lo revisa.
- **La versión sube sola:** EAS lleva el número de cada compilación (`cli.appVersionSource: remote`) y `production` lo
  sube en cada una (`autoIncrement`). `app.config.ts` no fija `android.versionCode` ni `ios.buildNumber`; la versión que
  ve la persona sigue siendo `version`.
- **Sin minificar en `preview` y `production`.** El APK local ya lo apaga porque con R8 el release se cierra al abrir:
  desaparece la base interna de WorkManager, que llega con las dependencias de la ubicación
  (`herramientas/apk-local/compilar.ps1`, C-236). Con el mismo ajuste en `expo-build-properties`, la compilación de las
  tiendas sale como el APK que ya se probó. Cuesta un paquete más grande. Volver a minificar con las reglas de
  conservación de WorkManager es una mejora para después, que necesita su prueba en un teléfono.
- **Lo que no se comprobó:** el registro de una compilación real de EAS, que espera la cuenta de Expo del entorno
  (ADR-0012) y, para `production`, el identificador definitivo (DD-088, punto 4).

### 6. El envío de Android queda en borrador (tarea 4.2, publicacion-E12)

- El perfil de envío `production` de EAS Submit, con el mismo nombre que el de la compilación, manda la compilación a la
  pista de producción de Google Play Console con `releaseStatus: draft`: no se publica sola, y se libera a mano el mismo
  día que en App Store, cuando las dos revisiones estén aprobadas.
- La llave de la cuenta de servicio de Google Play no va en `eas.json` (`serviceAccountKeyPath`): se sube a las
  credenciales de EAS de la cuenta del cliente cuando exista.
- **Puede cambiar con DD-017:** si la cuenta de Google Play es personal, la primera publicación pide antes la prueba
  cerrada de 14 días con 12 personas (R-029, grado E), y el primer envío iría a esa pista. El borrador se queda igual.
- El envío de iPhone no se configura: es publicacion-E11, bloqueado.

## Tablas y migración

No crea tablas: ninguna tabla de `vault/arquitectura/modelo-de-datos.md` nace aquí (ADR-0016). En prueba y en producción
corren las migraciones de los demás cambios, las mismas que la integración continua ya revisó con la prueba de esquema
de `modelo-de-datos` (modelo-E01 a modelo-E17) después de `migrate:fresh`; este cambio no agrega ninguna. Los triggers
`impedir_cambio()` de `pedidos`, `pedido_eventos`, `pagos` y `reembolsos` llegan con esas migraciones.

Usa, sin cambiar su forma:

| Tabla | Columnas que toca | Para qué | Nace en |
|---|---|---|---|
| `pagos` | `pasarela`, `id_pasarela_orden`, `estado`, `pagado_en` | publicacion-E14 y publicacion-E15: la orden en modo producción y el pago confirmado | `pago` |
| `eventos_pasarela` | `pasarela`, `id_pasarela_evento`, `procesado_en` | publicacion-E14 y publicacion-E15: el webhook de producción, procesado una sola vez | `pago` |
| `pedidos`, `pedido_eventos` | las del recorrido | publicacion-E15 y publicacion-E16: el pedido real y su seguimiento | `resumen-y-confirmacion` |
| `dispositivos` | `token_fcm`, `plataforma` | publicacion-E01: el aviso con el Firebase de cada entorno | `avisos` |

El valor de la columna `pasarela` en producción es `conekta`, la del contrato, o el de la elegida si se firma el cambio
(DD-079). La transacción real de publicacion-E15 queda en `pedidos`
y `pagos` como cualquier otra, y la base no deja borrarla (trigger `impedir_cambio()`). Si se devuelve, es una fila de
`reembolsos` (`pago`); cuándo se reembolsa sigue en DD-012, DD-016 y DD-103.

## Risks / Trade-offs

- [DD-070 sigue abierta] → no hay entorno de prueba, así que el hito alterno del pago 2 no se puede acreditar.
- [Sin cuenta de Apple no hay compilación de iPhone ni avisos en iPhone] → DD-017, DD-084 y DD-069. El D-U-N-S debía
  pedirse a más tardar el 2027-01-11 (DD-084).
- [Cuenta personal de Google Play] → la prueba cerrada de 14 días con 12 personas tiene que empezar al menos tres
  semanas antes del envío (R-029, grado E).
- [El identificador de la app no se puede cambiar después de publicar] → publicacion-E08 detiene la compilación con el
  marcador; el definitivo espera a la marca (DD-023).
- [Los revisores de Apple necesitan una cuenta de demostración y una app con contenido; el B.10 advierte rechazos por
  "información insuficiente sobre el modelo de negocio"] → DD-121.
- [Google Play pide además una página web para solicitar el borrado de la cuenta (grado E, consultado el 2026-11-24,
  SIN VERIFICAR; verificaciones, fila 4)] → la cubre la adenda del 2026-11-24 de ADR-0005: una página pública
  informativa en `textos-legales`, `/borrado-de-cuenta` (textos-legales-E06 y E07, tarea 1.4). Declarar su dirección en
  Play Console sigue en publicacion-E11, y qué datos se borran y cuáles se conservan sigue en DD-109.
- [Pasar de las cuentas de prueba de EL PRESTADOR a las del cliente antes de producción] → es la consecuencia negativa
  que ya acepta ADR-0012.
- [El plan gratuito de EAS da 15 compilaciones por plataforma, con cola lenta] → ADR-0002; el plan de pago es costo de
  desarrollo.

## Open Questions

1. **Cómo se despliega a prueba y a producción:** desde la integración continua, con un panel para el servidor o con el
   servicio administrado que se elija. Depende de DD-070 y **necesita ADR, o ampliar ADR-0012**.
2. **Los respaldos de la base.** El B.4 los presupuesta como "Respaldos periódicos de la base de datos"
   (`NSV-2026-001-v8.pdf:29:ANEXO B, Sección B.4`) y DD-070 los menciona, pero no están en el inventario funcional ni en
   ningún cambio. ¿Entran aquí? **Pregunta para Arthur.** *Resuelta por el concilio del 2026-11-24, por delegación de
   Arthur (C-236, C-245; `acta-2026-11-20-modelo-y-pasarela.md`, decisión 67):* sí, sin código, como un renglón más
   del mapa de cuentas de publicacion-E03. El B.4 los presupuesta como un servicio que contrata y paga EL CLIENTE
   (`NSV-2026-001-v8.pdf:6:Cláusula III.B`), a su nombre (`NSV-2026-001-v8.pdf:34:ANEXO B, Sección B.11`). No se
   agrega un escenario de restauración: ningún criterio del A.4 lo pide, y verificar que los respaldos permiten
   recuperar la información es del mantenimiento posterior (`NSV-2026-001-v8.pdf:40:ANEXO C, Sección C.5`), que los
   Non-Goals ya excluyen. La retención extendida, que el B.4 recomienda, la decide EL CLIENTE. Espera DD-070, que fija
   el alojamiento.
3. **La página web para pedir el borrado de la cuenta** (DD-109): ¿se amplía ADR-0005 y va en
   `perfil-y-soporte` o en `textos-legales`? *Resuelta por el concilio del 2026-11-24, por delegación de Arthur (C-236,
   C-245; `acta-2026-11-20-modelo-y-pasarela.md`, decisión 66):* en `textos-legales`, como una página pública
   informativa con el diseño base de la landing y una adenda de ADR-0005. Explica cómo pedir el borrado de la cuenta
   desde la app (perfil-usuario-E15) o por el correo de contacto y el WhatsApp de soporte de la configuración. Es solo
   informativa: no crea un flujo ni otra superficie de inicio de sesión, y no promete un borrado manual que el panel no
   tiene (el Módulo D #3 solo da consulta, historial y bloqueo, `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3`). No
   promete qué datos se borran ni cuáles se conservan: eso es de EL CLIENTE (`NSV-2026-001-v8.pdf:10:Cláusula X`) y
   sigue en DD-109. El requisito de Google Play es grado E (verificaciones, fila 4) y se confirma antes de construir la
   página. No desbloquea la tarea 4.1, que sigue esperando las cuentas (DD-017, DD-084), la ficha (DD-121) y los datos
   (DD-109).
4. **La ficha de cada tienda y las declaraciones de datos** (DD-121).
5. **¿El entorno de prueba pide no ser indexado por los buscadores?** La misma pregunta está en
   `medicion-de-la-landing`. *Resuelta por el concilio del 2026-11-24, por delegación de Arthur (C-236, C-245;
   `acta-2026-11-20-modelo-y-pasarela.md`, decisión 65),* con la adenda del 2026-11-24 de ADR-0012: el archivo de
   robots responde `Disallow: /` en todo entorno que no sea producción (medicion-E15, en `medicion-de-la-landing`).
