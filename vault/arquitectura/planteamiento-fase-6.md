---
estado: borrador
grado: mixto
fuente: contrato NSV-2026-001 v8 (A); notas del vault al 2026-10-09 (B); actas hasta C-105 (C); investigación web del 2026-10-09 en `negocio/competencia.md` §11 y en la sección "Construir, adaptar o comprar" de esta nota (E, SIN VERIFICAR); horas por función, supuesto S-006 (S)
fecha: 2026-10-09
dueño: Nadir Hadad Navarrete
---
# Antes del stack — el planteamiento crítico de la Fase 6

**Para qué es esta nota.** Nadir pidió que, antes de fijar los pesos de la matriz, se analice a fondo si el
camino es el correcto, sin buscar el camino feliz (C-105). Esta nota es la agenda de esa conversación y la
fuente de la **sala**, la página donde Nadir responde: <https://claude.ai/artifact/WN6jmpKuxSkgFrf8GDAXDB>.

La sala es un espejo, como el tablero. Las preguntas, las puertas, los referentes y las opciones salen de esta
nota y de `negocio/competencia.md` §11; se rehacen con `herramientas/sala-fase-6/construir_sala.py`. Lo que
Nadir escribe en la sala **no es grado C todavía**: sube a C cuando la sesión de la Fase 6 lo transcribe literal
a un acta con fecha, igual que un mensaje de WhatsApp.

## Veredicto

**La Fase 6 sigue, y va tarde. Pero el framework no es lo que más falta decidir.** Antes de pesar la matriz hay
tres respuestas que mueven más que cualquier candidato: si las horas alcanzan, si conviene construir, adaptar o
comprar, y si hay lavanderías reclutadas para probar la app con pedidos reales. Ninguna de las seis hipótesis de
negocio se probó y sus fechas ya pasaron. Con el arranque más temprano posible quedan ocho semanas de
construcción: de 120 a 240 horas contra las 389 que pide la estimación media. **Y los referentes no respaldan el
modelo:** no se encontró ninguna plataforma de tres lados rentable en una ciudad del tamaño de Durango o Torreón, y en
México la más parecida, Shawi, cerró el 14 de agosto de 2026 para venderles software a las lavanderías. **Ninguna
plantilla pasa las puertas**: comprar no resuelve las horas.

## Los pasos de la conversación

El orden importa: cada paso usa lo que sale del anterior. Estados: `pendiente` · `en curso` · `cerrado`.

| Paso | Título | Objetivo | Qué sale | Estado |
|---|---|---|---|---|
| 1 | Lo que no está a discusión | Separar lo que el contrato ya decidió de lo que sí se decide, para no gastar la conversación en lo primero | Nadir confirma la frontera, o la objeta, en el acta | cerrado |
| 2 | ¿Es el modelo correcto? | Responder con evidencia si una plataforma de tres lados es el camino, y separar lo que le toca a Nadir de lo que le toca al cliente | Respuestas de Nadir; preguntas para la sesión de consultoría con EL CLIENTE (O-23) | en curso |
| 3 | ¿Cabe? | Horas reales contra el alcance, y qué palanca se mueve si no cabe | Horas confirmadas, palanca elegida, aviso de retraso redactado | en curso |
| 4 | Construir, adaptar o comprar | Evaluar las familias de opciones **solo contra las puertas**, antes de los pesos | `ADR-0015` propuesto | cerrado |
| 5 | Los pesos | Fijar los pesos de la matriz | `matriz-de-criterios-stack.md` en `vigente` | cerrado |
| 6 | Candidatos y ADRs | Puntuar candidatos con los pesos fijados y escribir los ADRs | `ADR-0002` a `ADR-0015` | cerrado |

**Estado al 2026-11-13.** Desde el traspaso, la Fase 6 la lleva Arthur Naranjo (`acta-2026-11-12-traspaso-del-proyecto.md`, C-132 y C-143), y la sala de Nadir solo tenía P-07 y P-11 (C-148).

- **Paso 1, cerrado:** la frontera y las puertas se aplicaron tal cual en los ADRs, y nadie las objetó.
- **Paso 2, en curso:** es del negocio y le toca a Nadir con EL CLIENTE. La recalibración respondió una parte.
- **Paso 3, en curso:** las horas están confirmadas (C-134: unas 14 por semana con agentes). La palanca, construir por etapas con Orden de Cambio, la negocia Nadir. El aviso de retraso está redactado.
- **Pasos 4 y 5, cerrados:** `ADR-0015` (C-139) y pesos (C-142).
- **Paso 6, cerrado con pendientes:** 13 de los 14 ADRs están `vigente`. `ADR-0014` sigue en borrador (DD-071), y el hosting está en DD-070.

**Por qué el paso 4 va antes de los pesos, y por qué eso no rompe el método.** El método prohíbe mirar candidatos
antes de fijar los pesos, para no elegir primero y justificar después (`prompt-maestro-01-fundacion-v5.md`, Fase
6). Comprar o construir no es un candidato de stack: es de dónde sale el código. Y no se puntúa con los pesos:
se pasa o no se pasa por las puertas, que se fijaron el 2026-09-11, antes de ver ninguna opción. Si una plantilla
pasa las puertas, su tecnología entra a la matriz en el paso 6 como un candidato más, con los mismos pesos que
los demás.

