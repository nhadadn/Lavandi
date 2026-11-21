---
name: cerrar-sesion
description: Deja el vault al día antes de terminar. Úsala sin que nadie la pida al terminar cualquier trabajo que haya cambiado algo en el repo, cuando se tomó o respondió una decisión, cuando el hook de cierre avisa que el vault tiene cambios sin commit o errores, y cuando el usuario dice "cierra", "guarda", "sube" o se despide.
---

# Cerrar la sesión

Regla de `CLAUDE.md` §0-bis (C-204, C-207): lo que pasó en la sesión queda en el repo antes de terminar.
Este procedimiento la ejecuta. No espera a que un humano lo pida.

## Pasos, en orden

1. **Qué cambió.** `git status` y `git diff --stat`. Repasa la conversación: ¿hubo una decisión, una
   respuesta de Nadir o del cliente, un dato nuevo, un hallazgo, un artefacto publicado?
2. **Quién habló.** Si la sesión no sabe con certeza quién está escribiendo (CLAUDE.md §0-bis, paso 0;
   C-220), no asienta declaraciones grado C: las deja como pregunta para la siguiente sesión.
3. **Archivar lo que falte.**
   - Declaración de Nadir o de Arthur (las dos son grado C, C-211) → acta del día en `vault/meta/actas/` (`plantilla-acta-de-sesion`), con el
     siguiente `C-NN` libre y el nombre de quien habló: `- C-NN (Nombre). …`. Literal, no interpretada.
   - Pregunta que quedó sin respuesta → nota `DD-NNN` con la skill `nueva-decision`.
   - Decisión técnica → ADR en `vault/arquitectura/adr/`.
   - Artefacto publicado (lienzo, página) → nota del vault que lo enlaza y dice qué es.
   - Dato externo → grado E con fuente, fecha y `SIN VERIFICAR`.
4. **Deuda respondida.** Si la sesión contestó una `DD-`, actualiza su estado con el `C-NN` o el ADR.
5. **Índice y enlaces.** Cada nota nueva queda enlazada desde `vault/meta/indice.md` o desde su nota madre.
6. **Tarea.** Actualiza la nota de la sesión en `vault/meta/trabajo/`: qué se hizo y, si terminó,
   `situacion: terminada` con el enlace al pull request.
7. **Validar.** `python herramientas/validar-vault/validar.py`. Con errores nuevos no se sigue: se corrigen.
8. **Commit y push.** En la rama de la sesión, nunca en `main`. Mensaje `wip(fase-N): <qué>` durante el
   cimiento, en español. Si el push falla, se dice con el error exacto y no se reporta como hecho.
9. **Pull request.** Si la rama no tiene PR, se propone al usuario abrirlo. No se abre sin que lo pida.
10. **Reporte.** En el mensaje final: qué notas cambiaron, qué quedó pendiente y para quién.

## Lo que esta skill no hace

- No inventa una decisión para cerrar una deuda.
- No reescribe actas ya unidas a `main`: corrige con una acta nueva.
- No toca `legal/`.
