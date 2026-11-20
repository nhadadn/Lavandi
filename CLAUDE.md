# CLAUDE.md — nsv-lavanderia (nombre clave provisional)

Instrucciones permanentes para cualquier sesión de Claude Code en este repositorio. Se leen antes
de cualquier tarea y prevalecen sobre hábitos generales.

## 0. El método de este proyecto

Antes de trabajar, lee **`vault/meta/instrucciones/prompt-maestro-01-fundacion-v5.md`**. Es el documento
fuente que define cómo se trabaja aquí: las fases y su orden, el tablero de configuración,
el rol, la regla de evidencia completa, el modo de trabajo con puertas blandas, el formato de respuesta de
cada turno y las prohibiciones. Este `CLAUDE.md` es el resumen operativo; aquel es la fuente. La v4 quedó
`superseded` el 2026-09-25: la v5 es la misma más la Fase 5-bis.

Orden de las fases, que no se altera: 0 espacio de trabajo · 1 lectura ejecutiva del contrato · 2 negocio ·
3 marca · 4 design system · 5 arquitectura C4 · **5-bis análisis funcional** · 6 stack · 7 formas de
trabajo · 8 instrumental de Claude.

Todo documento que gobierne el trabajo se archiva en `vault/meta/instrucciones/` **antes** de ejecutarlo.
Una instrucción que solo vive en un chat no es un documento del proyecto.

## 0-bis. Todo vive en el repo, y Claude administra el vault

Nadir lo pidió el 2026-11-20 (C-139). Es regla para cualquier sesión, de cualquiera de las dos personas:

- **Todo se guarda en el vault, dentro del repo, en la misma sesión en que ocurre.** Desarrollo, decisión,
  respuesta de Nadir o del cliente, spec, hallazgo, investigación, cambio de alcance. Lo que se quedó en un
  chat, en Notion, en un artefacto de claude.ai o en la cabeza de alguien no existe para el otro.
- Un artefacto publicado (lienzo, tablero, sala) no sustituye al vault: la nota que lo explica y enlaza va
  en el vault.
- **Claude administra el vault de Obsidian.** En cada sesión, sin que se le pida:
  0. **al empezar, pregunta quién está trabajando** (Nadir Hadad Navarrete o Arthur Naranjo) y lo anota en
     el campo `dueño` de su tarea. Pueden usar la misma cuenta de Claude, así que la cuenta no dice quién
     escribe (C-220). Sin esa respuesta, la sesión no asienta ninguna declaración grado C ni nada reservado
     a Nadir. Si a media sesión cambia quien escribe, se dice y se anota. Luego lee las tareas `en-curso` de
     `vault/meta/trabajo/` y abre la suya con
     `plantilla-tarea-en-curso`; si otra toca las mismas notas, lo dice antes de escribir;
  1. archiva lo que se decidió (acta, ADR, spec o nota de deuda) con frontmatter y grado;
  2. actualiza `vault/meta/indice.md` y los enlaces `[[nota]]` de lo que creó o movió;
  3. cierra o actualiza la deuda de decisión que la sesión respondió;
  4. corre `python herramientas/validar-vault/validar.py` y no termina con errores nuevos;
  5. hace commit y push en una rama, y avisa si el push falló;
  6. al terminar, marca su tarea `terminada` con el enlace al pull request.
- **No depende de que un humano lo pida (C-142).** Los pasos 1 a 6 los ejecuta la skill `cerrar-sesion`,
  que Claude invoca solo al terminar cualquier trabajo que haya cambiado el repo o tomado una decisión.
  Si no lo hace, el hook de cierre se lo devuelve.
- **El instrumental que lo hace cumplir**, en `.claude/`:

  | Pieza | Cuándo corre | Qué hace |
  |---|---|---|
  | `hooks/session-start.sh` | Al iniciar o reanudar | Trae lo último de origin si se puede sin riesgo, avisa si `main` avanzó, lista tareas en curso, valida |
  | `hooks/stop-vault.sh` | Al terminar cada turno | Si el vault tiene cambios sin commit o no valida, devuelve el turno para ejecutar `cerrar-sesion` |
  | skill `cerrar-sesion` | Al terminar, sola | Pasos 1 a 6 de arriba |
  | skill `nueva-decision` | Cuando algo queda sin respuesta | Nota `DD-NNN` con el siguiente número libre |
  | skill `nueva-spec` | Cuando se especifica una función | Spec en `vault/specs/` con su cita del contrato |
  | subagente `revisor-de-evidencia` | Antes de abrir un PR que toque `vault/`, y al revisar el de la otra persona | Afirmaciones sin grado ni cita, datos inventados, decisiones presentadas como contrato |
  | `settings.json` | Siempre | Registra los hooks; prohíbe editar `legal/` |

- Guía para quien llega: `vault/formas-de-trabajo/guia-de-arranque.md`.
- La estructura del vault (carpetas, plantillas, `vault/.obsidian/`, la validación) la propone y mantiene
  Claude. Un cambio de estructura entra por pull request como cualquier otro.

## 1. Qué es este proyecto

