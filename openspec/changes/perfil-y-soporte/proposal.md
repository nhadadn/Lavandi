## Why

El Módulo A pide un "Perfil de usuario" con "Nombre, foto, direcciones guardadas, métodos de pago y notificaciones" y un "Soporte" con "Chat de WhatsApp Business y formulario de reporte" (`NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3`, Módulo A, numerales 15 y 16). Con ellos se cierra el Módulo A para A43-6 (`NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3`). Borrar la cuenta desde la app no lo nombra el contrato: lo piden las dos tiendas donde el contrato manda publicar (`NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3`) y lo fija ADR-0005 (grado D).

## What Changes

- El usuario cambia su nombre y su foto de perfil (F-A15). La foto se comprime en el teléfono y se guarda privada (ADR-0006).
- El perfil muestra sus direcciones guardadas y lleva a editarlas (F-A15, con el cambio `direcciones`).
- El perfil tiene la sección de métodos de pago sin pedir, guardar ni devolver jamás un número de tarjeta (F-A15, SEG-4). Guardar la tarjeta como referencia de la pasarela: Nadir dijo que sí (C-198, DD-055); queda bloqueado hasta saber si el checkout de la pasarela permite reutilizarla (ADR-0010, punto 5; DD-079), igual que pago-E06.
- El perfil muestra si los avisos están activos en el teléfono y ofrece activarlos (F-A15, ADR-0008).
- Soporte abre el chat de WhatsApp Business de EL CLIENTE con el número tomado de la configuración (F-A16). El número real es DD-082: no bloquea construir, bloquea acreditar en producción.
- Soporte tiene un formulario de reporte que se guarda ligado al usuario y, si él quiere, a uno de sus pedidos (F-A16). A quién le llega queda bloqueado (DD-108).
- El usuario borra su cuenta desde el perfil, con confirmación (ADR-0005). Qué datos se borran y cuáles se conservan queda bloqueado (DD-109).

**Qué NO cambia**

- El registro, el inicio y el cierre de sesión y la recuperación de la contraseña: cambio `registro-e-inicio-de-sesion`.
- El alta, la edición y el borrado de direcciones: cambio `direcciones` (F-A08).
- El cobro con la pasarela: cambio `pago`.
- El aviso de privacidad y los términos dentro de la app: cambio `textos-legales` (F-T18).
- Qué avisos se mandan y cuándo: cambio `avisos` (DD-011).
- El correo del usuario no se cambia desde el perfil: el numeral 15 no lo lista.

**Fuera del contrato**

- Clasificar el reporte por tema ("Un pedido", "Un pago", "La app"), que muestra el mock A16: el numeral 16 solo pide "formulario de reporte". Posible Orden de Cambio si se quiere.
- Interruptores por tipo de aviso en el perfil (avisos del pedido y promociones), que muestra el mock A15: el numeral 15 solo dice "notificaciones". Posible Orden de Cambio.
- El "dictamen de responsiva" y el "seguro" por prendas dañadas que el cliente mencionó (nota del mock A16): son operación de EL CLIENTE (DD-047). La app solo recibe el reporte.

## Capabilities

### New Capabilities

- `perfil-usuario`: perfil del usuario con nombre, foto, direcciones guardadas, métodos de pago y notificaciones; soporte por WhatsApp Business y formulario de reporte; y borrado de la cuenta desde la app (F-A15, F-A16 y ADR-0005).

### Modified Capabilities

Ninguna.

## Impact

- **`servidor/`:** la API del perfil (nombre y foto), el dato de configuración con el número de soporte, el guardado de los reportes y el borrado de la cuenta con la revocación de sus tokens de Sanctum.
- **`movil/`:** las pantallas "Mi perfil" y "Ayuda" del Módulo A, con la cámara y la galería para la foto.
- **Base de datos:** crea `reportes_soporte`. Usa `usuarios.foto_ruta` y el borrado suave `usuarios.deleted_at`, que crea `registro-e-inicio-de-sesion`. `metodos_pago_guardados` solo nace si DD-055 dice que sí. El detalle está en `design.md`, sección "Tablas y migración" (`vault/arquitectura/modelo-de-datos.md`, ADR-0016).
- **Almacenamiento:** la foto de perfil en el bucket privado del entorno (ADR-0006).
- **Dependencias:** una librería de Expo para cámara, galería y compresión de imagen, que se confirma al implementar. La decisión de comprimir ya está en ADR-0006.
- **ADRs:** ADR-0002, ADR-0003, ADR-0005, ADR-0006, ADR-0008, ADR-0010 (punto 5, tarjeta guardada) y ADR-0016 (modelo de datos).
- **Horas fuera del inventario:** borrar la cuenta no tiene horas en el plan; el inventario lo anota sin tamaño (`vault/contrato/inventario-funcional.md`, sección 2, BR-09).
