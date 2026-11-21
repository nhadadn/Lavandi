---
estado: borrador
grado: mixto
fuente: NSV-2026-001-v8.pdf:4:Cláusula I, :5-6:Cláusula II, :6:Cláusula III, :7-8:Cláusula IV, :8:Cláusulas V y VI, :15-23:ANEXO A y :29-34:ANEXO B (A); contrato/inventario-funcional.md, trazabilidad-criterio-test.md, sprint-y-sesion-diaria.md, deuda-de-decision.md y brecha-ejecutivo-tecnico.md (B); acta-2026-11-12-traspaso-del-proyecto.md C-134, acta-2026-11-17-fase-7-formas-de-trabajo.md C-157 y C-158, acta-2026-11-19-normativa-y-arranque.md C-181, C-182 y C-185 (C); supuestos.md S-006 (S); orden, reparto por sprint y fechas, cálculo de Claude del 2026-11-19
fecha: 2026-11-19
dueño: Arthur Naranjo
---
# Plan de sprints: las fases de seguimiento

Arthur pidió "las fases de seguimiento, sprints, del cómo se va a comenzar a trabajar" (C-185), empezando por la app
(C-182) y sin salirse del contrato (C-181, `CLAUDE.md` §2-bis). Es una **propuesta de Claude**: la aprueba Arthur y se
rehace al cerrar el sprint 02, con las horas reales (recalibración de S-006, [[sprint-y-sesion-diaria]]).

## En corto

1. **Doce hitos y 36 sprints.** El hito H0 es el arranque (sprint 00, del 19 al 22 de noviembre). Los hitos H1 a H11
   reparten las 76 funciones del inventario, en el orden del pedido: entrar, encontrar, pedir, pagar, atender, seguir.
2. **Con la banda media de S-006 son 389 horas.** Con 11 horas por semana (un supuesto: C-157 fija el sprint de una semana, no las horas), el último sprint, el 35,
   termina el **25 de julio de 2027**.
3. **No está listo.** El calendario del contrato termina el 27 de diciembre de 2026. Para entonces caben **61 horas**:
   el 16 % del alcance. **Faltan 328 horas**, unos 30 sprints. Lo que corresponde es el aviso de retraso (DD-064) y
   la Orden de Cambio por etapas (DD-068).
4. **Primeros resultados que se pueden enseñar:**
   - el usuario se registra y entra, al cerrar el sprint 02 (6 de diciembre);
   - pide y paga en sandbox, al cerrar el sprint 10 (31 de enero de 2027), si el cliente decide a tiempo el modelo de
     cobro y la pasarela;
   - el recorrido completo en local, con la lavandería atendiendo y el usuario siguiendo su pedido, al cerrar el
     sprint 17 (21 de marzo de 2027).
5. **Lo que más pesa** es la administración con los indicadores: 101 horas, diez sprints (H9).
6. **Al especificar, las horas salieron al doble.** Las 26 specs de OpenSpec sumaron 810 horas el 2026-11-19 (C-186), y
   las 27 re-propuestas el 2026-11-20 suman 878, con la misma banda media, contando las tareas bloqueadas, contra las 389 de este plan. El plan pesó cada función una vez;
   las specs la parten en tareas del servidor y de la app, de 2 horas como mínimo, y suman las pruebas en el teléfono
   y en los navegadores. Con 11 horas por semana, 878 horas terminarían hacia junio de 2028. La cuenta suma los tamaños de cada
   `openspec/changes/*/tasks.md`. **No está listo:** el
   número que vale es el de las horas reales, que se mide en los sprints 00 a 02, y con él se rehace este plan el 6 de
   diciembre. Detalle por fase: `notas-locales/lavandi-specs-por-fase.pdf`, que no se versiona.
7. **El orden no cambia el alcance.** Se construyen las 76 funciones del contrato. Lo que se mueve son las fechas, y
   eso solo vale con Orden de Cambio firmada (`NSV-2026-001-v8.pdf:8:Cláusula IV`).

## Reglas del plan

- **El sprint** dura de lunes a domingo, con 11 horas de tareas, 2 de arranque y cierre y 1 de margen. C-157 fija la semana; las
  horas no tienen acta y son un supuesto ([[sprint-y-sesion-diaria]]). Cierra el domingo con el pull request semanal hacia Nadir (C-158).
- **Las horas** son las de Arthur, con la banda media de S-006, que se estimó con las horas de Nadir; aplicarla a las
  de Arthur es un supuesto hasta medirlas: S = 2, M = 5 y L = 12. Una L se parte antes de entrar
  ([[definicion-de-listo-y-terminado]]).
- **Cada cambio de OpenSpec** vive en `openspec/changes/<cambio>/` (acta del 2026-11-19, decisión 3), con sus
  funciones del inventario y su cita A. Un requisito sin cita no entra (`CLAUDE.md` §2-bis).
