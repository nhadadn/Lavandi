---
name: asentar-acta
description: Asienta en un acta lo que Arthur o Nadir declararon o decidieron, para que valga como evidencia grado C. Transcribe literal, sigue la numeración C-NN, registra las decisiones y abre filas en la deuda de decisión para lo que quedó sin respuesta. Úsala cuando Arthur diga "asiéntalo", "anótalo en el acta", "Nadir dijo…", "decidimos…", cuando responda una pregunta de decisión, o cuando pegue un mensaje de Nadir o del cliente.
---

# Asentar acta

Lo que alguien dice en el chat no es evidencia hasta que está en un acta (CLAUDE.md §3; `vault/meta/convenciones.md`,
sección Actas).

## Procedimiento

1. **El acta del día.** Si ya existe `vault/meta/actas/acta-AAAA-MM-DD-<tema>.md` de la fecha de hoy, se le agrega.
   Si no, se crea con la plantilla `vault/plantillas/plantilla-acta-de-sesion.md`.
2. **El número.** Busca el último `C-NN` en todas las actas y sigue desde ahí. Nunca se reutiliza un número.
3. **La transcripción:**
   - **literal**, entre comillas;
   - se corrige solo la ortografía, sin cambiar el sentido;
   - con quién lo dijo y la fecha;
   - si llegó por un tercero ("Nadir me dijo…"), dice por quién llegó.

   Lo que es interpretación de Claude va fuera de las comillas, y se dice que lo es.
4. **Las decisiones.** Cada decisión va a la tabla de decisiones del acta con:
   - las opciones que se consideraron;
   - quién decide;
   - la nota que cambia.

   Recuerda quién decide qué:
   - lo técnico, Arthur (C-143);
   - lo del cliente, Nadir (C-133).
5. **Lo que quedó sin respuesta** va a la tabla de preguntas abiertas del acta y como fila en
   `vault/meta/deuda-de-decision.md`, con quién responde, qué bloquea y la fecha límite.
6. **Los datos externos.** Un dato que alguien confirmó y antes era grado E sube a C. Va en la tabla final del acta,
   con su fuente original.
7. **Las notas afectadas.** Se actualizan en la misma sesión (ADR, spec, estado), citando el `C-NN`.

## Lo que no hace

- Poner en boca de alguien lo que no dijo.
- Tratar como grado C un mensaje que solo vive en el chat.
- Asentar como decidido lo que alguien solo preguntó.
