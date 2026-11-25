---
estado: vigente
grado: D
fuente: acta-2026-11-13-cierre-de-la-fase-6.md C-152; restricciones-no-negociables.md RN-29 (A); brecha-ejecutivo-tecnico.md BR-42 (B); presupuesto-de-mapas.md (D); verificaciones-fase-6.md, filas 6, 6b, 8a, 8b y sección del 2026-11-12 (E); ADR-0002, ADR-0003 y ADR-0004 (D); adenda del 2026-11-24: acta-2026-11-20-modelo-y-pasarela.md, decisión 40, del concilio del 2026-11-24, por delegación de C-236 y C-245 (C); DD-139 (B); developers.google.com/maps/documentation/geocoding/policies, consultada el 2026-11-23 (E, SIN VERIFICAR contra los términos firmados); developers.google.com/maps/documentation/places/web-service/place-id, consultada el 2026-11-24 (E, SIN VERIFICAR); NSV-2026-001-v8.pdf:29:ANEXO B, Sección B.5 (A)
fecha: 2026-11-13
dueño: Arthur Naranjo
adr: ADR-0009
supersede:
---
# ADR-0009 — Mapas: `react-native-maps` en la app y versiones mínimas iOS 18 y Android 11

## Contexto

- **Qué pide el contrato (A):**
  - mapas de Google en Android y de Apple en iPhone (RN-29);
  - compatibilidad "con la gran mayoría de los teléfonos" (BR-42).
- **Qué ya está decidido (D):**
  - `presupuesto-de-mapas.md` ya acota el consumo: distancia en línea recta, una geocodificación por dirección en su
    vida, la colonia como campo del formulario, el seguimiento sin mapa y la landing con polígonos propios. Son dos
    eventos de pago por pedido de un usuario nuevo y uno por uno recurrente.
  - El radio de servicio y las zonas se resuelven con PostGIS (`ADR-0004`).
- **Costos (E):** los mapas nativos de Google en Android y de Apple en iPhone no tienen costo por uso. Los mapas web
  de Google tienen 10,000 cargas gratis al mes (verificaciones, filas 6 y 6b).
- **Versiones en México, agosto de 2026 (E, medida de tráfico web):**
  - iOS 26 tiene al menos el 57 % e iOS 18 al menos el 31 %, así que exigir iOS 18 cubre cerca del 90 %.
  - Android 11 o superior tiene al menos el 92 % (verificaciones, filas 8a y 8b).

## Criterios y pesos

Los de la matriz `vigente` (C-142). RN-29 funciona como puerta.

## Alternativas evaluadas (mapa de la app)

| Alternativa | RN-29 | Familiaridad (30) | Velocidad (25) | Proveedor (15) | Tiempo real (10) | Costo (10) | Multi-plaza (10) | **Total** |
|---|---|---|---|---|---|---|---|---|
| **`react-native-maps` 1.29.11** (MIT, 16 mil estrellas). Sin `provider` usa MapKit en iOS y Google en Android | pasa | 3 | 5 | 4 | 5 | 5 | 5 | **86** |
| `expo-maps` 58.0.11, estable desde el SDK 58 y exige iOS 17 | pasa | 3 | 4 | 4 | 5 | 5 | 5 | **81** |

`react-native-maps` gana por madurez. Además, Expo la recomienda para el SDK 58 (E).

## Decisión

1. **App:** `react-native-maps` sin `provider`, que da Apple Maps en iPhone y Google Maps en Android. Lleva la llave
   de Google Maps SDK para Android, restringida a la app.
2. **Versiones mínimas (BR-42): iOS 18 y Android 11.** Cubren cerca del 90 % y al menos el 92 % de los teléfonos
   que navegan en México (E). Si el SDK de Expo o el de Conekta exigen más, manda el mayor.
3. **Panel de administración:** Google Maps JavaScript, con plugin de Filament o un componente propio, para:
   - el monitoreo de pedidos activos (O-16);
   - el mapa de calor por colonia, dibujado con zonas o marcadores agregados en el servidor.

   No depende de la capa de mapa de calor del proveedor. Cabe en las 10,000 cargas gratis al mes.
