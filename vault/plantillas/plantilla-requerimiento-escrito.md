---
estado: borrador
grado: D
fuente: formas-de-trabajo/protocolos-con-el-cliente.md (B); NSV-2026-001-v8.pdf:12:Cláusula XII (A); acta-2026-09-07-fundacion.md C-37 (C)
fecha: 2026-11-17
dueño: Nadir Hadad Navarrete
---
# Plantilla — Requerimiento escrito de insumos

**Cuándo se usa.** Cuando falta un insumo del cliente que detiene trabajo comprometido. Para que 30 días sin
respuesta cuenten como abandono del cliente, hacen falta por lo menos dos requerimientos escritos dentro de ese
periodo. Cuándo se usa lo decide Nadir.

**Quién lo envía.** Nadir, por correo, con un WhatsApp el mismo día que avisa del correo. Claude lo redacta.

**Tono.** Cordial y concreto, sin amenazas. El objetivo es el insumo, no la terminación.

---

## Mensaje (esto es lo que se copia y se envía)

**Asunto:** Lo que necesitamos para seguir con [qué se detiene]

Hola [nombre del interlocutor],

Para seguir con [qué se detiene, en palabras simples] necesitamos que nos envíes:

1. **[Insumo]:** [qué es exactamente y en qué forma lo necesitamos].
2. **[…]:** […].

Hoy esto detiene [qué no puede avanzar]. [Solo en el segundo requerimiento: Te lo pedimos por primera vez el
[fecha del primero].]

¿Nos lo puedes enviar a más tardar el [fecha]?

Gracias,
Nadir

---

## Lo que se archiva

Nota en `cliente/requerimientos/AAAA-MM-DD-requerimiento-NN.md`, con el correo exportado a PDF al lado:

```
estado: vigente
grado: B
fuente: <filas de deuda-de-decision.md que el insumo resuelve>
fecha: AAAA-MM-DD
dueño: Nadir Hadad Navarrete
enviada: AAAA-MM-DD hh:mm, por correo, a <interlocutor>; aviso por WhatsApp a las hh:mm
requerimiento: NN (primero o segundo de este insumo)
primero: AAAA-MM-DD (solo en el segundo)
```

Y se actualiza la tabla de `cliente/requerimientos/`: por insumo, la fecha del primer requerimiento, la del segundo
y los días transcurridos.