- **Si un hito se traba** por una fila abierta de la deuda, no se espera: entra el siguiente trabajo sin bloqueo. El
  colchón natural es la landing (H10), que se construye en local sin dominio, y el Módulo B (H5).
- **Las dos semanas antes de enviar la app a las tiendas** no entran funciones nuevas ([[sprint-y-sesion-diaria]]).
  Por eso los sprints 34 y 35 son solo de publicación.
- **El plan se rehace** al cerrar el sprint 02 (6 de diciembre), con las horas reales de [[registro-de-horas]].

## La cuenta contra el contrato

El contrato ponía el desarrollo en las semanas 4 a 13, hasta el 6 de diciembre; el envío a las tiendas en la semana
14 y la entrega formal en la semana 16, el 27 de diciembre (`NSV-2026-001-v8.pdf:5:Cláusula II`,
[[calendario-contractual]]).

| Banda de S-006 | Horas del alcance | Sprints después del 00 | El último sprint termina | Hechas al 27 dic 2026 | Faltan al 27 dic 2026 |
|---|---|---|---|---|---|
| Optimista | 240 | 22 | 2027-04-25 | 61 | 179 |
| **Media** | **389** | **35** | **2027-07-25** | **61** | **328** |
| Conservadora | 629 | 57 | 2027-12-26 | 61 | 568 |

- **Las 61 horas:** 6 del sprint 00 más 5 sprints de 11 horas, del 23 de noviembre al 27 de diciembre.
- **Si los "tres meses" de C-136 se cuentan desde hoy** (DD-068), terminan a mediados de febrero de 2027. Al cerrar el
  sprint 12, el 14 de febrero, van 138 horas: el usuario pide y paga en sandbox (H4), pero la lavandería todavía no
  recibe pedidos (H5).
- **Si Arthur comprometiera sus 14 horas completas** (C-134), sin cierre ni margen, la banda media terminaría el 6 de
  junio de 2027. No se propone: el cierre del domingo es el que alimenta el reporte semanal que pide el contrato
  (`NSV-2026-001-v8.pdf:6:Cláusula III`).
- **Lo que no entra en la cuenta:** el tiempo de revisión de las tiendas y los 30 días de operación. Los dos van
  después del sprint 35, y no son horas de Arthur. Tampoco entra lo de la sección "Lo que no está en las 389 horas".

**No está listo.** Con la banda media, la app se envía a las tiendas a fines de julio de 2027. El pago 2 llegaría
después de la revisión de las tiendas, y el pago 3, treinta días más tarde (`NSV-2026-001-v8.pdf:8:Cláusula VI`).
Toca a Nadir: el aviso de retraso (DD-064) y la Orden de Cambio por etapas (DD-068).

## Los hitos

Cada hito termina con algo que se puede enseñar en el teléfono el domingo.

| Hito | Qué se puede ver al terminar | Sprints | Fechas | Horas | Criterios que mueve | Pago al que acerca |
|---|---|---|---|---|---|---|
| H0 · Arranque | `servidor/` y `movil/` creados, con la integración continua en verde | 00 | 19–22 nov 2026 | 6 | Prepara A43-1 | — |
| H1 · La persona entra | Registro, inicio de sesión con correo y con Google, recuperación de la contraseña y bienvenida | 01–03 | 23 nov–13 dic | 26 | SEG-2, A43-6 | 2 |
| H2 · Encuentra su lavandería | Mapa con lavanderías cercanas, filtros y perfil de la lavandería | 03–07 | 7 dic–10 ene 2027 | 45 | A43-2, A43-6 | 2 |
| H3 · Pide | Fecha y hora, dirección, resumen y confirmación con folio y correo | 07–09 | 4–24 ene | 27 | A43-2 | 2 |
| H4 · Paga | Pago con tarjeta y en efectivo en el modo de prueba de la pasarela (Conekta, la del contrato; la elegida si el cliente firma el cambio, DD-079) | 09–10 | 18–31 ene | 12 | A43-2, A43-4 (en sandbox), SEG-4 | 2 |
| H5 · La lavandería recibe | Alta de la lavandería, aprobación desde la administración, pedidos en tiempo real, aceptar o rechazar | 11–14 | 1–28 feb | 39 | SEG-3, A43-6 | 2 |
| H6 · La persona sigue su pedido | Cambios de estado de la lavandería en 60 s o menos y avisos al teléfono: **el recorrido completo en local** | 14–17 | 22 feb–21 mar | 34 | A43-2, A43-3, A43-5 | 2 |
| H7 · La persona vuelve | Historial, calificación, perfil y soporte: **Módulo A completo** | 17–18 | 15–28 mar | 14 | A43-6 | 2 |
| H8 · El repartidor | Módulo C completo, alta de repartidores y lo que faltaba del Módulo B | 18–21 | 22 mar–18 abr | 25 | A43-3, A43-6 | 2 |
| H9 · Administración e indicadores | Módulo D completo, los 11 indicadores y la exportación | 21–30 | 12 abr–20 jun | 101 | A43-6, A43-7, A44-1, A44-2 | 2 |
| H10 · Landing | Las 12 secciones, medición y textos legales | 30–33 | 14 jun–11 jul | 39 | A42-1 a A42-4 | 2 |
| H11 · Publicación | Ambiente de prueba y producción, compilaciones y envío a las dos tiendas | 33–35 | 5–25 jul | 21 | A43-1, A43-4, SEG-1 | 2, y luego 3 |
| | | | | **389** | | |

