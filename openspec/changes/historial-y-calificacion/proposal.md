## Why

El Módulo A pide un "Historial de pedidos" con "Lista con filtro por fecha y opción de repetir pedido" y una "Calificación del servicio" con "Escala de 1 a 5 estrellas con comentario opcional" (`NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3`, Módulo A, numerales 13 y 14). Sin estas dos funciones el Módulo A no está completo y no se acredita A43-6: "Los cuatro módulos […] accesibles y funcionales conforme a A.3" (`NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3`). Es el hito H7 del plan de sprints (sprints 17 y 18).

## What Changes

- El usuario ve la lista de sus pedidos, cada uno con su folio, su fecha, su lavandería y su estado (F-A13).
- Filtra esa lista por un rango de fechas (F-A13).
- Tiene la opción de repetir un pedido. Qué datos se copian del pedido original queda bloqueado (DD-107).
- Califica el servicio de un pedido con 1 a 5 estrellas y un comentario opcional (F-A14). Se guarda en la tabla `calificaciones` (componente `calificaciones` de `vault/arquitectura/c3-api.md`), ligada al pedido y a la lavandería del pedido.
- Cada calificación es de la lavandería o del repartidor del pedido, exactamente uno de los dos, y la base lo impone (ADR-0016).
- La API solo le entrega y le deja calificar sus propios pedidos (SEG-3).
- Si el usuario califica también al repartidor queda bloqueado (DD-052).

**Qué NO cambia**

- La máquina de estados del pedido y cómo se ve un pedido cancelado o rechazado: cambio `estados-del-pedido` (BR-22).
- El recorrido para pedir y pagar: cambios `agenda-de-recoleccion`, `direcciones`, `resumen-y-confirmacion` y `pago`. Repetir un pedido reutiliza ese recorrido; no crea otro.
- El promedio de calificación y las reseñas del perfil de la lavandería: cambio `perfil-de-lavanderia` (F-A05).
- La "calificación recibida" que ven la lavandería y el repartidor: cambios `historial-de-la-lavanderia` y `repartidor`.
- El ranking del indicador F-I07: cambio `indicadores`.

**Fuera del contrato**

- Ninguna petición registrada toca este cambio. Los atajos del mock para filtrar ("Últimos 30 días", "Este año") son forma de presentar el filtro por fecha, no una función nueva.

## Capabilities

### New Capabilities

- `historial`: lista de pedidos del usuario con filtro por fecha, opción de repetir pedido y calificación del servicio de 1 a 5 estrellas con comentario opcional (F-A13 y F-A14).

### Modified Capabilities

Ninguna.

## Impact

- **`servidor/`:** la API del historial del usuario (lista y filtro) y la de calificaciones, dentro del componente de autorización único (`CLAUDE.md` §9.5).
- **`movil/`:** las pantallas "Mis pedidos" y "Califica tu servicio" del Módulo A.
- **Base de datos** (ADR-0016, `vault/arquitectura/modelo-de-datos.md`): completa `calificaciones`, que nace en `perfil-de-lavanderia`, con `id_repartidores` y la regla de exactamente un calificado por fila. Los pedidos ya existen desde H3 y H6.
- **Dependencias:** ninguna nueva.
- **ADRs:** ADR-0002 (app), ADR-0003 (API), ADR-0004 (base de datos), ADR-0005 (sesión con tokens de Sanctum) y ADR-0016 (modelo de datos).
- **Depende de** que existan los cambios `modelo-de-datos`, `perfil-de-lavanderia`, `resumen-y-confirmacion`, `estados-del-pedido` y `autorizacion-por-rol`. La llave de `id_repartidores` necesita además la tabla `repartidores`, del cambio `repartidor`, que va después en el plan (`design.md`, Risks).
