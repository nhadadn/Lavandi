---
estado: borrador
grado: D
fuente: formas-de-trabajo/protocolos-con-el-cliente.md (B); NSV-2026-001-v8.pdf:7:Cláusula IV (A); acta-2026-09-07-fundacion.md C-37 (C)
fecha: 2026-11-17
dueño: Nadir Hadad Navarrete
---
# Plantilla — Notificación de entrega

**Cuándo se usa.** Cuando un entregable cumple todos sus criterios del A.4. Antes se revisa la tabla de
trazabilidad: notificar algo incompleto le da al cliente una observación fundada.

**Quién la envía.** Nadir, por correo, con un WhatsApp el mismo día que avisa del correo. Claude la redacta.

**Regla de redacción.** La de la nota ejecutiva (C-37): todo ejecutivo, sin jerga y sin números de cláusula.

---

## Mensaje (esto es lo que se copia y se envía)

**Asunto:** Entrega de [nombre del entregable]

Hola [nombre del interlocutor],

Te entregamos [nombre del entregable]. Con esto queda completo lo que acordamos para esta parte:

- **[Lo que se acordó, en palabras simples]:** [dónde verlo o cómo abrirlo].
- **[…]:** […].

**Cómo revisarlo:** [pasos cortos para comprobar cada punto].

Tienes diez días hábiles, hasta el [fecha de vencimiento], para enviarnos por escrito cualquier observación sobre lo
que se entrega. Si en ese plazo no recibimos observaciones, el entregable queda aceptado, como lo acordamos en el
contrato.

Quedo atento,
Nadir

---

## Lo que se archiva

Nota en `cliente/notificaciones-de-entrega/AAAA-MM-DD-<entregable>.md`, con este frontmatter, y junto a ella el
correo exportado a PDF con el mismo nombre:

```
estado: vigente
grado: B
fuente: <criterios de trazabilidad-criterio-test.md que se acreditan>
fecha: AAAA-MM-DD
dueño: Nadir Hadad Navarrete
enviada: AAAA-MM-DD hh:mm, por correo, a <interlocutor>; aviso por WhatsApp a las hh:mm
vence: AAAA-MM-DD (10 días hábiles: de lunes a viernes, sin días de descanso obligatorio)
```
