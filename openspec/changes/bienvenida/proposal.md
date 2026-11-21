## Why

Es lo primero que ve quien abre la app. El contrato lo pone como función 1 del Módulo A: "Pantalla de bienvenida y 3
pantallas informativas sobre el servicio" (`NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3`, Módulo A #1). Los textos y
las imágenes son insumo de EL CLIENTE, que debe proporcionar "los insumos, accesos, materiales, contenidos e
información" (`NSV-2026-001-v8.pdf:6:Cláusula III, inciso B`). Por eso las pantallas se construyen con contenido
configurable, y la función se acredita cuando llegue el suyo (DD-081).

## What Changes

- Una pantalla de bienvenida con el nombre del producto tomado de la configuración (`base`) y con su contenido del
  archivo de contenido.
- Tres pantallas informativas sobre el servicio, que se recorren en orden.
- Al terminar la tercera, la app ofrece registrarse o iniciar sesión (`registro`).
- Los textos y las imágenes viven en un archivo de contenido de la app, no escritos en las pantallas. Mientras no
  llegue el de EL CLIENTE, el contenido es provisional.

**Qué NO cambia:**

- El registro y el inicio de sesión: `registro-e-inicio-de-sesion` (F-A02).
- El ícono y la pantalla de arranque nativa de la app, que llevan imagen de marca (ver `design.md`, Open Questions).
- La explicación de por qué se piden la ubicación, la cámara y los avisos: va donde se usa cada permiso, en los
  cambios de esas pantallas.

**Fuera del contrato:**

- Nada. Para este cambio nadie pidió algo sin cita.

## Capabilities

### New Capabilities

- `bienvenida`: la pantalla de bienvenida y las tres pantallas informativas del Módulo A, con contenido configurable.

### Modified Capabilities

- Ninguna.

## Impact

- **`movil/`:** las pantallas de bienvenida y las tres informativas, y el archivo de contenido con sus textos e
  imágenes.
- **`servidor/`:** no cambia.
- **Base de datos y dependencias:** no cambian.
- **ADRs:** ADR-0002 (la app) y ADR-0013 (los tokens del design system).
- **Insumo de EL CLIENTE:** los textos e imágenes de las cuatro pantallas, antes del sprint 03, el 7 de diciembre
  (DD-081).