4. **Geocodificación:** Google Geocoding, una vez por dirección al guardarla, como dice `presupuesto-de-mapas.md`.
5. **Landing:** el mapa de cobertura se dibuja con polígonos propios, no con teselas de pago (`presupuesto-de-mapas.md`).

La aprobó Arthur Naranjo el 2026-11-13 (`acta-2026-11-13-cierre-de-la-fase-6.md`, C-152), con la opción que recomendó Claude.

## Consecuencias

- **Positivas:**
  - Cumple RN-29 con la librería más usada.
  - El costo de mapas por pedido queda acotado.
  - BR-42 queda con número y con fuente.
- **Negativas:**
  - Exigir iOS 18 deja fuera cerca del 10 % de los iPhone. Si el cliente lo objeta, se baja a iOS 17, con el
    costo de probar en uno más.
  - El plugin de mapas de Filament está en beta. Si falla, el mapa del panel se hace como componente propio.
- **Condiciona:** la spec del Módulo A (buscar lavandería), la del Módulo D (monitoreo y mapa de calor) y el
  ADR-0012 (llaves de Google por entorno).

## Criterio de reversión

- Si en la prueba temprana el mapa de Apple no se ve en iPhone con `react-native-maps` sin `provider`, se pasa a
  `expo-maps`.
- Si el consumo real de mapas web o de geocodificación pasa del nivel gratuito en el primer mes de operación, se
  revisan las cinco decisiones de `presupuesto-de-mapas.md`.

## Costo estimado de revertir

Bajo. El mapa vive en uno o dos componentes de la app, y las dos librerías tienen API parecida.

## Trazabilidad

- **Obligaciones:** O-12, O-13 y O-16.
- **Restricciones:** RN-29 y BR-42.
- **Notas:** `presupuesto-de-mapas.md`.
- **ADRs relacionados:** ADR-0002, ADR-0003, ADR-0004 y ADR-0012.

## Adenda del 2026-11-21: la dirección asistida y desde dónde se geocodifica

**Contexto.**

