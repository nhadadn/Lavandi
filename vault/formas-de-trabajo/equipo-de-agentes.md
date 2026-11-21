---
estado: vigente
grado: D
fuente: prompt-maestro-01-fundacion-v5.md, Fase 8 (B); .claude/agents/ y .claude/skills/ (B); documentación de Claude Code sobre subagentes, skills, memoria y worktrees, consultada el 2026-11-18 (E); acta-2026-11-18-fase-8-instrumental.md C-161 a C-163 (C); acta-2026-11-18-entorno-y-mocks.md C-169 (C); skills externas revisadas el 2026-11-18 (E)
fecha: 2026-11-18
dueño: Arthur Naranjo
---
# Equipo de agentes

El instrumental de Claude para este proyecto (Fase 8). Son agentes nuevos y propios de este proyecto: no se
reutilizó ninguno de otros proyectos. Propuesta de Claude, aceptada por Arthur con "ok, adelante" (C-161).

## Dónde viven y cómo se cargan

- **Los agentes** están en `.claude/agents/` y **las skills** en `.claude/skills/`. Son método de EL PRESTADOR
  y no se ceden al cliente (A, Cláusula IX): al entregar, solo salen `movil/` y `servidor/`.
- **Todo vive en este repositorio,** el código incluido (C-169). Las sesiones arrancan aquí, y Claude Code carga
  los agentes, las skills y el `CLAUDE.md` sin pasos extra.
- **Los dos implementadores trabajan en un worktree aislado** (`isolation: worktree`). Así pueden trabajar en
  paralelo sin pisarse. El archivo `.worktreeinclude` de la raíz les copia los `.env` locales.

## Los seis agentes

| Agente | Qué **decide** | Su carpeta | Por qué hace falta |
|---|---|---|---|
| `especificador` | Qué requisitos y escenarios tiene una capacidad, con qué cita, y si algo está fuera del alcance | `openspec/changes/` | El ciclo empieza por la spec, y "sin cita no entra" es lo que detecta las Órdenes de Cambio antes de construir de más (A, Cláusula IV) |
| `implementador-servidor` | Cómo se implementa un escenario en el servidor, el panel o la landing | `servidor/` | Es la mitad más grande del sistema (ADR-0003). Va separado de la app para trabajar en paralelo |
| `implementador-movil` | Cómo se implementa un escenario en la app | `movil/` | Otra tecnología (ADR-0002) y otras reglas: tokens generados y pruebas en teléfono real |
| `revisor` | Si un PR se puede fusionar | Ninguna: no edita | Con una sola persona, sin revisión nadie más vería el código. Vigila lo que más se puede equivocar: la autorización por rol, tenencia y plaza |
| `verificador-de-aceptacion` | Si un criterio del A.4 avanza de estado, y si un entregable se puede notificar | La tabla de trazabilidad y las carpetas `evidencias/` | La aceptación mueve los pagos 2 y 3. Notificar algo sin probar le da al cliente una observación fundada |
| `redactor-cliente` | Qué mensaje corresponde y cómo se dice | Borradores en `vault/cliente/` | El contrato exige el reporte semanal, los avisos con 48 h, la notificación de entregas y los requerimientos escritos (Cláusulas III, IV y XII). **Nunca envía:** envía Nadir |

Cada agente dice, en su archivo, sus entradas, sus salidas y **cuándo responde "no sé" o "falta contexto"**. Todos
comparten una regla: **ninguno declara algo implementado sin evidencia grado B con su test** (CLAUDE.md §3,
regla 7).

**Lo que no tiene agente propio, y por qué:**

- **La investigación de datos externos.** El agente general de Claude Code basta; lo propio del proyecto (etiquetar
  grado E con fecha y fuente) está en `CLAUDE.md`.
- **Las actas, el estado y la deuda.** Son procedimientos, no decisiones: van como skills.

## Las cuatro skills

| Skill | Se invoca | Por qué hace falta |
|---|---|---|
| `arranque-de-sesion` | A las 22:00, o con "arrancamos" | Reparte el trabajo entre los agentes en paralelo en los primeros 10 minutos (C-134) |
| `cierre-de-sesion` | A las 23:50, o con "cerramos" | Registra horas para recalibrar S-006 y deja el vault al día para arrancar mañana sin reconstruir nada |
| `cierre-de-sprint` | Los domingos, o con "cierra el sprint" | El PR semanal hacia Nadir (C-158) y el reporte semanal al cliente (A, Cláusula III) |
| `asentar-acta` | Cuando Arthur o Nadir declaran o deciden algo | Sin acta no hay grado C (CLAUDE.md §3) |

## Skills externas

