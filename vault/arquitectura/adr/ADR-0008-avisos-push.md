---
estado: vigente
grado: D
fuente: acta-2026-11-13-cierre-de-la-fase-6.md C-152; restricciones-no-negociables.md RN-06 y RN-30 (A); brecha-ejecutivo-tecnico.md BR-49 (B); deuda-de-decision.md DD-011 y DD-054 (B); verificaciones-fase-6.md, filas 7 y sección del 2026-11-12 (E); ADR-0002 y ADR-0003 (D); adenda del 2026-11-22: decisión 36, por delegación de C-236 (C), y openspec/changes/avisos/design.md (B)
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

## Adenda del 2026-11-21: el servidor llama a FCM sin `laravel-notification-channels/fcm`

**Qué cambia.** `servidor/` manda los avisos con la API HTTP v1 de FCM:
- el canal propio `App\Avisos\Fcm\CanalDeFcm`, con el cliente HTTP de Laravel;
- la cuenta de servicio, firmada con `firebase/php-jwt`, que ya estaba instalado (adenda de ADR-0005);
- sin dependencias nuevas.

La app no cambia: sigue con `@react-native-firebase/messaging`.

**Por qué.** `laravel-notification-channels/fcm` depende de `kreait/firebase-php`, y ninguna de sus versiones se
puede instalar aquí (`composer require --dry-run`, 2026-11-21):
- la 8.0 choca con `firebase/php-jwt` ^7.2, que usa el inicio con Google;
- de la 8.1 a la 8.3 piden guzzle 7, y el proyecto tiene la 8.5.2;
- de la 8.4 en adelante piden la extensión `sodium`, que está apagada en el PHP local.

**Qué queda igual.**
- Los avisos van en cola, con un trabajo por teléfono y reintentos.
- Un fallo de FCM nunca detiene el cambio de estado (avisos-E02 y E16).
- La credencial es la ruta a la cuenta de servicio en `FCM_CREDENCIALES`, fuera del repositorio (ADR-0012).

**Criterio de reversión.** Si se enciende `sodium` en el hosting y una versión del paquete convive con las
dependencias, se puede volver a él: el canal es la única pieza que cambia.

La aprobó Arthur Naranjo el 2026-11-21 (C-239), con la recomendación de Claude.

## Adenda del 2026-11-22: Firebase en la app solo cuando el entorno trae su archivo

**Contexto.**
- La app recibe los avisos con `@react-native-firebase/app` y `messaging` (avisos 4.1).
- Las cuentas de Firebase de prueba todavía no existen (DD-072). Sin su archivo, el complemento de Gradle de Google
  rompe la compilación.
- La app se prueba hoy con el APK local y con Expo Go en iPhone (C-240), que no trae el módulo nativo.

**Decisión.**
- **4, precisión.** Firebase se enlaza en la app solo cuando el entorno trae el archivo de Android
  (`GOOGLE_SERVICES_JSON`, ADR-0012):
  - **Sin el archivo:** el proyecto nativo sale como antes, sin los dos módulos, y la app no pide el permiso de avisos.
  - **Con el archivo:** entran el `googleServicesFile`, el complemento de Gradle de Google y `POST_NOTIFICATIONS`.
  - **En Expo Go:** el módulo se carga de forma diferida, como el inicio de sesión de Google, y no se pide.
- **Los complementos de configuración de las dos librerías no se listan.** El de `app` duplica en Android lo que Expo
  ya hace con `googleServicesFile`, y su parte de iPhone rompería esa compilación. El de `messaging` solo pone el ícono
  del aviso, que espera la marca.
- **iPhone** no enlaza Firebase todavía: la versión 27 pide paquetes de Swift y frameworks dinámicos. Entra con la
  tarea 5.2 de `avisos` (DD-069).

**La tomó** Claude, por delegación de Arthur (C-236), en la decisión 36 de `acta-2026-11-20-modelo-y-pasarela.md`. El
detalle está en `openspec/changes/avisos/design.md`.