## Lo que no está a discusión

### FR-01 · El producto ya está comprado
- **Qué dice:** una sola app para iPhone y Android con cuatro módulos (usuario, socio lavandería, socio repartidor y administración), una landing de 12 secciones, un panel de indicadores y el branding.
- **Cita:** `NSV-2026-001-v8.pdf:4:Cláusula I`; ANEXO A.
- **Qué implica:** "¿es una app tipo Uber o Rappi la mejor solución?" lleva dos preguntas dentro. **Para el negocio del cliente** es legítima, y Nadir tiene consultoría contratada para plantearla (O-23). **Para lo que Nadir entrega** ya está respondida: cambiar la forma del producto es Orden de Cambio firmada (O-32). La conversación sirve para decidir **cómo** se construye y **qué le conviene saber al cliente**, no para cambiar el producto en silencio.

### FR-02 · Las puertas del stack se fijaron antes de ver candidatos
- **Qué dice:** una sola base de código, mapas de Google en Android y de Apple en iPhone, Conekta y el costo del ANEXO B.4. Son eliminatorias: no se ponderan.
- **Cita:** `arquitectura/matriz-de-criterios-stack.md`, escrita el 2026-09-11; RN-01, RN-02, RN-22, RN-28 y RN-29.
- **Qué implica:** no se mueven para que quepa una opción atractiva. Si una opción falla una puerta, queda fuera aunque ahorre horas.

### FR-03 · Lo que se entrega se cede
- **Qué dice:** al pago íntegro se ceden al cliente los derechos patrimoniales sobre código, diseños y contenidos; EL PRESTADOR conserva sus componentes de uso general. Cuentas y licencias van a nombre del cliente.
- **Cita:** `NSV-2026-001-v8.pdf:10:Cláusula IX`; O-30; BR-08; RN-20.
- **Qué implica:** cualquier código comprado tiene que poder entregarse y operarse a nombre del cliente. Un servicio que no da el código fuente, o una licencia que no permite entregarlo, choca con esto. Es la puerta G-5.

### FR-04 · Lo que el Módulo C excluye
- **Qué dice:** sin asignación automática de pedidos, sin optimización de rutas, sin GPS continuo fuera de un pedido activo, sin liquidaciones automáticas a repartidores, sin verificación documental.
- **Cita:** `NSV-2026-001-v8.pdf:18:Módulo C`; RN-11 y RN-12.
- **Qué implica:** una plantilla que traiga esas funciones no ahorra trabajo: el contrato no las pide, y el rastreo continuo toca datos personales que la Cláusula X acota. Habría que quitarlas o apagarlas, y probar que quedaron apagadas.

### FR-05 · Los plazos son estimados; los pagos dependen de hitos
- **Qué dice:** los plazos por entregable son estimados y los pagos 2 y 3 dependen de hitos, no del paso del tiempo. El pago 2 pide la app publicada en las dos tiendas y la landing en su dominio; si la publicación se atrasa por causas que no son de EL PRESTADOR, basta la app compilada, en ambiente de prueba y validada conforme al A.4.3. Ante un retraso previsto, aviso escrito con 48 horas.
- **Cita:** `NSV-2026-001-v8.pdf:6:Cláusula II`; `:8:Cláusula VI`; RN-34; `contrato/calendario-contractual.md`.
- **Qué implica:** el calendario no castiga por sí solo; llegar a la semana 16 sin el hito sí. Y el desarrollo ya está atrasado sin aviso escrito (P-11).

## Las puertas

Las cuatro primeras se fijaron el 2026-09-11. La quinta es propuesta de Claude del 2026-10-09 y aplica solo a
**de dónde sale el código** (paso 4), no a los frameworks. **Nadir la confirma o la quita.**

| Puerta | Qué exige | Cita |
|---|---|---|
| G-1 | Una sola base de código para iPhone y Android, con comportamiento idéntico | RN-01, RN-02 (p. 16, A.3) |
| G-2 | Mapas de Google en Android y de Apple en iPhone. Un solo SDK de mapa para los dos no cumple | RN-29 (p. 19, A.3) |
| G-3 | Conekta como pasarela, con tarjeta; OXXO Pay según R-028 | RN-28 (pp. 16 y 19) |
| G-4 | Costo de infraestructura de 1,200 a 4,100 MXN al mes con volumen inicial bajo | RN-22 (pp. 29 y 33, B.4) |
| G-5 | El código se puede entregar al cliente, operarse en cuentas a su nombre y modificarse después | Cláusula IX (p. 10); O-30; BR-08; RN-20 |

## Las preguntas

Cada pregunta dice por qué importa, **qué pasa si la respuesta es mala** y qué evidencia hay hoy. Quien
responde: `Nadir`, `EL CLIENTE` o `Nadir con EL CLIENTE`. Las del cliente no las inventa Nadir: si no sabe, las
marca para la sesión de consultoría.