De los 19 criterios, los cuatro del branding (A41) y los dos del lanzamiento (A45) no son código: no están en ningún
hito ([[trazabilidad-criterio-test]]).

## Cada hito por dentro

### H0 · Arranque — sprint 00

- **Cambio `base-tecnica`:**
  - F-T04, el servidor: "recibiendo y procesando cada pedido, cada inicio de sesión y cada consulta de los usuarios"
    (`NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3`);
  - F-T05, la base de datos (`:20:A.3`);
  - F-T12, una sola app: "un solo programa que funciona tanto en iPhone como en teléfonos Android" (`:16:A.3`).
  - Sostén técnico: ADR-0002, ADR-0003, ADR-0004, ADR-0009 y ADR-0012.
- **Bloqueos:** ninguno. Las cuentas de prueba las abre Arthur con su correo (DD-072, cerrada por C-170).
- **De Nadir o del cliente:** nada.
- Detalle: [[sprint-00]].

### H1 · La persona entra — sprints 01 a 03

- **Cambio `registro-e-inicio-de-sesion`:**
  - F-A02: "Correo y contraseña, con opción de Google Sign-In" (`:16:A.3, Módulo A #2`);
  - F-T07, la sesión abierta "sin necesidad de que escriba su contraseña cada vez" (`:20:A.3`);
  - F-T09, contraseñas "no se guardan en texto legible" (`:20:A.3`);
  - F-T13, el correo "al recuperar una contraseña" (`:30:ANEXO B, Sección B.5`).
  - Sostén técnico: ADR-0005 y ADR-0014.
- **Cambio `bienvenida`:** F-A01, "Pantalla de bienvenida y 3 pantallas informativas sobre el servicio" (`:16:A.3 #1`).
- **Bloqueos:**
  - DD-071: el proveedor de correo, de Arthur. Solo hace falta fuera de local; en local, el correo se revisa sin
    enviarlo.
  - DD-069: el inicio de sesión con Apple se prueba hasta que haya cuenta de Apple.
  - DD-053: solo toca a los socios. El registro del usuario no la espera.
- **Del cliente:** los textos e imágenes de las 3 pantallas informativas son insumo suyo
  (`:6:Cláusula III, inciso B`). Hacen falta antes del sprint 03 (7 de diciembre). Sin ellos, la pantalla se
  construye con contenido configurable y no se puede acreditar.
- Detalle del primer sprint: [[sprint-01]].

### H2 · Encuentra su lavandería — sprints 03 a 07

- **Cambio `plazas-y-zonas`:**
  - F-T05, lo que falta de la base;
  - F-T15, la expansión a plazas nuevas "sin necesidad de reescritura del sistema ni de rediseño de la base de datos"
    (`:4:Cláusula I`).
  - Sostén técnico: ADR-0004 y `arquitectura/modelo-multi-plaza.md`.
- **Cambio `lavanderias-cercanas`:**
  - F-T03, "los mapas de Google en teléfonos Android y los mapas de Apple en iPhone" (`:19:A.3`);
  - F-A03, "Mapa con lavanderías cercanas activas" (`:16:A.3 #3`);
  - F-A04, "Filtros por distancia, calificación y disponibilidad" (`:16:A.3 #4`).
  - Sostén técnico: ADR-0009.
- **Cambio `perfil-de-lavanderia`:**
  - F-T06, las fotos (`:20:A.3`);
  - F-A05, "Fotos, descripción, servicios, precios por kilo, calificación y reseñas" (`:16:A.3 #5`);
  - F-A06, "lavado estándar, lavado con planchado y lavado en seco" (`:16:A.3 #6`).
  - Sostén técnico: ADR-0006.
- **Las lavanderías de este hito son de prueba,** sembradas en la base. El alta real llega en H5.
- **Bloqueos:**
  - BR-15: qué es "cercana", qué es "activa" y qué es "disponibilidad". Es la fila DD-083.
  - BR-16 y DD-010: quién fija el precio y en qué unidad va el lavado en seco.
- **Del cliente:** DD-010 y BR-16 antes del sprint 06 (28 de diciembre). Sin respuesta, el precio queda como dato
  configurable y la spec lleva `SIN RESPUESTA`.

### H3 · Pide — sprints 07 a 09

- **Cambio `agenda-de-recoleccion`:** F-A07, "Selector de fecha y hora, con ventanas de 1 hora" (`:16:A.3 #7`).
  Huecos: BR-17 y DD-078.
