---
name: nueva-decision
description: Registra una decisión pendiente como nota DD-NNN en vault/meta/deuda/ con el siguiente número libre. Úsala cada vez que falte una respuesta para avanzar, que algo quede SIN RESPUESTA, o que surja una pregunta para Nadir o para el cliente.
---

# Nueva decisión pendiente

1. **Actualizar antes de numerar.** `git fetch origin`, para ver lo que la otra persona ya unió.
2. **Siguiente número.** El mayor `DD-NNN` entre la tabla `vault/meta/deuda-de-decision.md` y los archivos
   de `vault/meta/deuda/`, más uno:
   ```bash
   { grep -ohE '^\| DD-[0-9]+' vault/meta/deuda-de-decision.md; ls vault/meta/deuda/; } | grep -oE 'DD-[0-9]+' | grep -oE '[0-9]+' | sort -n | tail -1
   ```
3. **¿Ya existe?** Busca la pregunta en la tabla y en `vault/meta/deuda/`. Si ya está, se actualiza.
4. **Archivo.** `vault/meta/deuda/DD-NNN.md` desde `vault/plantillas/plantilla-deuda-de-decision.md`: la
   pregunta en una línea, quién responde, qué bloquea, opciones y recomendación si las hay.
5. **Marcador.** Donde se necesitaba la respuesta: `SIN RESPUESTA — bloquea: <qué> (DD-NNN)`.
6. **Validar.** Si la CI dice que el número se repite con otra rama, la segunda en unirse se renumera.

Una recomendación no es la decisión. La decisión la toma quien está en `quien-responde`.
