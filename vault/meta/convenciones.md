---
estado: vigente
grado: D
fuente: vault/meta/actas/acta-2026-09-07-fundacion.md (C-15, C-16, C-17) y brief de sesión
fecha: 2026-09-07
dueño: Nadir Hadad Navarrete
---
# Convenciones del vault

## Idioma
Español en todo. Términos técnicos sin traducción asentada se dejan en inglés (commit, sprint, push, token, backend, endpoint).

## Árbol
Por dominio, sin prefijos numéricos. Obsidian abre en `vault/`.

| Carpeta | Qué vive ahí |
|---|---|
| `meta/` | índice (MOC raíz), convenciones, deuda de decisión, actas de sesión (`meta/actas/`) |
| `contrato/` | lectura ejecutiva del contrato, brecha ejecutivo→técnico, restricciones, calendario, saneamiento |
| `negocio/` | propuesta de valor, competencia, unit economics, hipótesis críticas |
| `marca/` | territorio de marca, misión-visión-valores, candidatos de nombre y rúbrica, decisión de marca, tono de voz |
| `design-system/` | tokens JSON, tipografía, color, componentes, cobertura del ANEXO A.1, `muestra/index.html` |
| `arquitectura/` | C1–C3 y `arquitectura/adr/` |
| `specs/` | specs openspec por capacidad |
| `formas-de-trabajo/` | ciclo openspec+TDD, DoR/DoD, sprint, ramas, CI, cadencia con el cliente, protocolos |
| `riesgos-supuestos/` | `riesgos.md`, `supuestos.md` y fichas individuales |
| `cliente/` | `reportes-semanales/`, `notificaciones-de-entrega/`, `requerimientos/`, `comunicaciones/` (notas ejecutivas ad hoc que piden aprobación u opinión del cliente, fuera del ciclo semanal y de las notificaciones formales de entrega), órdenes de cambio |
| `plantillas/` | una plantilla por tipo de nota |

## Nombres de archivo
kebab-case, sin acentos ni espacios: `unit-economics.md`, `ADR-0001-formato-de-diagramas.md`, `acta-2026-09-07-fundacion.md`.

## Frontmatter obligatorio
```yaml
---
estado: borrador | vigente | superseded
grado: A | B | C | D | E | S | mixto
fuente: <de dónde sale la nota>
fecha: AAAA-MM-DD
dueño: <persona>
---
```
Una nota `superseded` dice en su primera línea qué nota la reemplaza.

Que algo ya se mandó al cliente no es un estado: la nota queda `vigente` y lleva el campo
`enviada: AAAA-MM-DD HH:MM, por <medio>, a <quién> (C-NN)` (C-141).

## Grados y citas
Ver `CLAUDE.md`, sección 3. Formatos de cita:
- A: `NSV-2026-001-v8.pdf:4:Cláusula I` · `NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3`
- B: `vault/contrato/obligaciones-ejecutivas.md:37`
- C: `vault/meta/actas/acta-2026-09-07-fundacion.md:C-06`
- D: `ADR-0003`
- E: `<URL o fuente> (consultado 2026-09-07) SIN VERIFICAR`
- S: solo en `riesgos-supuestos/supuestos.md`, con ID `S-00X`

## Marcadores
| Marcador | Significado |
|---|---|
| `SIN RESPUESTA — bloquea: <qué>` | Falta una respuesta de Nadir o del cliente. Debe tener fila `DD-` en `deuda-de-decision.md`. |
| `ESPECIFICACIÓN PENDIENTE` | Obligación ejecutiva (A) sin especificación técnica todavía. Estado normal al inicio. |
| `NO JUSTIFICADO` | Elemento de arquitectura sin obligación ni hipótesis que lo sostenga. Sale del diagrama. |
| `SIN VERIFICAR` | Dato grado E que nadie ha confirmado. |
| ~~`{{MARCA}}`, `{{DOMINIO}}`~~ | **Retirados el 2026-09-12.** Se usaron hasta que la marca quedó decidida; hoy el vault dice Lavandi y lavandi.mx. La plantilla de decisión de marca los conserva para el próximo proyecto. |

