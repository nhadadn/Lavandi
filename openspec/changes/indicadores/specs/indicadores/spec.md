## ADDED Requirements

### Requirement: Pedidos totales por día, semana y mes

El panel de indicadores DEBE (MUST) mostrar el número de pedidos de cada día, semana o mes del rango que elija la administración, en una gráfica de barras y en una tabla comparativa que pone cada periodo junto al anterior. Cuenta como pedido todo pedido que llegó a Confirmado, y su día es el de su plaza.

Cita: A → `NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5`; A → `NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3` (Módulo A #12)

#### Scenario: indicadores-E01 — Pedidos por día

- **DADO** tres pedidos confirmados el lunes y dos el miércoles de la misma semana
- **CUANDO** la administración pide los pedidos diarios de esa semana
- **ENTONCES** la gráfica y la tabla dan 3 el lunes, 0 el martes y 2 el miércoles

Criterio: A44-1

#### Scenario: indicadores-E02 — Pedidos por semana y por mes

- **DADO** pedidos confirmados en dos semanas de enero y en febrero
- **CUANDO** la administración cambia la vista a semanal y después a mensual
- **ENTONCES** cada semana y cada mes suman sus pedidos
- **Y** la tabla pone cada periodo junto al anterior

Criterio: A44-1

#### Scenario: indicadores-E03 — El día es el de la plaza

- **DADO** un pedido confirmado a las 23:30 en la hora de su plaza, cuando en UTC ya es el día siguiente
- **CUANDO** la administración pide los pedidos diarios
- **ENTONCES** el pedido cuenta en el día de su plaza

### Requirement: Pedidos por estado

El panel de indicadores DEBE (MUST) mostrar en una gráfica de dona cuántos pedidos del rango están activos, completados y cancelados. Activo es el pedido confirmado que todavía no se entrega ni se cancela; completado, el entregado.

Cita: A → `NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5`

#### Scenario: indicadores-E04 — Activos y completados

- **DADO** dos pedidos En proceso, uno Confirmado y tres Entregados en el rango
- **CUANDO** la administración abre el indicador
- **ENTONCES** la dona da 3 activos y 3 completados

Criterio: A44-1

#### Scenario: indicadores-E05 — Cancelados

- **DADO** pedidos cancelados o rechazados en el rango
- **CUANDO** la administración abre el indicador
- **ENTONCES** la dona los cuenta como cancelados, según quede definido el estado de cancelación

SIN RESPUESTA — bloquea: indicadores-E05 (DD-016; BR-22: los estados de cancelado y rechazado todavía no existen)

### Requirement: Usuarios activos por día, semana y mes

El panel de indicadores DEBE (MUST) mostrar en una línea de tendencia cuántas personas distintas usaron la aplicación cada día, cada semana y cada mes del rango, como lo define el glosario del contrato. Una persona que la usó varios días cuenta una sola vez en su semana y en su mes.

Cita: A → `NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5`; A → `NSV-2026-001-v8.pdf:46:ANEXO D, Sección D.5` (usuarios activos)

#### Scenario: indicadores-E06 — Personas distintas

- **DADO** un usuario con actividad el lunes, el martes y el jueves de una semana, y otro con actividad solo el martes
- **CUANDO** la administración pide los usuarios activos diarios y semanales
- **ENTONCES** el lunes da 1, el martes 2 y el jueves 1
- **Y** la semana da 2, no 4

Criterio: A44-1, A43-7

#### Scenario: indicadores-E07 — Qué cuenta como usar la aplicación

- **DADO** un usuario que solo abrió la app con su sesión, otro que hizo un pedido y una lavandería que atendió pedidos
- **CUANDO** se calculan los usuarios activos del día
- **ENTONCES** cuentan los que diga la definición de usuario activo

SIN RESPUESTA — bloquea: indicadores-E07 (BR-33; DD-111)

### Requirement: Usuarios que vuelven a pedir, por mes de registro

El panel de indicadores DEBE (MUST) mostrar una tabla con una fila por mes de registro, con cuántos usuarios de ese mes hicieron su primer pedido y qué porcentaje de ellos volvió a pedir, como define la retención el glosario del contrato.

Cita: A → `NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5`; A → `NSV-2026-001-v8.pdf:46:ANEXO D, Sección D.5` (retención)

#### Scenario: indicadores-E08 — Porcentaje por mes de registro

- **DADO** cuatro usuarios registrados en enero, de los cuales dos hicieron un pedido y uno de esos dos hizo otro
- **CUANDO** la administración abre el indicador
- **ENTONCES** la fila de enero da 2 usuarios con primer pedido y 50 % que volvió a pedir
- **Y** un mes cuyos usuarios no han pedido da 0 y no muestra porcentaje

Criterio: A44-1

### Requirement: Monto promedio por pedido

El panel de indicadores DEBE (MUST) mostrar como indicador destacado el monto promedio de los pedidos del periodo y su tendencia frente al periodo anterior de la misma duración.

Cita: A → `NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5`; A → `NSV-2026-001-v8.pdf:46:ANEXO D, Sección D.5` (monto promedio por pedido)

#### Scenario: indicadores-E09 — Promedio y tendencia

- **DADO** tres pedidos del periodo con montos de 200, 250 y 300 pesos, y dos del periodo anterior de 200 pesos
- **CUANDO** la administración abre el indicador
- **ENTONCES** el indicador da 250 pesos
- **Y** la tendencia dice que subió frente al periodo anterior

Criterio: A44-1

#### Scenario: indicadores-E10 — Qué monto cuenta

- **DADO** un pedido cobrado con un estimado y ajustado después
- **CUANDO** se calcula el monto promedio
- **ENTONCES** cuenta el monto que fije el modelo de cobro: el estimado o el final

SIN RESPUESTA — bloquea: indicadores-E10 (DD-012)

### Requirement: Indicadores de socios

El panel de indicadores DEBE (MUST) mostrar las lavanderías activas frente a las registradas en un indicador destacado, la calificación promedio de cada lavandería en un ranking con semáforo y las entregas completadas por cada repartidor en una tabla comparativa. Los cortes del semáforo son datos de configuración.

Cita: A → `NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5` (categoría Socios)

#### Scenario: indicadores-E11 — Lavanderías activas frente a registradas

- **DADO** cinco lavanderías registradas, de las cuales tres cumplen la definición de activa
- **CUANDO** la administración abre el indicador
- **ENTONCES** el indicador dice 3 de 5

SIN RESPUESTA — bloquea: indicadores-E11 (DD-083: qué lavandería cuenta como activa, y si una pendiente de aprobación cuenta como registrada)

#### Scenario: indicadores-E12 — Ranking por calificación promedio

- **DADO** tres lavanderías con promedio de 4.8, 3.9 y 4.2, y una sin calificaciones
- **CUANDO** la administración abre el ranking
- **ENTONCES** aparecen en orden 4.8, 4.2 y 3.9
- **Y** la que no tiene calificaciones aparece al final como "sin calificaciones"

Criterio: A44-1

#### Scenario: indicadores-E13 — Semáforo con cortes de configuración

- **DADO** dos cortes cargados en la configuración
- **CUANDO** una lavandería tiene un promedio arriba del corte alto, otra entre los dos y otra abajo del bajo
- **ENTONCES** se marcan verde, amarillo y rojo
- **Y** cada color va con su texto, no solo con el color

#### Scenario: indicadores-E14 — Valores de los cortes del semáforo

- **DADO** el semáforo con cortes de configuración
- **CUANDO** EL CLIENTE da los valores de los cortes
- **ENTONCES** se cargan en la configuración sin cambiar el código

SIN RESPUESTA — bloquea: indicadores-E14 (BR-33; DD-114)

#### Scenario: indicadores-E15 — Entregas completadas por repartidor

- **DADO** dos repartidores, uno con cuatro entregas marcadas Entregado en el rango y otro con dos
- **CUANDO** la administración abre el indicador
- **ENTONCES** la tabla los pone lado a lado con 4 y 2

Criterio: A44-1

### Requirement: Ingresos brutos y comisiones

El panel de indicadores DEBE (MUST) mostrar los ingresos brutos por periodo en una gráfica de barras agrupadas, y las comisiones generadas por cada socio y en total en una tabla detallada con su total. Las cifras son las mismas de los reportes financieros de la administración.

Cita: A → `NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5` (categoría Financiero); A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #6)

#### Scenario: indicadores-E16 — Ingresos brutos por periodo

- **DADO** pagos confirmados en enero y en febrero
- **CUANDO** la administración abre el indicador mensual
- **ENTONCES** cada mes da la suma de lo cobrado
- **Y** cada cifra coincide con el reporte financiero de ese mes

Criterio: A44-1

#### Scenario: indicadores-E17 — Por qué se agrupan las barras

- **DADO** los ingresos de un periodo
- **CUANDO** se dibujan las barras agrupadas
- **ENTONCES** cada grupo corresponde al criterio que fije EL CLIENTE

SIN RESPUESTA — bloquea: indicadores-E17 (BR-31; DD-115)

#### Scenario: indicadores-E18 — Comisiones por socio y en total

- **DADO** pedidos con pago confirmado de dos lavanderías en el rango
- **CUANDO** la administración abre el indicador
- **ENTONCES** la tabla da la comisión de cada lavandería y el total, iguales a las del reporte financiero

SIN RESPUESTA — bloquea: indicadores-E18 (DD-020, DD-013; depende de administracion-E15)

### Requirement: Mapa de calor por colonia o por zona

El panel de indicadores DEBE (MUST) mostrar un mapa interactivo con la concentración de pedidos del rango, agrupada por colonia o por zona de servicio, a elección de la administración. La colonia es la que se capturó en la dirección del pedido y no se deduce del mapa. Los agregados se calculan en el servidor.

Cita: A → `NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5` (categoría Geográfico); D → ADR-0009

#### Scenario: indicadores-E19 — Por colonia

- **DADO** cinco pedidos de la colonia A y dos de la colonia B en el rango
- **CUANDO** la administración elige ver el mapa por colonia
- **ENTONCES** el mapa trae un punto por colonia, con 5 y 2 pedidos, y el de 5 con más intensidad
- **Y** no se hace ninguna geocodificación inversa

Criterio: A44-1

#### Scenario: indicadores-E20 — Por zona de servicio

- **DADO** pedidos dentro de dos zonas de servicio
- **CUANDO** la administración elige ver el mapa por zona
- **ENTONCES** cada zona se pinta con su número de pedidos

### Requirement: Panel en tiempo real y con datos reales

El panel de indicadores DEBE (MUST) vivir dentro del panel de administración, calcular cada indicador con los pedidos y usuarios de la base, sin exportar ni procesar nada a mano, y refrescarse solo mientras está abierto, con el intervalo como dato de configuración.

Cita: A → `NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5`; A → `NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.4`; A → `NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3`

#### Scenario: indicadores-E21 — Los indicadores se refrescan solos

- **DADO** el panel de indicadores abierto
- **CUANDO** se confirma un pedido nuevo
- **ENTONCES** los pedidos totales y los pedidos por estado lo cuentan en el siguiente refresco, sin recargar la página

Criterio: A44-1

#### Scenario: indicadores-E22 — El intervalo de refresco

- **DADO** el refresco con intervalo de configuración
- **CUANDO** se fija cuánto es "en tiempo real" para los indicadores
- **ENTONCES** el panel refresca con ese intervalo

Cita: D → `ADR-0007`, adenda del 2026-11-22: los widgets de indicadores se refrescan cada 30 s, con `indicadores.refresco` en `30s` por omisión (decisión 33 de `acta-2026-11-20-modelo-y-pasarela.md`; cierra DD-116)

Criterio: A44-1

#### Scenario: indicadores-E23 — Cifras de la base, no de ejemplo

- **DADO** una base con pedidos y usuarios sembrados por el test
- **CUANDO** la administración abre el panel de indicadores
- **ENTONCES** cada indicador da las cifras que salen de esa base
- **Y** el código del panel no trae cifras de ejemplo

Criterio: A43-7, A44-1

Acreditar A43-7 en el entorno de prueba o en producción necesita lavanderías reales (DD-021). El test no lo espera.