Plataforma digital de lavandería on-demand para EL CLIENTE del contrato **NSV-2026-001 v8**:
app iOS/Android única con cuatro módulos (usuario, socio lavandería, socio repartidor, panel de
administración), landing page de 12 secciones, panel de indicadores del negocio y branding.
Territorio inicial: la totalidad del Estado de Durango y los municipios de Torreón y Matamoros,
Coahuila (`legal/NSV-2026-001-v8.pdf:4:Cláusula I`).

- EL PRESTADOR es Nadir Hadad Navarrete, bajo la marca **Nazmelia**. Nazmelia es marca de EL
  PRESTADOR y no se cede (`NSV-2026-001-v8.pdf:10:Cláusula IX`). No se mezcla con la marca del producto.
- Calendario contractual: 16 semanas del 7 de septiembre al 27 de diciembre de 2026, más 30 días
  de operación; liquidación final el 26 de enero de 2027 (`NSV-2026-001-v8.pdf:5:Cláusula II`).
- El contrato vive en `legal/`. Su lectura ejecutiva vive en `vault/contrato/`.
- `nsv-lavanderia` es un **nombre clave** del repo. **La marca del producto es Lavandi** y el dominio
  `lavandi.mx`: EL CLIENTE eligió el nombre (C-49) y Alfredo Navarrete lo aprobó por escrito (C-65); el
  dominio está verificado (C-54). Nadir decidió escribir Lavandi en el vault aunque
  `vault/marca/decision-de-marca.md` siga en `borrador` (C-66), asumiendo el riesgo: faltan la búsqueda
  ante el IMPI (DD-030) y las colisiones de uso (DD-028), y el nombre es descriptivo (R-017). Los
  marcadores `{{MARCA}}` y `{{DOMINIO}}` se retiraron el 2026-09-12 (`vault/meta/convenciones.md`).

## 2. La distinción que gobierna todo

> El contrato es fuente de verdad de los requerimientos ejecutivos. **No** es una especificación técnica.

- El contrato dice **qué se entrega** y **con qué criterio se acepta** (ANEXO A, Sección A.4). Eso es grado A.
- El contrato **no dice cómo se construye**. Ninguna decisión de stack, modelo de datos, patrón,
  infraestructura o flujo puede presentarse como "lo pide el contrato".
- Toda especificación técnica nace en el vault y se sostiene sola: por su ADR
  (`vault/arquitectura/adr/`) o por su spec (`vault/specs/`), nunca por el PDF.
- Obligación ejecutiva sin especificación todavía se marca `ESPECIFICACIÓN PENDIENTE`. Es el
  estado normal al inicio, no un error.
- Las pocas frases del ANEXO A que sí nombran tecnología (Conekta; mapas de Google en Android y
  de Apple en iPhone; avisos por el servicio gratuito de Google; Google Sign-In; GA4, Meta Pixel,
  GTM y Search Console; Canva o Figma) se tratan como **restricciones grado A** que la arquitectura
  debe satisfacer, no como diseño. Están listadas en `vault/contrato/restricciones-no-negociables.md`.

## 3. Regla de evidencia

| Grado | Significado | Cómo se cita |
|---|---|---|
| **A** | Obligación ejecutiva contractual | `NSV-2026-001-v8.pdf:pág:cláusula` o `NSV-2026-001-v8.pdf:pág:ANEXO X, Sección X.Y` |
| **B** | Hecho verificable en el repo | `ruta/archivo:línea` |
| **C** | Dato o decisión que Nadir Hadad Navarrete o Arthur Naranjo declaró en sesión (C-143) | `vault/meta/actas/acta-AAAA-MM-DD-<tema>.md:línea` (o el número `C-NN` del acta) |
| **D** | Decisión técnica tomada aquí, con ADR | `vault/arquitectura/adr/ADR-000X.md` |
| **E** | Dato externo buscado por Claude | fuente + fecha de consulta + `SIN VERIFICAR` |
| **S** | Supuesto | Solo en `vault/riesgos-supuestos/supuestos.md`, con dueño y fecha límite. **Nunca** en prosa como hecho |

Reglas duras:
1. Ante duda entre dos grados, elige el más bajo y dilo.
2. Una mención suelta (README, TODO, comentario, nombre de variable) no es evidencia.
3. **Prohibido inventar el negocio.** Precios, número de lavanderías, márgenes esperados,
   preferencias de usuarios: se preguntan, o se buscan y se etiquetan grado E. No se estiman en silencio.
4. Un dato grado E no sube a grado C hasta que Nadir o Arthur lo confirme explícitamente en un acta.
   Cada declaración dice quién la hizo. Si contradice una anterior del otro, el acta nombra el `C-NN` que
   revoca; si no queda claro cuál vale, se abre una `DD-` y ninguna de las dos se aplica hasta resolverla.
   Lo que el contrato o el método reservan a Nadir sigue siendo solo de Nadir: aprobar envíos a EL CLIENTE,
   dominio e IMPI, y lo que firme como EL PRESTADOR.
5. **Dominio e IMPI**: la disponibilidad del dominio y la búsqueda fonética ante el IMPI las verifica
   Nadir. Claude entrega rúbrica y candidatos, nunca un veredicto de disponibilidad.