- **Cambio `direcciones`:** F-A08, "Ingreso manual o desde mapa. Guardado de direcciones favoritas" (`:16:A.3 #8`).
  Huecos: BR-18 y BR-12.
- **Cambio `resumen-y-confirmacion`:**
  - F-A09, "Servicio, fecha, dirección, precio estimado y política de cancelación" (`:16:A.3 #9`);
  - F-A11, "Número de folio, resumen y notificación push más correo" (`:17:A.3 #11`). El aviso al teléfono se conecta
    en H6, cuando exista F-T02.
- **Bloqueos:**
  - DD-078: los pedidos de noche, que el cliente confirma por escrito;
  - DD-016: la política de cancelación que muestra el resumen;
  - DD-012: cómo se estima el precio antes de pesar;
  - DD-019 y DD-025: el envío.
- **Del cliente:** DD-078 antes del sprint 07 (4 de enero). DD-016, DD-019 y DD-025 antes del sprint 09
  (18 de enero).

### H4 · Paga — sprints 09 y 10

- **Cambio `pago`:**
  - F-A10, "Conekta: tarjeta de crédito o débito y OXXO Pay" (`:16:A.3 #10`);
  - F-T01, el cobro (`:19:A.3`);
  - F-T11, los números de tarjeta "nunca se guardan en la plataforma" (`:20:A.3`).
  - Sostén técnico: ADR-0010.
- **A43-4 solo avanza a `implementado`:** el test corre contra el sandbox. Se acredita en producción en H11.
- **Bloqueos:**
  - DD-012, cuándo se cobra. **Sin ella, el cobro no entra** ([[definicion-de-listo-y-terminado]], punto 2).
  - DD-079, contractual. Mientras no haya Orden de Cambio firmada, la pasarela es Conekta (`CLAUDE.md` §2-bis.3).
  - DD-056, el efectivo si Conekta ya no da OXXO; DD-055, la tarjeta guardada; DD-013, la liquidación.
- **De Nadir y del cliente:** DD-012 y DD-079 antes del sprint 09 (18 de enero): DD-079 por la pantalla de pago
  (pago-E02, con DD-055); el servidor se integra con Conekta sin esperarla. Si no llegan, H4 se salta y entra H5.

### H5 · La lavandería recibe — sprints 11 a 14

- **Cambio `autorizacion-por-rol`:** F-T10, "Un socio lavandería no puede consultar los datos de otro socio, ni los
  de los usuarios que no le han hecho pedidos" (`:20:A.3`).
  - El componente nace en H1 con el rol de usuario (`CLAUDE.md` §9.5). Aquí se completa, cuando aparece el segundo
    rol, con las pruebas contra fugas.
- **Cambio `alta-de-lavanderias`:**
  - F-B01, "Nombre, dirección, foto, servicios, precios y horario" (`:17:A.3, Módulo B #1`);
  - F-D01, "Alta, baja, suspensión y edición de socios" (`:19:A.3, Módulo D #1`);
  - F-B06, "ventanas de disponibilidad" (`:17:Módulo B #6`).
- **Cambio `pedidos-de-la-lavanderia`:** F-B02, "Lista en tiempo real. Aceptar o rechazar con motivo"
  (`:17:Módulo B #2`).
- **Bloqueos:**
  - DD-053: cómo llega cada rol a su módulo;
  - DD-054: el aviso a la lavandería con la app cerrada;
  - DD-078, pregunta 4: cuándo acepta un pedido de noche;
  - DD-022: si la lavandería entrega con su gente.
- **De Nadir y del cliente:** DD-053 y DD-054 antes del sprint 11 (1 de febrero).

### H6 · La persona sigue su pedido — sprints 14 a 17

- **Cambio `estados-del-pedido`:**
  - F-B03, "Recolectado → En proceso → Listo → En proceso de entrega" (`:17:Módulo B #3`);
  - F-A12, "Confirmado → Recolectado → En proceso → Listo → Entregado" (`:17:Módulo A #12`).
  - Sostén técnico: ADR-0007.
- **Cambio `avisos`:**
  - F-T02, "Se utiliza el servicio gratuito de Google" (`:19:A.3`);
  - F-A17, "Confirmación, recolección, listo para entrega y entregado" (`:17:Módulo A #17`).
  - Sostén técnico: ADR-0008.
- **Al cerrar H6, el recorrido del A.4.3 se completa en local:** "elegir lavandería, agendar recolección, pagar y dar
  seguimiento" (`:21:ANEXO A, Sección A.4.3`). En Android; en iPhone, cuando haya cuenta de Apple.
- **Bloqueos:**
  - DD-011, los cinco momentos de los avisos. Bloquea la spec de A43-5.
  - BR-22: los estados de cancelado y rechazado.
  - DD-069 y DD-017: sin cuenta de Apple no hay avisos en iPhone.
