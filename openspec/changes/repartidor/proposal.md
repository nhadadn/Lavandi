## Why

El Módulo C, "en su versión operativa mínima", pide registro, pedidos asignados en tiempo real con aceptar o rechazar con motivo, los botones "Recolectado → En tránsito → Entregado", historial y estadísticas (`NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3`). El Módulo D pide "Alta, baja, suspensión y edición de socios repartidores" (`NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3`). Sin ellos no se acreditan A43-3 ni A43-6 (`NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3`). Es el hito H8 del plan de sprints (sprints 18 a 21).

## What Changes

- El repartidor se registra en la app con nombre, teléfono, correo, foto de perfil y tipo de vehículo, y queda pendiente hasta que EL CLIENTE lo autorice (F-C01).
- La administración, en el panel, da de alta, suspende, da de baja y edita a los repartidores (F-D02). La validación ocurre fuera de la plataforma.
- El repartidor ve sus pedidos asignados, actualizados cada 30 s con la app abierta (F-C02, ADR-0007), y los acepta o rechaza con motivo.
- Un pedido sin pago acreditado no se le ofrece al repartidor: no se recolecta lo que no está pagado (C-188). El orden lo sostiene el contrato: resumen (#9), pago (#10) y confirmación con folio (#11) (`NSV-2026-001-v8.pdf:16-17:ANEXO A, Sección A.3`).
- Mueve el pedido que aceptó con "Recolectado → En tránsito → Entregado" (F-C03).
- Al marcar Entregado puede tomar una foto como evidencia de entrega, que se guarda privada (`:20:A.3`, ADR-0006).
- Ve sus entregas completadas y cuántas lleva en el día y en la semana (F-C04 y F-C05).
- El componente de autorización único suma las reglas del rol repartidor (`CLAUDE.md` §9.5): solo ve lo suyo y solo en su plaza.
- Quedan bloqueados: quién asigna (DD-014), cómo entra cada rol (DD-053), el aviso con la app cerrada (DD-054), cómo se ven sus estados en la app del usuario (DD-022 y DD-011), cuánto gana (DD-019 y DD-013), su calificación recibida (DD-052) y cuándo es obligatoria la evidencia (BR-29, DD-110).

**Qué NO cambia**

- Lo que el Módulo C excluye no se construye: verificación documental (licencia, póliza, tarjeta de circulación, placas, identificación), datos biométricos, cálculo de liquidaciones, propinas o dispersión de pagos, optimización de rutas o asignación automática, seguimiento GPS fuera de un pedido activo, y verificación de antecedentes o de identidad contra bases externas (`NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3`).
- El Módulo C no pide la ubicación del teléfono: ninguna de sus cinco funciones la usa.
- La máquina de estados del pedido y lo que ve el usuario: cambio `estados-del-pedido`.
- Los avisos al usuario: cambio `avisos`.
- El monitoreo de pedidos y los indicadores del panel: cambios `administracion` e `indicadores` (F-I08 usa las entregas de este cambio).

**Fuera del contrato**

- Reparto con Rappi, DiDi, Uber o flotillas, y asignación automática (guía de la reunión): la asignación automática está excluida (`:18:Módulo C`). DD-014.
- Ver en un mapa dónde va el pedido (video del 2026-11-14): sin GPS continuo (`:18:Módulo C`). DD-077.
- Selector de periodo en el historial de entregas y desglose por día en las estadísticas (mocks C04 y C05): el contrato pide entregas completadas y conteos del día y de la semana. Posible Orden de Cambio si se quieren.

## Capabilities

### New Capabilities

- `repartidor`: Módulo C completo —registro, pedidos asignados con aceptar o rechazar con motivo, estados Recolectado, En tránsito y Entregado, evidencia de entrega, historial y estadísticas— y la gestión de repartidores del Módulo D (F-C01 a F-C05 y F-D02).

### Modified Capabilities

Ninguna.

## Impact

- **`servidor/`:** el registro de repartidores; la API de pedidos asignados, aceptación, rechazo y estados; la subida de evidencias; el historial y las estadísticas; el recurso de repartidores en el panel de Filament; y las reglas del rol repartidor en el componente de autorización.
- **`movil/`:** las pantallas del Módulo C: registro, pedidos asignados, rechazo con motivo, detalle con los botones de estado y la foto de evidencia, historial y estadísticas.
- **Base de datos:** crea `repartidores` (con su plaza y su estado: `pendiente`, `activo`, `suspendido`, `baja`), `pedido_asignaciones` (la oferta, la aceptación y el rechazo con motivo) y `evidencias`, y le pone a `pedidos.id_repartidores` su llave foránea. `repartidores` no tiene columnas de documentos: la verificación documental está excluida (`NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3`); si un día entra por Orden de Cambio, es una tabla nueva, `documentos_repartidor`. El detalle está en `design.md`, sección "Tablas y migración" (`vault/arquitectura/modelo-de-datos.md`, ADR-0016).
- **Almacenamiento:** las fotos de perfil y las evidencias en el bucket privado (ADR-0006).
- **Dependencias:** ninguna nueva. Reutiliza la cámara de `perfil-y-soporte`.
- **ADRs:** ADR-0002, ADR-0003, ADR-0005, ADR-0006, ADR-0007, ADR-0008 (solo si DD-054 se aprueba) y ADR-0016 (modelo de datos).
