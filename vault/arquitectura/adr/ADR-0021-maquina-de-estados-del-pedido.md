---
estado: vigente
grado: D
fuente: acta-2026-11-20-modelo-y-pasarela.md C-236 (C) y decisión 26; DD-123; C-188 (C); openspec/changes/estados-del-pedido/design.md (B); NSV-2026-001-v8.pdf:17-18:ANEXO A, Sección A.3 (A)
fecha: 2026-11-21
dueño: Arthur Naranjo
adr: ADR-0021
supersede:
---
# ADR-0021 — La máquina de estados del pedido: el estado operativo se guarda y el del usuario se deriva

## Contexto

- **Qué dice el contrato (A):**
  - el usuario ve cinco estados: "Confirmado → Recolectado → En proceso → Listo → Entregado" (`NSV-2026-001-v8.pdf:17:ANEXO
    A, Sección A.3`, Módulo A #12);
  - la lavandería tiene sus botones: "Recolectado → En proceso → Listo → En proceso de entrega" (Módulo B #3);
  - el repartidor tiene los suyos: "Recolectado → En tránsito → Entregado" (`:18`, Módulo C #3);
  - la lavandería acepta o rechaza con motivo (Módulo B #2).
- **Lo que decidió Nadir (C):** el pedido no llega a la lavandería sin pago acreditado (C-188). Nace antes del pago.
- **Lo que propone la spec (B):** guardar el estado operativo y derivar el que ve el usuario con `estados_pedido`
  (`nombre_usuario`, `visible_usuario`), en el `design.md` de `estados-del-pedido`.
- **Lo que falta:** los estados internos, sus transiciones y quién mueve cada una (DD-123, R-008).

## Criterios y pesos

Los de la matriz `vigente` (C-142): familiaridad 30, velocidad 25, proveedor 15, tiempo real 10, costo 10, multi-plaza 10.

## Alternativas evaluadas

| Alternativa | Familiaridad (30) | Velocidad (25) | Proveedor (15) | Tiempo real (10) | Costo (10) | Multi-plaza (10) | **Total** |
|---|---|---|---|---|---|---|---|
| **A. Un catálogo de estados operativos, cada uno con el nombre que ve el usuario,** como propone la spec | 5 | 5 | 5 | 5 | 5 | 5 | **100** |
| B. Solo los cinco estados del usuario, con banderas aparte para el pago y las excepciones | 4 | 4 | 5 | 4 | 5 | 5 | **86** |

B reparte el estado en varias columnas que pueden contradecirse. A deja una sola columna y una tabla que la traduce.

## Decisión

1. **El pedido guarda un solo estado operativo,** `pedidos.id_estados_pedido`, del catálogo `estados_pedido`. Lo que ve
   el usuario sale de `nombre_usuario` y `visible_usuario` de ese estado.
2. **Los estados:**

   | Código | Lo ve el usuario como | Quién lo pone | Desde |
   |---|---|---|---|
   | `por_pagar` | No lo ve (`visible_usuario` falso) | El servidor, al aceptar el resumen (confirmacion-E05) | — |
   | `confirmado` | Confirmado | El servidor, cuando la pasarela acredita el pago (C-188) | `por_pagar` |
   | `recolectado` | Recolectado | La lavandería (Módulo B #3) o el repartidor (Módulo C #3) | `confirmado` |
   | `en_proceso` | En proceso | La lavandería | `recolectado` |
   | `listo` | Listo | La lavandería | `en_proceso` |
   | `en_entrega` | Listo | La lavandería ("En proceso de entrega") o el repartidor ("En tránsito") | `listo` |
   | `entregado` | Entregado | El repartidor, o la lavandería si DD-022 lo decide | `en_entrega` |
   | `rechazado` | Ninguno de los cinco: la banda de excepción (C-201) | La lavandería, con motivo (Módulo B #2) | `confirmado` |
   | `cancelado` | Ninguno de los cinco: la banda de excepción (C-201) | Según la política de cancelación (DD-016) | Según DD-016 |

3. **Cada estado se siembra con el cambio que lo usa:**
   - `por_pagar` y los cinco del usuario, con `resumen-y-confirmacion`;
   - `en_entrega` y `rechazado`, con `pedidos-de-la-lavanderia` y `repartidor`;
   - `cancelado`, cuando responda DD-016.
4. **Una transición fuera de la tabla se rechaza** en el servidor, en un solo componente del pedido. Las de las
   excepciones esperan a DD-016 y DD-103.

La tomó Claude por delegación de Arthur Naranjo (C-236). Cierra DD-123, salvo lo que esperan DD-016, DD-022 y DD-103.

## Consecuencias

- **Positivas:**
  - una sola fuente del estado;
  - el usuario ve exactamente los cinco del contrato;
  - "En proceso de entrega" y "En tránsito" son el mismo paso operativo.
- **Negativas:** el usuario ve "Listo" mientras el pedido va en camino. Es lo que permiten los cinco estados del
  contrato; un sexto estado sería Orden de Cambio.
- **Condiciona:** `estados-del-pedido`, `pedidos-de-la-lavanderia`, `repartidor`, `avisos` (los momentos se cuelgan de
  los cambios de estado) e `indicadores`.

## Criterio de reversión

Si EL CLIENTE pide que el usuario vea "En camino", es Orden de Cambio: un `nombre_usuario` nuevo para `en_entrega`, sin
tocar la tabla del pedido.

## Costo estimado de revertir

Bajo: una migración de datos en `estados_pedido`.

## Trazabilidad

- **Restricciones:** RN-18.
- **Pendientes:** DD-123, cerrada en lo que no esperan DD-016, DD-022 y DD-103.
- **Riesgos:** R-008.
- **ADRs relacionados:** ADR-0007, ADR-0016 y ADR-0020.

## Adenda del 2026-11-22: el estado `aceptado` y cómo ve el usuario los estados operativos

Asentada el 2026-11-22 (decisión 30 del acta del 2026-11-20). La escribió Claude por delegación de Arthur Naranjo
(C-236), con la tarea 1.2 de `pedidos-de-la-lavanderia`, porque aceptar necesita un estado operativo propio que ADR-0021 no nombra. Lo que esperan DD-022 (entregar sin
repartidor), DD-016 (cancelación) y DD-103 (plazo y consecuencias del rechazo) **no** se decide aquí.

1. **`aceptado`, un estado operativo nuevo.** Lo pone la lavandería al aceptar (Módulo B #2), desde `confirmado`. El
   usuario lo sigue viendo como **Confirmado**: el contrato no nombra un sexto estado para él (Módulo A #12).
2. **Las transiciones de la lavandería**, en la tabla de `App\Pedidos\CambioDeEstado` (ADR-0021, decisión 4):

   | Desde | Acción de la lavandería | Llega a |
   |---|---|---|
   | `confirmado` | Aceptar | `aceptado` |
   | `confirmado` | Rechazar, con motivo | `rechazado` |
   | `aceptado` | Recolectado | `recolectado` |
   | `recolectado` | En proceso | `en_proceso` |
   | `en_proceso` | Listo | `listo` |
   | `listo` | En proceso de entrega | `en_entrega` |

   - La lavandería marca Recolectado desde `aceptado`, no desde `confirmado`: sin aceptar no hay botones (estados-E09).
     Desde dónde marca el repartidor su Recolectado lo fija `repartidor` (H8).
   - Un pedido aceptado ya no se rechaza, ni uno rechazado se acepta: el pedido ya fue respondido
     (pedidos-lavanderia-E08). Si después de aceptar se puede deshacer, es DD-103.
   - Ninguna acción de la lavandería sale de `en_entrega`: Entregado espera DD-022 (estados-E13).
3. **Cómo se siembra cada estado nuevo** (sin columnas nuevas):

   | Código | `nombre_usuario` | `orden` | `visible_usuario` | Lo siembra |
   |---|---|---|---|---|
   | `aceptado` | Confirmado | el de `confirmado` (1) | falso | `pedidos-de-la-lavanderia`, tarea 1.2 |
   | `rechazado` | Rechazado | 99, fuera del flujo | falso | `pedidos-de-la-lavanderia`, tarea 1.3 |
   | `en_entrega` | Listo | el de `listo` (4) | falso | `estados-del-pedido`, tarea 2.1 |

4. **Cómo se deriva lo que ve el usuario.** `visible_usuario` dice si el estado es **uno de los cinco pasos del
   seguimiento**. Un estado operativo que el usuario ve como uno de los cinco lleva `visible_usuario` falso, el nombre de
   ese paso y su mismo `orden`. La API del usuario le presenta el paso: `estado.codigo` es el del paso, `estado.nombre`
   es el `nombre_usuario` del estado, y `estado.visible_usuario` es verdadero. Un estado sin paso de su orden
   (`por_pagar`, `rechazado`) está fuera del flujo: sale con su propio código y `visible_usuario` falso, como ya salía
   `por_pagar`. Lo hace `App\Http\Resources\PedidoResource::estadoParaElUsuario()`, con la relación
   `EstadoPedido::paso()`.
   - **Por qué así:** no hay columna nueva ni cambio de esquema para otros cambios; la app del Módulo A no cambia, porque
     ubica el paso actual por `estado.codigo` y, tras el pago, espera `confirmado` aunque la lavandería ya haya
     aceptado; el seguimiento sigue trayendo exactamente los cinco (estados-E01); y el criterio de reversión del ADR
     ("En camino") sigue siendo un `nombre_usuario` nuevo.
   - **Lo que ve el usuario de un pedido rechazado** sigue abierto (estados-E19, DD-103): hoy sale fuera del flujo, sin
     nombre que la app muestre.
5. **Cada cambio publica `App\Pedidos\EstadoDelPedidoCambiado`,** también aceptar y rechazar: es un cambio del estado
   actual, y el aviso de solo datos deja que la app del usuario consulte de inmediato (ADR-0007; avisos-E14). Ninguno de
   los dos es un momento de aviso visible (`config/avisos.php`).
