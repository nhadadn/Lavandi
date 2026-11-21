---
estado: vigente
grado: mixto
fuente: acta-2026-11-17-fase-7-formas-de-trabajo.md C-156 y C-158 (C); acta-2026-11-18-entorno-y-mocks.md C-169 (C); acta-2026-11-20-modelo-y-pasarela.md C-195 (C); NSV-2026-001-v8.pdf:10:Cláusula IX (A); ADR-0013 (D); CLAUDE.md §5, §7 y §9 (B)
fecha: 2026-11-18
dueño: Arthur Naranjo
---
# Repositorios, ramas, commits y revisión

## Un solo repositorio

**El código vive en este mismo repositorio,** junto al vault. Arthur lo decidió el 2026-11-18: "no hay que generar
uno nuevo; hay que seguir en este de Lavandi" (C-169). Eso reemplaza a C-156, que lo ponía aparte.

El repositorio es `nhadadn/Lavandi`, el de Nadir. Arthur trabaja en su fork, `AsberNaranjo/Lavandi`: una copia en
su cuenta, conectada con la de Nadir, desde la que le manda los cambios por pull request.

**Estructura:**

```
vault/                     el método, las specs, los ADRs y la trazabilidad
legal/                     el contrato
herramientas/              las herramientas del método, el script de tokens y las verificaciones de CI
mocks/                     las pantallas navegables y las propuestas visuales (C-167, C-175)
movil/                     app de React Native con Expo (ADR-0002)
servidor/                  Laravel con Filament: API, panel y landing (ADR-0003, ADR-0011)
```

**Los tokens** (lo que el ADR-0013 dejó para esta fase): el script vive en `herramientas/tokens/`. Lee directo
`vault/design-system/tokens.json` y genera `theme.ts` dentro de `movil/` y las variables CSS dentro de `servidor/`.
No hace falta copiar el archivo.

**Al entregar, se separa el código.** Al pagarse el contrato completo se ceden el código, los diseños y los
contenidos de la landing; EL PRESTADOR conserva sus metodologías y herramientas (A, Cláusula IX). El vault tiene
además notas de negocio, riesgos y el contrato. Por eso al cliente se le entregan **solo `movil/` y `servidor/`**,
en un repositorio nuevo, con su historial (`git filter-repo --path movil --path servidor`) o sin él. Nunca este
repositorio completo.

## Ramas

| Rama | Para qué | Regla |
|---|---|---|
| `main` | Lo terminado | Siempre en verde. Solo entra por pull request |
| `feat/<capacidad>-<cambio>` | Una función nueva. Ejemplo: `feat/pedidos-aceptar` | Una por cambio de la spec |
| `fix/<capacidad>-<defecto>` | Un defecto | — |
| `chore/…`, `ci/…`, `docs/…` | Mantenimiento, integración continua y documentación | — |
| `spike/…` | Código desechable para aprender algo | **Nunca se fusiona.** Se borra al terminar |

**Los commits de Claude nunca van directo a `main`:** siempre en una rama, y la fusión la hace Arthur.

## Commits

Para el código, **Conventional Commits con la descripción en español**:

```
tipo(alcance): qué cambia, en presente

Escenario: pedidos-E03 · Spec: openspec/changes/pedidos/
```

- **Tipos:** `feat`, `fix`, `test`, `refactor`, `docs`, `chore`, `ci`, `build`. Son términos técnicos y se dejan
  en inglés (CLAUDE.md §6).
- **Alcance:** la capacidad de la spec: `pedidos`, `pagos`, `avisos`, `panel`, `landing`, `acceso`…
- **Cuerpo:** el escenario y la spec que atiende el commit.

Para el vault se siguió la convención del cimiento mientras duraron las fases (`Fase N: …` y
`wip(fase-N): …`). Después:

- `vault: <qué cambió>` para el trabajo diario;
- `openspec: <qué cambió>` para las specs;
- `Sprint N: <qué cerró>` al cierre de cada sprint.

**Sin atribución.** Ningún commit, pull request ni nota de avance lleva `Co-Authored-By: Claude`, "Generated with
Claude Code" ni otra firma de la herramienta (Arthur, 2026-11-20). El autor es el que tiene configurado git.

**Cómo se hace en la práctica:** la skill `git-lavandi` (`.claude/skills/git-lavandi/SKILL.md`) lleva el
procedimiento completo: revisar el estado, la rama, qué entra al commit, las verificaciones, el mensaje, el push al
fork, la liga del PR, traer lo de Nadir y resolver conflictos.

## Revisión y cómo llega el trabajo a Nadir

Arthur decidió el 2026-11-17 que el trabajo llega a Nadir **por sprint** (C-158).

1. **Cada cambio** va en su rama del fork de Arthur y abre un pull request hacia el `main` de ese mismo fork.
2. **Se fusiona** con tres condiciones: la integración continua en verde, sin hallazgos abiertos del agente revisor
   (Fase 8) y con la aprobación de Arthur.
3. **Al cerrar el sprint** (domingo), Arthur abre **un solo pull request** del `main` de su fork hacia el `main`
   del repositorio de Nadir. La descripción del PR lista las specs, los escenarios y los criterios del A.4 que
   avanzaron en la semana.
4. **La fusión en el repositorio de Nadir** la hace Nadir o quien él autorice.

**Aceptar el PR no es aceptar el entregable.** La aceptación contractual es otra cosa
([[definicion-de-listo-y-terminado]]).
