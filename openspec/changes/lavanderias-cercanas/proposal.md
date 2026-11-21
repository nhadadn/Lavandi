## Why

El Módulo A pide una "Pantalla principal" con "Mapa con lavanderías cercanas activas. Acceso rápido a pedido nuevo" y
una "Búsqueda y filtros" con "Filtros por distancia, calificación y disponibilidad"
(`NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3`, Módulo A #3 y #4). El mapa es "de Google en teléfonos Android y los
mapas de Apple en iPhone" (`NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3`). Es el primer tramo del recorrido que se
acepta: "elegir lavandería" (`NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3`).

## What Changes

- Lavanderías de prueba sembradas, con su plaza, su ubicación y su estado, en la tabla `lavanderias`, que crea
  `perfil-de-lavanderia` (ADR-0016).
- Calificaciones de prueba de 1 a 5, sembradas en `calificaciones` (también de `perfil-de-lavanderia`), para el filtro
  y la tarjeta. El vínculo con el pedido llega en `historial-y-calificacion`.
- La consulta de lavanderías activas para un punto, con su distancia en línea recta calculada en la base.
- Los filtros por distancia máxima y por calificación mínima, combinables.
- En la app: el mapa nativo de cada plataforma, el permiso de ubicación solo mientras se usa, los pines con su tarjeta,
  el acceso rápido a pedido nuevo y la pantalla de filtros.

**Qué NO cambia**

- El perfil de la lavandería y la selección de servicio: `perfil-de-lavanderia`.
- El alta real de lavanderías, su aprobación y su suspensión desde el panel: `alta-de-lavanderias` (H5).
- Las direcciones de recogida y entrega, y el mapa para elegirlas: `direcciones`.
- Crear calificaciones desde la app: `historial-y-calificacion`.
- El seguimiento del pedido no lleva mapa (`vault/arquitectura/presupuesto-de-mapas.md`, decisión 5).

**Fuera del contrato**

- Que la app asigne la lavandería en lugar de que la persona la elija: el cliente marcó en A03 "No, se asigna en la
  app" (formulario en papel del 2026-11-05, sin confirmar). El contrato pone que la persona elige
  (`NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3`; `:16:A.3`, Módulo A #3 a #5). Posible Orden de Cambio: DD-095.
- La distancia por ruta o por tiempo de traslado: el contrato no la pide y cada cálculo tiene costo por uso
  (`NSV-2026-001-v8.pdf:29:ANEXO B, Sección B.5`). Va en línea recta (ADR-0009). Sin fila.
- La ubicación en segundo plano: ninguna función del Módulo A la necesita, y el rastreo continuo está excluido
  (`NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3, Módulo C`). Sin fila.

## Capabilities

### New Capabilities

- `cercanas`: el mapa de lavanderías cercanas activas, la ubicación del teléfono, el acceso rápido a pedido nuevo y la
  búsqueda con filtros por distancia, calificación y disponibilidad.

### Modified Capabilities

Ninguna.

## Impact

- **`servidor/`:** la consulta de cercanas con sus filtros sobre `lavanderias`, `plazas` y `calificaciones`, detrás de
  la sesión de Sanctum y del componente de autorización; el sembrado de prueba, que no corre en producción.
- **`movil/`:** la pantalla principal con el mapa, el permiso de ubicación, la tarjeta de lavandería (componente 1 de
  `vault/design-system/componentes.md`) y la pantalla de filtros.
- **Base:** no crea tablas. Lee `lavanderias` y `calificaciones`, de `perfil-de-lavanderia`, y `plazas`, de
  `plazas-y-zonas` (`vault/arquitectura/modelo-de-datos.md`, columna "Nace en").
- **Dependencias:** `react-native-maps` (ADR-0009) y un módulo de ubicación del SDK de Expo (ver `design.md`). La llave
  de Google Maps para Android, de prueba, en la configuración del entorno (ADR-0012).
- **ADRs:** ADR-0002, ADR-0003, ADR-0004, ADR-0009, ADR-0012 y ADR-0016.
