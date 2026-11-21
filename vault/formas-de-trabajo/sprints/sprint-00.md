---
estado: borrador
grado: mixto
fuente: plantillas/plantilla-sprint.md (B); sprints/plan-de-sprints.md (B); NSV-2026-001-v8.pdf:16:, :19: y :20:ANEXO A, Sección A.3, y :21:A.4.3 (A); ADR-0002, ADR-0003, ADR-0004, ADR-0005, ADR-0009 y ADR-0012 (D); acta-2026-11-12-traspaso-del-proyecto.md C-134, acta-2026-11-18-fase-8-instrumental.md C-163, acta-2026-11-18-entorno-y-mocks.md C-166 y C-170, acta-2026-11-19-normativa-y-arranque.md C-182 y C-183 (C); supuestos.md S-006 (S)
fecha: 2026-11-19
dueño: Arthur Naranjo
---
# Sprint 00 — del 2026-11-19 al 2026-11-22

Sprint corto de arranque, de jueves a domingo (hito H0 de [[plan-de-sprints]]). Deja creados `servidor/` y `movil/`,
con la integración continua en verde, y la spec del registro lista para el lunes.

**Comprometido:** 7 h. **Capacidad:** unas 8 h, cuatro sesiones de 2 h (C-134). Cuenta en el plan como 6 h de base
técnica más 1 h de la spec del registro, que es parte de F-A02.

## Tareas comprometidas

| # | Tarea (spec y escenarios) | Función (inventario) | Tamaño | Agente | Rama | Estado |
|---|---|---|---|---|---|---|
| 1 | Spec del cambio `base-tecnica` en `openspec/changes/base-tecnica/`: propuesta, requisitos con cita y escenarios. Cubre que la API responde con PostGIS en local, que la app arranca, que el nombre del producto sale de la configuración y que la app pide iOS 18 y Android 11 como mínimo (A43-1, ADR-0009) | F-T04, F-T05, F-T12 | S (1 h) | `especificador` | la de trabajo del vault | en revisión |
| 2 | Crear `servidor/`: Laravel 13, Filament 5 y Sanctum (ADR-0003), conectado al PostGIS local (ADR-0004, C-166), con los tests de `base-tecnica` en verde y `.env.example` sin secretos | F-T04, F-T05 | S (2 h) | `implementador-servidor` | `chore/base-servidor` | por hacer |
| 3 | Crear `movil/`: Expo SDK 58 (ADR-0002), Jest con `jest-expo` y React Native Testing Library, con los tests de `base-tecnica` en verde | F-T12 | S (2 h) | `implementador-movil` | `chore/base-movil` | por hacer |
| 4 | Instalar las skills externas de C-163: Laravel Boost con `filament-development` (`BOOST_RULES_ENABLED=false`), el plugin `expo`, `react-native-testing` de Callstack y el MCP de Maestro ([[equipo-de-agentes]]) | — (instrumental) | S (1 h) | Arthur, con Claude | `chore/skills-externas` | por hacer |
| 5 | Spec del cambio `registro-e-inicio-de-sesion` en `openspec/changes/`, aprobada por Arthur el domingo 22 | F-A02, F-T07, F-T09, F-T13 | S (1 h) | `especificador` | la de trabajo del vault | en revisión |

Estados: `por hacer` · `en curso` · `en revisión` · `terminada` · `sale del sprint`.

**Orden:** 1 → 2 y 3 en paralelo, cada agente en su worktree → 4, que necesita `servidor/` para Laravel Boost. La 5
corre en paralelo desde el jueves.

## Definition of Ready

| # | Lo que cumple | Lo que le falta |
|---|---|---|
| 1 y 5 | Son specs: la DoR se aplica a las tareas que las implementan | — |
| 2 y 3 | Tamaño S; sin filas abiertas de la deuda (DD-072, cerrada por C-170); sin pantallas que diseñar; criterio que preparan: A43-1 | **La spec de la tarea 1, aprobada por Arthur.** Las versiones de PHP, Node y PostGIS de la integración continua se confirman al crear los proyectos (`.github/workflows/ci.yml`, grado E) |
| 4 | Las cuatro skills ya se eligieron (C-163) | `servidor/` creado. Si los symlinks de la skill de Callstack fallan en Windows, se anota (`SIN VERIFICAR` en [[equipo-de-agentes]]) |

## Lo que entró a mitad del sprint, y lo que salió a cambio

- **2026-11-19:** las specs de las tareas 1 y 5 quedaron escritas y validadas con `openspec validate`, junto con las
  otras 24 del plan (C-186). Esperan la aprobación de Arthur. Antes de las tareas 2 y 3 hacen falta DD-088 (el
  identificador de la app) y DD-089; antes del sprint 01, DD-086, DD-087, DD-090 y DD-071.

## Cierre

- **Horas:** 7 comprometidas vs. reales, en [[registro-de-horas]].
- **Criterios del A.4 que cambiaron de estado:** A43-1, si su test queda en verde.
- **PR semanal hacia Nadir:** el primero (C-158).
- **Qué cambiamos:**
- **Si la tarea 5 no queda aprobada el domingo,** el sprint 01 arranca con la spec y pierde unas 2 horas.
