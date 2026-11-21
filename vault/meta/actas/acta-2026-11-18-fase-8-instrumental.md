---
estado: vigente
grado: C
fuente: sesión de Arthur Naranjo con Claude Code del 2026-11-18
fecha: 2026-11-18
dueño: Arthur Naranjo
---
# Acta — 2026-11-18 — Fase 8: instrumental de Claude

Participantes: Arthur Naranjo · Claude Code.
Fase(s) de la sesión: 8.

Continúa la numeración de [[acta-2026-11-17-fase-7-formas-de-trabajo]], que llega hasta C-160.

## Declaraciones

- C-161. **C (Arthur).** "Ok, adelante", en respuesta a la propuesta de abrir la Fase 8. Con esto se abre y se
  cierra la fase con la propuesta de Claude: seis agentes, cuatro skills y el protocolo de traspaso.
- C-162. **C (Arthur).** "Si sientes que los agentes que haya en ese md, no sé si ya estaban predefinidos, pero si
  no terminan de encajar, busca otras skills que puedas obtener para tener mejores agentes." Claude respondió que
  los seis son nuevos y propios del proyecto, y buscó skills externas sin instalar nada.
- C-163. **C (Arthur).** De las skills externas, eligió tres: "Laravel Boost + Filament", "Plugin oficial de Expo"
  y "Testing de la app" (la skill `react-native-testing` de Callstack y el MCP de Maestro). No eligió
  `pr-review-toolkit`.

## Decisiones tomadas en la sesión

| # | Decisión | Opciones consideradas | Quién decide | Nota resultante |
|---|---|---|---|---|
| 1 | Seis agentes propios del proyecto: `especificador`, `implementador-servidor`, `implementador-movil`, `revisor`, `verificador-de-aceptacion` y `redactor-cliente` | Un agente de investigación y otro de actas, que quedaron como agente general y como skill | Arthur (C-161) | [[equipo-de-agentes]] |
| 2 | Cuatro skills: `arranque-de-sesion`, `cierre-de-sesion`, `cierre-de-sprint` y `asentar-acta` | — | Arthur (C-161) | [[equipo-de-agentes]] |
| 3 | Los agentes y las skills viven en el repositorio del vault, y las sesiones de desarrollo lo suman con `--add-dir` desde el repositorio de código | Copiarlos al repositorio de código | Arthur (C-161) | [[equipo-de-agentes]] |
| 4 | El repositorio de código trae su propio `CLAUDE.md`, porque el del vault no se carga con `--add-dir` | Definir la variable `CLAUDE_CODE_ADDITIONAL_DIRECTORIES_CLAUDE_MD` | Arthur (C-161) | `herramientas/esqueleto-repo-codigo/CLAUDE.md` |
| 5 | Los dos implementadores trabajan cada uno en su propio worktree, para ir en paralelo | Ramas en el mismo directorio | Arthur (C-161) | `.claude/agents/` |
| 6 | **La Fase 8 se cierra, y con ella el cimiento** (Fases 0 a 8). Desde aquí se puede escribir código del producto, en el repositorio de código y con `formas-de-trabajo/` | — | Arthur (C-161), con el método (puertas blandas) | [[estado-del-proyecto]]; CLAUDE.md §5 |
| 7 | Se adoptan Laravel Boost con la skill de Filament, el plugin oficial de Expo, la skill `react-native-testing` y el MCP de Maestro. Se instalan en el sprint 1, en el repositorio de código, cada uno en una rama aparte y leído antes de usarse | `pr-review-toolkit`, `superpowers`, `security-guidance`, `webapp-testing`, el MCP de Conekta, Software Mansion y skills comunitarias de Maestro | Arthur (C-163) | [[equipo-de-agentes]]; [[entorno-local]] |

## Preguntas abiertas que salieron de la sesión

Ninguna nueva. Para el sprint 1 siguen pendientes:
- el repositorio de código (DD-073);
- las cuentas de prueba (DD-072);
- la cuenta de Apple para iPhone (DD-069).

## Datos grado E de la sesión

De la documentación de Claude Code (code.claude.com), consultada el 2026-11-18 por un subagente:

- Con `--add-dir` se cargan los agentes y las skills del directorio sumado, pero no su `CLAUDE.md`. Para cargarlo
  hace falta la variable `CLAUDE_CODE_ADDITIONAL_DIRECTORIES_CLAUDE_MD=1`.
- Un agente puede pedir su propio worktree con `isolation: worktree` en su frontmatter.
- `.worktreeinclude` copia archivos ignorados por git, como los `.env`, a esos worktrees.

Las skills externas, revisadas el 2026-11-18 por un subagente de investigación. Están en [[equipo-de-agentes]],
sección "Skills externas", con sus fuentes: Laravel Boost v2.10.4, el plugin `expo` v1.15.4, la skill de Callstack
y el MCP de Maestro, además de las descartadas y su motivo.

Fuentes de la documentación de Claude Code: https://code.claude.com/docs/en/sub-agents.md · https://code.claude.com/docs/en/skills.md ·
https://code.claude.com/docs/en/memory.md · https://code.claude.com/docs/en/worktrees.md