- **De Nadir y del cliente:** DD-011 antes del sprint 14 (22 de febrero).
  - Para que la cuenta de Apple del cliente llegue a tiempo, el D-U-N-S debe pedirse a más tardar el 11 de enero de
    2027. El contrato da hasta 30 días para el D-U-N-S y de 1 a 2 semanas más para la cuenta (`:33-34:ANEXO B,
    Sección B.10`).

### H7 · La persona vuelve — sprints 17 y 18

- **Cambio `historial-y-calificacion`:**
  - F-A13, "Lista con filtro por fecha y opción de repetir pedido" (`:17:Módulo A #13`);
  - F-A14, "Escala de 1 a 5 estrellas con comentario opcional" (`:17:Módulo A #14`).
- **Cambio `perfil-y-soporte`:**
  - F-A15, "Nombre, foto, direcciones guardadas, métodos de pago y notificaciones" (`:17:Módulo A #15`);
  - F-A16, "Chat de WhatsApp Business y formulario de reporte" (`:17:Módulo A #16`).
  - Borrar la cuenta desde la app va aquí (ADR-0005).
- **Bloqueos:** DD-052, a quién califica; DD-055, la tarjeta guardada; BR-25, a quién llega el reporte.
- **Del cliente:** el número de WhatsApp Business. Según su formulario, "aún no lo tenemos" (marca en papel del
  2026-11-05, sin confirmar).

### H8 · El repartidor — sprints 18 a 21

- **Cambio `repartidor`:**
  - F-C01 a F-C05: el registro, los pedidos asignados, los estados, el historial y las estadísticas
    (`:18:A.3, Módulo C`);
  - F-D02, el alta de repartidores (`:19:Módulo D #2`).
  - Lo que el Módulo C excluye no se construye: verificación documental, liquidaciones, asignación automática, GPS
    fuera de un pedido activo (`:18:Módulo C`).
- **Cambio `historial-de-la-lavanderia`:** F-B04 y F-B05, el historial y las estadísticas (`:17:Módulo B #4 y #5`).
- **Bloqueos:** DD-014, quién asigna; DD-019, cuánto gana el repartidor; DD-022; DD-013.

### H9 · Administración e indicadores — sprints 21 a 30

- **Cambio `administracion`:** F-D03 a F-D07: usuarios, monitoreo con mapa, comisiones, reportes financieros con la
  conciliación de Conekta y avisos masivos (`:19:A.3, Módulo D`).
  - El panel es web, con Filament (DD-040 cerrada por C-140; ADR-0003).
- **Cambio `indicadores`:** F-I01 a F-I11 (`:22:ANEXO A, Sección A.5`).
- **Cambio `exportacion`:** F-T14, "Descarga de reportes en formato CSV y Excel para cualquier rango de fechas"
  (`:22:A.5`).
- **Bloqueos:**
  - DD-013 y DD-020: el modelo del dinero y la comisión;
  - BR-31 a BR-34: la base de la comisión, la segmentación, qué es un usuario activo y los umbrales del semáforo;
  - DD-021: A43-7 pide "datos reales", y eso exige lavanderías reales.

### H10 · Landing — sprints 30 a 33

- **Cambio `landing`:** F-L01 a F-L12, las 12 secciones (`:15:ANEXO A, Sección A.2`). Sostén técnico: ADR-0011.
- **Cambio `medicion-de-la-landing`:** F-T16, la analítica y los buscadores (`:15:A.2`); F-T17, la velocidad y el
  candado (`:16:A.2` y `:21:A.4.2`).
- **Cambio `textos-legales`:** F-T18. El contrato dice que se incorporan "los documentos que EL CLIENTE le
  proporcione" (`:31:ANEXO B, Sección B.7`).
- **Bloqueos:**
  - DD-023: el dominio comprado; DD-070: el hosting.
  - Las cuentas de analítica, a nombre del cliente (`:34:ANEXO B, Sección B.11`).
  - Los textos del cliente: preguntas frecuentes, testimonios y aviso de privacidad (`:6:Cláusula III, inciso B`).
  - BR-41: a dónde llegan los formularios.
- **Es el colchón del plan.** Lo que no depende del dominio se adelanta cuando otro hito se traba.

### H11 · Publicación — sprints 33 a 35

- **Cambio `publicacion`:**
  - F-T04, lo que falta: el ambiente de prueba y el de producción;
  - F-T08, las comunicaciones cifradas (`:20:A.3`);
  - F-T12, lo que falta: las compilaciones firmadas y el envío a las dos tiendas (`:16:A.3` y `:21:A.4.3`).
  - Sostén técnico: ADR-0012; hosting, DD-070.
- **Lo que acredita:**
  - A43-1, la app entregada para publicarse;
  - A43-4, Conekta en producción;
  - SEG-1, las comunicaciones cifradas.
  - Con eso se pide el pago 2 (`:8:Cláusula VI`). El camino alterno está en `:7:Cláusula IV`.
- **Bloqueos:** los trámites del cliente (`:33-34:B.10`): D-U-N-S, cuentas de Apple y de Google, alta en Conekta y
  aviso de privacidad publicado. También DD-017.
