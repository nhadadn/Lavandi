---
estado: vigente
grado: C
fuente: sesión de Arthur Naranjo con Claude Code del 2026-11-19
fecha: 2026-11-19
dueño: Arthur Naranjo
---
# Acta — 2026-11-19 — Normativa del contrato y arranque del desarrollo

Participantes: Arthur Naranjo · Claude Code.
Continúa la numeración de [[acta-2026-11-18-pedidos-de-noche]], que llega hasta C-180.

## Declaraciones

- C-181. **C (Arthur).** "Ok, lo ideal es comenzar con la APP y no desviarnos de ninguna manera sobre el contrato,
  así que déjalo por NORMATIVA en el CLAUDE.md: que no se debe salir de lo ya establecido en el contrato." Queda
  como la sección 2-bis de `CLAUDE.md`.
- C-182. **C (Arthur).** "Lo ideal es comenzar con la APP." El desarrollo empieza por el Módulo A, el del usuario,
  con el servidor mínimo que necesita.
- C-183. **C (Arthur).** "Instala el OpenSpec en el proyecto, o actívalo, ya que ya se tiene instalado en global."
  Se corrió `openspec init --tools claude` en el repositorio (OpenSpec 1.3.1).
- C-184. **C (Arthur).** "Después de eso también aplica el /graphify para generar nodos."
- C-185. **C (Arthur).** "Ahora sí empiezas a darme las fases de seguimiento, sprints, del cómo se va a comenzar a
  trabajar […] y antes de continuar, comenzar a ver cómo repartir las tareas de desarrollo de nuestra parte y con
  Nadir. Usa los agentes necesarios para agilizar este proceso."
- C-186. **C (Arthur).** "Ok, entonces /opsx:explore + /opsx:propose. Cuando tengas los specs vemos cómo incluir a
  Nadir; si no, pues de modo igual ya apoyará con lo del contacto con el cliente. Ahora inicia todo y genera un PDF
  donde estén los specs que se van a hacer por fase, ya sabes, cubriendo lo del contrato, y cuando termines de hacer
  todo, pues hibernas el equipo." Se exploró la cobertura del plan contra el contrato y se propusieron sus 26 cambios
  de OpenSpec; el PDF queda en `notas-locales/`.
- C-187. **C (Arthur), 21:10.** "De los specs, siento que faltó definir algo en el proceso, que es lo más importante:
  ¿cómo va a estar la estructura de base de datos? […] Necesito saber cómo va a quedar, cómo se planteó." Pidió verlo
  antes de que entre a las specs. Respuesta de Claude: el motor está decidido (ADR-0004) y hay reglas en
  `arquitectura/modelo-multi-plaza.md` y `c3-api.md`, pero **no existe un modelo de datos completo**; cada spec define
  sus tablas por su lado. Claude presentó una propuesta consolidada para aprobación.
