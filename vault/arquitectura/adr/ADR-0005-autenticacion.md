---
estado: vigente
grado: D
fuente: acta-2026-11-13-cierre-de-la-fase-6.md C-149; restricciones-no-negociables.md RN-08, RN-17 y RN-24 (A); brecha-ejecutivo-tecnico.md BR-14 y BR-48 (B); deuda-de-decision.md DD-053 (B); verificaciones-fase-6.md, filas 3 y 4 y secciones del 2026-11-12 y 2026-11-13 (E); ADR-0002 y ADR-0003 (D)
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