6. "No sé / falta X" es una respuesta correcta y esperada. Lo que dependa de una respuesta que no
   existe se escribe `SIN RESPUESTA — bloquea: <qué>` en el archivo y como fila en
   `vault/meta/deuda-de-decision.md`. Jamás se rellena con una suposición disfrazada de hecho.
7. Ningún agente declara algo implementado sin evidencia grado B con test asociado.

## 4. Vault (Obsidian)

- Obsidian abre en `vault/`. Árbol por dominio, sin prefijos numéricos, nombres en kebab-case.
- **Frontmatter obligatorio** en toda nota: `estado` (borrador | vigente | superseded), `grado`,
  `fuente`, `fecha`, `dueño`. Sin frontmatter, la nota no cuenta.
- **El repo es la única copia del vault.** No hay espejo a Notion: Nadir lo retiró el 2026-11-20 (C-138).
- Trabajan **dos personas, cada una con Claude Code** (C-137). Nadie escribe directo en `main`: rama por
  tarea y pull request que revisa la otra persona. Reglas completas, incluidos los archivos que un agente
  no edita sin aprobación: `vault/formas-de-trabajo/colaboracion-en-el-vault.md`.
- Antes de abrir un PR: `python herramientas/validar-vault/validar.py`. La CI corre lo mismo.
- Decisión pendiente nueva: una nota `vault/meta/deuda/DD-NNN.md`, no una fila más en la tabla.
- Plantillas en `vault/plantillas/`. Toda nota nueva de un tipo con plantilla usa la plantilla.
- Detalle de convenciones, marcadores e identificadores: `vault/meta/convenciones.md`.

## 5. Prohibiciones permanentes

- **No hardcodear marca ni dominio en código.** En notas y documentos se escribe Lavandi y `lavandi.mx`
  (C-66). En código, el nombre de producto, dominio, handle social, bundle id y nombre de paquete van
  siempre en variable de configuración, nunca literales: mientras el IMPI no responda (DD-030), cambiar el
  nombre tiene que costar un archivo, no una búsqueda en todo el código.
- No presentar una decisión técnica como si viniera del contrato.
- No elegir stack sin matriz de criterios ponderados previa y ADR. Sin ADR, la decisión no existe.
- No presentar un supuesto como hecho, ni una spec como implementación.
- No escribir código de producto mientras la fase de cimiento (Fases 0 a 8) no esté cerrada.
- No inventar datos de mercado, precios ni preferencias (regla 3 de evidencia).
- No adular ni suavizar. Si la propuesta de valor no se sostiene, si el margen no da o si el
  calendario no cierra, la frase es **"no está listo"**, seguida del motivo y la evidencia.
- No enviar nada a EL CLIENTE sin que Nadir lo apruebe explícitamente en sesión.

## 6. Idioma

Español en todo: notas, ADRs, specs, commits, comentarios de código, nombres de archivos técnicos,
mensajes de CI, documentación de agentes y skills. Los identificadores de código siguen la
convención del lenguaje que se elija en la Fase 6, pero la documentación que los acompaña es en
español. Términos técnicos sin traducción asentada (commit, sprint, push, token, backend) se dejan en inglés.

## 7. Git

- Rama principal `main`. Durante el cimiento: un commit por fase cerrada, con mensaje que nombre
  la fase (`Fase N: <qué cerró>`). Trabajo intermedio de una fase abierta: `wip(fase-N): <qué>`.
- Convención de ramas, commits y revisión para desarrollo: se define en `vault/formas-de-trabajo/` (Fase 7).
- `legal/` se versiona: el contrato es la fuente de verdad ejecutiva.
- Nadie escribe directo en `main`: rama por tarea y pull request (`vault/formas-de-trabajo/colaboracion-en-el-vault.md`).

## 8. Dónde está cada cosa

| Necesito… | Voy a… |
|---|---|
| Lo que el contrato obliga y cómo se acepta | `vault/contrato/obligaciones-ejecutivas.md` |
| Lo que el contrato NO dice y hay que decidir | `vault/contrato/brecha-ejecutivo-tecnico.md` |
| Restricciones que toda arquitectura debe cumplir | `vault/contrato/restricciones-no-negociables.md` |
| Fechas, semanas y trámites de terceros | `vault/contrato/calendario-contractual.md` |
| Si el instrumento está sano | `vault/contrato/nota-de-saneamiento.md` |
| Qué está pendiente de decidir y quién bloquea | `vault/meta/deuda-de-decision.md` y `vault/meta/deuda/` |
| Cómo trabajamos los dos en el repo | `vault/formas-de-trabajo/colaboracion-en-el-vault.md` |
| Lo que Nadir o Arthur declararon (grado C) | `vault/meta/actas/` |
| Supuestos y riesgos | `vault/riesgos-supuestos/` |
| Decisiones técnicas | `vault/arquitectura/adr/` |
| Convenciones del vault | `vault/meta/convenciones.md` |
| Qué significa cada sigla y grado | `vault/meta/glosario.md` |
| Mapa de todo | `vault/meta/indice.md` |
