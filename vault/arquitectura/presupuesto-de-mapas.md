---
estado: borrador
grado: mixto
fuente: NSV-2026-001-v8.pdf:29-30:ANEXO B, Sección B.5 (A); restricciones-no-negociables.md RN-23 (B); brecha-ejecutivo-tecnico.md BR-12, BR-15, BR-18 (B); riesgos.md R-006 (B); adenda del 2026-11-24 de ADR-0009 (D)
fecha: 2026-09-11
dueño: Nadir Hadad Navarrete
---
# Presupuesto de consultas de mapas

El método es explícito en que esto es arquitectura y no un costo ajeno:

> *"Qué se cachea y qué pantalla no monta mapa dinámico es decisión de arquitectura, no un costo
> ajeno"* — `prompt-maestro-01-fundacion-v4.md:272-273`

Y el contrato ya advierte el problema (RN-23, ANEXO B.5): Google **retiró el crédito gratuito**, cada
función tiene su propia cuota, y **cada pedido dispara varias consultas**. Es R-006.

## Dónde aparece un mapa, según el contrato

| Pantalla | Cita (A) | ¿Necesita mapa dinámico de pago? |
|---|---|---|
| Cobertura, en la landing | p. 15, A.2 sección 7 | **No.** Ver decisión 1 |
| Lavanderías cercanas, en la app | p. 16, A.3 numeral 3 | **Sí.** Es el caso de uso central |
| Elegir dirección desde el mapa | p. 16, A.3 numeral 8 | **Sí**, pero una vez por dirección nueva |
| Seguimiento del pedido | p. 17, A.3 numeral 12 | **No.** El contrato pide estados, no mapa |
| Monitoreo de pedidos activos, en administración | p. 19, Módulo D | **Sí**, una sesión por turno de trabajo |
| Mapa de calor por colonia | p. 22, A.5 | **No.** Ver decisión 4 |

## Las cinco decisiones que acotan el consumo

Todas grado D. Ninguna viene del contrato; todas lo satisfacen.

**1. El mapa de cobertura de la landing se dibuja con polígonos propios, no con teselas de pago.**
El contrato exige que sea *interactivo*, y lo será: se puede acercar y consultar qué zonas cubre. Lo
que no necesita es una imagen de calle por cada visita. La landing es la página con más visitas y
menos valor por consulta: pagar teselas ahí es donde el gasto se dispara sin devolver nada.

**2. La distancia se calcula en línea recta, no por ruta.**
BR-15 lo anticipa: *"distancia lineal por defecto para no pagar rutas"*. Calcular rutas reales es una
llamada de pago por cada lavandería listada; con diez lavanderías en pantalla son diez llamadas por
búsqueda. La distancia en línea recta cuesta cero y ordena igual de bien para elegir lavandería. Si
más adelante se quiere el tiempo real de traslado, es una decisión con su propio costo declarado.

**3. Una dirección se geocodifica una sola vez en su vida.**
Al guardarse. Las direcciones favoritas (A.3 numeral 8) ya existen por contrato, así que el usuario
recurrente consume **cero** geocodificaciones. Un pedido repetido —que el contrato también exige,
numeral 13— tampoco consume ninguna.

*Precisión:* la adenda del 2026-11-24 de `ADR-0009` (DD-139) usa sin consultar el punto que da Google
durante 30 días y, pasado el plazo, lo vuelve a pedir por su place ID al usar la dirección. El punto
que marca la persona en el mapa no vence.

**4. La colonia se captura en el formulario, no se deduce del mapa.**
El mapa de calor de A.5 agrupa por colonia. Deducirla de unas coordenadas es una geocodificación
inversa **por cada pedido**, para siempre. Pedirla como campo de la dirección cuesta un campo y una
sola vez. Además mejora la entrega: en el territorio la referencia vale más que la coordenada.

**5. El seguimiento del pedido no monta mapa.**
El contrato pide mostrar cinco estados (numeral 12), no una posición en un mapa. Y el rastreo
continuo del repartidor está excluido (RN-12). Un mapa aquí sería `NO JUSTIFICADO`: se vería bien y
costaría una sesión de mapa en la pantalla que más se abre de toda la app.

## Consumo esperado por pedido

| Evento de pago | Usuario nuevo | Usuario recurrente |
|---|---|---|
| Sesión de mapa al buscar lavandería | 1 | 1 |
| Geocodificación de dirección | 1 | 0 |
| Geocodificación inversa | 0 | 0 |
| Cálculo de rutas | 0 | 0 |
| Sesión de mapa en seguimiento | 0 | 0 |
| **Total** | **2** | **1** |

El 0 del usuario recurrente vale mientras el punto de Google de su dirección tenga 30 días o menos;
pasado el plazo, usarla cuesta una consulta por el place ID (adenda del 2026-11-24 de `ADR-0009`).

Sin estas cinco decisiones, el mismo pedido dispararía una sesión de mapa en la landing, otra al
buscar, otra al seguir, una geocodificación por pedido, una inversa para la colonia y una llamada de
rutas por cada lavandería listada. La diferencia no es de afinación: es de un orden de magnitud.

## Lo que falta, y es de verdad importante

`SIN VERIFICAR` (grado E) — **los precios vigentes de Google Maps Platform tras el retiro del crédito
gratuito.** Sin ese dato, esta nota acota el **número** de consultas pero no puede afirmar el **gasto
en pesos**, y por lo tanto no puede acreditar que quepa en el rango del ANEXO B.4.

No se estima aquí. Inventar un precio sería justo lo que prohíbe la regla 3 de evidencia. Se busca,
se etiqueta con fuente y fecha, y alimenta `ADR-0009` en la Fase 6.

**Quién paga.** El servicio se contrata a nombre de EL CLIENTE (RN-20, B.11). Estas decisiones no le
ahorran dinero a EL PRESTADOR: le ahorran una factura mensual a él, y evitan la conversación de
diciembre en la que el mapa costó más de lo previsto.

## Pendientes

| Marcador | Qué falta | Dónde |
|---|---|---|
| `ESPECIFICACIÓN PENDIENTE` | Cómo se trazan las zonas: polígonos, radios, códigos postales o colonias (BR-02) | Fase 5, alimenta la decisión 1 |
| `SIN VERIFICAR` | Precios vigentes de Google Maps Platform | Verificación grado E, antes de `ADR-0009` |
| `ESPECIFICACIÓN PENDIENTE` | Si el autocompletado de direcciones usa un servicio de pago o una lista propia | `ADR-0009`, Fase 6 |