### P-01 · ¿Qué le resuelve Lavandi al usuario que su lavandería de barrio con WhatsApp no le resuelve, y qué evidencia hay fuera de nuestra cabeza?
- **Paso:** 2
- **Por qué importa:** la lista de lo que la app da y el WhatsApp no es hora fija, precio antes de pesar, pago sin efectivo, seguimiento y alguien que da la cara. El vault ya escribió que esa lista solo funciona si el mapa tiene lavanderías (`negocio/propuesta-de-valor.md`, sección g).
- **Si la respuesta es mala:** si la respuesta honesta es "nada por lo que pague el envío", la app se entrega, cumple el contrato y nadie la usa. El mes de operación se vuelve un mes de silencio.
- **Evidencia hoy:** H-002, treinta conversaciones con vecinos con un precio concreto, tenía límite el 2026-10-04 y no se hizo. Un competidor de Torreón anuncia el domicilio sin costo (R-015). En 2025 tenían lavadora el 83.6 % de las viviendas de Durango y el 89.4 % de las de Coahuila, y la cifra sube (`negocio/competencia.md` §11, LC-10).
- **Responde:** Nadir con EL CLIENTE
- **Bloquea:** no bloquea el stack. Sí decide qué pantallas se pulen primero.

### P-02 · ¿Cuántas lavanderías ha reclutado EL CLIENTE hoy, y cuántas tienen que estar el día del lanzamiento?
- **Paso:** 2
- **Por qué importa:** EL CLIENTE no aporta oferta propia (C-33). La lectura del vault es que el recorrido completo del pedido del A.4.3 necesita al menos una lavandería real que acepte pedidos reales (`negocio/propuesta-de-valor.md`). Y O-27 pide dar de alta socios del territorio inicial: depende de que existan.
- **Si la respuesta es mala:** con cero lavanderías la app puede estar terminada y el entregable no acreditarse con pedidos reales. El reclutamiento tenía que empezar antes que el desarrollo.
- **Evidencia hoy:** DD-021, el plan de reclutamiento, está marcada crítica y vencida desde el 2026-09-13. H-001, veinte llamadas, tenía límite en la semana 3. R-005.
- **Responde:** EL CLIENTE
- **Bloquea:** las pruebas de aceptación con datos reales y el hito del pago 2.

### P-03 · ¿Tres lados o dos? ¿Hace falta una red de repartidores de la plataforma, o entrega la lavandería con su gente, o se contrata a un tercero?
- **Paso:** 2
- **Por qué importa:** un modelo de tres lados tiene que llenar los tres a la vez, en dos plazas y sin oferta propia. Varias lavanderías del territorio ya reparten por su cuenta. El Módulo C es obligación, pero el contrato no dice que el repartidor no pueda ser de la lavandería (DD-022).
- **Si la respuesta es mala:** si se exige una red propia de repartidores, el arranque en frío se triplica, y $35 por dos trayectos puede no pagarle a nadie (R-013, S-003).
- **Evidencia hoy:** DD-014, DD-019 y DD-022 abiertas; H-003 sin probar. Uber Envíos existe en Torreón, Gómez Palacio y Durango y se puede pedir a mano, pero dos tramos contratados cuestan casi el triple del envío de $35, y un pedido grande solo cabe en auto. Los que sobreviven fuera de las megaciudades son tiendas que entregan con su gente (`negocio/competencia.md` §11, LC-13 y LC-04).
- **Responde:** Nadir con EL CLIENTE
- **Bloquea:** la máquina de estados del pedido (BR-22) y quién usa la app de repartidor.

### P-04 · ¿Cierra la economía de un pedido que necesita dos viajes?
- **Paso:** 2
- **Por qué importa:** con las cifras ilustrativas del propio contrato quedan $38.69 por pedido antes de pagar el reparto. Si la plataforma paga una recolección y una entrega, no queda nada (`negocio/unit-economics.md`, secciones 3 y 4). Es la causa de cierre más citada de las apps de lavandería a domicilio (`competencia.md` §11).
- **Si la respuesta es mala:** cada pedido pierde dinero y crecer acelera la pérdida.
- **Evidencia hoy:** precio por kilo, comisión, estructura del envío y momento del cobro siguen abiertos: DD-010, DD-012, DD-019, DD-020 y DD-025. Todos los que publican precio meten el envío en el precio y piden un mínimo; ninguno cobra el envío aparte (LC-12).
- **Responde:** EL CLIENTE
- **Bloquea:** no el stack. Sí obliga a que precio, envío y comisión sean configurables por plaza, que ya se recomendó.

### P-05 · Después del primer pedido, ¿qué impide que el usuario y la lavandería sigan por WhatsApp sin la plataforma?
- **Paso:** 2
- **Por qué importa:** la plataforma cobra comisión por pedido, y desde el primero la lavandería conoce al cliente, su domicilio y su teléfono. La fuga es el mal típico de los mercados de servicios locales que se repiten cada semana.
- **Si la respuesta es mala:** el cliente paga por conseguir cada usuario y la lavandería se lo queda. El socio ya lo anticipa: "¿por qué voy a pagar comisión por mis propios clientes?" (`propuesta-de-valor.md`, sección b).
- **Evidencia hoy:** ninguna regla escrita. En lavandería la fuga no está medida (LC-07). Lo documentado va en otra dirección: Shawi concluyó que a las lavanderías no les faltaban clientes sino un canal propio, y hoy un software de 20 dólares al mes sin comisión les da ese canal (LC-09, RF-20).
- **Responde:** Nadir con EL CLIENTE
- **Bloquea:** reglas de producto, no el stack.

