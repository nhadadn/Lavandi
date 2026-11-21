## Context

- Completa el Módulo B en el hito H8. Lee los pedidos que la lavandería ya atendió con `pedidos-de-la-lavanderia` y
  `estados-del-pedido`, y pasa por el componente de autorización único (`CLAUDE.md` §9.5).
- Son dos pantallas de lectura y dos consultas. No hay decisión técnica nueva: se apoya en ADR-0002, ADR-0003 y
  ADR-0004.

## Goals / Non-Goals

**Goals:**

- Dejar implementables, sin respuestas pendientes, historial-lavanderia-E01, E02, E05 y E06.

**Non-Goals:**

- El monto y el ingreso (DD-013, DD-020 y DD-012) y la calificación recibida y su promedio (DD-052).
- Filtros de periodo y desgloses por día: no tienen cita.

## Decisions

Tres lecturas que fijan los escenarios; Arthur las aprueba con la spec:

1. **"Completado" es Entregado,** el último de los cinco estados del pedido (Módulo A #12). La lavandería no marca
   Entregado (su último botón es "En proceso de entrega", Módulo B #3), pero el pedido termina ahí. La fecha del
   historial es la del paso a Entregado.
2. **"Pedidos del día y de la semana" cuenta pedidos completados**, igual que el historial del numeral 4 y que las
   "entregas del día y de la semana" del repartidor. Alternativa: contar los pedidos recibidos en el día. Ver Open
   Questions.
3. **"Del día" y "de la semana"** se cuentan en la zona horaria de la plaza (`vault/arquitectura/modelo-multi-plaza.md`).
   La semana va de lunes a domingo.

## Tablas y migración

No crea tablas ni migración. Lee, sin escribir, tablas que crean otros cambios (`vault/arquitectura/modelo-de-datos.md`,
ADR-0016):

| Tabla | Para qué | La crea |
|---|---|---|
| `pedidos` | Los pedidos de la lavandería por `id_lavanderias` e `id_plazas`, con su folio (E01, E02, E05 y E06); con E03 y E07, el monto y la copia `comision_porcentaje` | `resumen-y-confirmacion` |
| `pedido_eventos` y `estados_pedido` | El paso a Entregado y su `ocurrido_en`, que dan la fecha del historial y los conteos del día y de la semana (E01 y E05) | `resumen-y-confirmacion` (base) y `estados-del-pedido` |
| `plazas` | La `zona_horaria` de la plaza, para "del día" y "de la semana" | `plazas-y-zonas` |
| `calificaciones` | Las filas con `id_lavanderias`: la calificación recibida y su promedio (E04 y E08, bloqueados por DD-052) | `perfil-de-lavanderia` (base) y `historial-y-calificacion` |
| `comisiones` | El monto neto de comisión, si DD-013 y DD-020 lo piden (E03 y E07, bloqueados) | `administracion` |

`comisiones` nace en `administracion` (sprints 21 a 24), después de este cambio (sprints 20 y 21). Solo la necesitan
E03 y E07, que ya están bloqueados. `pedidos` trae además su columna `comision_porcentaje`; si vale para el monto neto
de un pedido ya cobrado lo dice DD-020 ("desde cuándo aplica un cambio de porcentaje").

## Risks / Trade-offs

- [Se usa el historial antes de cerrar DD-013 y DD-052] → la lavandería ve folio y fecha, pero no monto ni
  calificación, y F-B04 no se acredita completo para A43-6.

## Open Questions

- **Qué cuenta como "pedido del día":** el completado ese día (lectura de este cambio) o el recibido ese día. Si
  Arthur cree que es una pregunta de negocio, va a EL CLIENTE como fila de la deuda.
- **DD-013, DD-020, DD-012 y DD-052.**
- **Pantallas sin componente.** El historial y las estadísticas no tienen componente en
  `vault/design-system/componentes.md`.
