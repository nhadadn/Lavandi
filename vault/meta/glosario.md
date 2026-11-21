---
estado: vigente
grado: B
fuente: vault/meta/convenciones.md y CLAUDE.md §3; pedido de Nadir del 2026-11-20 (C-212 en acta-2026-11-20-decisiones-de-maquetas)
fecha: 2026-11-20
dueño: Nadir Hadad Navarrete
---
# Glosario: las nomenclaturas del proyecto

Resumen para no errar. Las reglas completas están en [[convenciones]] y en `CLAUDE.md` §3. Si algo aquí
contradice a esas dos, valen ellas y este glosario se corrige.

## Grado C y DD, que son las que más se confunden

**Grado C: lo que Nadir o Arthur dijeron en una sesión, y quedó escrito en un acta.**
- Ejemplo: "se cobra al estimar" es grado C porque Nadir lo dijo y está en el acta como C-197.
- La condición es el acta. Lo dicho en un chat que no se transcribió no es grado C todavía.
- Cada declaración lleva un número `C-NN` y el nombre de quien habló: `C-211 (Nadir Hadad Navarrete)`.

**DD: deuda de decisión.** Una pregunta que alguien tiene que contestar para que el trabajo avance sin
suposiciones.
- Ejemplo: DD-124, "¿quién arma el tabulador de pesos por prenda?".
- Dice quién responde, qué bloquea y para cuándo. Se cierra con la respuesta y dónde quedó: un `C-NN` o un ADR.
- Hasta DD-125 viven en la tabla [[deuda-de-decision]]. Las nuevas van en una nota por decisión en
  `meta/deuda/`.

La relación: una **DD** es la pregunta abierta, y casi siempre se cierra con una declaración **C**.

## Grados de evidencia: de dónde sale lo que se afirma

| Grado | Qué es | Ejemplo de cita |
|---|---|---|
| **A** | Lo que obliga el contrato | `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3` |
| **B** | Algo que se puede comprobar en el repo | `vault/design-system/tokens.json:102` |
| **C** | Lo que Nadir o Arthur declararon, en acta | `C-197` |
| **D** | Decisión técnica con su ADR | `ADR-0007` |
| **E** | Dato externo que buscó Claude, sin confirmar | fuente, fecha y `SIN VERIFICAR` |
| **S** | Supuesto | solo en `riesgos-supuestos/supuestos.md`, con dueño y fecha límite |

- Ante la duda entre dos grados, se elige el más bajo.
- Un dato E sube a C solo cuando Nadir o Arthur lo confirma en un acta.
- `mixto` en el frontmatter: la nota combina varios grados y cada afirmación lleva el suyo.

## Identificadores

| Prefijo | Qué es | Dónde vive |
|---|---|---|
| `O-NN` | Obligación del contrato | `contrato/obligaciones-ejecutivas.md` |
| `F-XNN` | Función o pantalla del producto. La letra es el módulo: A usuario, B lavandería, C repartidor, D administración, L landing, I indicadores, T transversal | `contrato/inventario-funcional.md` |
| `BR-NN` | Brecha: algo que el contrato pide pero no especifica | `contrato/brecha-ejecutivo-tecnico.md` |
| `RN-NN` | Restricción no negociable | `contrato/restricciones-no-negociables.md` |
| `DD-NNN` | Decisión pendiente | [[deuda-de-decision]] y `meta/deuda/` |
| `C-NN` | Declaración de Nadir o Arthur | `meta/actas/` |
| `ADR-000X` | Decisión técnica | `arquitectura/adr/` |
| `H-00X` | Hipótesis de negocio | `negocio/hipotesis-criticas.md` |
| `R-00X` | Riesgo | `riesgos-supuestos/riesgos.md` |
| `S-00X` | Supuesto | `riesgos-supuestos/supuestos.md` |

## Marcadores dentro de una nota

| Marcador | Significa |
|---|---|
| `SIN RESPUESTA — bloquea: <qué>` | Falta una respuesta; siempre va con su `DD-` |
| `ESPECIFICACIÓN PENDIENTE` | El contrato lo pide y todavía no tiene spec; es normal al inicio |
| `SIN VERIFICAR` | Dato externo que nadie ha confirmado |
| `NO JUSTIFICADO` | Elemento de arquitectura sin obligación que lo respalde; se quita |

## Estado de una nota

| Estado | Significa |
|---|---|
| `borrador` | En trabajo; todavía no se cita como regla |
| `vigente` | La que vale |
| `superseded` | Reemplazada; su primera línea dice por cuál |

Que algo se haya enviado al cliente no es un estado: va en el campo `enviada` (C-206).

## Otras palabras del proyecto

| Palabra | Qué es |
|---|---|
| EL CLIENTE / EL PRESTADOR | Las partes del contrato NSV-2026-001 v8. EL PRESTADOR es Nadir, bajo la marca Nazmelia |
| Acta | Transcripción de una sesión en `meta/actas/`; es la única fuente del grado C |
| ADR | Registro de una decisión técnica, con alternativas y criterio de reversión |
| Spec | Especificación de una función en `vault/specs/`; no es implementación |
| Fase | Etapa del método, de 0 a 8 más la 5-bis ([[prompt-maestro-01-fundacion-v5]]) |
| Cimiento | Las fases 0 a 8. Mientras no se cierren, no se escribe código de producto |
| Orden de Cambio | Lo que se pide fuera del contrato; no se construye sin ella firmada ([[alcance-del-proyecto]]) |
| Tarea en curso | Nota en `meta/trabajo/` que dice quién trabaja en qué y en qué rama |