- **Después del sprint 35:** la revisión de las tiendas (el contrato le reservaba dos semanas, `:5-6:Cláusula II`) y
  los 30 días de operación del pago 3 (`:8:Cláusula VI` y `:23:ANEXO A, Sección A.7`).

## Sprint por sprint

Las horas de cada función se reparten en orden, 11 por sprint. El detalle de las tareas se arma cada lunes, con la
plantilla `plantillas/plantilla-sprint.md`.

| Sprint | Del – al | Hito | Cambios de OpenSpec | Funciones | Horas |
|---|---|---|---|---|---|
| 00 | 19–22 nov 2026 | H0 | `base-tecnica`; spec de `registro-e-inicio-de-sesion`; `modelo-de-datos` (13 h, nuevo el 2026-11-20: termina en el sprint 01, antes de la primera migración) | F-T04, F-T05, F-T12 | 6 |
| 01 | 23–29 nov | H1 | `registro-e-inicio-de-sesion`: correo, contraseña y sesión | F-A02, F-T07, F-T09 | 11 |
| 02 | 30 nov–6 dic | H1 | `registro-e-inicio-de-sesion`: Google y recuperación por correo. **Recalibración** | F-A02, F-T07, F-T13 | 11 |
| 03 | 7–13 dic | H1 → H2 | `bienvenida`; `plazas-y-zonas` | F-A01, F-T13, F-T05 | 11 |
| 04 | 14–20 dic | H2 | `plazas-y-zonas`; la migración de `perfil-de-lavanderia` (tarea 1.1, se adelanta: `lavanderias-cercanas` lee sus tablas); `lavanderias-cercanas` | F-T05, F-T03, F-A03 | 11 |
| 05 | 21–27 dic | H2 | `lavanderias-cercanas`. **Termina el calendario del contrato** | F-A03, F-A04 | 11 |
| 06 | 28 dic–3 ene 2027 | H2 | `lavanderias-cercanas`; `perfil-de-lavanderia` | F-A04, F-T06, F-A05 | 11 |
| 07 | 4–10 ene | H2 → H3 | `perfil-de-lavanderia`; `agenda-de-recoleccion`; `direcciones` | F-A05, F-A06, F-A07, F-A08 | 11 |
| 08 | 11–17 ene | H3 | `direcciones` | F-A08 | 11 |
| 09 | 18–24 ene | H3 → H4 | `resumen-y-confirmacion`; `pago` | F-A09, F-A11, F-A10 | 11 |
| 10 | 25–31 ene | H4 | `pago` | F-A10 | 11 |
| 11 | 1–7 feb | H5 | `autorizacion-por-rol` | F-T10 | 11 |
| 12 | 8–14 feb | H5 | `autorizacion-por-rol`; `alta-de-lavanderias` | F-T10, F-B01, F-D01 | 11 |
| 13 | 15–21 feb | H5 | `alta-de-lavanderias`; `pedidos-de-la-lavanderia` | F-B06, F-B02 | 11 |
| 14 | 22–28 feb | H5 → H6 | `pedidos-de-la-lavanderia`; `estados-del-pedido` | F-B02, F-B03 | 11 |
| 15 | 1–7 mar | H6 | `estados-del-pedido` | F-A12 | 11 |
| 16 | 8–14 mar | H6 | `estados-del-pedido`; `avisos` | F-A12, F-T02 | 11 |
| 17 | 15–21 mar | H6 → H7 | `avisos`; `historial-y-calificacion` | F-T02, F-A17, F-A13 | 11 |
| 18 | 22–28 mar | H7 → H8 | `historial-y-calificacion`; `perfil-y-soporte`; `repartidor` | F-A13, F-A14, F-A15, F-A16, F-C01 | 11 |
| 19 | 29 mar–4 abr | H8 | `repartidor` | F-C01, F-D02, F-C02 | 11 |
| 20 | 5–11 abr | H8 | `repartidor`; `historial-de-la-lavanderia` | F-C03, F-C04, F-C05, F-B04 | 11 |
| 21 | 12–18 abr | H8 → H9 | `historial-de-la-lavanderia`; `administracion` | F-B05, F-D03, F-D04 | 11 |
| 22 | 19–25 abr | H9 | `administracion` | F-D04, F-D05 | 11 |
| 23 | 26 abr–2 may | H9 | `administracion` | F-D05, F-D06 | 11 |
| 24 | 3–9 may | H9 | `administracion`; `indicadores` | F-D06, F-D07, F-I01 | 11 |
| 25 a 28 | 10 may–6 jun | H9 | `indicadores` | F-I01 a F-I11 | 44 |
| 29 | 7–13 jun | H9 | `indicadores`; `exportacion` | F-I10, F-I11, F-T14 | 11 |
| 30 | 14–20 jun | H9 → H10 | `exportacion`; `landing` | F-T14, F-L01 a F-L04 | 11 |
| 31 y 32 | 21 jun–4 jul | H10 | `landing` | F-L05 a F-L12 | 22 |
| 33 | 5–11 jul | H10 → H11 | `landing`; `medicion-de-la-landing`; `textos-legales`; `publicacion` | F-L12, F-T16, F-T17, F-T18, F-T04 | 11 |
| 34 | 12–18 jul | H11 | `publicacion`: ambiente de prueba y producción | F-T04, F-T08, F-T12 | 11 |
| 35 | 19–25 jul | H11 | `publicacion`: compilaciones y envío a las tiendas | F-T12 | 9 |

