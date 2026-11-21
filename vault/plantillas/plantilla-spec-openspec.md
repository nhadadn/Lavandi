---
estado: superseded
grado: D
fuente: 
fecha: AAAA-MM-DD
dueño: 
spec: <capacidad>
cambio: <nombre-del-cambio-openspec>
---
# Spec — <capacidad>

> **Superseded el 2026-11-19.** Desde C-183 las specs viven en `openspec/` y se escriben con la herramienta:
> `openspec new change`, `openspec instructions` y las reglas de `openspec/config.yaml`. Ver
> `formas-de-trabajo/ciclo-openspec-tdd.md`. Esta plantilla se conserva como registro.

Alcance: qué obligación (A) y qué ADR (D) la sostienen. Un requisito sin cita no entra.
Formato openspec: `proposal.md` → `specs/<capacidad>/spec.md` → `design.md` (si hace falta) → `tasks.md`.

## Propuesta (proposal.md)
**Por qué.** Problema u obligación, con cita.
**Qué cambia.**
**Qué NO cambia.**

## Requisitos (specs/<capacidad>/spec.md)

### Requisito: <nombre>
El sistema DEBE <comportamiento observable>.
Trazabilidad: A → `NSV-2026-001-v8.pdf:pág:sección` · D → `ADR-000X`

#### Escenario: <nombre>
- **Dado** <estado inicial>
- **Cuando** <acción>
- **Entonces** <resultado observable y medible>

## Diseño (design.md) — solo si hay una decisión no trivial
Referencia al ADR que la sostiene. No se repite el ADR aquí.

## Tareas (tasks.md)
- [ ] Test que acredita el Escenario 1 (rojo)
- [ ] Implementación mínima que lo pone en verde
- [ ] Refactor con el test en verde

Cada tarea nombra el test que la cierra. Sin test, la tarea no se marca.

## Trazabilidad criterio → test
| Criterio de aceptación (A.4.x) | Escenario | Test (ruta) | Estado |
|---|---|---|---|