### P-06 · ¿En qué plaza se arranca de verdad?
- **Paso:** 2
- **Por qué importa:** el territorio del contrato es todo el estado de Durango más Torreón y Matamoros. Abrir dos plazas a la vez divide la densidad, y la densidad es lo que abarata cada viaje. El sistema nace multi-plaza (O-26), así que esto es una decisión de operación, no de software.
- **Si la respuesta es mala:** dos mapas medio vacíos en lugar de uno lleno.
- **Evidencia hoy:** C-33, sin oferta propia en las dos plazas. Ninguna meta de lanzamiento por plaza.
- **Responde:** EL CLIENTE
- **Bloquea:** el orden del reclutamiento y la prueba con lavanderías reales.

### P-07 · ¿Llevamos P-01 a P-06, P-19 y P-20 al cliente en la sesión de consultoría que está pendiente?
- **Paso:** 2
- **Por qué importa:** el contrato le da a Nadir consultoría con EL CLIENTE (O-23). La primera sesión tocaba en la semana 2 y la segunda en las 4 y 5; no hay registro de ninguna. Es el lugar natural para decirle al cliente lo que esta sala encuentre del modelo, por escrito y a tiempo.
- **Si la respuesta es mala:** si no se le dice, el cliente invierte en la app creyendo que el software es el riesgo, cuando el riesgo es el reclutamiento y el reparto. Y si el negocio no arranca, lo va a recordar.
- **Evidencia hoy:** O-23 sin registro; DD-021 vencida.
- **Responde:** Nadir
- **Bloquea:** un borrador para el cliente en `vault/cliente/comunicaciones/`. Nada se envía: lo envía Nadir.

### P-19 · ¿Cómo paga un usuario que prefiere efectivo, y por dónde pide uno que vive en WhatsApp?
- **Paso:** 2
- **Por qué importa:** el 85.2 % prefiere pagar en efectivo las compras de hasta $500, que es donde cae un pedido, y el 93 % de quienes usan internet usa mensajería (LC-11). El contrato cobra con tarjeta y OXXO Pay por Conekta, y OXXO Pay ya no sería de Conekta (R-028). Los referentes latinoamericanos tomaban pedidos por WhatsApp (GetLavado) o regresaron a él (Shawi).
- **Si la respuesta es mala:** si OXXO Pay se cae, la app cobra solo con tarjeta en un mercado que paga en efectivo. Pedir por WhatsApp o pagar en efectivo al entregar no están en el contrato: serían Orden de Cambio, y WhatsApp no tiene pagos en México.
- **Evidencia hoy:** R-028; DD-056; `negocio/competencia.md` §11, LC-11.
- **Responde:** Nadir con EL CLIENTE
- **Bloquea:** `ADR-0010`, la pasarela; y, si el cliente quiere otro canal, una Orden de Cambio.

### P-20 · ¿Qué le ofrece Lavandi a la lavandería, si hoy un software barato le da su propio canal sin comisión?
- **Paso:** 2
- **Por qué importa:** "más clientes", el argumento que el contrato pone para los socios (A.2), es lo que prometió Jeff y lo que Shawi concluyó que no faltaba. Un software de marca blanca le da a cada lavandería su propio canal por unos 20 dólares al mes y sin comisión (RF-20).
- **Si la respuesta es mala:** si Lavandi no tiene un argumento mejor que "más clientes", el reclutamiento repite lo que ya falló en México, y la lavandería que entra se puede ir al software más barato.
- **Evidencia hoy:** `negocio/competencia.md` §11, LC-03, LC-09 y RF-20; R-027.
- **Responde:** EL CLIENTE
- **Bloquea:** el guion de reclutamiento (DD-021) y, con él, las pruebas con lavanderías reales.

### P-08 · ¿Cuántas horas por semana tienes para el producto, de verdad, de aquí al 6 de diciembre?
- **Paso:** 3
- **Por qué importa:** con el arranque más temprano posible, el lunes 12 de octubre, quedan ocho semanas de construcción hasta el fin de la semana 13. Con 20 a 30 horas por semana son de 160 a 240 horas; si las 5 horas de diseño no son del producto, de 120 a 200 (C-91). La banda media del inventario pide 389.
- **Si la respuesta es mala:** faltan entre 150 y 270 horas. Ni la plantilla de la landing, que ahorraría de 15 a 20 (C-95), ni el mejor stack cierran eso solos. Y las Fases 6 a 8 todavía tienen que cerrarse antes de la primera línea de código.
- **Evidencia hoy:** `contrato/inventario-funcional.md` §7, que contaba nueve semanas desde el 5 de octubre; C-91; S-006, sin calibrar.
- **Responde:** Nadir
- **Bloquea:** todo el plan de desarrollo y los pesos de familiaridad y velocidad.