## La re-propuesta del 2026-11-20

Las 26 specs se re-propusieron con el modelo de datos aprobado (C-193), y se sumó `modelo-de-datos`. La tabla de arriba
todavía usa las horas de antes; se recalibra con Arthur.

- **Unas 62 horas más**, contando las bloqueadas: 13 de `modelo-de-datos` y unas 49 de tareas de migración y escenarios
  nuevos (C-188) en los demás cambios.
- **Un cambio de orden:** la migración de `perfil-de-lavanderia` se adelanta al sprint 04, porque `lavanderias-cercanas`
  lee sus tablas.
- **Llaves hacia tablas que nacen después:** `repartidor` le agrega la llave a `pedidos.id_repartidores` y a
  `calificaciones.id_repartidores`; `historial-y-calificacion`, a `calificaciones.id_pedidos`. No se mueve ningún
  sprint por eso.
- **No está listo para H3 y H4 sin DD-079 y DD-055.** Con C-188, el folio solo aparece cuando la pasarela confirma el
  pago. El servidor se integra con Conekta, la del contrato, sin esperar la firma; el pago desde el teléfono espera
  pago-E02 (DD-055, DD-079), y el efectivo, además, DD-056.
- **Escenarios que esperan a un cambio posterior**, ya bloqueados por su fila de la deuda: `pedidos-lavanderia` E15 y E16
  (`dispositivos`), `avisos` E20 y la parte del repartidor de `autorizacion` (tablas de `repartidor`),
  `administracion` E02 (`actividad_usuarios`) e `historial-lavanderia` E03 y E07 (`comisiones`).

## Lo que no está en las 389 horas

El inventario cuenta las funciones de software. Hay trabajo del contrato que no es una función y no tiene dueño ni
sprint:

| Qué | Cita | Estado hoy |
|---|---|---|
| Las 12 plantillas de redes sociales | `:21:A.4.1` (A41-3) | No existen (DD-050) |
| La notificación de entrega del branding | `:7:Cláusula IV` | Sin enviar (DD-050) |
| Perfiles de marca y sesión de capacitación | `:21:A.4.5` (A45-1, A45-2) | Por venir |
| Tres sesiones de consultoría, dentro de los 90 días del lanzamiento | `:22-23:ANEXO A, Sección A.6` | Por venir |
| Hasta 20 horas de soporte en los 60 días tras la publicación | `:23:A.7` | Por venir |
| La revisión de las tiendas y la corrección de rechazos | `:5-6:Cláusula II` y `:23:A.7` | Es tiempo de calendario, no horas de Arthur |

Es la fila DD-085.

## Fuera del contrato: posibles Órdenes de Cambio

Salieron del video, de la libreta, de los audios, del kit, de los formularios y de los mocks. **No entran a ningún
sprint** mientras no haya Orden de Cambio firmada (`CLAUDE.md` §2-bis.2, `:8:Cláusula IV`).

| Qué se pidió o se mostró | De dónde | Lo que dice el contrato | Fila |
|---|---|---|---|
| Cobrar por prenda, con tabulador por prenda | Guía de la reunión y libreta de precios | "precios por kilo" (`:16:A.3 #5`) | DD-010 |
| Foto y descripción de la ropa al pedir | Formulario del 2026-11-05 | No está en el Módulo A (`:16-17:A.3`) | — |
| Beneficio por llevar más prendas | Video del 2026-11-14 | No lo nombra | DD-076 |
| Ver en un mapa dónde va el pedido | Video del 2026-11-14 | Seguimiento por estados (`:17:A.3 #12`); sin GPS continuo (`:18:Módulo C`) | DD-077 |
| Lavado de calzado | Libreta ("Tenis") y C-83 | Tres tipos de servicio (`:16:A.3 #6`) | DD-046 |
| Reparto con Rappi, DiDi, Uber o flotillas; asignación automática | Guía de la reunión | Asignación automática excluida (`:18:Módulo C`) | DD-014 |
| Efectivo contra entrega | Guía de la reunión | Tarjeta y OXXO Pay con Conekta (`:16:A.3 #10`) | DD-056 |
| Otra pasarela en lugar de Conekta | C-180 | Conekta (`:16:A.3 #10`, `:19`, `:21:A.4.3`) | DD-079 |
| Distintivo de lavandería fundadora | Kit para sumar lavanderías | No lo nombra. La comisión menor sí cabe: "Porcentaje de comisión por socio" (`:19:Módulo D #5`) | — |
| Verificación del teléfono por mensaje de texto | — | "No forma parte del alcance base" (`:30:B.5`) | — |

