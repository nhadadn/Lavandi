## Why

El contrato pide la "Dirección de recogida y entrega" con "Ingreso manual o desde mapa. Guardado de direcciones
favoritas" (`NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3`, Módulo A #8; F-A08), con los mapas de Google en Android y
los de Apple en iPhone (`NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3`), y que el servicio opere solo en las zonas del
TERRITORIO INICIAL (`NSV-2026-001-v8.pdf:4:Cláusula I`). Es el tercer paso del pedido en el hito H3.

## What Changes

- La app deja escribir una dirección (calle y número, colonia, municipio y referencias) o marcarla en el mapa nativo de
  cada plataforma.
- Una dirección escrita se convierte en un punto del mapa **una sola vez**, al guardarla, y su punto se vuelve a pedir
  por el place ID al usarla si pasó de 30 días (adenda del 2026-11-24 de ADR-0009); una marcada en el mapa se guarda
  con el punto del pin, sin consulta de pago. La colonia es campo obligatorio (ADR-0009).
- El usuario guarda direcciones favoritas con un nombre y las elige después sin volver a escribirlas.
- El pedido lleva una dirección de recogida y una de entrega, que puede ser la misma (`:16:A.3 #8`; `:30:ANEXO B,
  Sección B.5`).
- El servidor solo acepta, para un pedido, direcciones dentro de una zona de servicio de la plaza de la lavandería, y
  dice el motivo cuando rechaza una.
- Cada cuenta ve y usa solo sus direcciones (`:20:A.3`, separación por tipo de usuario).
- **Dirección asistida (C-238).**
  - El municipio se elige de una lista con solo los del territorio del contrato (`:4:Cláusula I`).
  - La colonia se sugiere por el código postal, con el catálogo de Correos de México.
  - La calle se autocompleta con Google Places desde el servidor, apagado hasta tener su llave y su costo aceptado
    (DD-137).
  - Es la forma de cumplir "Ingreso manual" sin agrandar el alcance (direcciones-E16 a E23; adenda del 2026-11-21 de
    ADR-0009).

**Qué NO cambia:**

- El trazado y la carga de las zonas de servicio son del cambio `plazas-y-zonas` (BR-02). Aquí solo se consultan.
- Editar o borrar las direcciones guardadas desde el perfil (F-A15, "direcciones guardadas") va en `perfil-y-soporte`
  (H7).
- No hay geocodificación inversa: soltar el pin no sugiere la calle (decisiones 3 y 4 de
  `vault/arquitectura/presupuesto-de-mapas.md`). El autocompletado de la calle queda apagado hasta DD-137.
- Si la lavandería atiende toda su plaza o solo un radio alrededor de ella es DD-083 (`lavanderias-cercanas`). Aquí
  queda el escenario bloqueado.
- El seguimiento del pedido no muestra mapa (decisión 5 de `presupuesto-de-mapas.md`).

**Fuera del contrato:**

- Ver en un mapa dónde va el pedido (DD-077, contractual; video del cliente del 2026-11-14, C-172). El contrato pide el
  seguimiento por estados (`:17:A.3 #12`) y excluye el GPS continuo fuera de un pedido activo (`:18:A.3`, Módulo C).
  Es posible Orden de Cambio (`NSV-2026-001-v8.pdf:8:Cláusula IV`); no entra aquí.

## Capabilities

### New Capabilities

- `direcciones`: alta de direcciones escritas o marcadas en el mapa, geocodificadas una sola vez al guardarlas y con el
  punto de Google renovado por el place ID pasados 30 días; favoritas con nombre; recogida y entrega del pedido dentro
  de la zona de servicio de la plaza; y aislamiento por cuenta.

### Modified Capabilities

Ninguna.

## Impact

- **`servidor/`:** rutas de la API para guardar, listar y elegir direcciones; la llamada a Google Geocoding (ADR-0009,
  decisión 4) detrás de una interfaz que los tests simulan; la consulta de zona con PostGIS (ADR-0004).
- **`movil/`:** el formulario de dirección, la elección en el mapa con `react-native-maps` sin `provider` (ADR-0009) y
  la lista de favoritas con recogida y entrega.
- **Base de datos:** nace la tabla `direcciones`, con su `id_publico`, su plaza, su punto geográfico, su colonia
  obligatoria y si es favorita (columnas en `design.md`, tomadas de `vault/arquitectura/modelo-de-datos.md`). Las zonas
  (`zonas`) vienen de `plazas-y-zonas`. La copia de la dirección en el pedido (`pedido_direcciones`) la hace
  `resumen-y-confirmacion`.
- **Catálogos:** nacen `entidades_federativas`, `municipios` y `colonias`, que carga el comando
  `direcciones:cargar-catalogo` desde el archivo de SEPOMEX (DD-138). Sale `direcciones.municipio` y entra
  `direcciones.id_municipios`. Las rutas nuevas son `GET /api/catalogos/municipios` y
  `GET /api/codigos-postales/{codigo_postal}/colonias`.
- **Dependencias:** las llaves de Google Geocoding y, cuando se encienda, la de Places, por entorno, en variables de
  entorno y nunca en el repositorio (ADR-0012). `react-native-maps` ya entra con `lavanderias-cercanas`.
- **ADRs:** ADR-0002, ADR-0003, ADR-0004, ADR-0009, ADR-0012 y ADR-0016 (modelo de datos).