### P-09 · Si no cabe, ¿qué palanca vas a mover?
- **Paso:** 3
- **Por qué importa:** las palancas son cuatro: más horas tuyas; otra persona (C-90 dijo "por lo pronto"); comprar o adaptar código (paso 4); o renegociar con el cliente, por escrito, el alcance o la fecha del hito.
- **Si la respuesta es mala:** no elegir ninguna también es elegir: llegar a la semana 13 con la mitad hecha y descubrirlo en las pruebas de aceptación.
- **Evidencia hoy:** `inventario-funcional.md` §7, "Lo que se puede mover sin tocar el alcance".
- **Responde:** Nadir
- **Bloquea:** el plan de desarrollo, el paso 4 y el reporte semanal al cliente.

### P-10 · ¿La conversación técnica del 29 de septiembre decidió algo del stack?
- **Paso:** 3
- **Por qué importa:** C-94 anunció que la parte técnica seguiría en otra conversación. En el vault no hay rastro. Si ahí se eligió algo, los pesos que se fijen ahora justificarían una elección ya hecha.
- **Si la respuesta es mala:** una decisión tomada fuera del vault no existe para el método hasta tener ADR (`CLAUDE.md` §5). Y si ya hay código, el método prohíbe escribirlo antes de cerrar las Fases 6 a 8.
- **Evidencia hoy:** DD-062.
- **Responde:** Nadir
- **Bloquea:** saber si los pasos 4 a 6 parten de cero.

### P-11 · ¿Avisamos por escrito al cliente que el desarrollo va atrasado?
- **Paso:** 3
- **Por qué importa:** el contrato pide aviso escrito con 48 horas ante un retraso previsto (RN-34). El desarrollo debía arrancar el 28 de septiembre y no ha arrancado (R-026). Tampoco salió el aviso del retraso del branding (DD-036, vencida).
- **Si la respuesta es mala:** sin aviso, el retraso queda sin explicación por escrito. Si parte de la causa son insumos o trámites del cliente sin registro (los del B.10), solo un aviso escrito lo deja asentado. No es asesoría legal.
- **Evidencia hoy:** O-28; `vault/cliente/` sin avisos de retraso del desarrollo.
- **Responde:** Nadir
- **Bloquea:** un borrador de aviso en `vault/cliente/comunicaciones/`. Lo envía Nadir.

### P-12 · ¿Qué parte del sistema es igual en cualquier app de reparto y cuál es solo de Lavandi?
- **Paso:** 4
- **Por qué importa:** "no reinventar la rueda" se puede hacer pieza por pieza, no solo con una app completa. Inicio de sesión, cobro con tarjeta, avisos, mapas y pantallas de administración son piezas que existen hechas. El ciclo del pedido con peso estimado y peso real, el precio y el envío por plaza y los tres roles son lo propio.
- **Si la respuesta es mala:** comprar una app completa para ahorrarse las piezas comunes y descubrir que lo propio, que es lo difícil, hay que rehacerlo dentro de un código ajeno.
- **Evidencia hoy:** las 76 funciones y las 7 capacidades móviles de `contrato/inventario-funcional.md`.
- **Responde:** Nadir
- **Bloquea:** el `ADR-0015`.

### P-13 · ¿Alguna plantilla completa de app de lavandería o de reparto pasa las cinco puertas?
- **Paso:** 4
- **Por qué importa:** las plantillas que se venden casi siempre usan un solo mapa de Google en las dos plataformas y traen pasarelas de otros países. Si falla G-2 o G-3, hay que reescribir justo las dos piezas móviles más difíciles dentro de un código ajeno.
- **Si la respuesta es mala:** se compra el sábado y el lunes se descubre que mapas y pagos se rehacen; el ahorro se vuelve deuda.
- **Evidencia hoy:** la investigación del 2026-10-09, en la sección "Construir, adaptar o comprar" de esta nota, grado E.
- **Responde:** Nadir
- **Bloquea:** el `ADR-0015`.

### P-14 · Para quien nunca ha hecho una app móvil, ¿es más rápido heredar un código grande y ajeno, o construir uno más chico y propio sobre piezas probadas?
- **Paso:** 4
- **Por qué importa:** tu experiencia es web y servidor, ninguna en móvil (C-89). El soporte del contrato cubre todo el código que entregas, también el que compraste: 60 días, una bolsa de 20 horas y 4 horas de respuesta a una falla crítica; y el pago 3 pide el mes de operación con las observaciones atendidas (O-24). Leer código ajeno es lento justo en lo que no conoces.
- **Si la respuesta es mala:** cada falla del código comprado se paga en horas de arqueología, sale de la bolsa de 20 horas y retiene el pago 3.
- **Evidencia hoy:** C-89; O-24; `inventario-funcional.md`: 7 de las 15 funciones grandes son móviles.
- **Responde:** Nadir
- **Bloquea:** el `ADR-0015` y el peso de familiaridad.

