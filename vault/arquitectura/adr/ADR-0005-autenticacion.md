---
estado: vigente
grado: D
fuente: acta-2026-11-13-cierre-de-la-fase-6.md C-149; restricciones-no-negociables.md RN-08, RN-17 y RN-24 (A); brecha-ejecutivo-tecnico.md BR-14 y BR-48 (B); deuda-de-decision.md DD-053 (B); verificaciones-fase-6.md, filas 3 y 4 y secciones del 2026-11-12 y 2026-11-13 (E); ADR-0002 y ADR-0003 (D); adenda del 2026-11-24: acta-2026-11-20-modelo-y-pasarela.md, decisión 66, del concilio del 2026-11-24, por delegación de Arthur (C-236, C-245) (C); NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3, NSV-2026-001-v8.pdf:23:ANEXO A, Sección A.7, NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3 y NSV-2026-001-v8.pdf:10:Cláusula X (A); support.google.com/googleplay/android-developer/answer/13327111, consultada el 2026-11-24, SIN VERIFICAR (E); deuda-de-decision.md DD-109 (B)
fecha: 2026-11-13
dueño: Arthur Naranjo
adr: ADR-0005
supersede:
---
# ADR-0005 — Inicio de sesión: correo y contraseña, Google con Credential Manager y Apple en iPhone

## Contexto

- **Qué pide el contrato (A):**
  - registro con correo y contraseña, con opción de Google Sign-In (RN-17);
  - contraseñas que nadie pueda recuperar, ni quien administra (RN-08);
  - verificación por SMS fuera del alcance (RN-24).
- **Qué exige Apple, regla 4.8 (E):** si se ofrece un inicio de sesión de terceros, hay que ofrecer otro equivalente
  que limite los datos a nombre y correo, permita ocultar el correo y no rastree (BR-14). Sign in with Apple lo
  cumple.
- **Qué exigen las dos tiendas (E):** borrar la cuenta desde la app (verificaciones, fila 4).
- **Google retiró el inicio de sesión antiguo de Android (E, 2026-11-13).** Lo quitó de `play-services-auth` 22.0.0
  el 2026-08-26 y manda usar Credential Manager. La versión gratuita de `@react-native-google-signin` se apoya en ese
  SDK antiguo.
- **Cómo llega cada rol a su módulo sigue abierto (DD-053).**
- **El stack:** app en React Native con Expo y API en Laravel.

## Criterios y pesos

Los de la matriz `vigente` (C-142). RN-17 obliga a ofrecer Google. Este ADR elige la librería.

## Alternativas evaluadas (Google en la app)

| Alternativa | Familiaridad (30) | Velocidad (25) | Proveedor (15) | Tiempo real (10) | Costo (10) | Multi-plaza (10) | **Total** |
|---|---|---|---|---|---|---|---|
| **A. `react-native-nitro-google-signin` 2.3.1** (MIT, Credential Manager, Expo 49+) | 3 | 4 | 3 | 5 | 5 | 5 | **77** |
| B. Universal Sign In, la versión de pago de `@react-native-google-signin` (79 USD al año personal, 249 USD por equipo) | 3 | 5 | 2 | 5 | 3 | 5 | **75** |
| C. `@react-native-google-signin` gratuita, con el SDK antiguo | 3 | 4 | 1 | 5 | 5 | 5 | **71** |
| D. `expo-auth-session` con Google | — | — | — | — | — | — | descartada |

**Por qué cada puntaje:**

- **D se descarta:** Google ya no acepta esquemas de URI propios en Android, y la guía de Expo dejó de proponerla (E).
- **C** depende de un SDK que Google ya quitó de la versión actual.
- **B** no deja clara la cesión de su licencia al cliente, y tiene costo anual.
- **A** es gratuita, MIT y usa el mecanismo vigente. De su mantenimiento no hay dato: se prueba primero.

## Decisión

**Cómo entra cada quien:**

- **Correo y contraseña** para todos los roles. Las contraseñas se guardan con el hash de Laravel (RN-08), y la
  recuperación es por correo con un enlace que vence (ADR-0014).
- **Google:** en la app con `react-native-nitro-google-signin`. `api` valida el `idToken` contra Google.
- **Apple:** solo en iPhone, con `expo-apple-authentication`. `api` valida el token de identidad con
  `socialiteproviders/apple`.

**Sesiones:** la app usa tokens de Sanctum, y el panel de Filament, sesión web con rol de administración.

**Borrar la cuenta** se puede desde la app, como piden las dos tiendas.

**Roles y entrada por módulo:** quedan en DD-053. La recomendación vigente es una cuenta y un rol en la v1, y socios
aprobados desde el Módulo D.

**Si `react-native-nitro-google-signin` no pasa la prueba temprana,** se usa Universal Sign In (opción B). Se intenta
con la licencia gratuita que ofrecen a clientes de EAS o, si no, con la licencia comprada a nombre del cliente.

La aprobó Arthur Naranjo el 2026-11-13 (`acta-2026-11-13-cierre-de-la-fase-6.md`, C-149), con la opción que recomendó Claude.

## Consecuencias

- **Positivas:**
  - Cumple RN-17, RN-08 y la regla 4.8 sin costo.
  - Usa el mecanismo que Google sostiene hoy.
- **Negativas:**
  - Depende de una librería de la comunidad.
  - El inicio de sesión de Apple en Android no se ofrece, porque la regla 4.8 es de la App Store. Si el cliente lo
    pide, es otra decisión.
