## Why

El Módulo B pide "Historial de pedidos: Pedidos completados con fecha, monto y calificación recibida" y "Estadísticas básicas: Pedidos del día y de la semana, ingreso total y calificación promedio" (`NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3`, Módulo B, numerales 4 y 5). Son lo que le falta al Módulo B para A43-6: "Los cuatro módulos […] accesibles y funcionales conforme a A.3" (`NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3`). Es parte del hito H8 del plan de sprints (sprints 20 y 21).

## What Changes

- La lavandería ve sus pedidos completados, cada uno con su folio y la fecha en que se entregó (F-B04).
- Ve cuántos pedidos completó en el día y en la semana (F-B05).
- La API le entrega solo sus pedidos, de su plaza (SEG-3).
- Quedan bloqueados: el monto y el ingreso total, porque no está decidido si son brutos o netos de comisión ni si el precio es el estimado o el final (DD-013, DD-020 y DD-012); y la calificación recibida y su promedio (DD-052).

**Qué NO cambia**

- Los pedidos activos con aceptar o rechazar: cambio `pedidos-de-la-lavanderia` (F-B02).
- Los botones de estado de la lavandería: cambio `estados-del-pedido` (F-B03).
- El perfil y los horarios de la lavandería: cambio `alta-de-lavanderias` (F-B06).
- La calificación que da el usuario: cambio `historial-y-calificacion`.
- Los indicadores del panel, como el ranking por lavandería (F-I07): cambio `indicadores`.

**Fuera del contrato**

- Selector de periodo en el historial y desglose de la semana por día en las estadísticas (mocks B04 y B05): el contrato pide los pedidos completados y los conteos del día y de la semana. Posible Orden de Cambio si se quieren.

## Capabilities

### New Capabilities

- `historial-lavanderia`: historial de pedidos completados de la lavandería con fecha, monto y calificación recibida, y sus estadísticas básicas del día y de la semana (F-B04 y F-B05).

### Modified Capabilities

Ninguna.

## Impact

- **`servidor/`:** las consultas del historial y de las estadísticas de la lavandería, dentro del componente de autorización único.
- **`movil/`:** las pantallas "Historial de pedidos" y "Estadísticas" del Módulo B.
- **Base de datos:** sin tablas nuevas ni migración. Lee `pedidos`, `pedido_eventos`, `estados_pedido` y `plazas`; cuando cierre DD-052, `calificaciones`; cuando cierren DD-013 y DD-020, `comisiones`. El detalle está en `design.md`, sección "Tablas y migración".
- **Dependencias:** ninguna nueva.
- **ADRs:** ADR-0002, ADR-0003, ADR-0004 y ADR-0016 (modelo de datos).