### P-15 · ¿El código comprado se puede ceder al cliente?
- **Paso:** 4
- **Por qué importa:** la Cláusula IX cede el código al pago íntegro. Un servicio de marca blanca no entrega código; algunas licencias de mercados de plantillas limitan a quién se entrega o exigen otra licencia si los usuarios pagan; otras plantillas validan una clave de compra contra el servidor de su autor.
- **Si la respuesta es mala:** se entrega algo que el cliente no puede poseer ni operar sin el vendedor. Choca con G-5.
- **Evidencia hoy:** la investigación del 2026-10-09, grado E. La licencia concreta se lee al elegir. No es asesoría legal.
- **Responde:** Nadir
- **Bloquea:** el `ADR-0015`.

### P-16 · ¿Aceptas que construir, adaptar o comprar sea un ADR propio que se decide antes de los pesos y solo contra las puertas?
- **Paso:** 4
- **Por qué importa:** es la forma de responder tu pregunta sin romper el método: las puertas se fijaron antes de ver opciones, así que pasar o no pasar no es elegir primero. Si una plantilla pasa, su tecnología entra al paso 6 como un candidato más.
- **Si la respuesta es mala:** si se decide comprar después de fijar los pesos, la plantilla impone su stack y la matriz se vuelve decorativa.
- **Evidencia hoy:** `prompt-maestro-01-fundacion-v5.md`, Fase 6; C-95 ya aplicó la misma lógica a la plantilla de la landing (`ADR-0011`).
- **Responde:** Nadir
- **Bloquea:** el orden de los pasos 4 a 6.

### P-17 · ¿Los seis criterios de la matriz son los correctos, o falta uno?
- **Paso:** 5
- **Por qué importa:** al pago íntegro el cliente recibe el código, y alguien que no eres tú tendrá que mantenerlo. Eso cabe en parte en "riesgo de proveedor único y ruta de salida", pero no dice si hay gente en México que conozca esa tecnología.
- **Si la respuesta es mala:** se elige lo más rápido para ti y el cliente hereda algo que nadie más en su ciudad sabe mantener.
- **Evidencia hoy:** `matriz-de-criterios-stack.md`: seis criterios, dos propuestas de pesos (2026-09-11 y 2026-09-25).
- **Responde:** Nadir
- **Bloquea:** los pesos.

### P-18 · Fija los pesos
- **Paso:** 5
- **Por qué importa:** sin pesos no hay candidatos, y sin candidatos no hay ADRs. Es lo único que la matriz espera desde el 2026-09-25.
- **Si la respuesta es mala:** fijarlos antes de responder P-08, P-09 y P-16 es pesar a ciegas: la familiaridad y la velocidad dependen de esas tres.
- **Evidencia hoy:** las dos propuestas de Claude, en la sala y en la matriz.
- **Responde:** Nadir
- **Bloquea:** el paso 6.

## Construir, adaptar o comprar

Investigación web del 2026-10-09, grado E, `SIN VERIFICAR`. No se compró ni se descargó nada, así que la calidad
real del código de ninguna candidata está inspeccionada. Las calificaciones de cada puerta son del producto **tal
como se vende**: `falla` quiere decir que habría que reescribir esa parte, no que sea imposible.

**La premisa que cambia el cálculo.** Las 389 horas de la banda media son horas de Nadir para **revisar, probar en
el teléfono e integrar**, con Claude escribiendo la mayor parte del código (S-006). Una plantilla ahorra sobre todo
escribir código, que en este proyecto ya es lo barato. No ahorra lo caro: entender, probar e integrar. Y revisar
código ajeno que no se escribió para esta especificación probablemente cuesta más que revisar el que se escribe
para ella.

**El techo, con aritmética sobre S-006 y no con un dato:** si una plantilla encajara perfecto, ahorraría como mucho
de 40 a 54 % de las 389 horas. Los módulos A a D suman unas 204; dentro de ellos, mapas, Conekta y la conciliación
financiera (unas 48) se rehacen igual; lo transversal que podría traer suma hasta 53. Quedan fuera la landing, los
indicadores y la app única, que va **en contra** de casi todas las plantillas. **Ninguna candidata encaja perfecto**,
y el ahorro neto real no tiene evidencia: puede ser negativo.

### OP-01 · Construir sobre piezas probadas
- **Familia:** aceleradores: arrancadores de proyecto con licencia MIT, un servicio de backend administrado con la cuenta a nombre del cliente, avisos de Google y librerías de mapas y pagos.
- **Qué es:** el código del producto se escribe para esta especificación, pero las piezas comunes (inicio de sesión, base de datos, archivos, avisos, el esqueleto del proyecto) no se escriben: se toman hechas. Es "no reinventar la rueda" pieza por pieza (P-12).
- **Qué ahorra:** las piezas comunes, sin amarrar el producto a nadie. El código generado por un arrancador MIT es de quien lo genera, compatible con la cesión.
- **Qué cuesta:** escribir lo propio, que es justo lo que el inventario mide. Conekta no tiene SDK oficial para todos los frameworks: puede tocar integrarlo a mano. Los mapas por plataforma dependen del framework que se elija en el paso 6.
- **Puertas:** G-1 pasa · G-2 incierto · G-3 incierto · G-4 pasa · G-5 pasa
- **Veredicto:** **el camino con sentido.** G-2 y G-3 no son defectos de la opción: se resuelven al elegir el framework, en el paso 6, con los pesos ya fijados.
- **Fuentes:** licencia de Ignite, https://github.com/infinitered/ignite/blob/master/LICENSE · Very Good CLI, https://github.com/VeryGoodOpenSource/very_good_cli · precios de Supabase, https://supabase.com/pricing · guía de tarjeta de Conekta, https://developers.conekta.com/tutorials/card

