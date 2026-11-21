---
estado: borrador
grado: mixto
fuente: cliente/kit-de-prospeccion/04-presentacion-para-la-visita.pdf y 01-hoja-de-presentacion.pdf, enviados al cliente (B); design-system/componentes.md y tokens.json (B); diseno/lavandi-identidad/ (B); mocks/ y el diseño "Desarrollo Temsa L" de Claude Design (B); acta-2026-11-18-entorno-y-mocks.md C-177 (C)
fecha: 2026-11-18
dueño: Arthur Naranjo
---
# Revisión de los mocks contra las tres referencias

Arthur pidió revisar los mocks contra las tres referencias "para no estar pasando nada por alto". Nadir dijo que el
kit que trae el mock de cómo debe quedar son la 1 y la 3 (C-177). Esta nota compara contra las tres. Los dos juegos de
mocks son:

- **Propuesta 1:** las 18 pantallas de Claude Design.
- **Los locales:** las 20 pantallas de `mocks/`.

## En corto

- **Lo que está bien:**
  - los cinco estados del pedido;
  - la paleta y las tipografías vigentes;
  - el total como estimado;
  - el espacio para la cancelación;
  - el usuario ve qué lavandería atiende su ropa.
- **El conflicto de fondo es el precio.** El kit ya les prometió a las lavanderías que el precio lo pone
  Lavandi, **igual en todas las lavanderías socias y con el envío incluido**. Los dos juegos de mocks hacen otra cosa:
  - la Propuesta 1 muestra precios distintos por lavandería y el envío aparte;
  - los locales cobran un envío de $35 aparte (C-31).
- **Hay que decidir:** si se alinean los mocks con lo que prometió el kit. Toca DD-019, DD-025 y BR-46.

## 1. Kit para sumar lavanderías (enviado al cliente)

Lo que prometen la presentación y la hoja de presentación, y cómo quedan los mocks:

| El kit dice | Propuesta 1 (Claude Design) | Locales (`mocks/`) | Qué hacer |
|---|---|---|---|
| "El precio del lavado lo pone Lavandi, igual en todas las lavanderías socias, con el envío incluido". La tabla de precios por kilo, "por definir" | **No cumple.** El perfil de la lavandería muestra sus propios precios (los de la libreta) y otra tarjeta dice "Desde [PRECIO]". El resumen trae "Envío [ENVÍO]" aparte | **No cumple.** El resumen cobra "Envío $35" aparte (C-31), aunque lo marca como por confirmar y anota que el cliente encerró "envío incluido" | **Decidir** (DD-019, DD-025, BR-46). Recomendación: alinear los dos juegos con el kit: una tabla de precios de Lavandi y el envío incluido |
| La tintorería "recibe por Lavandi su lavado por kilo" (lámina para tintorerías) | Ofrece "Tintorería" por prenda, con los precios de la libreta | Ofrece "lavado en seco" por kilo, con la unidad por definir | Decidir la unidad del lavado en seco (BR-16). El kit no promete tintorería por prenda |
| Cinco pasos que se llaman siempre igual: Confirmado, Recolectado, En proceso, Listo, Entregado | Cumple | Cumple | — |
| Quien pide ve qué lavandería atiende su ropa | Cumple | Cumple | — |
| La lavandería decide qué días y a qué horas recibe pedidos, y qué servicios ofrece | El horario aparece; los servicios por lavandería no | Supone que cada lavandería ofrece solo algunos tipos | Coincide con BR-16: los servicios son de cada lavandería y el precio es de Lavandi |
| La parte de la lavandería (lámina 5): "Pedidos de hoy", pedido nuevo con hora y "Lavado y planchado · 6 kg", Aceptar o Rechazar, y "Esta semana 12 pedidos" | No existe todavía (módulo B) | No existe todavía (módulo B) | Usarla como base del módulo B. El contrato exige motivo al rechazar, y la lámina no lo muestra |
| Lavanderías "fundadoras": distintivo y comisión menor durante un tiempo | No aparece | No aparece | Un distintivo en la tarjeta es poco trabajo, pero el contrato no lo nombra. Preguntar si se quiere |
| Quién recoge y entrega: "por definir" | El seguimiento habla de "el repartidor" | Igual | Sigue abierto (DD-014, DD-022) |

