---
estado: vigente
grado: C
fuente: sesión con Nadir Hadad Navarrete del 2026-11-20, sobre la revisión de las cuatro propuestas de maqueta del Módulo A
fecha: 2026-11-20
dueño: Nadir Hadad Navarrete
---
# Acta — 2026-11-20 — Decisiones para las maquetas del Módulo A

Participantes: Nadir Hadad Navarrete · Claude Code.
Fase(s) de la sesión: 6. El encargo que gobierna el trabajo es [[encargo-revision-ux-y-maquetas-modulo-a]].

Continúa la numeración de [[acta-2026-11-20-modelo-y-pasarela]], que llega hasta C-196. Esta sesión corrió en paralelo con las de Arthur y numeró sus declaraciones como C-132 a C-142; al integrar las dos líneas, el 2026-11-20, pasaron a C-197 a C-207. Sus filas DD-067, DD-068 y DD-069 pasaron a DD-079 (la misma pregunta de la pasarela), DD-124 y DD-125.

## Declaraciones de Nadir (grado C)

- C-197. A la pregunta "¿Cómo se estima y cuándo se cobra?" (DD-012): "Se estima en base a un tabulador de pesos
  de cada prenda. y se cobra al estimar."
- C-198. Sobre DD-055 (tarjeta guardada por referencia y captura dentro de la app; lo escribió "DD-0555"): "Si a
  ambas. Pero busquemos otro que no sea Conekta."
- C-199. Sobre DD-056 (efectivo en tienda): "Hay que confirmar que proveedores hay de ello."
- C-200. Sobre DD-052 (a quién califica el usuario): "Dos como P1." Es decir, lavandería y repartidor.
- C-201. "De ahí en más estoy de acuerdo con todas tus recomendaciones, necesito generes los mocks en base a ello."
- C-202. A la pregunta de si la otra persona que trabaja en el repo también usa Claude Code: "sí, los dos usamos
  Claude Code". Contexto que dio Nadir antes: "estamos trabajando dos personas en el repositorio de forma
  agentica", y pidió mantener la documentación y las specs actualizadas y organizadas desde el comienzo con
  Obsidian.
- C-203. "Quita el espejo a Notion."
- C-204. "Lo que es necesario es que se instruya que cualquier desarrollo, decisión, etc. se guarde en el vault
  y tú administres Obsidian en el proyecto. Todo debe de estar en el repo para estar sincronizados."
- C-205. Sobre C-54 a C-62 transcritas en dos actas del 2026-09-12: "La más reciente es la correcta." Por la
  hora de su commit, la más reciente es `acta-2026-09-12-marca-visual` (00:33, contra 00:00 de
  `acta-2026-09-12-paleta-y-logotipo`).
- C-207. "Haz el 1 y el 2, esto debe de formar parte de claude.md para que el vault se mantenga actualizado y
  no dependa de un humano para ejecutar el skill." El 1 es una guía de arranque para la otra persona; el 2,
  el instrumental de Claude (skills, subagente revisor, hook de cierre y permisos).
- C-211 (Nadir Hadad Navarrete). A la pregunta de cómo se llama la otra persona del equipo: "Arthur
  Naranjo." A si lo que él decide en sesión cuenta como grado C: "Sí, cuenta como grado C."
- C-212 (Nadir Hadad Navarrete). Pidió las nomenclaturas del proyecto "para no errar", qué es una decisión
  grado C y qué es una DD; ante la propuesta de guardarlas como glosario en el vault: "sí hazlo".
- C-213 (Nadir Hadad Navarrete). Ante el aviso de que `CLAUDE.md` seguía mandando escribir `{{MARCA}}`:
  "sí, corrige lo de la marca."
- C-206. Sobre el estado «enviada» que no está en las convenciones: "De acuerdo." Se lee como acuerdo con
  normalizarlo: la nota queda `vigente` y el envío va en su campo `enviada`.

## Decisiones tomadas en la sesión
| # | Decisión | Quién decide | Nota resultante |
|---|---|---|---|
| 1 | El estimado sale de un tabulador de pesos por prenda; se cobra el estimado al confirmar (C-197) | Nadir lo declara. En la deuda, DD-012 es de EL CLIENTE: falta su confirmación escrita | Maquetas P5: pasos "Prendas" y "Revisa y paga" |
| 2 | La tarjeta se captura dentro de la app y se guarda solo la referencia de la pasarela (C-198) | Nadir | Maquetas P5: "Pago" y "Perfil" |
| 3 | Buscar una pasarela distinta de Conekta (C-198) | Nadir pide buscar. **Cambia el contrato** (RN-10, RN-28): no es decisión hasta que haya acuerdo escrito con EL CLIENTE, matriz y ADR | DD-079; `arquitectura/pasarelas-candidatas.md` |
| 4 | Dos calificaciones, lavandería y repartidor (C-200) | Nadir lo declara. DD-052 es de EL CLIENTE, vía Nadir | Maqueta P5: "Calificación" |
| 5 | Se aceptan las demás recomendaciones de la revisión (C-201): banda de excepción para cancelado o rechazado, fuera de los cinco estados; quinto aviso "En proceso"; no mostrar al usuario cada cuánto se consulta el estado; "repetir pedido" repite todo menos la ventana; explorar lavanderías sin cuenta y registrar al confirmar; pedido activo en Inicio; logotipo del diseñador (C-98) | Nadir. Lo que toca a EL CLIENTE (BR-22, DD-011, DD-016) sigue pendiente de él | Maquetas P5 |