- Arthur pidió la dirección asistida y aprobó sus tres piezas (C-238). F-A08 pide "Ingreso manual o desde mapa" sin
  decir cómo (`NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3`, Módulo A #8).
- RN-29 restringe el mapa, no la geocodificación ni el autocompletado. RN-23 pide el consumo de mapas acotado por diseño
  (`NSV-2026-001-v8.pdf:29-30:ANEXO B, Sección B.5`).
- `presupuesto-de-mapas.md` dejó pendiente "si el autocompletado de direcciones usa un servicio de pago o una lista
  propia", para este ADR.
- DD-135, punto 6, decidió que la geocodificación sale del servidor, "con un ADR corto" (C-236).

**Decisión.**

- **4, precisión.** La geocodificación de la decisión 4 la hace el servidor (`api`), nunca la app. La llave no viaja en
  la app y las consultas de pago se cuentan en un solo lugar. El texto que se geocodifica lleva el municipio y el estado
  del catálogo.
- **6. El municipio y la colonia se asisten con listas propias, sin costo por consulta.** Los municipios del territorio
  y las colonias por código postal, del Catálogo Nacional de Códigos Postales de Correos de México, cargados en la base
  con un comando. El municipio se elige de la lista y la API rechaza uno que no esté en ella. La colonia y el código
  postal siguen siendo texto: el catálogo los sugiere y no los exige.
- **7. La calle se autocompleta con Google Places (la API nueva), desde el servidor, apagado por omisión.** Se enciende
  en cada entorno con `GOOGLE_PLACES_ACTIVO` y `GOOGLE_PLACES_LLAVE` (ADR-0012). Una vez construidas las tareas 3.2 a
  3.4, encenderlo o apagarlo no pide otra versión de la app ni cambia el resto de la API.
  - **Una sesión por dirección.** La app crea un token, un UUID v4, al empezar a escribir la calle de una dirección
    nueva. Lo manda en cada petición de sugerencias y al guardar.
  - **La sesión termina en una sola consulta de detalle,** al guardar, con el mismo token y la máscara de campos
    `location`. Esa consulta sustituye a la geocodificación de esa dirección, así que una dirección sigue costando una
    sola consulta de pago al guardarse.
  - **Menos peticiones:** tres caracteres antes de pedir, 300 ms de espera entre teclas, hasta cinco sugerencias, 30
    peticiones por minuto por cuenta, y la app deja de pedir cuando el servidor responde que está apagado.
  - **Solo México,** con el municipio y el estado elegidos.
  - **Con la atribución de Google** que pidan sus condiciones (grado E).

**Alternativas para la calle.**

| Alternativa | Qué pasa con ella |
|---|---|
| Places desde la app, con la llave en la app | Se descarta. La llave de un servicio web viaja en cada teléfono y se restringe peor que en el servidor (grado E). El conteo de consultas queda repartido. Contradice la precisión de la decisión 4 |
| Un catálogo propio de calles | Se descarta. SEPOMEX no trae calles. El de vialidades del INEGI (grado E, sin verificar) pesa más y hay que mantenerlo y cruzarlo con los números |
| Sin autocompletado | No se descarta: es lo que queda mientras esté apagado, y la dirección escrita se sigue pudiendo guardar |

**Costo** (grado E, `SIN VERIFICAR`; precios en dólares):

- **De `verificaciones-fase-6.md`, fila 6, consultado el 2026-09-28** en
  `https://developers.google.com/maps/billing-and-pricing/pricing`:
  - geocodificación: 10,000 gratis al mes y después 5 USD por mil;
  - autocompletado: 10,000 gratis y después 2.83 USD por mil.
- **De memoria de Claude, sin consulta en esta sesión,** en esa página y en
  `https://developers.google.com/maps/documentation/places/web-service/session-pricing`:
  - el detalle de un lugar con solo campos básicos, como `location`, se cobra en el nivel más bajo, con un precio
    cercano al de la geocodificación;
  - las sugerencias de una sesión que termina en una consulta de detalle no se cobran aparte;
  - las de una sesión que no termina se cobran por petición.

**Consumo por dirección nueva escrita:**

| Evento de pago | Apagado | Encendido, eligió una sugerencia | Encendido, escribió sin elegir |
|---|---|---|---|
| Geocodificación | 1 | 0 | 1 |
| Detalle de Places | 0 | 1 | 0 |
| Peticiones de sugerencias que se cobran | 0 | 0 (E) | Las que pidió (E) |

Esta nota no estima el gasto en pesos. Para eso falta el número de direcciones nuevas al mes, que es dato del negocio
(regla 3 de evidencia).

**Quién decide qué.** La asentó Claude por delegación de Arthur Naranjo (C-236), sobre su pedido (C-238). Encender el autocompletado en producción es gasto de un
servicio contratado a nombre de EL CLIENTE (RN-20): lo acepta Nadir con EL CLIENTE, en la fila que propone este diseño.

**Criterio de reversión.** Si en el primer mes con el autocompletado encendido las sugerencias pasan del nivel
gratuito, se apaga por configuración, sin tocar nada más. Si la licencia de SEPOMEX no permite usar el catálogo, la
colonia vuelve a ser solo texto y el municipio sale de otra fuente.

**Costo estimado de revertir.** Bajo: el autocompletado es un interruptor, y la colonia sin lista es el formulario de
hoy.

**Trazabilidad.** direcciones-E16 a E23; DD-137, DD-138 y DD-139; decisión 29 del acta del 2026-11-20.

## Adenda del 2026-11-24: cuánto se guarda el punto de Google (DD-139)

**Contexto.**

- La política de Geocoding permite guardar la latitud y la longitud hasta 30 días consecutivos, y el place ID sin
  límite (grado E, `SIN VERIFICAR` contra los términos firmados:
  `https://developers.google.com/maps/documentation/geocoding/policies`, consultada el 2026-11-23).
- Choca con la decisión 4, que geocodifica cada dirección una sola vez en su vida y guarda su punto para siempre
  (`presupuesto-de-mapas.md`, decisión 3).
- El contrato no dice cuánto se guarda el punto:
  - RN-29 restringe el SDK del mapa, no la geocodificación;
  - RN-23 pide el consumo de mapas acotado por diseño (`NSV-2026-001-v8.pdf:29:ANEXO B, Sección B.5`);
  - F-A08 pide "Ingreso manual o desde mapa. Guardado de direcciones favoritas" (`NSV-2026-001-v8.pdf:16:ANEXO A,
    Sección A.3`, Módulo A #8).
- DD-139 la responde Arthur, que delegó en el concilio lo que esperaba de él (C-236, C-245).

**Decisión (opción b).**

- **El punto de Google guarda su place ID y su fecha.** Cada dirección geocodificada por Google guarda su place ID y la
  fecha de su punto.
  - Dentro de 30 días se usa el punto guardado, sin consultar.
  - Pasado ese plazo, el punto se vuelve a pedir por el place ID al usar la dirección.
- **4, precisión.** Una consulta al guardar la dirección, y otra solo al usar una dirección cuyo punto pasó de 30 días.
- **El punto que marca la persona en el mapa (direcciones-E04) no viene de Google y no vence.**

**Las copias del punto.** El punto de Google también queda en:

- `pedido_direcciones.ubicacion`, `NOT NULL` y parte del historial del pedido
  (`servidor/database/migrations/2026_11_21_400000_crear_estados_y_pedidos.php:190`);
- `lavanderias.ubicacion` (`servidor/app/Lavanderias/UbicacionDeLaLavanderia.php`), que también se geocodifica una vez.

SIN RESPUESTA — bloquea: el código de DD-139 (DD-139). El trato de las dos copias se fija en esta misma adenda, antes de
cualquier código, con los términos firmados verificados.

**Lo que la decisión no dice.** También se fija antes del código de la tarea 3.5:

- qué hace el servidor al usar la dirección si la consulta por place ID falla, porque Google no responde (hoy
  `GeocodificadorDeGoogle` lanza `GeocodificacionNoDisponible`, `servidor/app/Direcciones/GeocodificadorDeGoogle.php:45`)
  o porque el place ID ya no existe y Google responde `NOT_FOUND` (grado E, `SIN VERIFICAR`, la fuente de la tabla de
  alternativas);
- qué pasa con las direcciones guardadas antes de la columna nueva, que no tienen place ID ni fecha;
- si el punto del detalle de Places (direcciones-E22) entra en la misma regla y guarda como place ID el `lugar` de la
  sugerencia.

SIN RESPUESTA — bloquea: la tarea 3.5 (DD-139).

**Costo.** Por uso (RN-23, `NSV-2026-001-v8.pdf:29:ANEXO B, Sección B.5`). El gasto va con el presupuesto de mapas
(RN-20), que aceptan Nadir y EL CLIENTE.

**Cuándo.** Se implementa antes de `publicacion`, con la llave de Google. No bloquea el desarrollo.

**Alternativas descartadas.**

| Alternativa | Por qué se descarta |
|---|---|
| (a) Volver a geocodificar por texto a los 30 días | Repite la búsqueda por texto, que puede dar otro punto que el guardado. El motivo es de quien asentó la adenda: el registro del concilio no lo trae. Que el place ID dé el mismo lugar es grado E, `SIN VERIFICAR`: Google dice que un place ID puede cambiar con el tiempo y que uno obsoleto responde `NOT_FOUND` (`https://developers.google.com/maps/documentation/places/web-service/place-id`, consultada el 2026-11-24) |
| (c) Que el punto guardado sea el que confirma la persona en el mapa | Agrega un paso a direcciones-E01, y que ese punto quede fuera de la condición de Google es dudoso (grado E) |

**Quién la decidió.** La decidió el concilio del 2026-11-24 por delegación de Arthur Naranjo (C-236, C-245), en la
decisión 40 de `acta-2026-11-20-modelo-y-pasarela.md`. Siguen abiertos la verificación de los términos firmados, el
trato de las copias del punto y lo que la decisión no dice (DD-139).

**Trazabilidad.** direcciones-E07 y E24; DD-139.
