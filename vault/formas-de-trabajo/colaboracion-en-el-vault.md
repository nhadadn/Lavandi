---
estado: vigente
grado: C
fuente: decisiones de Nadir del 2026-11-20 (C-202 y C-203 en acta-2026-11-20-decisiones-de-maquetas) y propuesta de Claude aceptada en esa sesión
fecha: 2026-11-20
dueño: Nadir Hadad Navarrete
---
# Cómo se trabaja el vault entre dos personas con agentes

Primera nota de la Fase 7. Cubre solo la colaboración en el repo y el vault. El ciclo openspec + TDD, la
definición de terminado, el sprint y la cadencia con el cliente van en sus propias notas de esta carpeta.

## Quién escribe y con qué

- Somos **dos personas, cada una con Claude Code** (C-202). Los agentes escriben en el repo igual que una
  persona: por rama y por pull request.
- **El vault es la carpeta `vault/` del repo.** Obsidian es solo el lector y editor. Lo que se comparte se
  comparte con Git, no con Obsidian Sync ni con carpetas en la nube.
- **No hay espejo a Notion** (C-203). El repo es la única copia. Lo que haya hoy en Notion queda como
  histórico y no se actualiza.

## Todo queda en el repo (C-204)

Cualquier desarrollo, decisión o respuesta se guarda en el vault en la misma sesión en que ocurre, y
Claude administra el vault: archiva, indexa, enlaza, cierra la deuda respondida, valida y hace commit. La
lista completa está en `CLAUDE.md` §0-bis. Lo que no está en el repo, la otra persona no lo ve.

## Abrir el vault

1. Clonar el repo.
2. En Obsidian: *Abrir carpeta como bóveda* y elegir `vault/`, no la raíz del repo.
3. La configuración compartida ya viene en `vault/.obsidian/`: plantillas en `plantillas/`, enlaces
   `[[nota]]`, y los enlaces se actualizan solos al renombrar. Lo personal (tema, atajos, plugins de la
   comunidad) no se sube; está en `.gitignore`.

## Ramas y pull requests

- **Nadie escribe directo en `main`.** Ni personas ni agentes.
- Una rama por tarea, corta: `<persona>/<tema>` (por ejemplo `nadir/fase-7-sprint`). Los agentes de
  Claude Code en la nube usan la rama que les asigna la sesión.
- Todo cambio entra por pull request. **La otra persona revisa** antes de unir. Es el punto donde se
  detecta que un agente inventó un dato, citó mal o pisó una decisión.
- Antes de abrir el PR: actualizar la rama con `main` (`git pull --rebase origin main`) y correr
  `python herramientas/validar-vault/validar.py`.
- Mensajes de commit: los de `CLAUDE.md` §7 durante el cimiento (`Fase N: …`, `wip(fase-N): …`).

## Lo que revisa quien aprueba un PR que toca `vault/`

1. Cada afirmación nueva tiene grado y cita (`CLAUDE.md` §3). Un dato sin fuente no entra.
2. Lo grado C está en un acta. Una frase de chat no es grado C.
3. No se tocó una nota `vigente` de otro dominio sin decirlo en la descripción del PR.
4. No se cambió ningún archivo de `legal/` ni un acta ya unida.
5. La CI está en verde.

## Archivos que ningún agente edita sin aprobación explícita en el PR

| Archivo | Por qué |
|---|---|
| `legal/` | Es la fuente grado A. Solo se agregan versiones nuevas del contrato |
| `vault/meta/actas/` ya unidas a `main` | Son la transcripción de lo que se dijo. Se corrige con una acta nueva, no reescribiendo |
| `CLAUDE.md` y `vault/meta/convenciones.md` | Cambian las reglas para los dos |
| `vault/meta/instrucciones/` | Documentos que gobiernan el trabajo; una versión nueva se archiva al lado |

## Quién está en qué

Cada tarea abre una nota en `vault/meta/trabajo/` (plantilla `plantilla-tarea-en-curso`) con dueño, rama y
qué notas toca. Al iniciar sesión, el hook `.claude/hooks/session-start.sh` trae lo último de origin, lista
esas tareas y valida el vault, para que ningún agente trabaje sobre una copia vieja ni duplique trabajo.

Reparto recomendado: por área, no por archivo (por ejemplo, una persona la Fase 6 y la otra las specs del
Módulo A).

Obsidian no se actualiza solo. Para ver lo que unió la otra persona: `git pull`, o el plugin de la
comunidad *Obsidian Git* configurado **solo para traer** cambios cada pocos minutos, nunca para hacer
commit o push automáticos (escribiría directo en `main`).

## Cómo se evitan los choques

Los choques vienen de dos ramas que tocan el mismo renglón o toman el mismo número. Las reglas:

- **Deuda de decisión, una nota por fila.** Desde el 2026-11-20 las decisiones pendientes nuevas van en
  `vault/meta/deuda/DD-NNN.md`, con la plantilla `plantilla-deuda-de-decision`. La tabla
  `deuda-de-decision.md` queda para las filas que ya existían y se cierra poco a poco.
- **Números.** Antes de tomar un `DD-NNN` o un `C-NN` se hace `git pull` y se toma el siguiente libre. Si
  dos ramas toman el mismo, la CI lo detecta y **la segunda en unirse se renumera**.
- **El índice** (`meta/indice.md`) se edita solo para agregar renglones al final de su sección. Si chocan
  dos renglones, se quedan los dos.
- **Un acta por sesión, a nombre de quien la tuvo.** Si las dos personas tienen sesión el mismo día, son
  dos actas con tema distinto en el nombre.

## Qué valida la CI

`.github/workflows/validar-vault.yml` corre `herramientas/validar-vault/validar.py` en cada PR y en cada
push a `main`. Falla si:

1. una nota no tiene `estado`, `grado`, `fuente`, `fecha` y `dueño`, o su estado no es
   `borrador | vigente | superseded`;
2. un enlace `[[nota]]` no lleva a ninguna nota;
3. un `C-NN` o un `DD-NNN` está definido dos veces.

Los errores que ya existían al crearla están en `herramientas/validar-vault/excepciones.txt`. Esa lista
solo se achica.

## Specs

`vault/specs/` es la carpeta que más va a crecer. Una spec por función del inventario, con su ID en el
nombre (`F-A09-resumen-del-pedido.md`), usando `plantilla-spec-openspec`. Cada criterio de aceptación
enlaza su cita del contrato y, cuando exista, el test que lo acredita.

## Pendiente de esta fase

`SIN RESPUESTA — bloquea: Fase 7 completa`. Faltan, en sus propias notas: duración y tamaño del sprint,
definición de listo y de terminado, ciclo openspec + TDD, protocolo de entrega al cliente y cadencia
semanal (`prompt-maestro-01-fundacion-v5`, Fase 7).