| 6 | Sin espejo a Notion: el repo es la única copia del vault (C-203). Revoca la regla de C-28 y la fila DD-005 | Nadir | `meta/convenciones.md`, `CLAUDE.md` §4 |
| 7 | Forma de trabajo entre dos personas con Claude Code (C-202): rama y pull request para todo, una nota por decisión pendiente, configuración compartida de Obsidian y validación del vault en la CI | Nadir acepta la propuesta de Claude | `formas-de-trabajo/colaboracion-en-el-vault.md` |
| 8 | Todo desarrollo y decisión se guarda en el repo en la misma sesión, y Claude administra el vault (C-204) | Nadir | `CLAUDE.md` §0-bis |
| 9 | Vale la transcripción de C-54 a C-62 de `acta-2026-09-12-marca-visual`; la otra queda tachada (C-205) | Nadir | `acta-2026-09-12-paleta-y-logotipo` |
| 10 | «Enviada» no es estado: campo `enviada` (C-206) | Nadir | `meta/convenciones.md` |
| 11 | Guía de arranque y primer instrumental de la Fase 8: skills `cerrar-sesion`, `nueva-decision` y `nueva-spec`, subagente `revisor-de-evidencia`, hook de cierre que devuelve el turno si el vault quedó sin guardar o con errores, y prohibición de editar `legal/`. Todo en `CLAUDE.md` §0-bis para que no dependa de un humano (C-207) | Nadir | `formas-de-trabajo/guia-de-arranque.md`, `.claude/` |
| 12 | Lo que Arthur Naranjo declare en sesión es grado C, igual que lo de Nadir (C-211). Una sola serie `C-NN`; cada renglón dice quién. Quedan reservados a Nadir los envíos al cliente, dominio e IMPI y lo que firma como EL PRESTADOR, por `CLAUDE.md` §5 y el contrato | Nadir | `CLAUDE.md` §3, `meta/convenciones.md`, plantilla de acta |
| 13 | Glosario de nomenclaturas en `meta/glosario.md`, enlazado desde el índice, la guía de arranque y `CLAUDE.md` §8 (C-212) | Nadir | `meta/glosario.md` |
| 14 | `CLAUDE.md` §1 y §5 dicen Lavandi y `lavandi.mx`, con su respaldo: C-49, C-54, C-65 y C-66, y el retiro de los marcadores en `meta/convenciones.md`. Se conserva la regla de no dejar la marca literal en código, porque el IMPI sigue pendiente (DD-030, R-017). Se quitaron dos `{{MARCA}}` sobrantes de `arquitectura/cruce-enatega-inventario.md` (C-213) | Nadir | `CLAUDE.md` §1 y §5 |

## Lo que estas declaraciones no resuelven

| # | Pregunta | Bloquea | Fila en `deuda-de-decision.md` |
|---|---|---|---|
| 1 | Sustituir Conekta exige modificar el contrato por escrito. ¿Quién lo plantea a EL CLIENTE y cuándo? | Alta de la pasarela; ADR-0010 | DD-079 |
| 2 | El tabulador de pesos por prenda no existe (C-131 ya decía que no hay precios). ¿Quién lo arma y con qué prendas? | El estimado de F-A09; el cobro | DD-124 |
| 3 | Si se cobra el estimado, ¿qué pasa cuando el peso real difiere: cobro adicional, reembolso, tolerancia? | Texto de "Revisa y paga"; reembolsos | DD-125 |
| 4 | Proveedores de pago en efectivo vigentes (C-199) | F-A10 efectivo | DD-056, sigue abierta |
| 5 | ~~Resuelta por C-205 y C-206.~~ Al crear el validador aparecieron dos errores que ya existían: las actas `acta-2026-09-12-marca-visual` y `acta-2026-09-12-paleta-y-logotipo` transcriben C-54 a C-62 dos veces con redacción distinta, y una comunicación al cliente usa el estado «enviada». ¿Cuál acta es la fuente? | Citas a C-54 a C-62 | Anotado en `herramientas/validar-vault/excepciones.txt` |
| 6 | Reparto de áreas entre las dos personas | Guía de arranque, sección "Quién lleva qué" | — |

## Nota sobre el método

`prompt-maestro-01-fundacion-v5` define el grado C como lo que Nadir declara y no se edita. C-211 lo amplía a
Arthur Naranjo; la regla operativa vive en `CLAUDE.md` §3. Si el método se reescribe, la v6 lo incorpora.

## Nota sobre ADR-0001

`ADR-0001` pondera "sobrevive al espejo a Notion" con 10 puntos. Sin espejo, ese criterio ya no aplica. No
cambia el resultado: Mermaid suma 88 contra 60 del siguiente; sin ese criterio quedaría 78 contra 58. No se
reescribe el ADR; queda esta nota.
