---
estado: vigente
grado: C
fuente: sesión de Arthur Naranjo con Claude Code del 2026-11-15 al 2026-11-17, en una misma conversación
fecha: 2026-11-17
dueño: Arthur Naranjo
---
# Acta — 2026-11-17 — Fase 7: formas de trabajo

Participantes: Arthur Naranjo · Claude Code.
Fase(s) de la sesión: 6 (pendientes) y 7.

Continúa la numeración de [[acta-2026-11-13-cierre-de-la-fase-6]], que llega hasta C-154. Las declaraciones son de
Arthur. C-155 a C-158 son del 2026-11-17; C-159 y C-160 son del 2026-11-15 y se asentaron al final.

## Contexto del 2026-11-15

Arthur preguntó qué hay que instalar para el entorno local, si Laravel conviene cuando la app es React Native, y por
otras opciones de hosting: el cPanel de GoDaddy, Vercel y Next.js. Pidió además un PDF para transmitirle a Nadir lo
que se necesita de su parte. El PDF vive en `notas-locales/`, que no se versiona. Los datos externos de esa
conversación están en [[verificaciones-fase-6]], sección del 2026-11-15.

## Declaraciones

- C-155. **C (Arthur, 2026-11-17).** "Ok, pues por mí entonces, como temporalmente será en local, ya después lo
  demás; pues adelante, termina la fase y continuemos." Con esto se abre y se cierra la Fase 7, con las propuestas
  de Claude, y el hosting sigue pendiente (C-154).
- C-156. **C (Arthur, 2026-11-17).** El código vive en un repositorio aparte: eligió "Repo aparte para el código",
  la opción recomendada, contra tenerlo todo en este repositorio. Queda en [[repositorios-ramas-y-revision]].
- C-157. **C (Arthur, 2026-11-17).** Sprint: "menos de la semana, pero dejémoslo en 1 semana, pero ya vamos
  atrasados". Queda en [[sprint-y-sesion-diaria]].
- C-158. **C (Arthur, 2026-11-17).** El trabajo llega a Nadir "por sprint", la opción recomendada, contra un pull
  request por cada cambio. Queda en [[repositorios-ramas-y-revision]].
- C-159. **C (Arthur, 2026-11-15).** "Laravel Cloud es la primera vez que escucho de él, ya que la app web donde yo
  gestiono el servicio es el cPanel de GoDaddy." Arthur conoce cPanel, no Laravel Cloud. Toca a DD-070.
- C-160. **C (Arthur, 2026-11-15).** Tras comparar Next.js con Laravel (65 contra 96 con la matriz vigente): "Ok,
  bueno, entonces dame los mejores host y los host de bajo costo." El ADR-0003 no cambia.

## Decisiones tomadas en la sesión

| # | Decisión | Opciones consideradas | Quién decide | Nota resultante |
|---|---|---|---|---|
| 1 | El código va en un repositorio aparte, que se transfiere al cliente tal cual al final | Todo en este repositorio | Arthur (C-156) | [[repositorios-ramas-y-revision]] |
| 2 | Sprints de una semana, de lunes a domingo, con unas 11 h comprometidas | Dos semanas | Arthur (C-157) | [[sprint-y-sesion-diaria]] |
| 3 | El trabajo llega a Nadir con un pull request por sprint | Un PR por cambio | Arthur (C-158) | [[repositorios-ramas-y-revision]] |
| 4 | El ciclo openspec + TDD, la Definition of Ready, la Definition of Done, la trazabilidad, la integración continua y el registro de horas, como los propuso Claude | — | Arthur (C-155) | `formas-de-trabajo/` |
| 5 | Los protocolos con el cliente quedan en borrador hasta que Nadir los apruebe | Aplicarlos ya | El método: lo de EL CLIENTE es de Nadir (C-133) | [[protocolos-con-el-cliente]]; DD-074 |
| 6 | **La Fase 7 se cierra con pendientes**: el repositorio de código (DD-073), los protocolos (DD-074) y el espejo a Notion (DD-075) | Esperar a cerrarlos | Arthur (C-155), con el método (puertas blandas) | [[estado-del-proyecto]] |
| 7 | El ADR-0003 (Laravel) se mantiene después de compararlo con Next.js | Next.js | Arthur (C-160) | [[verificaciones-fase-6]] |

## Cambio de forma

`cliente/comunicaciones/2026-09-20-respuesta-al-feedback-del-manual.md` decía `estado: enviada`, fuera del
vocabulario de `meta/convenciones.md`. Pasa a `vigente`; el envío se conserva en su campo `enviada:`. Así la
integración continua del vault pasa desde el primer día.

## Preguntas abiertas que salieron de la sesión

| # | Pregunta | Bloquea | Fila en `deuda-de-decision.md` |
|---|---|---|---|
| 1 | ¿Las cuentas de prueba (Firebase, Google Cloud, el sandbox de Conekta y Expo) las abre Nadir o Arthur? | Integrar avisos, mapas y pagos en local | DD-072 |
| 2 | ¿En qué cuenta de GitHub nace el repositorio de código, y cuándo se transfiere al cliente? | El sprint 1 y la integración continua del código | DD-073 |
| 3 | ¿Nadir aprueba los protocolos con el cliente? ¿Cuál es el correo de Alfredo? | Los avisos formales por correo y el reporte semanal | DD-074 |
| 4 | ¿Se monta el espejo a Notion, y en qué espacio? | El espejo | DD-075 |

## Datos grado E de la sesión

No suben a C. Están en [[verificaciones-fase-6]], sección del 2026-11-15: GoDaddy, Hostinger, Vultr, los paneles
para un VPS, Oracle, Vercel, Render, Hetzner y Next.js.
