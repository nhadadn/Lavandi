---
name: cierre-de-sesion
description: Cierra la sesión diaria de desarrollo de 23:50 a 24:00. Registra las horas por función, pone al día la spec, la trazabilidad, el estado y el acta si hubo declaraciones, corre las verificaciones del vault y deja todo en un commit en una rama. Úsala cuando Arthur diga "cerramos", "terminamos por hoy", "cierra la sesión", "ya es tarde" o al final de cualquier sesión de desarrollo.
---

# Cierre de sesión

Atiende los últimos 10 minutos de la sesión (`vault/formas-de-trabajo/sprint-y-sesion-diaria.md`). El objetivo es
que mañana se pueda arrancar sin reconstruir nada de memoria.

## Procedimiento

1. **Lo que hicieron los agentes.** Junta sus reportes de traspaso (`vault/formas-de-trabajo/equipo-de-agentes.md`).
   Lo que no tenga evidencia grado B (la ruta del test y su salida en verde) se reporta como "en curso", nunca como
   hecho.
2. **Las horas.** Agrega a `vault/formas-de-trabajo/registro-de-horas.md` una fila por función del inventario en
   que se trabajó: fecha, sprint, función, tamaño estimado, horas de la sesión, horas acumuladas y si quedó
   terminada. **Si no sabe cuántas horas fueron, se las pregunta a Arthur.** No las estima.
3. **La spec y la trazabilidad.**
   - En `tareas.md`, marca las tareas cerradas, cada una con la ruta de su test.
   - Si se fusionó algo que toca un criterio del A.4, se lo pasa al `verificador-de-aceptacion`.
4. **El acta.** Si Arthur declaró o decidió algo en la sesión, usa la skill `asentar-acta`.
5. **El estado y la deuda.** Si cambió el estado de algo, se cambia en `vault/meta/estado-del-proyecto.md`. Si
   salió una pregunta sin respuesta, va como fila en `vault/meta/deuda-de-decision.md`.
6. **Las verificaciones.** Corre `python herramientas/ci/verificar_vault.py` y
   `python herramientas/tablero/construir_tablero.py`. Si fallan, se corrige antes del commit.
7. **El commit.**
   - En una rama de trabajo y **nunca en `main`**.
   - El mensaje sigue `vault/formas-de-trabajo/repositorios-ramas-y-revision.md`, sin ninguna atribución a Claude.
   - Se hace con la skill `git-lavandi`.
   - **No se hace push si Arthur no lo pide.**
8. **Para mañana.** Tres líneas:
   - lo que quedó a medias;
   - lo primero que se hace;
   - lo que se espera de Nadir o del cliente.

## Lo que no hace

- Fusionar PR. Eso lo hace Arthur con el dictamen del revisor.
- Estimar horas que nadie midió.
