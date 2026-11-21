---
estado: vigente
grado: D
fuente: acta-2026-11-13-cierre-de-la-fase-6.md C-152; restricciones-no-negociables.md RN-29 (A); brecha-ejecutivo-tecnico.md BR-42 (B); presupuesto-de-mapas.md (D); verificaciones-fase-6.md, filas 6, 6b, 8a, 8b y sección del 2026-11-12 (E); ADR-0002, ADR-0003 y ADR-0004 (D)
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