## Identificadores
| Prefijo | Qué | Dónde se numera |
|---|---|---|
| `O-NN` | Obligación ejecutiva | `contrato/obligaciones-ejecutivas.md` |
| `F-XNN` | Función del inventario funcional. La letra dice de dónde viene: A, B, C y D son los módulos del A.3; L, la landing (A.2); I, los indicadores (A.5); T, lo transversal | `contrato/inventario-funcional.md` |
| `BR-NN` | Fila de brecha ejecutivo→técnico | `contrato/brecha-ejecutivo-tecnico.md` |
| `RN-NN` | Restricción no negociable | `contrato/restricciones-no-negociables.md` |
| `DD-NNN` | Deuda de decisión | `meta/deuda-de-decision.md` hasta DD-069; desde el 2026-11-20, una nota por decisión en `meta/deuda/` |
| `C-NN` | Declaración de Nadir Hadad Navarrete o de Arthur Naranjo (C-143). Una sola serie para los dos; desde C-143 cada renglón dice quién: `- C-NN (Nombre). …` | acta correspondiente |
| `ADR-000X` | Decisión técnica | `arquitectura/adr/` |
| `H-00X` | Hipótesis | `negocio/hipotesis-criticas.md` |
| `R-00X` | Riesgo | `riesgos-supuestos/riesgos.md` |
| `S-00X` | Supuesto | `riesgos-supuestos/supuestos.md` |

## Espejo Notion — retirado
Retirado el 2026-11-20 por Nadir (C-138): el repo es la única copia del vault. La regla anterior (se
espejaba todo `vault/` porque EL CLIENTE no consulta el Notion, C-28) queda solo como antecedente.

## Trabajo entre dos personas
Dos personas con Claude Code (C-137). Rama por tarea, pull request revisado por la otra persona,
validación con `herramientas/validar-vault/validar.py`. Reglas completas en
`formas-de-trabajo/colaboracion-en-el-vault.md`.

## Tablero del proyecto
- El tablero (<https://claude.ai/artifact/T2tZpPePdPhccB4GmFRjbK>) es **un espejo del vault**: no guarda nada propio y no se edita a mano. Se rehace con `herramientas/tablero/construir_tablero.py`.
- El estado de fases, actividades, obligaciones, pagos y trámites vive en `meta/estado-del-proyecto.md`, con vocabulario fijo y evidencia por fila. Si cambia un estado, se cambia ahí primero.
- **No se comparte con EL CLIENTE tal como está**: muestra deuda y riesgos. Para el cliente haría falta otra versión, sin esas pestañas, asentada antes en un acta (la regla de C-28).

## Salas de decisión
- Una **sala** es una página donde Nadir responde las preguntas de una fase (la primera: <https://claude.ai/artifact/WN6jmpKuxSkgFrf8GDAXDB>, Fase 6). Como el tablero, sus preguntas salen del vault y se rehacen con un generador.
- **Lo que Nadir escribe en una sala no es grado C todavía.** Sube a C cuando la sesión lo transcribe literal a un acta con fecha, igual que un mensaje de WhatsApp. La pregunta queda entonces `cerrada`, con su `C-NN`.
- Al actualizar una sala, lo que ya existe se actualiza con `update`, nunca con `set`, para no borrar respuestas.
- No se comparte con EL CLIENTE.

## Git
`main`, protegida: todo entra por pull request. Un commit por fase cerrada (`Fase N: …`); trabajo intermedio `wip(fase-N): …`. Ramas, revisión y choques: `formas-de-trabajo/colaboracion-en-el-vault.md`.

## Actas
Toda sesión con Nadir o con Arthur Naranjo produce un acta en `meta/actas/acta-AAAA-MM-DD-<tema>.md`. Lo
que cualquiera de los dos dice es grado C solo si está en un acta, con su nombre en el renglón (C-143). Un
dato E confirmado por uno de ellos se registra en la tabla final del acta. Si dos actas del mismo día
chocan en número, la segunda en unirse se renumera. Una declaración que contradice otra anterior nombra el
`C-NN` que revoca; si no está claro cuál vale, se abre una `DD-`.
