# nsv-lavanderia

> **Nombre clave provisional.** No es la marca del producto. La marca se decide en `vault/marca/`.

Repositorio de cimiento del proyecto **NSV-2026-001 v8**: plataforma digital de lavandería
on-demand (app iOS/Android única con módulos de usuario, socio lavandería, socio repartidor y
panel de administración; landing page; panel de indicadores; branding) para el Estado de Durango
y los municipios de Torreón y Matamoros, Coahuila.

EL PRESTADOR: Nadir Hadad Navarrete, bajo la marca Nazmelia.

## Qué hay aquí

| Carpeta | Contenido |
|---|---|
| `CLAUDE.md` | Instrucciones permanentes para Claude Code. Léelo primero. |
| `legal/` | Contrato v8 y versiones previas. Fuente de verdad de los requerimientos ejecutivos. |
| `vault/` | Vault de Obsidian: contrato leído, negocio, marca, design system, arquitectura, specs, formas de trabajo, riesgos, cliente, plantillas. |
| `.claude/agents/` | Subagentes específicos de este proyecto (Fase 8). |
| `.claude/skills/` | Skills del proyecto (Fase 8). |

## Cómo se trabaja

1. Abre Obsidian en `vault/` (abrir carpeta como bóveda). El repo es la única copia: no hay espejo a Notion.
2. Nadie escribe directo en `main`: rama por tarea y pull request. Ver `vault/formas-de-trabajo/colaboracion-en-el-vault.md`.
3. Toda nota lleva frontmatter `estado`, `grado`, `fuente`, `fecha`, `dueño`.
4. Toda afirmación lleva grado de evidencia (A–E o S). Ver `CLAUDE.md`, sección 3.
5. Lo que no se sabe se escribe `SIN RESPUESTA — bloquea: <qué>` y se registra en
   `vault/meta/deuda-de-decision.md`.

## Estado

Fase de cimiento (Fases 0 a 8). No hay código de producto todavía, a propósito.
