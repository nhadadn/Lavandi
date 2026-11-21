---
estado: vigente
grado: D
fuente: acta-2026-11-13-cierre-de-la-fase-6.md C-152; restricciones-no-negociables.md RN-06 y RN-30 (A); brecha-ejecutivo-tecnico.md BR-49 (B); deuda-de-decision.md DD-011 y DD-054 (B); verificaciones-fase-6.md, filas 7 y sección del 2026-11-12 (E); ADR-0002 y ADR-0003 (D)
fecha: 2026-11-13
dueño: Arthur Naranjo
adr: ADR-0008
supersede:
---
# ADR-0008 — Avisos con Firebase Cloud Messaging directo

## Contexto

- **Qué pide el contrato (A):**
  - avisos en iPhone y Android para los cinco momentos del pedido (RN-06);
  - con "el servicio gratuito de Google" (RN-30), que es Firebase Cloud Messaging (FCM). En iPhone, FCM se apoya en
    el servicio de Apple, que necesita una clave de la cuenta de desarrollador (E, verificaciones, fila 7).
- **Los cinco momentos siguen sin definir (DD-011).** El cliente marcó "los cinco más en camino" (2026-11-05,
  todavía sin confirmar).
- **Los socios:** si la lavandería y el repartidor reciben aviso es DD-054. La recomendación es que sí (BR-49).
- **En Android 13 o superior la app debe pedir permiso,** y ningún paso crítico puede depender solo del aviso (E).

## Criterios y pesos

Los de la matriz `vigente` (C-142). RN-30 funciona como puerta.

## Alternativas evaluadas

| Alternativa | RN-30 | Familiaridad (30) | Velocidad (25) | Proveedor (15) | Tiempo real (10) | Costo (10) | Multi-plaza (10) | **Total** |
|---|---|---|---|---|---|---|---|---|
| **A. FCM directo:** `@react-native-firebase/messaging` en la app y `laravel-notification-channels/fcm` en `api` | pasa | 3 | 4 | 4 | 5 | 5 | 5 | **80** |
| B. Servicio de avisos de Expo | **dudosa** | 4 | 5 | 3 | 5 | 5 | 5 | 88 |

**B suma más puntos pero no se elige.** El servicio de Expo pone a Expo como intermediario y, en iPhone, habla
directo con Apple sin pasar por FCM. Eso no es literalmente "el servicio gratuito de Google" (RN-30). Una puerta
dudosa no se compra con puntos.

## Decisión

Los avisos salen de `api` (Laravel) por **FCM**, con `laravel-notification-channels/fcm` 6.2.0 (E). La app los
recibe con `@react-native-firebase/messaging` 27.1.1 (E) en un development build.

**A quién avisa:**

- **Al usuario,** en los momentos que fije DD-011.
- **A la lavandería y al repartidor,** cuando les llega un pedido nuevo o asignado, si se aprueba DD-054.
- **Al cambiar de estado un pedido,** para el refresco del `ADR-0007`.

La aprobó Arthur Naranjo el 2026-11-13 (`acta-2026-11-13-cierre-de-la-fase-6.md`, C-152), con la opción que recomendó Claude.

## Consecuencias

- **Positivas:** cumple RN-30 al pie de la letra, no tiene costo y es el mismo servicio en las dos plataformas.
- **Negativas:**
  - En iPhone depende de la clave de la cuenta de Apple, que espera el D-U-N-S del cliente (DD-017, DD-069).
  - Hay que pedir permiso de avisos en Android 13 o superior.
  - Los momentos están abiertos (DD-011): se programa el mecanismo y la lista se llena después.
- **Condiciona:**
  - ADR-0012: un proyecto de Firebase por entorno, a nombre del cliente.
  - La spec de avisos.

## Criterio de reversión

Se reabre si la prueba temprana en iPhone (DD-069) no logra recibir un aviso FCM en un development build en 3
sesiones de trabajo.

## Costo estimado de revertir

Bajo. El envío está encapsulado en un canal de notificaciones de Laravel, y la recepción, en un módulo de la app.

## Trazabilidad

- **Obligaciones:** O-19.
- **Restricciones:** RN-06 y RN-30.
- **Pendientes:** DD-011 y DD-054.
- **ADRs relacionados:** ADR-0002, ADR-0003, ADR-0007 y ADR-0012.
