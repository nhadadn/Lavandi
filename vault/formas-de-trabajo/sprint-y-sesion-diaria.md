---
estado: vigente
grado: mixto
fuente: acta-2026-11-17-fase-7-formas-de-trabajo.md C-157 (C); acta-2026-11-12-traspaso-del-proyecto.md C-134 (C); NSV-2026-001-v8.pdf:5:Cláusula II y :6:Cláusula III.A (A); calendario-contractual.md (B); supuestos.md S-006 (S)
fecha: 2026-11-18
dueño: Arthur Naranjo
---
# Sprint y sesión diaria

## El sprint

**Dura una semana, de lunes a domingo.** Arthur lo decidió el 2026-11-17: "menos de la semana, pero dejémoslo en 1
semana, pero ya vamos atrasados" (C-157). Coincide con la comunicación semanal que pide el contrato durante el
desarrollo (A, Cláusula III.A).

**La capacidad:** Arthur trabaja todos los días de 22 a 24 h, unas 14 h por semana, con agentes en paralelo (C-134).

| Concepto | Horas por semana | Por qué |
|---|---|---|
| Tareas comprometidas | **11** | Las tareas de la semana, con sus tamaños de S-006 |
| Arranque, revisión y cierre | 2 | Lo que piden la sesión del lunes y la del domingo |
| Margen | 1 | Imprevistos. Si no se usa, entra la siguiente tarea de la lista |

- Una tarea L (de 8 a 20 h) no cabe: se parte antes de entrar ([[definicion-de-listo-y-terminado]]).
- Las horas son las de Arthur. El trabajo de los agentes no se cuenta aparte: ya está dentro de las bandas de S-006,
  que suponen a Claude Code escribiendo la mayor parte del código.

**La recalibración.** Al cerrar el sprint 2 se comparan las horas reales de [[registro-de-horas]] contra las bandas
de S-006. Con eso se rehace el tamaño de la etapa 1 y se corrige la Orden de Cambio por etapas.

## El calendario no está listo

C-157 lo dice: ya vamos atrasados. Los hechos:

- El contrato ponía el desarrollo en las semanas 4 a 13, del 28 de septiembre al 6 de diciembre de 2026
  (A, Cláusula II; `contrato/calendario-contractual.md`).
- Hoy, 17 de noviembre, es la semana 11 y el desarrollo no ha empezado (B: no existen `movil/` ni `servidor/`).
- La estimación media es de 389 h (S-006). Con 11 h comprometidas por semana son unas 35 semanas.

**No está listo:** el alcance completo no cabe en el calendario del contrato. La ruta es la de la recalibración:
construir por etapas, con alcance y calendario nuevos firmados en una Orden de Cambio. Depende de DD-068 y la
negocia Nadir ([[protocolos-con-el-cliente]]).

**Cuándo empieza el sprint 1:** cerrada la Fase 8, cuando estén las cuentas de prueba (DD-072) y la primera spec.

## La semana

**El plan de cada sprint** vive en `formas-de-trabajo/sprints/sprint-NN.md`, con la plantilla
`plantillas/plantilla-sprint.md`. Lo crea la skill `cierre-de-sprint` y lo usa la skill `arranque-de-sesion`
([[equipo-de-agentes]]).

| Día | Qué pasa |
|---|---|
| **Lunes** | Planeación, en los primeros 20 minutos: se eligen las tareas que cumplen la Definition of Ready, hasta 11 h |
| **Martes a sábado** | Sesiones de trabajo |
| **Domingo** | Cierre del sprint: demostración en el teléfono, grabada; pull request semanal hacia Nadir ([[repositorios-ramas-y-revision]]); insumos del reporte semanal ([[protocolos-con-el-cliente]]); 10 minutos de "qué cambiamos" |

## La sesión de 2 h

| Hora | Qué |
|---|---|
| **22:00–22:10** | **Arranque.** Leer el estado del sprint, elegir la tarea del día y repartir en paralelo a los agentes lo que no depende entre sí, cada uno en su rama |
| **22:10–23:30** | **Trabajo.** Rojo, verde y refactor por escenario ([[ciclo-openspec-tdd]]). Arthur revisa lo que traen los agentes |
| **23:30–23:50** | **Integración.** Pull requests con la integración continua en verde, revisión del agente revisor y fusión a `main` |
| **23:50–24:00** | **Cierre.** Horas registradas; trazabilidad, spec y estado al día; commit del vault |

## Qué entra a mitad del sprint

**Entra:**

1. Un defecto que rompe `main` o un criterio del A.4 ya acreditado.
2. Una respuesta de Nadir o del cliente que desbloquea algo ya comprometido.
3. Un problema de seguridad.

**No entra:** funciones nuevas ni cambios. Van al siguiente sprint. Si no se pueden citar al contrato, son una
posible Orden de Cambio y se le pasan a Nadir ([[protocolos-con-el-cliente]]).

**Si entra algo, sale algo** del mismo tamaño.

## Las dos semanas antes de enviar a las tiendas

El contrato reservaba las semanas 15 y 16 para la revisión de las tiendas (A, Cláusula II). Se conserva la regla
para cada etapa que publique: **en las dos semanas anteriores al envío no entran funciones nuevas**. Solo
correcciones que pidan las tiendas y defectos de criterios del A.4.