### OP-02 · Plantilla de app de lavandería (CodeCanyon)
- **Familia:** plantillas de código fuente. Candidatas vistas: ReadyLaundry, FastLaundry, Rith Laundry, Best Laundry, UltraWash, AnyWash y Sudsora, de 19 a 79 dólares más complementos.
- **Qué es:** apps de cliente, lavandería y repartidor, casi siempre **tres apps separadas** más un panel web, sobre Flutter o React Native con un backend en Laravel, PHP o Node.
- **Qué ahorra:** ver un flujo completo funcionando, un modelo de datos y el esqueleto de un panel.
- **Qué cuesta:** entender tres apps y un backend ajenos; fusionarlas en una sola (F-T12, BR-48); reemplazar el mapa en iPhone; integrar Conekta desde cero; quitar la asignación automática, el rastreo y las billeteras que el contrato excluye; volver a pintar cada pantalla con la marca; traducir; auditar la seguridad; resolver la activación con código de compra; y mantenerla sin actualizaciones del autor, que se pierden al modificarla. Ventas de 5 a 122 y calificaciones de 3.4 a 5.0 con pocas reseñas.
- **Puertas:** G-1 incierto · G-2 falla · G-3 falla · G-4 incierto · G-5 falla
- **Veredicto:** **no como base.** La licencia de Envato permite entregar el producto a un cliente, pero el autor conserva la propiedad del ítem: el cliente recibiría una licencia, no los derechos que promete la Cláusula IX. Si eso cabe en los "componentes de uso general" es pregunta para un abogado. Además impondría su stack sin matriz ni ADR.
- **Fuentes:** licencia Regular de Envato, https://codecanyon.net/licenses/terms/regular · FAQ, https://codecanyon.net/licenses/faq · ReadyLaundry, https://codecanyon.net/item/readylaundry-app-website-with-admin-panel-multistore-laundry-booking-system-ondemand-laundry/61451908 · FastLaundry, https://codecanyon.net/item/fastlaundry-multivendor-laundry-booking-app-dry-cleaning-laundry-services-complete-solution/49028376 · Rith Laundry, https://codecanyon.net/item/rith-laundry-react-native-app/24541654

### OP-03 · Plataforma de reparto multi-negocio (6amMart)
- **Familia:** plantillas de código fuente. La más madura: 3,773 ventas, actualizada el 2026-10-02.
- **Qué es:** un marketplace de productos (comida, súper, farmacia, tienda, paquetería) con admin, panel de tienda y apps separadas de cliente, tienda y repartidor. **No trae lavandería.**
- **Qué ahorra:** un marketplace probado en producción por miles de compradores.
- **Qué cuesta:** convertir un marketplace de productos en uno de servicios con peso estimado y peso real; todo lo de OP-02; y una app de tienda que **no funciona** sin activarse con el usuario de CodeCanyon y el código de compra. Su política dice que, una vez personalizada, las actualizaciones ya no aplican.
- **Puertas:** G-1 incierto · G-2 falla · G-3 falla · G-4 incierto · G-5 falla
- **Veredicto:** **la más madura y la peor para este caso.** Es enorme para depurar por una persona sin experiencia móvil.
- **Fuentes:** https://codecanyon.net/item/6ammart-multivendor-food-grocery-ecommerce-parcel-pharmacy-delivery-app-with-admin-website/36772112 · activación obligatoria, https://6ammart.app/documentation/vendor-application-configuration/vendor-app-mandatory-setup/ · política de soporte, https://6ammart.app/service-support-policy/

### OP-04 · Software de marca blanca para lavanderías
- **Familia:** servicio por suscripción: CleanCloud, Cents (que absorbió a Starchup), Turns, Curbside Laundries.
- **Qué es:** el software con el que una lavandería, o una cadena, opera su mostrador, sus rutas y su app con su marca.
- **Qué ahorra:** operar de inmediato.
- **Qué cuesta:** se cobra por tienda al mes; la app con marca propia solo viene en los planes altos. No entrega código. No aparece Conekta. Y es para **un solo negocio** con sus sucursales, no un marketplace de lavanderías independientes.
- **Puertas:** G-1 incierto · G-2 incierto · G-3 falla · G-4 falla · G-5 falla
- **Veredicto:** **descartado** como base del producto. Sí es un dato para P-01: cualquier lavandería del territorio puede tener su propia app sin pasar por Lavandi (ya se había visto con Easy Laundry, `negocio/competencia.md` §10).
- **Fuentes:** https://cleancloudapp.com/pricing · https://app.starchup.com/pricing · https://trycents.com/pricing

