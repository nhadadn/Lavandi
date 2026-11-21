---
estado: borrador
grado: mixto
fuente: NSV-2026-001-v8.pdf:5-8:Cláusulas II, III.A, IV y :11-12:Cláusulas XII y XIV (A); acta-2026-09-07-fundacion.md C-26 y C-37 (C); acta-2026-11-12-traspaso-del-proyecto.md C-132 y C-133 (C); deuda-de-decision.md DD-050 (B); cliente/ (B)
fecha: 2026-11-17
dueño: Nadir Hadad Navarrete
---
# Protocolos con el cliente

**Borrador para que Nadir lo apruebe (DD-074).** Todo lo que toca a EL CLIENTE es de Nadir: las Órdenes de
Cambio, los avisos y los envíos (C-133). Claude redactó esta propuesta en la Fase 7 y Arthur la entrega a Nadir.

## Reglas para todos los mensajes

- **Quién hace qué:**
  - Claude redacta;
  - Arthur pone lo técnico: avance, horas y bloqueos;
  - **Nadir aprueba y envía.** Nada sale hacia EL CLIENTE sin su aprobación en sesión (CLAUDE.md §5).
- **A quién:** a Alfredo Navarrete, el interlocutor único (C-26).
- **Cómo se escribe:** todo ejecutivo, sin jerga técnica y sin números de cláusula (C-37;
  `plantillas/plantilla-nota-ejecutiva-cliente.md`).
- **Por dónde:** los avisos formales van **por correo**, porque el correo da fecha cierta y deja copia. Se manda
  además un WhatsApp corto que avisa del correo, porque WhatsApp es el canal real con el cliente (actas del 12, 20 y
  30 de septiembre). DD-050 ya lo pedía así: "Va por correo, no por WhatsApp".
  - `SIN RESPUESTA — bloquea: los avisos formales por correo:` en el vault no consta el correo de Alfredo (DD-074).
- **Cómo se archiva:** cada mensaje enviado se guarda como nota en su carpeta de `vault/cliente/`. Lleva
  `estado: vigente` y un campo `enviada:` con la fecha, la hora, el canal y el destinatario. Junto a la nota va el
  correo exportado a PDF, con el mismo nombre.

## Los cinco mensajes

| Mensaje | Cuándo | Prepara | Firma y envía | Plantilla | Se archiva en |
|---|---|---|---|---|---|
| **Reporte semanal** | Cada semana del desarrollo (A, Cláusula III.A) | Claude, con el cierre del sprint de Arthur | Nadir | `plantilla-reporte-semanal-cliente.md` | `cliente/reportes-semanales/` |
| **Notificación de entrega** | Cuando un entregable cumple sus criterios del A.4 (A, Cláusula IV) | Claude, con la trazabilidad de Arthur | Nadir | `plantilla-notificacion-de-entrega.md` | `cliente/notificaciones-de-entrega/` |
| **Aviso de retraso** | Al prever un retraso, con 48 h de anticipación por lo menos (A, Cláusula II y III.A) | Claude, con el dato de Arthur | Nadir | `plantilla-aviso-de-retraso.md` | `cliente/comunicaciones/` |
| **Requerimiento escrito** | Cuando falta un insumo del cliente que detiene el trabajo (A, Cláusula XII) | Claude | Nadir | `plantilla-requerimiento-escrito.md` | `cliente/requerimientos/` |
| **Orden de Cambio** | Antes de ejecutar cualquier cambio de alcance (A, Cláusulas IV y XIV) | Claude, con las horas de Arthur | Nadir y EL CLIENTE, los dos | `plantilla-orden-de-cambio.md` | `cliente/ordenes-de-cambio/` |

## Reporte semanal

- **El ciclo:** el domingo Arthur cierra el sprint ([[sprint-y-sesion-diaria]]). El lunes Claude arma el reporte
  con la plantilla, y Nadir lo revisa y lo envía.
- **Qué lleva:**
  - el avance de la semana;
  - lo que sigue;
  - los pendientes del cliente, con sus días de retraso;
  - los trámites del ANEXO B.10;
  - las decisiones que se le piden;
  - los avisos formales de la semana.
- **Hoy (B):** `cliente/reportes-semanales/` está vacía. Empezarlo es la acción 11 de "Lo que sigue" en
  `meta/estado-del-proyecto.md`.