- C-188. **C (Arthur), 21:10.** Sobre DD-012: "Prácticamente se tiene que pagar antes de que llegue todo lo del
  proceso: solicitas en base a los precios, hace el peso automático por la aplicación y te va a solicitar el pago. Si
  no se hace el pago, no se va a proceder a hacer el proceso de recolección; no se va a recolectar si no ha sido
  pagado." El orden del contrato lo sostiene: resumen con "precio estimado" (#9), pasarela de pago (#10) y
  confirmación con folio (#11) (`NSV-2026-001-v8.pdf:16-17:ANEXO A, Sección A.3`), y el recorrido "elegir lavandería,
  agendar recolección, pagar y dar seguimiento" (`NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3`). No consta si la
  regla viene del cliente.
- C-189. **C (Arthur), sobre el modelo de datos.** "Recuerda que hay que apegarnos al contrato, nunca hay que salirnos
  del contrato, pero la base de datos hay que dejarla con la posibilidad y la escalabilidad, es decir, que quede
  mutable pero bien definida: normalizada, con una buena estructura y con buenas prácticas." Coincide con una
  obligación grado A: diseñar "bajo criterios de escalabilidad que permitan su expansión a plazas adicionales […] sin
  necesidad de reescritura del sistema ni de rediseño de la base de datos" (`NSV-2026-001-v8.pdf:4:Cláusula I`).
  Queda en `arquitectura/adr/ADR-0016-modelo-de-datos.md` y `arquitectura/modelo-de-datos.md`, en borrador.
- C-190. **C (Arthur), sobre el modelo de datos.** "Ojo, hay algo que a mí me gusta: en las tablas, los que son PK
  AI, que son el Id que sale, deben tener siempre esta nomenclatura: id_(nombre de la tabla). Se separan por _ los que
  serían espacios. Ejemplo: Id_Categorias_Especial, algo así es un ejemplo." Se aplica en minúsculas
  (`id_pedidos`, `id_tipos_servicio`) y la llave foránea toma el nombre de la llave a la que apunta (decisión 7).
- C-191. **C (Nadir, transmitido por Arthur), sobre cambiar Conekta (DD-079).** "Es un cambio menor, inclusive se le
  comentó que eso cambiaría; solo hay que hacer un aviso oficial al cliente para que quede pactado el cambio." Lo que
  pide el contrato para que valga: "Cualquier cambio al alcance deberá formalizarse mediante Orden de Cambio firmada
  por ambas partes" (`NSV-2026-001-v8.pdf:8:Cláusula IV`); "Toda modificación deberá constar por escrito y estar
  firmada por ambas partes", y el contrato deja sin efecto "cualquier […] acuerdo previo, verbal o escrito"
  (`NSV-2026-001-v8.pdf:12:Cláusula XIV`). El aviso sirve si el cliente lo firma: una Orden de Cambio de una hoja, sin
  costo ni cambio de plazo. No consta qué pasarela reemplaza a Conekta. **Nadir lo confirmó el 2026-11-20 (C-216):**
  "Ya se le expresó, está pendiente la firma, pero tenemos un OK expreso."

## Decisiones tomadas en la sesión

| # | Decisión | Opciones consideradas | Quién decide | Nota resultante |
|---|---|---|---|---|
| 1 | No se construye nada que el contrato no establezca; lo demás es posible Orden de Cambio | — | Arthur (C-181) | `CLAUDE.md` §2-bis |
| 2 | Se empieza por la app: Módulo A | Empezar por la landing o por el panel | Arthur (C-182) | `formas-de-trabajo/sprints/plan-de-sprints.md` |
| 3 | Las specs viven en `openspec/`: el cambio en curso en `openspec/changes/<cambio>/` y la spec aceptada en `openspec/specs/<capacidad>/spec.md`. `vault/specs/` deja de usarse | Dejar la fuente en `vault/specs/` con la herramienta solo local, como decía el ciclo del 2026-11-18 | Propuesta de Claude que se desprende de C-183; Arthur la puede corregir | [[ciclo-openspec-tdd]] |
| 4 | Se proponen de una vez los 26 cambios del plan, de `base-tecnica` a `publicacion`, uno por capacidad, solo con requisitos nuevos (`ADDED`). Todos quedan como propuesta: cada uno se aprueba antes de su sprint | Proponer solo los de H0 y H1 | Arthur (C-186) | `openspec/changes/` |
| 5 | `design.md` siempre existe, aunque sea breve, porque la herramienta lo pide antes de `tasks.md` | Dejarlo solo para decisiones no triviales, como decía el ciclo | Claude, por la herramienta; Arthur lo puede corregir | `openspec/config.yaml` |
| 6 | El modelo de datos se define completo antes de implementar, normalizado y extensible, sin construir lo que el contrato no pide | Que cada spec defina sus tablas por su lado, como quedó el 2026-11-19 | Arthur (C-187, C-189) | [[ADR-0016-modelo-de-datos]] |
| 7 | La llave primaria autoincremental de cada tabla se llama `id_<tabla>` (`id_pedidos`, `id_tipos_servicio`). Va en minúsculas, porque PostgreSQL convierte a minúsculas todo nombre sin comillas: `Id_Pedidos` obligaría a escribir `"Id_Pedidos"` con comillas en cada consulta. La llave foránea lleva el mismo nombre que la llave a la que apunta (`pedidos.id_usuarios`), y `evento_id` de Conekta pasa a `conekta_evento_id` para no confundirse con una llave | `id` y `<singular>_id`, lo que espera Laravel y traía el borrador; `Id_<Tabla>` con mayúsculas, como el ejemplo de C-190 | Arthur (C-190) la nomenclatura; las minúsculas y el nombre de las llaves foráneas los propuso Claude, y Arthur los puede corregir | [[ADR-0016-modelo-de-datos]], [[modelo-de-datos]] |

**Lo que implica la decisión 3.** OpenSpec solo lee `openspec/`, así que la spec sale del vault: Obsidian y el espejo
de Notion ya no la ven. Las palabras clave de la herramienta se quedan en inglés porque su validador las exige
(`## Why`, `## What Changes`, `### Requirement:`, `#### Scenario:`, `MUST`); el texto va en español y los escenarios
usan **DADO / CUANDO / ENTONCES**. Se probó en una copia aparte el 2026-11-19: un cambio con frontmatter, texto en
español y "DEBE (MUST)" valida y se archiva sin errores. Las reglas están en `openspec/config.yaml`.

## Preguntas abiertas que salieron de la sesión

Ninguna nueva. La normativa vuelve más urgente la fila que ya estaba abierta:

| # | Pregunta | Bloquea | Fila en `deuda-de-decision.md` |
|---|---|---|---|
| 1 | ¿Quién decidió descartar Conekta y el cliente firma la Orden de Cambio? Sin ella, la normativa deja Conekta. Nadir lo ve como cambio menor que se pacta con un aviso oficial (C-191); falta elegir la pasarela que lo reemplaza y que el cliente firme | La spec de pago | DD-079 |
