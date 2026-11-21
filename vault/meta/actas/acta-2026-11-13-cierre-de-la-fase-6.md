---
estado: vigente
grado: C
fuente: sesión de Arthur Naranjo con Claude Code del 2026-11-13
fecha: 2026-11-13
dueño: Arthur Naranjo
---
# Acta — 2026-11-13 — Cierre de la Fase 6

Participantes: Arthur Naranjo · Claude Code.
Fase(s) de la sesión: 6.

Continúa la numeración de [[acta-2026-11-12-traspaso-del-proyecto]], que llega hasta C-148. Las declaraciones son de
Arthur, que toma las decisiones técnicas desde el traspaso (C-132 y C-143).

## Declaraciones

Claude redactó los ADR-0005 a 0014 con su recomendación y los puntuó con la matriz `vigente` (C-142), con datos
grado E consultados el 2026-11-12 y el 2026-11-13. Arthur eligió:

- C-149. **C (Arthur).** Inicio de sesión con Google: **"nitro-google-signin"**, la opción recomendada, contra
  Universal Sign In, que es de pago. Queda en [[ADR-0005-autenticacion]].
- C-150. **C (Arthur).** Landing: **"Laravel + plantilla con licencia"**, la opción recomendada. Las otras eran Laravel
  sin plantilla y un sitio estático aparte. Queda en [[ADR-0011-landing]].
- C-151. **C (Arthur).** Correo automático: **"pendiente"**. Las opciones eran Resend, la recomendada, y Amazon SES.
  [[ADR-0014-correo-automatico]] queda en `borrador` (DD-071).
- C-152. **C (Arthur).** "Apruebo los siete", como estaban en la tabla que presentó Claude:
  - [[ADR-0006-almacenamiento-de-imagenes]];
  - [[ADR-0007-tiempo-real]];
  - [[ADR-0008-avisos-push]];
  - [[ADR-0009-mapas]], que incluye las versiones mínimas iOS 18 y Android 11 para BR-42;
  - [[ADR-0010-pasarela-de-pago]];
  - [[ADR-0012-entornos]];
  - [[ADR-0013-adaptador-de-tokens]].
- C-153. **C (Arthur).** "Continúa y termina con eso y haces push para que se guarde." Claude sube la rama
  `fase-6/stack` al fork de Arthur (`origin`, `AsberNaranjo/Lavandi`). Al repositorio de Nadir no se sube nada: eso
  va por pull request y lo hace Arthur. El push no se pudo hacer desde la sesión porque el control de permisos de
  Claude Code lo bloqueó. Lo sube Arthur.
- C-154. **C (Arthur).** "El hosting queda pendiente porque ya sería consultarlo con Nadir; de momento sería host
  local." La comparación entre DigitalOcean y Laravel Cloud de DD-070 es un dato, no una decisión. Mientras no se
  decida, el backend corre solo en la máquina de Arthur, que es el entorno local del `ADR-0012`.

## Decisiones tomadas en la sesión

| # | Decisión | Opciones consideradas | Quién decide | Nota resultante |
|---|---|---|---|---|
| 1 | Correo y contraseña para todos; Google con `react-native-nitro-google-signin` (Credential Manager); Apple en iPhone; tokens de Sanctum | Universal Sign In, la versión gratuita antigua, `expo-auth-session` | Arthur (C-149) | ADR-0005 |
| 2 | La landing vive en el mismo Laravel y se edita desde Filament. La plantilla se compra solo si su licencia permite entregarla | Laravel sin plantilla, sitio estático | Arthur (C-150) | ADR-0011 |
| 3 | El proveedor de correo queda pendiente | Resend, Amazon SES | Arthur (C-151) | ADR-0014 en borrador; DD-071 |
| 4 | Siete ADRs aprobados como se propusieron | — | Arthur (C-152) | ADR-0006 a 0010, 0012 y 0013 |
| 5 | **La Fase 6 se cierra con pendientes**: matriz `vigente` y 13 de los 14 ADRs `vigente`. Faltan el correo (DD-071), el hosting (DD-070) y la prueba en iPhone (DD-069) | Esperar a cerrar los pendientes | Arthur (C-153), con el método (puertas blandas) | [[estado-del-proyecto]] |
| 6 | El hosting se decide con Nadir. Mientras tanto, todo corre en local | Decidirlo en la sesión | Arthur (C-154) | DD-070; ADR-0012 |

## Preguntas abiertas que salieron de la sesión

| # | Pregunta | Bloquea | Fila en `deuda-de-decision.md` |
|---|---|---|---|
| 1 | ¿Resend o Amazon SES para el correo automático? | ADR-0014 | DD-071 |
| 2 | ¿Laravel Cloud admite PostGIS? No está confirmado (E, 2026-11-13). DigitalOcean sí lo admite en su edición Standard | DD-070 | DD-070 |

## Datos grado E de la sesión

No suben a C. Están en [[verificaciones-fase-6]], sección del 2026-11-13:

- PostGIS en Laravel Cloud y en DigitalOcean.
- Google retiró el inicio de sesión antiguo de Android.
- Los límites de Resend y el sandbox de SES.
- Las licencias de Tailwind Plus y ThemeForest.