**Dentro del contrato, pero sin definir:** los pedidos de noche completan la agenda de F-A07 (DD-078). El "sexto
aviso, cuando ya va en camino" entra solo si es uno de los cinco momentos de A43-5 (DD-011). El precio único con envío
incluido que prometió el kit es una forma de fijar el precio (DD-010).

## Lo que hay que pedirle a Nadir, por fecha

| Antes de | Qué | Quién responde | Fila |
|---|---|---|---|
| **Lunes 23 nov** | Enviar el aviso de retraso, con la cuenta de este plan | Nadir | DD-064 |
| **Lunes 23 nov** | Desde cuándo cuentan los "tres meses", y abrir la Orden de Cambio por etapas | Nadir, con Arthur | DD-068 |
| **Lunes 23 nov** | Aprobar los protocolos con el cliente. El primer reporte semanal sale el domingo 29 | Nadir | DD-074 |
| **Lunes 23 nov** | Confirmar que revisa el PR de cada domingo. El primero es el del 22 | Nadir | C-158 |
| 7 dic | Textos e imágenes de la bienvenida | EL CLIENTE, vía Nadir | DD-081 |
| 14 dic | Qué es "cercana", "activa" y "disponibilidad" | EL CLIENTE, vía Nadir | DD-083 |
| 28 dic | Quién fija el precio y en qué unidad va el lavado en seco | EL CLIENTE, vía Nadir | DD-010, BR-16 |
| 4 ene 2027 | La regla de los pedidos de noche, por escrito | EL CLIENTE, vía Nadir | DD-078 |
| 11 ene | Pedir el D-U-N-S y arrancar los demás trámites | EL CLIENTE, vía Nadir | DD-084 |
| 18 ene | Cuándo se cobra; la firma del cliente al cambio de pasarela, Stripe o Mercado Pago (C-191, C-192); cancelación; envío | EL CLIENTE y Nadir | DD-012, DD-079, DD-016, DD-019, DD-025 |
| 1 feb | Cómo entra cada rol, y el aviso a la lavandería | Nadir | DD-053, DD-054 |
| 22 feb | Los cinco momentos de los avisos | Nadir con EL CLIENTE | DD-011 |
| 22 mar | Quién asigna al repartidor y cuánto gana | EL CLIENTE | DD-014, DD-019 |
| 12 abr | El modelo del dinero y la comisión | EL CLIENTE | DD-013, DD-020 |
| 14 jun | Dominio, hosting, cuentas de analítica y textos legales | EL CLIENTE y Nadir | DD-023, DD-070 |

## Filas que abrió este plan en la deuda de decisión

Ya están en `meta/deuda-de-decision.md` como DD-081, DD-083, DD-084 y DD-085, en ese orden.

1. **DD-081. Textos e imágenes de la bienvenida.**
   - Qué: las 3 pantallas informativas de F-A01 (`:16:A.3 #1`) son insumo del cliente (`:6:Cláusula III, inciso
     B`).
   - Quién responde: EL CLIENTE, vía Nadir.
   - Qué bloquea: acreditar F-A01.
   - Límite sugerido: 2026-12-07.
2. **DD-083. "Cercana", "activa" y "disponibilidad" (BR-15).**
   - Qué: radio de búsqueda, orden por defecto y qué lavandería cuenta como activa o disponible (`:16:A.3 #3 y #4`).
   - Recomendación de Claude: radio configurable por plaza; "activa" es aprobada, no suspendida y con horario
     cargado.
   - Quién responde: EL CLIENTE, vía Nadir.
   - Qué bloquea: la spec de `lavanderias-cercanas`.
   - Límite sugerido: 2026-12-14.
3. **DD-084. Cuándo arrancan los trámites del B.10.**
   - Qué: ninguno ha empezado (formulario del 2026-11-05, marca en papel). Para que la cuenta de Apple del cliente
     sirva en H6, el D-U-N-S debe pedirse a más tardar el 2027-01-11 (`:33-34:B.10`).
   - Quién responde: EL CLIENTE, vía Nadir.
   - Qué bloquea: los avisos en iPhone (A43-5) y la publicación (A43-1).
   - Límite sugerido: 2027-01-11.
4. **DD-085. El trabajo del contrato que no está en el inventario.**
   - Qué: las 12 plantillas (A41-3), la notificación del branding, los perfiles y la capacitación (A45), las
     consultorías (A.6) y el soporte (A.7). Hay que decidir quién lo hace y en qué semanas.
   - Quién responde: Arthur con Nadir.
   - Qué bloquea: acreditar A41-3, A45-1 y A45-2, y el calendario de la Orden de Cambio.
   - Límite sugerido: 2026-11-29.
