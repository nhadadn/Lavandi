---
estado: vigente
grado: C
fuente: sesión de Arthur Naranjo con Claude Code del 2026-11-18, 22:24; dos notas de voz de WhatsApp de las 22:05, dirigidas a Nadir (cliente/recibido/2026-11-18/)
fecha: 2026-11-18
dueño: Arthur Naranjo
---
# Acta — 2026-11-18 — Pedidos de noche

Participantes: Arthur Naranjo · Claude Code.
Continúa la numeración de [[acta-2026-11-18-entorno-y-mocks]], que llega hasta C-177.

## Declaraciones

- C-178. **C (Arthur).** "Me pasaron estos audios para Lavandi, a ver si puedes entender qué necesita." Son dos notas
  de voz de las 22:05, dirigidas a Nadir. **Quién habla no consta**: por lo que dice, alguien que platica con
  lavanderías, del lado del cliente. La transcripción está en `cliente/recibido/2026-11-18/indice.md`.
- C-179. **C por confirmar (voz dirigida a Nadir, pasada por Arthur).** Lo que propone quien habla, en sus palabras:
  - "Con que dejen prendida la app, ahí les van a pedir."
  - "Les vamos a dar un día para que resuelvan lo de esa ropa."
  - "Prácticamente la lavandería decidiría, por decir, […] de ocho de la noche a ocho de la mañana: pues que la app
    sí está funcionando y la lavandería no."
  - "Puede haber gente que pide el servicio de Lavandi a la una de la mañana porque no tiene tiempo en el transcurso
    del día, y pues ya Lavandi le puede decir: '¿Sabes qué? Pues [pasado mañana] la van a lavar aquí; tu ropa, pasan
    por ella a las nueve de la mañana'."

  No es una decisión: es una regla de operación que alguien les explicó a lavanderías. Para usarla en la spec, el
  cliente la confirma por escrito (DD-078).
- C-180. **C (Arthur).** "Búscame las opciones para meterle los pagos con tarjeta a la app; se descarta Conekta."
  No dijo quién lo decidió ni por qué. **Conekta es grado A**: el contrato la nombra en la p. 16 (A.3 #10), la p. 19
  (la pasarela y la conciliación del panel), la p. 20 (los datos de tarjeta los resguarda Conekta), la p. 21 (el
  criterio de aceptación del A.4.3) y el ANEXO B.6. Cambiarla exige una Orden de Cambio firmada por los dos
  (Cláusula IV). Mientras no exista, el `ADR-0010` sigue vigente; se buscaron las alternativas para que Nadir
  negocie con datos (DD-079).

## Lo que toca

- **El contrato (A):** la recolección se programa con "selector de fecha y hora, con ventanas de 1 hora"
  (`NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3`, F-A07). El horario, la anticipación, el cupo y la ventana de
  entrega no están definidos (BR-17). La regla del audio contesta parte de BR-17.
- **El kit para sumar lavanderías (B):** ya promete "Usted manda en su horario: decide qué días y a qué horas recibe
  pedidos". La regla del audio va en la misma dirección.
- **El design system (B):** la tarjeta de lavandería tiene el estado "Cerrada: acción deshabilitada; se anuncia la
  próxima apertura" (`design-system/componentes.md`, componente 1). Con la regla del audio, una lavandería cerrada
  sí acepta pedidos, para la siguiente ventana. Cambia el comportamiento del componente.
- **Los avisos (B):** si la lavandería deja "prendida la app" de noche, el pedido le llega con la app cerrada. Es
  BR-49 y DD-054: avisarle a la lavandería de un pedido nuevo.

## Decisiones tomadas en la sesión

Ninguna. La regla se archiva y se convierte en preguntas.

## Preguntas abiertas que salieron de la sesión

| # | Pregunta | Bloquea | Fila en `deuda-de-decision.md` |
|---|---|---|---|
| 1 | ¿Quién habla en los audios, y el cliente confirma la regla por escrito? | Usar la regla en la spec de agenda | DD-078 |
| 2 | "Un día para lavar": ¿24 horas o el día hábil siguiente? ¿Contado desde la recolección? ¿Vale para todos los servicios, también la tintorería? | La fecha de entrega que se le promete al usuario | DD-078 |
| 3 | ¿El usuario elige la ventana de recolección dentro del horario de la lavandería, o la app le propone la primera disponible? | La pantalla de fecha y hora (F-A07) | DD-078 |
| 4 | ¿Cuándo debe aceptar la lavandería un pedido de noche: al abrir o antes de la recolección? | El plazo para aceptar (BR-26) | DD-078 |
| 5 | ¿Fines de semana y días festivos? | El cálculo de fechas | DD-078 |
| 6 | ¿Quién decidió descartar Conekta y por qué? ¿El cliente acepta la Orden de Cambio? | Cambiar de pasarela; el criterio A.4.3 y el ADR-0010 | DD-079 |