## Notificación de entrega

Protege los pagos 2 y 3. Desde la notificación corren **10 días hábiles**. Si en ese plazo no llegan observaciones
escritas, específicas y fundadas, el entregable queda aceptado de pleno derecho y el pago se vuelve exigible
(A, Cláusula IV).

1. **Antes de notificar:** se revisa en [[trazabilidad-criterio-test]] que todos los criterios del entregable estén
   acreditados. Notificar algo incompleto le da al cliente una observación fundada. Es el caso de DD-050: las 12
   plantillas no existen.
2. **Cómo se notifica:** por correo, con lo que se entrega, dónde verlo y cómo comprobar cada criterio. Dice en
   palabras simples que hay 10 días hábiles para enviar observaciones por escrito, y que sin ellas el entregable
   queda aceptado. Ese mismo día se manda el WhatsApp que avisa del correo.
3. **Cómo se acredita la fecha:** la fecha y hora de envío del correo. Se archiva el correo exportado a PDF y la
   nota con su campo `enviada:`. El acuse de recibo se pide, pero el plazo no depende de él.
4. **Cómo se cuenta el plazo:** el contrato no define "días hábiles". Criterio conservador de esta propuesta: de
   lunes a viernes, sin los días de descanso obligatorio. Si hay duda, se toma la fecha más tardía. La nota
   archivada dice la fecha en que vence el plazo.
5. **Qué no es un rechazo válido** (A, Cláusula IV): las preferencias estéticas que no estaban en el ANEXO A, las
   funciones nuevas o los cambios de alcance, las opiniones de terceros y los resultados comerciales. Si llega una
   observación así, se contesta con amabilidad, y lo que sea alcance nuevo se ofrece como Orden de Cambio.

## Aviso de retraso

- **Cuándo:** en cuanto Arthur prevé que una fecha comprometida no se cumple, con 48 h de anticipación por lo menos
  (A, Cláusula III.A).
- **Qué lleva:** qué se retrasa, la causa, la fecha nueva y qué se necesita del cliente, si algo.
- **Hoy (B):** hay un borrador sin enviar del 9 de octubre, `cliente/comunicaciones/2026-10-09-aviso-de-retraso-del-desarrollo.md`.
  Es la acción 4 de "Lo que sigue".

## Requerimiento escrito

Si el cliente no responde ni entrega insumos durante más de 30 días naturales seguidos, eso cuenta como
terminación imputable a él. Pero solo si EL PRESTADOR le requirió por escrito los insumos faltantes **por lo menos
dos veces dentro de ese periodo** (A, Cláusula XII).

1. **Cuándo:** cuando falta un insumo del cliente que detiene trabajo comprometido. Ejemplos: los cinco momentos
   de aviso (DD-011), el modelo de cobro (DD-012) o el alta en Conekta.
2. **Qué lleva:** cada insumo que falta, qué detiene y la fecha en que se pide.
3. **El conteo:** cada requerimiento se numera. La nota del segundo cita la fecha del primero, y una tabla en
   `cliente/requerimientos/` lleva, por insumo, el primer requerimiento, el segundo y los días transcurridos.
4. **Es una palanca contractual.** Cuándo se usa lo decide Nadir. El tono es cordial, sin amenazas: el objetivo es
   el insumo, no la terminación.

## Orden de Cambio

1. **Quién detecta que algo es fuera de alcance:** cualquiera. En la práctica:
   - Arthur, al escribir la spec, cuando un requisito no se puede citar al contrato ([[ciclo-openspec-tdd]]);
   - los agentes, que marcan "fuera de alcance" cuando no encuentran la cita (Fase 8).
2. **Qué se hace en ese momento:**
   1. Se detiene: no se construye.
   2. Se abre una fila `contractual` en la deuda de decisión.
   3. Arthur estima las horas.
   4. Nadir cotiza y negocia.
3. **Nada se ejecuta antes de la firma de los dos** (A, Cláusula IV: "Ninguna modificación al alcance surtirá
   efectos … en tanto no conste en Orden de Cambio debidamente firmada").
4. **La primera candidata** es la Orden de Cambio por etapas de la recalibración, con alcance, calendario e hitos de
   pago por etapa. Es la acción 5 de "Lo que sigue" y depende de DD-068.
