---
name: arranque-de-sesion
description: Arranca la sesión diaria de desarrollo de 22:00 a 22:10. Lee el estado del sprint, elige las tareas que cumplen la Definition of Ready y reparte el trabajo entre los agentes del proyecto en paralelo. Úsala cuando Arthur diga "arrancamos", "empecemos", "qué hacemos hoy", "abre la sesión" o al inicio de cualquier sesión de desarrollo.
---

# Arranque de sesión

Atiende los primeros 10 minutos de la sesión de 2 h (`vault/formas-de-trabajo/sprint-y-sesion-diaria.md`). El
objetivo es que a las 22:10 los agentes ya estén trabajando.

## Procedimiento

1. **El estado.** Lee y resume en cinco líneas:
   - el plan del sprint en curso (`vault/formas-de-trabajo/sprints/sprint-NN.md`): qué está comprometido, qué está
     hecho y cuántas horas van;
   - lo que quedó pendiente en el último cierre de sesión;
   - las filas de `vault/meta/deuda-de-decision.md` que se cerraron desde la última sesión y desbloquean algo.
2. **El repositorio.** La rama actual, si hay cambios sin commit y los PR abiertos. **Si está en `main` con
   cambios, se avisa antes de seguir.**
3. **Las tareas del día.** De las comprometidas en el sprint, elige las que cumplen la Definition of Ready
   (`vault/formas-de-trabajo/definicion-de-listo-y-terminado.md`). Si una no la cumple, dice qué le falta y no la
   propone.
4. **El reparto.** Separa lo que no depende entre sí y asigna un agente a cada pieza:

   | Si la tarea… | Agente |
   |---|---|
   | no tiene spec, o su spec no tiene escenarios | `especificador` |
   | toca el servidor, el panel o la landing | `implementador-servidor`, en su worktree |
   | toca la app | `implementador-movil`, en su worktree |
   | es un PR abierto sin dictamen | `revisor` |
   | tocó un criterio del A.4 ya fusionado | `verificador-de-aceptacion` |

   **Dos agentes no trabajan sobre el mismo archivo a la vez.** Si dos tareas tocan lo mismo, van en serie.
5. **El plan, a Arthur.** En una tabla corta: tarea, agente, rama y lo que se espera al final de la sesión. **Se
   lanza cuando Arthur dice que sí.**

## Lo que no hace

- Comprometer tareas nuevas a mitad del sprint, salvo las que permite `sprint-y-sesion-diaria.md`.
- Lanzar agentes sobre tareas con una fila abierta que las bloquee.