## 2. Design system (Fase 4)

| Componente | Lo que pide | Propuesta 1 | Locales |
|---|---|---|---|
| 1 · Tarjeta de lavandería | Estados Abierta, Sin cupo hoy (lleva a "ver horarios") y Cerrada (con la próxima apertura); precio "desde X por kilo" | Abierta y Sin cupo hoy; **falta Cerrada** | Cumple |
| 2 · Fecha y hora | Ventanas de 1 h; las sin cupo **tachadas** pero visibles; el día sin servicio con mensaje; 48 dp o más | Sin cupo con borde punteado y texto, **no tachada**; **falta el día sin servicio** | Cumple |
| 3 · Seguimiento | Los cinco estados literales, con color y texto | Cumple | Cumple |
| 4 y 5 · Pedido de lavandería y de repartidor | Rechazar con motivo; el monto informa, no dispara pagos | Pendiente (módulos B y C) | Pendiente |
| 6 · Resumen | "Estimado" donde se decide pagar; espacio para la cancelación; el botón depende de DD-012 | Cumple; el botón dice "Ir a pagar" | Cumple; muestra las tres opciones de DD-012 |
| Tokens y modo | Solo modo claro; colores de la capa semántica | Cumple | Cumple |

Las dos correcciones de la Propuesta 1 (falta "Cerrada" y la ventana sin cupo no va tachada) son de forma y no
necesitan decisión. Se publican junto con lo que tiene pendiente en Claude Design.

## 3. Identidad de marca (Fase 3)

| Elemento | Identidad | Propuesta 1 | Locales |
|---|---|---|---|
| Paleta | Agua #2E86AB, Cielo #A8D5E2, Hueso #F7F4EF y Limón #E8C547 | La misma, desde `tokens.json` | La misma |
| Tipografías | Quicksand y Figtree (exploración de septiembre) | Nunito y Source Sans 3, **las vigentes** | Las vigentes |
| Logotipo e ícono | El símbolo (L, lavadora y punto de entrega) | Monograma "TL" y el nombre "Desarrollo Temsa L", por la regla de Arthur | Monograma "L" |
| Frase | "De tu cel a tu closet" | La usa la bienvenida | — |

La identidad es de septiembre. Las tipografías se decidieron después (Nunito y Source Sans 3), y el logotipo final
lo entregó el diseñador del cliente (C-98). **Recomendación:** que los mocks locales usen el ícono de app y el
logotipo aprobados (`marca/visual/logotipo/`) en lugar del monograma "L".

## 4. Las imágenes de GPT (Propuesta 2)

No coinciden con ninguna de las tres referencias: otra paleta (verde oscuro y mandarina, o ciruela y terracota), otro
logotipo y otras fuentes. Sirven para comparar ideas de composición, no como base.

## Lo que queda por decidir

1. **El precio y el envío.** ¿Se alinean los mocks con lo que prometió el kit (precio único de Lavandi, envío
   incluido)? Toca DD-019, DD-025 y BR-46. El 2026-11-05 el cliente marcó en papel "Estandarización de precios (los
   manejaría Lavandi)" y "envío incluido", que van en el mismo sentido, pero esas marcas no son grado C.
2. **La tintorería.** ¿Por prenda, como en la libreta, o por kilo, como dice el kit? (BR-16)
3. **¿Cuál juego de mocks es la referencia** para la spec del pedido: la Propuesta 1 o los locales?
4. **¿Los mocks locales usan el logotipo y el ícono aprobados?**
