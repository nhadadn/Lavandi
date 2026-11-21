---
estado: vigente
grado: D
fuente: prompt-maestro-01-fundacion-v5.md, Fase 7 (B); openspec/config.yaml (B); CLAUDE.md §2-bis y §3, regla 7 (B); acta-2026-11-17-fase-7-formas-de-trabajo.md C-155 y C-156 (C); acta-2026-11-19-normativa-y-arranque.md C-181 y C-183 (C)
fecha: 2026-11-19
dueño: Arthur Naranjo
---
# Ciclo openspec + TDD

Qué se escribe antes de qué, desde la idea hasta el código en `main`. Propuesta de Claude, aceptada por Arthur con
"adelante, termina la fase" (C-155). Se corrige aquí si Arthur pide cambios.

## Dónde vive cada cosa

| Pieza | Repositorio | Ruta |
|---|---|---|
| El cambio en curso: propuesta, requisitos con escenarios, diseño y tareas | Este | `openspec/changes/<cambio>/` |
| La spec aceptada de cada capacidad, ya fusionada | Este | `openspec/specs/<capacidad>/spec.md` |
| Los cambios cerrados | Este | `openspec/changes/archive/` |
| Las reglas que OpenSpec le pasa a Claude | Este | `openspec/config.yaml` |
| Las capturas y videos que acreditan criterios del A.4 | Este | `vault/evidencias/<capacidad>/` |
| El código y sus tests | Este mismo (C-169) | `movil/` y `servidor/` |
| La tabla que liga cada criterio del A.4 con su test | Este | [[trazabilidad-criterio-test]] |

**OpenSpec está activo en el repositorio** desde el 2026-11-19 (C-183), con la versión 1.3.1. Antes, la fuente de
las specs era `vault/specs/` y la herramienta era opcional; eso quedó atrás (decisión 3 del acta
[[acta-2026-11-19-normativa-y-arranque]]). Lo que cambia:

- **La spec ya no está en el vault.** Obsidian y el espejo de Notion no la ven; se lee en el repositorio.
- **Las palabras clave de la herramienta van en inglés,** porque su validador las exige: `## Why`,
  `## What Changes`, `### Requirement:`, `#### Scenario:` y la palabra `MUST` en cada requisito. El texto va en
  español: "La app DEBE (MUST)…", y los escenarios usan **DADO / CUANDO / ENTONCES**.
- **Las reglas del proyecto viajan con la herramienta.** `openspec/config.yaml` lleva la NORMATIVA (§2-bis) y las
  reglas de cada pieza; `openspec instructions` se las entrega a Claude cada vez.
- **La plantilla `plantilla-spec-openspec.md` queda `superseded`.**

**Los comandos.** En Claude Code: `/opsx:propose` (crea el cambio con todas sus piezas), `/opsx:explore` (pensar
antes de proponer), `/opsx:apply` (implementar las tareas) y `/opsx:archive` (cerrar el cambio y fusionar su spec).
En la terminal: `openspec list`, `openspec validate <cambio>` y `openspec archive <cambio>`.

## El orden

1. **Spec.** La escribe el agente `especificador` como un cambio de OpenSpec en `openspec/changes/<cambio>/`
   ([[equipo-de-agentes]]):
   - `proposal.md`: por qué, qué cambia, qué no y qué quedó fuera del contrato;
   - `specs/<capacidad>/spec.md`: los requisitos, cada uno con su cita, A (contrato) o D (ADR), y sus escenarios
     en forma **DADO / CUANDO / ENTONCES**, cada uno con un identificador: `<capacidad>-E01`, `<capacidad>-E02`….
     Un requisito sin cita no entra (NORMATIVA, `CLAUDE.md` §2-bis);
   - `design.md`: siempre, porque la herramienta lo pide antes de `tasks.md`; si no hay decisión no trivial, lo
     dice en pocas líneas y remite a sus ADRs;
   - `tasks.md`: cada tarea con el test que la cierra.

   `openspec validate <cambio>` sale limpio y Arthur aprueba el cambio antes de implementarlo.
2. **Definition of Ready.** La tarea entra al sprint solo si cumple [[definicion-de-listo-y-terminado]].
3. **Rama.** Una rama por cambio ([[repositorios-ramas-y-revision]]).
4. **Rojo, verde, refactor, por escenario:**
   - el test se escribe primero, lleva el identificador del escenario en su nombre y falla;
   - se escribe el código mínimo que lo pone en verde;
   - se limpia con el test en verde.
5. **Trazabilidad.** Si el escenario acredita un criterio del A.4, se anota en [[trazabilidad-criterio-test]] la
   ruta del test.
6. **Pull request.** La integración continua en verde, la revisión del agente revisor (Fase 8) y la aprobación de
   Arthur ([[integracion-continua]]).
7. **Cierre.** Las tareas del cambio quedan marcadas, cada una con su test; el cambio se archiva con
   `openspec archive <cambio>` y su spec se fusiona en `openspec/specs/<capacidad>/spec.md`, donde se escribe el
   `## Purpose` en español; se registran las horas en [[registro-de-horas]].

## Cómo se nombran los tests

El identificador del escenario va en el nombre del test, para que la tabla de trazabilidad lo encuentre con una
búsqueda:

- **Servidor:** `servidor/tests/Feature/<Capacidad>/` y, dentro, `test('pedidos-E03: la lavandería acepta el pedido', …)`.
- **App:** `movil/__tests__/<capacidad>/` y, dentro, `describe('pedidos-E03: …')`.

## Herramientas de prueba

Propuesta. Las versiones y la compatibilidad se confirman en el sprint 1, al crear los proyectos (E).

| Capa | Herramienta | Qué prueba |
|---|---|---|
| Servidor y panel | `php artisan test`, con PostgreSQL y PostGIS reales | Reglas del pedido, permisos por rol, cobros con Conekta simulados, avisos, indicadores y exportaciones |
| App | Jest con `jest-expo` y React Native Testing Library | Pantallas, estados y la consulta cada 30 s (ADR-0007) |
| Recorrido en el teléfono | Maestro, en Android y en iPhone | El recorrido completo del A.4.3: elegir lavandería, agendar, pagar y seguir el pedido |
| Landing | Playwright con Chromium, WebKit y Firefox, y Lighthouse CI | Las 12 secciones en los tres navegadores y el puntaje de velocidad del A.4.2 |

## Lo que no se hace

- **Código sin escenario,** ni escenario sin cita.
- **El test después del código.** La excepción es un *spike*: código desechable para aprender algo, que vive en una
  rama `spike/…`, nunca se fusiona y se borra. La prueba temprana en iPhone (DD-069) es un spike.
- **Decir "implementado" sin un test en verde** (CLAUDE.md §3, regla 7). Una spec no es una implementación.