- **Condiciona:**
  - La spec de registro y acceso (DD-053).
  - ADR-0012: los clientes OAuth de Google y Apple por entorno, a nombre del cliente.
  - ADR-0014: correo de recuperación.

## Criterio de reversión

Se pasa a la opción B si, en la prueba temprana, `react-native-nitro-google-signin` no logra iniciar sesión con
Google en un development build de Android y de iPhone en 2 sesiones de trabajo.

## Costo estimado de revertir

Bajo. El inicio de sesión con Google vive en un componente de la app, y `api` valida un `idToken` igual venga de la
librería que venga.

## Trazabilidad

- **Obligaciones:** O-13 a O-16 y O-20.
- **Restricciones:** RN-08, RN-17, RN-24, BR-14 y BR-48.
- **Pendientes:** DD-053.
- **ADRs relacionados:** ADR-0002, ADR-0003, ADR-0012 y ADR-0014.

## Adenda del 2026-11-21: la librería que valida el `idToken` de Google

El servidor valida el `idToken` de Google con `firebase/php-jwt` (licencia BSD-3): la firma contra las llaves públicas
de Google, guardadas en caché, más el emisor, el cliente, el vencimiento, el sujeto y el correo verificado
(registro-E16 y E17). Se descartó `google/apiclient`, que trae el cliente completo de las API de Google para usar una
sola función. La tomó Claude por delegación de Arthur Naranjo (C-236), con la recomendación de DD-130.

## Adenda del 2026-11-24: la página web para pedir el borrado de la cuenta

**Contexto.**

- El A.4.3 pide la app "entregada para su publicación" en las dos tiendas (`NSV-2026-001-v8.pdf:21:ANEXO A, Sección
  A.4.3`).
- **Qué exige Google Play (E, consultado el 2026-11-24, SIN VERIFICAR contra lo que pida la revisión):** una app que
  deja crear cuentas debe dar un camino dentro de la app para borrarla **y** un recurso web donde se pueda pedir el
  borrado de la cuenta y de sus datos, cuyo enlace se declara en el formulario de seguridad de los datos de Play
  Console. La página debe abrir sin errores, nombrar la app o al desarrollador como aparecen en su ficha y dejar
  pedir el borrado sin volver a la app (support.google.com/googleplay/android-developer/answer/13327111;
  `vault/arquitectura/verificaciones-fase-6.md`, fila 4).
- Este ADR solo cubre el borrado desde la app ("Borrar la cuenta se puede desde la app, como piden las dos tiendas"),
  que ya existe para la cuenta de usuario (perfil-usuario-E15).
- Qué datos se borran y cuáles se conservan es de EL CLIENTE, como responsable de los datos
  (`NSV-2026-001-v8.pdf:10:Cláusula X`; DD-109).
- El panel de administración solo da consulta, historial y bloqueo de usuarios, no un borrado
  (`NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3`, Módulo D #3).

**Decisión.**

- **Una página pública informativa del servidor, en `textos-legales`,** con el diseño base de la landing
  (`publico.layout`), en una dirección fija que se abre sin iniciar sesión.
- **Explica cómo pedir el borrado de la cuenta:** cómo borrarla desde la app, con las etiquetas reales de la pantalla
  "Mi perfil", y cómo pedirlo por el correo de contacto (`landing.contacto.correo`) y el WhatsApp de soporte
  (`soporte.whatsapp`) de la configuración. Sin un contacto configurado, en producción no muestra ninguno de ejemplo.
- **Qué no hace:**
  - no crea un flujo de borrado ni otra superficie de inicio de sesión;
  - no dice qué datos se borran o se conservan (DD-109, de EL CLIENTE);
  - no promete un borrado manual desde el panel, que solo tiene bloqueo (Módulo D #3);
  - no es el aviso de privacidad (RN-27).

**Alternativas descartadas.**

- **Una página con inicio de sesión que borra la cuenta.** Agrega otra superficie de autenticación sin cita.
- **No hacer nada.** Arriesga un rechazo de Google Play, con solo 2 intentos de corrección cubiertos
  (`NSV-2026-001-v8.pdf:23:ANEXO A, Sección A.7`).

**Consecuencias.**

- La dirección se declara en la ficha de Google Play al enviar la app (publicacion-E11). Esa declaración espera las
  cuentas y la ficha (DD-017, DD-084, DD-121) y no la toca esta adenda.
- Mientras el correo de contacto (DD-117) y el WhatsApp de soporte (DD-082) no lleguen, la página de producción solo
  dice cómo borrar la cuenta desde la app.
- Una petición de borrado que llegue por el correo o por el WhatsApp no tiene en la plataforma con qué cumplirse: la
  administración solo bloquea (Módulo D #3), y las cuentas de socio lavandería y de socio repartidor no tienen borrado
  en la app. Cómo la atiende EL CLIENTE sigue en DD-109; la página no promete un borrado manual.
- Si al borrar la cuenta se conservan datos, Google Play pide informar esa retención, por ejemplo en el aviso de
  privacidad (E, la misma consulta del 2026-11-24, SIN VERIFICAR). Qué se conserva es de EL CLIENTE (DD-109) y el
  aviso también (DD-080): la página no lo dice, y esa declaración tiene que estar en el aviso antes del envío si se
  conservan datos.
- La especifican textos-legales-E06 y textos-legales-E07 (`openspec/changes/textos-legales/`).

La decidió el concilio del 2026-11-24 por delegación de Arthur Naranjo (C-236, C-245), la parte de Arthur de DD-109,
en la decisión 66 de `acta-2026-11-20-modelo-y-pasarela.md`.
