## Why

El usuario tiene que recibir avisos en su teléfono en los momentos del pedido. El contrato los enumera: "Confirmación, recolección, listo para entrega y entregado" (`NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3`, Módulo A, numeral 17), con "el servicio gratuito de Google" (`NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3`). Los acepta si "deben llegar correctamente tanto en iPhone como en Android, para los cinco momentos definidos del pedido" (`NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3`). Con este cambio se cierra el hito H6 del plan de sprints.

## What Changes

- La api manda los avisos por Firebase Cloud Messaging, sin intermediarios (ADR-0008), y un fallo del servicio no detiene el pedido.
- La app registra el token del teléfono al iniciar sesión y la api lo borra al cerrar sesión o cuando ya no sirve.
- La app pide el permiso de avisos y, si el usuario lo niega, sigue funcionando: el seguimiento se actualiza por consulta (ADR-0007).
- La api avisa al usuario en los cuatro momentos que nombra el contrato: confirmación, recolección, listo para entrega y entregado.
- La lista de momentos vive en un solo lugar, para sumar el quinto cuando se defina (DD-011).
- Cada cambio de estado manda un mensaje al teléfono del usuario que, con la app abierta en el seguimiento, adelanta la consulta (ADR-0007).
- Cada mensaje lleva lo que necesitan Android y iPhone; en iPhone, la prueba espera la cuenta de Apple (DD-069, DD-017, DD-084).
- Quedan escritos, y bloqueados, los avisos a la lavandería y al repartidor (DD-054).

**Qué NO cambia**

- Los estados del pedido y la consulta cada 30 segundos: cambio `estados-del-pedido`.
- El correo de confirmación del numeral 11 y el de recuperación de contraseña (`NSV-2026-001-v8.pdf:30:ANEXO B, Sección B.5`): cambios `resumen-y-confirmacion` y `registro-e-inicio-de-sesion`, con ADR-0014.
- Las notificaciones masivas del panel (`NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3`, Módulo D, numeral 7): cambio `administracion`, que usará este mismo canal.
- La sección de notificaciones del perfil del usuario (Módulo A, numeral 15): cambio `perfil-y-soporte`.
- El inicio y el cierre de sesión: cambio `registro-e-inicio-de-sesion`. Aquí solo se engancha el token del teléfono.
- El servicio de avisos: Firebase Cloud Messaging, no el de Expo (ADR-0008).

**Fuera del contrato**

- Un aviso aparte de los cinco momentos, como el "cuando ya va en camino" que marcó el cliente en su guía del 2026-11-05 (sin confirmar). Entra solo si resulta ser el quinto momento (DD-011); como sexto aviso, es posible Orden de Cambio (`NSV-2026-001-v8.pdf:8:Cláusula IV`).
- Abrir el pedido exacto al tocar el aviso, que muestra el mock A17. El contrato no lo nombra: posible Orden de Cambio, sin fila.
- Mandar un correo cuando el usuario niega los avisos, que sugiere BR-24. El contrato no lo pide y ADR-0007 ya cubre al usuario sin avisos con la consulta.

## Capabilities

### New Capabilities

- `avisos`: el envío por Firebase Cloud Messaging, el registro del teléfono, el permiso, los momentos de aviso del pedido, el mensaje que adelanta la consulta y la llegada en Android y en iPhone.

### Modified Capabilities

Ninguna.

## Impact

- **`servidor/`:** el componente `avisos` de la api, con el canal de FCM (`laravel-notification-channels/fcm` 6.2.0 y `kreait/firebase-php` 8.6.0, versiones grado E de `arquitectura/verificaciones-fase-6.md`); un punto de la api para registrar y borrar el token del teléfono; escuchas de los eventos del pedido.
- **`movil/`:** `@react-native-firebase/messaging` 27.1.1 (grado E) en un development build (ADR-0002), la petición del permiso y el manejador de mensajes con la app abierta y en segundo plano.
- **Base de datos:** crea `dispositivos` (ADR-0016, `arquitectura/modelo-de-datos.md`): el token de FCM de cada teléfono, único, ligado a la cuenta por `id_usuarios`, con su plataforma y su último uso.
- **Cuentas y secretos:** el proyecto de Firebase de prueba que abrió Arthur con su correo (C-170, ADR-0012); la credencial del servidor y los archivos de Firebase de la app viven fuera del repositorio (ADR-0012). En iPhone hace falta la clave de la cuenta de desarrollador de Apple (DD-069).
- **ADRs:** ADR-0008 (FCM directo), ADR-0007 (el aviso adelanta la consulta), ADR-0012 (un proyecto de Firebase por entorno), ADR-0002 y ADR-0003 (stack) y ADR-0016 (modelo de datos).
- **Depende de:** `modelo-de-datos` (las reglas de la tabla), `registro-e-inicio-de-sesion` (la sesión), `resumen-y-confirmacion` (el pedido confirmado) y `estados-del-pedido` (los cambios de estado). Para avisos-E20, además, `repartidor`, que va después en el plan.
