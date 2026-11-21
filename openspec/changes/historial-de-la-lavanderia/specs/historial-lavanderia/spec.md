## ADDED Requirements

### Requirement: Historial de pedidos completados de la lavandería
La app DEBE (MUST) mostrar a la lavandería sus pedidos completados, con fecha, monto y calificación recibida. La API DEBE (MUST) entregarle solo sus propios pedidos, de su plaza.

Cita: A → `NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3` (Módulo B #4) y `NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3` (Separación por tipo de usuario)

#### Scenario: historial-lavanderia-E01 — La lavandería ve sus pedidos completados
- **DADO** una lavandería con dos pedidos Entregados y uno en proceso
- **CUANDO** abre su historial de pedidos
- **ENTONCES** ve los dos pedidos Entregados, cada uno con su folio y la fecha en que se entregó
- **Y** no ve el que sigue en proceso

Criterio: A43-6

#### Scenario: historial-lavanderia-E02 — No ve pedidos de otra lavandería ni de otra plaza
- **DADO** un pedido Entregado de otra lavandería de la misma plaza y uno de una lavandería de otra plaza
- **CUANDO** la lavandería pide su historial, o pide cualquiera de esos pedidos por su identificador
- **ENTONCES** ninguno aparece en su historial
- **Y** la API rechaza entregarle cualquiera de ellos

Criterio: SEG-3

#### Scenario: historial-lavanderia-E03 — El monto de cada pedido
- **DADO** un pedido Entregado de la lavandería
- **CUANDO** abre su historial
- **ENTONCES** ve el monto que fije la respuesta pendiente, bruto o neto de comisión, sobre el precio estimado o el final

SIN RESPUESTA — bloquea: historial-lavanderia-E03 (DD-013, DD-020 y DD-012)

#### Scenario: historial-lavanderia-E04 — La calificación que recibió
- **DADO** un pedido Entregado y calificado por el usuario
- **CUANDO** la lavandería abre su historial
- **ENTONCES** ve la calificación que le corresponda según la respuesta pendiente

SIN RESPUESTA — bloquea: historial-lavanderia-E04 (DD-052)

### Requirement: Estadísticas básicas de la lavandería
La app DEBE (MUST) mostrar a la lavandería sus pedidos del día y de la semana, su ingreso total y su calificación promedio, contando solo sus propios pedidos.

Cita: A → `NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3` (Módulo B #5) y `NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3` (Separación por tipo de usuario)

#### Scenario: historial-lavanderia-E05 — Pedidos del día y de la semana
- **DADO** una lavandería con 2 pedidos entregados hoy, 3 en días anteriores de la semana en curso y 1 de la semana pasada
- **CUANDO** abre sus estadísticas
- **ENTONCES** ve 2 pedidos del día y 5 de la semana

Criterio: A43-6

#### Scenario: historial-lavanderia-E06 — Solo cuenta sus propios pedidos
- **DADO** otra lavandería con pedidos entregados hoy
- **CUANDO** la primera abre sus estadísticas
- **ENTONCES** sus conteos no incluyen ningún pedido de la otra lavandería

Criterio: SEG-3

#### Scenario: historial-lavanderia-E07 — El ingreso total
- **DADO** una lavandería con pedidos entregados
- **CUANDO** abre sus estadísticas
- **ENTONCES** ve el ingreso total que fije la respuesta pendiente, bruto o neto de comisión, y del periodo que fije esa misma respuesta

SIN RESPUESTA — bloquea: historial-lavanderia-E07 (DD-013, DD-020 y DD-012)

#### Scenario: historial-lavanderia-E08 — La calificación promedio
- **DADO** una lavandería con pedidos calificados
- **CUANDO** abre sus estadísticas
- **ENTONCES** ve el promedio de las calificaciones que le correspondan según la respuesta pendiente

SIN RESPUESTA — bloquea: historial-lavanderia-E08 (DD-052)
