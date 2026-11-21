---
name: cierre-de-sprint
description: Cierra el sprint semanal, normalmente el domingo. Verifica los criterios de aceptación, prepara la descripción del PR semanal hacia Nadir, pide al redactor el borrador del reporte semanal al cliente, suma las horas contra lo comprometido y deja el plan del siguiente sprint. Úsala cuando Arthur diga "cierra el sprint", "es domingo", "el PR para Nadir", "cómo nos fue esta semana" o al terminar la última sesión de la semana.
---

# Cierre de sprint

Atiende el domingo del sprint (`vault/formas-de-trabajo/sprint-y-sesion-diaria.md`) y el PR por sprint hacia Nadir
(C-158).

## Procedimiento

1. **La verificación.** El `verificador-de-aceptacion` pasa por la tabla de trazabilidad. De ahí salen:
   - qué criterios avanzaron, con su evidencia;
   - qué entregables ya se podrían notificar.
2. **La demostración.** Lista lo que Arthur debe mostrar en el teléfono y grabar: lo que se fusionó en la semana y
   lo que solo se comprueba en un teléfono real. Las grabaciones de criterios del A.4 van a
   `vault/evidencias/<capacidad>/`.
3. **El PR semanal hacia Nadir.** Redacta la descripción con:
   - las specs y los escenarios que avanzaron;
   - los criterios del A.4 que cambiaron de estado;
   - lo que quedó pendiente;
   - las horas de la semana.

   **El PR lo abre Arthur**, desde el `main` de su fork. Claude no hace push al repositorio de Nadir. La descripción
   va sin ninguna atribución a Claude, con el formato de la skill `git-lavandi` (paso 7).
4. **El reporte semanal.** El `redactor-cliente` hace el borrador para Nadir con la plantilla
   `vault/plantillas/plantilla-reporte-semanal-cliente.md`. Lo aprueba y lo envía Nadir.
5. **Las horas.** Suma las de `vault/formas-de-trabajo/registro-de-horas.md` y las compara contra las 11 h
   comprometidas.
   - **Al cerrar el sprint 2**, compara las horas reales por función contra las bandas de S-006. Propone el ajuste
     en `vault/riesgos-supuestos/supuestos.md` y el tamaño nuevo de la etapa 1.
   - Si la banda real es la media o peor, se dice: **"no está listo"**, con el número.
6. **Qué cambiamos.** Tres líneas en el plan del sprint que cierra: qué funcionó, qué no y qué se cambia.
7. **El siguiente sprint.** Crea `vault/formas-de-trabajo/sprints/sprint-NN.md` con la plantilla
   `vault/plantillas/plantilla-sprint.md`. Propone tareas que cumplen la Definition of Ready, hasta 11 h. **Arthur
   aprueba el lunes.**
8. **El commit** del vault: `Sprint N: <qué cerró>`, en una rama de trabajo. No se hace push si Arthur no lo pide.

## Lo que no hace

- Enviar el reporte al cliente.
- Abrir el PR hacia Nadir.
- Notificar entregas: eso es de Nadir, con el protocolo de `vault/formas-de-trabajo/protocolos-con-el-cliente.md`.
