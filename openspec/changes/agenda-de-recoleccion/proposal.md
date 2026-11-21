## Why

El contrato pide que el usuario programe su recolección con un "Selector de fecha y hora, con ventanas de 1 hora"
(`NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3`, Módulo A #7; F-A07), y que el recorrido "elegir lavandería, agendar
recolección, pagar y dar seguimiento" se complete sin errores que lo impidan
(`NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3`). Es el segundo paso del pedido en el hito H3: sin ventana elegida no
hay resumen ni confirmación.

## What Changes

- El servidor calcula las ventanas de una hora de una lavandería para una fecha, dentro de su horario y de sus ventanas
  de disponibilidad (`:17:A.3`, Módulo B #1 y #6), en la hora local de la plaza.
- Solo se ofrecen ventanas futuras. La anticipación mínima y los días hacia adelante son datos de configuración de la
  plaza; el valor de producción lo fija EL CLIENTE.
- El servidor rechaza cualquier ventana que no esté entre las que ofrece.
- La app muestra el selector con el componente 2 del design system (disponible, seleccionada, día sin servicio) y lleva
  la elección hasta el resumen del pedido.
- Quedan escritos, con `SIN RESPUESTA`, los escenarios que esperan respuesta: el cupo por ventana, los pedidos de noche,
  los días festivos y si la app propone la primera ventana.

**Qué NO cambia:**

- La lavandería no edita aquí su horario ni sus ventanas de disponibilidad. Eso es F-B06, en el cambio
  `alta-de-lavanderias` (H5). En este hito las lavanderías son de prueba, con horario sembrado.
- No se crea el pedido: lo crea el cambio `resumen-y-confirmacion`.
- No cambia la tarjeta de lavandería ni su estado "Cerrada" (componente 1), que son del cambio `lavanderias-cercanas`.
- Cuándo acepta la lavandería un pedido de noche (DD-078, pregunta 4; BR-26) va en `pedidos-de-la-lavanderia`.
- No se programa ni se muestra la entrega. El contrato programa la recolección (`:16:A.3 #7`). Si el usuario debe ver
  cuándo estará lista su ropa ("un día para lavar", DD-078, pregunta 2), se especifica cuando el cliente responda.

**Fuera del contrato:**

- Elegir una ventana de entrega aparte de la de recolección (BR-17; caja "Entrega" del mock `a07-fecha-hora.html`;
  marca del cliente del 2026-11-05, "Tiempo de regreso: lo decide cada lavandería", sin confirmar). El contrato solo
  pide el selector de la recolección (`:16:A.3 #7`). Si se pide un selector de entrega, es posible Orden de Cambio
  (`NSV-2026-001-v8.pdf:8:Cláusula IV`). Es DD-099.

## Capabilities

### New Capabilities

- `agenda`: ventanas de una hora para la recolección, calculadas desde el horario de la lavandería en la hora de su
  plaza, con anticipación y horizonte de configuración, validadas en el servidor y elegidas por el usuario en la app.

### Modified Capabilities

Ninguna.

## Impact

- **`servidor/`:** una consulta de la API que devuelve las ventanas de una lavandería para una fecha y una regla de
  validación que reusa la misma lógica (el componente `agenda` del C3). La consumen después `resumen-y-confirmacion` y
  `pedidos-de-la-lavanderia`.
- **`movil/`:** la pantalla de fecha y hora del pedido nuevo, con el componente 2 del design system.
- **Base de datos:** ninguna tabla nueva (`vault/arquitectura/modelo-de-datos.md`, columna "Nace en"). El horario y
  las ventanas de disponibilidad se leen de `lavanderia_horarios`, que crea `perfil-de-lavanderia` con lavanderías de
  prueba; su edición sigue siendo de F-B06. La anticipación mínima y los días hacia adelante son dos claves de
  `parametros_plaza`, y la zona horaria es `plazas.zona_horaria`; las dos tablas las crea `plazas-y-zonas`.
- **Dependencias:** ninguna nueva.
- **ADRs:** ADR-0002 (app), ADR-0003 (las reglas viven en `api`), ADR-0004 (PostgreSQL y modelo multi-plaza) y
  ADR-0016 (modelo de datos).