Los agentes del proyecto deciden; las skills externas les dan conocimiento del stack. Arthur pidió buscarlas
(C-162) y eligió tres (C-163).

**Cuándo se instalan:** en el sprint 1, en `servidor/` o en `movil/`, cada una en una rama aparte y leída antes de
usarse. Los pasos están en [[entorno-local]].

| Skill | Publica y licencia (E) | Para quién | Con qué ajustes |
|---|---|---|---|
| **Laravel Boost**, con la skill `filament-development` de Filament 5 | Laravel y Filament; MIT | `implementador-servidor` y `revisor` | Sin registro de reglas (`BOOST_RULES_ENABLED=false`), porque compite con el vault. Tinker apagado. **El TDD del proyecto va por encima de su guía de tests,** que admite escribirlos después del código |
| **Plugin `expo`**: 24 skills de Expo Router, UI nativa, dev client, upgrades y EAS | Expo; MIT | `implementador-movil` | Se leen antes sus scripts de Node y los comandos de `eas-cli` que preautoriza. Su MCP pide cuenta de Expo, y la búsqueda en la documentación es de pago |
| **`react-native-testing`** de Callstack, que mantiene React Native Testing Library, y el **MCP de Maestro**, que viene en su CLI | Callstack, MIT; Maestro, en su CLI | `implementador-movil` y `verificador-de-aceptacion` | De Callstack solo esa skill. `SIN VERIFICAR`: si sus symlinks funcionan en Windows |

**Revisadas y descartadas** (E, 2026-11-18):

| Qué | Por qué |
|---|---|
| `superpowers` | Un hook inyecta su método en cada sesión, y ofrece fusionar a la rama base en local: choca con "nunca a `main`" |
| `security-guidance` | Sus hooks mandan cada diff al modelo. Ya existe `/security-review` |
| `webapp-testing` de Anthropic | Pide correr un script sin leerlo |
| `pr-review-toolkit` | Opcional; el `revisor`, `/code-review` y `/security-review` ya lo cubren. Arthur no lo eligió (C-163) |
| El MCP de Conekta | Sin licencia, y operaría pagos reales |
| Las skills de Software Mansion | No declaran licencia, y su foco (animaciones, audio) no es este |
| Skills comunitarias de Maestro | Sin licencia y poco mantenidas; el MCP oficial basta |

Fuentes: https://laravel.com/docs/13.x/boost · https://github.com/laravel/boost ·
https://github.com/filamentphp/filament (skill `filament-development`) · https://github.com/expo/skills ·
https://github.com/callstackincubator/agent-skills · https://docs.maestro.dev/get-started/maestro-mcp ·
https://github.com/anthropics/claude-plugins-official · https://github.com/obra/superpowers

## Protocolo de traspaso

Cada agente entrega su trabajo con este reporte. Va en su respuesta y, si hay PR, en la descripción del PR:

```
## Traspaso de <agente> — AAAA-MM-DD
- Tarea y escenarios: <capacidad>-E01, …
- Rama y commits:
- Evidencia grado B: por escenario, la ruta del test y la salida del comando, en rojo y en verde
- Decisiones que tomé:
- Lo que no hice, y por qué:
- No sé / falta contexto:
- Horas:
```

| De → a | Qué entrega | Qué valida quien recibe, antes de aceptar |
|---|---|---|
| `especificador` → implementador | La ruta de la spec, los escenarios de la tarea y sus bloqueos | **Arthur aprobó la spec** (`vigente`). Cada escenario tiene Dado, Cuando y Entonces, y su cita. Ninguno de la tarea lleva `SIN RESPUESTA`. Se cumple la Definition of Ready |
| implementador → `revisor` | La rama, el PR y el reporte con la evidencia | La rama corre. Cada escenario tiene su test, con la salida en rojo y en verde. La integración continua está en verde. Si falta algo, **no recibe**: lo devuelve |
| `revisor` → Arthur | El dictamen y los hallazgos | Arthur lee los hallazgos graves y fusiona, o lo devuelve |
| `main` → `verificador-de-aceptacion` | El commit fusionado y las specs que toca | El test existe en `main`, pasa cuando lo corre él mismo y prueba el criterio. Para acreditar, la evidencia es del entorno correcto y tiene fecha |
| `verificador-de-aceptacion` → `redactor-cliente` | Las filas acreditadas y lo que falta | Solo lo acreditado se reporta como hecho al cliente |
| cualquiera → Arthur | Un "no sé" o un "falta contexto", con la pregunta concreta | Arthur responde, o la pregunta va como fila a la deuda de decisión |

**La regla de rechazo:** quien recibe un traspaso sin su evidencia no lo completa por su cuenta ni lo da por bueno.
Lo devuelve, diciendo qué falta.
