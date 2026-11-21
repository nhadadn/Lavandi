## Why

El contrato pide un "Panel de indicadores integrado en la sección de administración, que muestra en pantalla el estado del negocio sin necesidad de exportar ni procesar información manualmente", con once métricas en cinco categorías (`NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5`). Su criterio de aceptación es que "El panel muestra en tiempo real los indicadores definidos en A.5" (`NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.4`), y el de la app, que el panel de administración "muestra datos reales de pedidos y usuarios activos" (`NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3`).

## What Changes

- **Operaciones:** pedidos totales por día, semana y mes, en barras y en tabla comparativa (F-I01); pedidos por estado, en dona (F-I02).
- **Usuarios:** usuarios activos por día, semana y mes, en línea de tendencia (F-I03); porcentaje que vuelve a pedir, por mes de registro (F-I04); monto promedio por pedido, con su tendencia (F-I05).
- **Socios:** lavanderías activas frente a registradas (F-I06); ranking de calificación promedio por lavandería, con semáforo de cortes configurables (F-I07); entregas completadas por repartidor (F-I08).
- **Financiero:** ingresos brutos por periodo en barras agrupadas (F-I09) y comisiones por socio y en total (F-I10), con las mismas cifras que los reportes financieros de `administracion`.
- **Geográfico:** mapa de calor de pedidos por colonia o por zona de servicio (F-I11).
- **El panel entero:** dentro de la administración, con datos de la base y refresco automático (A44-1, A43-7).
- Las definiciones de "usuarios activos", "retención" y "monto promedio por pedido" son las del glosario del contrato (`NSV-2026-001-v8.pdf:46:ANEXO D, Sección D.5`).

**Qué NO cambia:**

- El cálculo de ingresos y comisiones vive en `administracion`; aquí se reutiliza.
- La descarga en CSV y Excel vive en `exportacion`, y el criterio A44-2 se acredita ahí.
- La colonia se captura en `direcciones`; las zonas, en `plazas-y-zonas`; las calificaciones, en `historial-y-calificacion`; las entregas, en `repartidor`.
- La vista reducida de administración en la app (C-140): no lleva indicadores en este cambio.

**Fuera del contrato** (posibles Órdenes de Cambio, `NSV-2026-001-v8.pdf:8:Cláusula IV`). Nadie ha pedido indicadores fuera del A.5. Quedan fuera, aunque el contrato los nombre en otra parte:

- **Costo de adquisición, valor del cliente y tasa de conversión.** El glosario los define (`NSV-2026-001-v8.pdf:46-47:ANEXO D, Sección D.5`) y la tercera consultoría los revisa (`NSV-2026-001-v8.pdf:23:ANEXO A, Sección A.6`), pero no están en el A.5. El contrato excluye los "Reportes de métricas o análisis de desempeño adicionales a los previstos en la Sección A.5" (`NSV-2026-001-v8.pdf:25:ANEXO A, Sección A.8`).
- **Filtrar los indicadores por plaza o por ciudad.** El A.5 no lo pide. Dar de alta una ciudad "en los reportes" es parte de abrir una ciudad nueva, que va por Orden de Cambio (`NSV-2026-001-v8.pdf:38:ANEXO C, Sección C.3`). El modelo lo permite; el panel no lo construye.

## Capabilities

### New Capabilities

- `indicadores`: los 11 indicadores del A.5 dentro del panel de administración, calculados con los datos de la base y refrescados solos.

### Modified Capabilities

Ninguna.

## Impact

- **`servidor/`:** una página de indicadores en el panel de Filament, con widgets de barras, dona, línea, indicador destacado y tablas; las consultas de cada indicador, que también usará `exportacion`; el mapa de calor con agregados calculados en el servidor; el registro de actividad que alimenta los usuarios activos. Tests en `servidor/tests/Feature/Indicadores/`.
- **`movil/`:** nada.
- **Base de datos:** crea `actividad_usuarios`, una fila por persona y día (qué la escribe espera DD-111). Los cortes del semáforo y el intervalo de refresco van como datos de configuración. Los demás indicadores se calculan de `pedidos`, `pedido_eventos`, `pagos` y `calificaciones`, sin tablas propias; si una consulta resulta lenta, se agrega una vista materializada, que no cambia ninguna tabla (DD-116, `vault/arquitectura/modelo-de-datos.md`). El detalle está en `design.md`, sección "Tablas y migración".
- **Dependencias:** ninguna fuera de los ADR: las gráficas y widgets de Filament (ADR-0003), PostGIS para las zonas (ADR-0004) y Google Maps JavaScript en el panel (ADR-0009).
- **Cambios de los que depende:** `administracion`, `estados-del-pedido`, `pago`, `direcciones`, `plazas-y-zonas`, `historial-y-calificacion`, `repartidor` y `alta-de-lavanderias`.
- **ADRs:** ADR-0003, ADR-0004, ADR-0007, ADR-0009 y ADR-0016 (modelo de datos).