### OP-05 · Vendedores de "clones" de Uber o Rappi
- **Familia:** agencias que venden "Uber para X": V3Cube, AppDupe, Uberdoo, Zipprr, SpotnRides, Inoru.
- **Qué es:** una app "lista" que la agencia adapta. Precio no publicado.
- **Qué ahorra:** nada que se pueda comprobar antes de pagar.
- **Qué cuesta:** precio opaco; no se encontró un contrato de licencia público; mapas por plataforma y Conekta serían trabajo a la medida cobrado aparte. Reseñas independientes con retrasos de meses, código que no se pudo lanzar y cobros extra; las "reseñas" de una de ellas están en dominios de la propia agencia.
- **Puertas:** G-1 incierto · G-2 incierto · G-3 incierto · G-4 incierto · G-5 incierto
- **Veredicto:** **la peor opción.** Aparenta resolverlo todo y es la menos comprobable.
- **Fuentes:** https://www.trustpilot.com/review/www.appdupe.com · https://www.sitejabber.com/reviews/appdupe.com · https://www.trustpilot.com/review/www.uberdoo.com

### OP-06 · Código abierto de reparto
- **Familia:** proyectos públicos: Enatega (apps con licencia MIT, backend de pago y propietario) y Fleetbase (AGPL, logística general). Los repositorios de lavandería que hay son maquetas abandonadas o sin licencia.
- **Qué es:** una base que se puede leer completa antes de decidir.
- **Qué ahorra:** ver cómo otros resolvieron roles, pedidos y repartidor.
- **Qué cuesta:** Enatega necesita su backend de pago, que no se puede ceder. Fleetbase obliga a publicar las modificaciones con AGPL o a comprar su licencia comercial, y pide un servidor de 4 GB de RAM.
- **Puertas:** G-1 incierto · G-2 incierto · G-3 falla · G-4 incierto · G-5 falla
- **Veredicto:** **referencia de lectura, no base.**
- **Fuentes:** https://github.com/enatega/food-delivery-multivendor · https://github.com/fleetbase/fleetbase

### OP-07 · Comprar una plantilla barata solo para estudiarla
- **Familia:** uso de estudio, no de producto.
- **Qué es:** comprar una de 19 o 29 dólares para ver sus flujos y su modelo de datos, con tiempo acotado, **sin copiar su código al producto**.
- **Qué ahorra:** dudas de diseño de flujos: cómo otros resolvieron el pedido, la tienda y el repartidor.
- **Qué cuesta:** las horas de leerla, que son las que faltan. Y la disciplina de no copiar nada, por la licencia.
- **Puertas:** G-1 no aplica · G-2 no aplica · G-3 no aplica · G-4 no aplica · G-5 no aplica
- **Veredicto:** **opcional.** Solo si Nadir cree que leerla le ahorra más horas de las que cuesta. Es la forma literal de "buscar una maqueta" que no choca con nada.
- **Fuentes:** las de OP-02.

**Riesgos documentados de las plantillas**, que valen para OP-02 y OP-03:

- **Activación con código de compra.** La app de tienda de 6amMart no funciona sin activarse. Un comprador de otra plantilla vio rechazada su llave al cambiar de dominio. No se pudo confirmar qué datos envía cada verificación ni qué pasa si el servidor del autor cae.
- **Seguridad.** Un análisis de Synacktiv (2025-07-10) encontró que la mayoría de las llaves secretas de Laravel más repetidas en internet vienen de productos de CodeCanyon que los compradores no cambiaron. Una llave conocida permite falsificar sesiones. https://synacktiv.com/en/publications/laravel-appkey-leakage-analysis
- **Tiendas de apps.** Apple rechaza apps hechas con plantillas comerciales si no las publica el dueño del contenido (guía 4.2.6) y ha citado plantillas reempaquetadas en rechazos por spam. https://developer.apple.com/app-store/review/guidelines/
- **Cuentas.** El código de compra queda en la cuenta de Envato de quien compra. Para cumplir "cuentas a nombre del cliente" (RN-20), tendría que comprar el cliente. Inferencia, no texto de Envato.

## Para el paso 6 (se lee después de fijar los pesos)

La investigación del 2026-10-09 encontró datos sobre **frameworks concretos**: qué librería de mapas usa el mapa de
Apple en iPhone y cuál está en versión preliminar, y en cuáles hay o no SDK de Conekta. Son datos de candidatos, y
el método dice que los candidatos se miran **después** de los pesos. Están guardados en
`arquitectura/verificaciones-fase-6.md`, sección "Investigación del 2026-10-09", y la sala no los muestra. Leerlos
antes de fijar los pesos es exactamente el sesgo que la matriz quiere evitar.

## Cómo se usa la sala

1. **Nadir responde en la sala**, a su ritmo, pregunta por pregunta. Puede marcar "no sé" o "le toca al cliente".
2. **La sesión de la Fase 6 lee las respuestas**, repregunta las vagas hasta que se puedan refutar, y escribe su
   lectura debajo de cada pregunta.
3. **Lo que queda firme se transcribe literal a un acta**, como `C-NN`, y la pregunta se marca cerrada con su acta.
   Antes de eso, una respuesta en la sala no es grado C.
4. **Si cambia esta nota**, se vuelve a correr el generador y se actualiza la sala sin borrar las respuestas (el
   README de `herramientas/sala-fase-6/` dice cómo).
5. **No se comparte con EL CLIENTE.** Tiene la deuda de decisión, los riesgos y la lectura crítica del negocio.
