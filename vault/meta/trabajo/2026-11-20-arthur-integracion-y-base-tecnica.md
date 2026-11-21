---
estado: vigente
grado: B
fuente: sesión de Arthur del 2026-11-19 y 2026-11-20 (C-187 a C-196, C-208 a C-210 y C-221; decisión 8 del acta del 2026-11-20)
fecha: 2026-11-20
dueño: Arthur Naranjo
rama: integracion/lavandi
situacion: en-curso
toca: vault/meta/, vault/arquitectura/, openspec/, CLAUDE.md, .claude/skills/, herramientas/ci/
---
# Modelo de datos, specs re-propuestas, integración con la línea de Nadir y base técnica

**Para qué.** El modelo de datos aprobado ([[modelo-de-datos]], [[ADR-0016-modelo-de-datos]], C-193); las 27
specs de `openspec/changes/` alineadas con él; la skill `git-lavandi` (C-195); la integración de las dos líneas de
trabajo del 2026-11-20 en un solo PR (C-208 a C-210); y el arranque del código con `base-tecnica`.

**Toca.** `vault/meta/` (actas, deuda), `vault/arquitectura/`, `openspec/`, `CLAUDE.md`, `.claude/skills/` y
`herramientas/ci/`. El código vive aparte, en las ramas `feat/base-servidor`, `feat/base-movil` y
`feat/modelo-de-datos`, todavía sin subir.

**Hecho.**
- PR [nhadadn/Lavandi#2](https://github.com/nhadadn/Lavandi/pull/2): la línea de Arthur del 12 al 20 de noviembre,
  con el PR #1 de Nadir integrado y renumerado (C-197 a C-207, DD-124 y DD-125). Lo que queda por decidir entre
  los dos: [[DD-126]] y [[DD-127]].
- Segunda integración: los 7 commits que Nadir subió a su PR #1 después del #2 (C-211 a C-220), con los dos
  lados completos (C-221).
- Lo que pidió la revisión de evidencia de Nadir en el PR #2 (C-214 a C-220): modelo-E17 y `created_at` en todas
  las specs; C-188 y C-197 como la regla del cobro; la marca escrita como Lavandi en el texto; la regla de Apple como
  grado E; C-177 por confirmar; la lista de precios en grado B; el espejo a Notion `superseded`; y la pasarela según
  la decisión 8 del [[acta-2026-11-20-modelo-y-pasarela]]: Conekta se integra mientras el cliente no firme el cambio,
  y DD-079 solo bloquea la pantalla de pago y la tarjeta guardada (pago-E02, pago-E06, perfil-usuario-E07), con
  DD-055.
- La ampliación del efectivo en [[alternativas-de-pasarela-2026-11-18]] (grado E, `SIN VERIFICAR`), para DD-056
  y C-199.
- `modelo-de-datos` implementado en `feat/modelo-de-datos` (52 tests en verde), todavía sin subir.

**Pendiente.**
- Los PR del código (`feat/base-servidor`, `feat/base-movil` y `feat/modelo-de-datos`), cuando entre el #2.

**Al terminar.** Se marca `terminada` cuando se una el PR #2.
