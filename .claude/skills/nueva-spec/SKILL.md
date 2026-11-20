---
name: nueva-spec
description: Crea o actualiza la spec de una función del inventario (F-A.., F-B.., F-C.., F-D.., F-L.., F-I.., F-T..) en vault/specs/ con su cita del contrato. Úsala cuando haya que especificar una pantalla o función, cuando una maqueta o una decisión fije el comportamiento de una función, o cuando el usuario pida "la spec de F-XNN".
---

# Nueva spec

1. **Fuente.** Busca la fila `F-XNN` en `vault/contrato/inventario-funcional.md`: texto literal del
   contrato, qué no dice, sus `BR-` y `DD-`. Si la función no está en el inventario, no se crea la spec: se
   avisa, porque estaría fuera de alcance.
2. **¿Ya existe?** Busca `vault/specs/F-XNN-*.md`. Si existe, se actualiza esa; no se crea otra.
3. **Archivo.** `vault/specs/F-XNN-<nombre-corto>.md` desde `vault/plantillas/plantilla-spec-openspec.md`,
   con frontmatter completo, `estado: borrador` y `grado: mixto`.
4. **Contenido.**
   - Cada requisito cita su obligación (`NSV-2026-001-v8.pdf:pág:sección`) o su ADR. Sin cita no entra.
   - Lo que dependa de una decisión abierta se escribe `SIN RESPUESTA — bloquea: <qué>` con su `DD-`.
   - Si hay maqueta, se enlaza la nota que la describe. La maqueta orienta, no decide.
   - Los escenarios son observables: Dado, Cuando, Entonces, con resultado medible.
5. **Trazabilidad.** Si la función tiene criterio de aceptación en el ANEXO A.4, va en la tabla final.
6. **Enlaces.** La spec se enlaza desde `vault/meta/indice.md`, sección Specs.
7. **Validar** con `python herramientas/validar-vault/validar.py`.

Una spec no es implementación: nada se declara hecho sin test (`CLAUDE.md` §3, regla 7).
