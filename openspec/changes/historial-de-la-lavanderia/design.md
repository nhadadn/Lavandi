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

## Contrato de la API

Lo implementó el implementador del servidor con las tareas 1.1 y 1.2. Es el mismo que está en el comentario de
`servidor/app/Http/Controllers/Api/HistorialDeLaLavanderiaController.php`. La app del Módulo B (tareas 2.1 y 2.2) se
hace contra él.

**Sesión y autorización.** Como las de `/api/lavanderia/pedidos` (`pedidos-de-la-lavanderia`, "Contrato de la API"):
`Authorization: Bearer <token>`, `auth:sanctum` y `autorizar:lavanderia`. La tenencia la decide
`App\Autorizacion\Autorizacion::pedidosDeLaLavanderia()`: los pedidos de las lavanderías que administra la cuenta, de su
misma plaza y con el pago acreditado (C-188).

| Respuesta | Cuándo |
|---|---|
| `401 { "message": "Unauthenticated." }` | Sin sesión |
| `403 { "message": "Esta cuenta no tiene acceso." }` | La cuenta no tiene el rol de lavandería, como una de usuario |

**El historial**, `GET /api/lavanderia/historial` (historial-lavanderia-E01 y E02):

```json
{
  "pedidos": [
    {
      "id_publico": "01jd8x6m3v2c9q4w5e7r8t0y1z",
      "folio": "7KQ2M9XA",
      "entregado_en": "2026-12-04T01:30:00Z",
      "fecha": "2026-12-03"
    }
  ]
}
```

| Campo | Qué es |
|---|---|
| `id_publico` | El ULID del pedido, el mismo de `GET /api/lavanderia/pedidos/{id_publico}` |
| `folio` | Siempre lleno: la lavandería solo ve pedidos pagados (ADR-0020) |
| `entregado_en` | El instante del paso a Entregado, en UTC, del historial del pedido en `pedido_eventos` (decisión 1) |
| `fecha` | Ese instante en la fecha de la plaza (decisión 3): una entrega a las 19:30 del 3 de diciembre en la plaza es el 3, aunque en UTC ya sea el 4 |

- Entran solo los pedidos en Entregado. No entran el que sigue en curso (de `confirmado` a `en_entrega`), el rechazado
  ni el que espera su pago.
- Solo los de las lavanderías de la cuenta, de su plaza. Uno de otra lavandería o de otra plaza no aparece, y pedirlo por
  su `id_publico` a `GET /api/lavanderia/pedidos/{id_publico}` responde `404 { "message": "No existe el pedido." }`, igual
  que uno que no existe (ADR-0019, decisión 3). El detalle de un pedido del historial es esa misma ruta: no hay otra.
- De la entrega más reciente a la más antigua; con la misma, del pedido más nuevo al más viejo. Sin paginación. Sin
  entregados, `"pedidos": []`.
- **Todavía sin monto ni calificación:** esperan DD-013, DD-020, DD-012 y DD-052 (E03 y E04). Cuando se respondan,
  entran como campos nuevos del mismo objeto, sin cambiar los que ya están.

**Las estadísticas**, `GET /api/lavanderia/estadisticas` (historial-lavanderia-E05 y E06):

```json
{ "pedidos_del_dia": 2, "pedidos_de_la_semana": 5 }
```

| Campo | Qué es |
|---|---|
| `pedidos_del_dia` | Los pedidos del historial cuyo paso a Entregado cae en el día de hoy de su plaza |
| `pedidos_de_la_semana` | Los que caen en la semana de hoy de su plaza, de lunes a domingo; incluye los de hoy |

- Cuentan los mismos pedidos del historial: solo los entregados, solo los de la cuenta y de su plaza.
- "Hoy" y "esta semana" se leen en la zona horaria de la plaza de cada pedido (decisión 3), con el reloj del servidor:
  el lunes a las 00:30 de la plaza empieza otra semana, aunque en UTC todavía sea domingo por la noche.
- Sin entregados, los dos en `0`.
- **Todavía sin ingreso total ni calificación promedio:** esperan DD-013, DD-020, DD-012 y DD-052 (E07 y E08). Cuando
  se respondan, entran como campos nuevos del mismo objeto.

## Risks / Trade-offs

- [Se usa el historial antes de cerrar DD-013 y DD-052] → la lavandería ve folio y fecha, pero no monto ni
  calificación, y F-B04 no se acredita completo para A43-6.

## Open Questions

- **Qué cuenta como "pedido del día":** el completado ese día (lectura de este cambio) o el recibido ese día. Si
  Arthur cree que es una pregunta de negocio, va a EL CLIENTE como fila de la deuda.
- **DD-013, DD-020, DD-012 y DD-052.**
- **Pantallas sin componente.** El historial y las estadísticas no tienen componente en
  `vault/design-system/componentes.md`.
