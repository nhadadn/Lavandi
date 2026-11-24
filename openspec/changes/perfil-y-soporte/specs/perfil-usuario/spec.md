## ADDED Requirements

### Requirement: Nombre del usuario en el perfil
La app DEBE (MUST) mostrar en el perfil el nombre del usuario y dejarlo cambiar. La API DEBE (MUST) rechazar un nombre vacío.

Cita: A → `NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3` (Módulo A #15, "Nombre")

#### Scenario: perfil-usuario-E01 — El usuario cambia su nombre
- **DADO** un usuario con sesión iniciada
- **CUANDO** cambia su nombre en el perfil y guarda
- **ENTONCES** el perfil muestra el nombre nuevo
- **Y** la API devuelve el nombre nuevo en la siguiente consulta

Criterio: A43-6

#### Scenario: perfil-usuario-E02 — Un nombre vacío se rechaza
- **DADO** un usuario con sesión iniciada
- **CUANDO** guarda su perfil con el nombre vacío o solo con espacios
- **ENTONCES** la API lo rechaza
- **Y** el nombre anterior se conserva

### Requirement: Foto de perfil del usuario
La app DEBE (MUST) dejar al usuario poner su foto de perfil desde la cámara o la galería. La foto DEBE (MUST) comprimirse en el teléfono, guardarse en almacenamiento privado y servirse con una URL firmada que vence.

Cita: A → `NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3` (Módulo A #15, "foto") y `NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3` (Almacenamiento de imágenes, "las fotos de perfil"); D → `ADR-0006`

#### Scenario: perfil-usuario-E03 — El usuario pone su foto
- **DADO** un usuario sin foto de perfil
- **CUANDO** toma una foto o elige una de su galería y la sube
- **ENTONCES** la foto queda guardada en el almacenamiento privado, ligada a su cuenta
- **Y** la API la entrega como URL firmada con vencimiento, nunca como URL pública

#### Scenario: perfil-usuario-E04 — Un archivo inválido se rechaza
- **DADO** un usuario con una foto de perfil
- **CUANDO** sube un archivo que no es imagen, o que pasa el tamaño máximo configurado
- **ENTONCES** la API lo rechaza
- **Y** la foto anterior se conserva

### Requirement: Direcciones guardadas en el perfil
El perfil DEBE (MUST) mostrar las direcciones guardadas del usuario, solo las suyas, y llevar a su edición.

Cita: A → `NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3` (Módulo A #15, "direcciones guardadas") y `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3` (Módulo A #8)

#### Scenario: perfil-usuario-E05 — El perfil muestra sus direcciones guardadas
- **DADO** un usuario con dos direcciones guardadas y otro usuario con una
- **CUANDO** el primero abre su perfil
- **ENTONCES** ve sus dos direcciones y no la del otro usuario

Criterio: SEG-3

### Requirement: Métodos de pago sin números de tarjeta
El perfil DEBE (MUST) tener la sección de métodos de pago. La plataforma DEBE (MUST) no pedir, no guardar y no devolver jamás un número de tarjeta.

Cita: A → `NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3` (Módulo A #15, "métodos de pago") y `NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3` (Datos de tarjeta)

#### Scenario: perfil-usuario-E06 — El perfil no guarda ni devuelve un número de tarjeta
- **DADO** un usuario con sesión iniciada
- **CUANDO** una petición a la API del perfil trae un campo con un número de tarjeta
- **ENTONCES** ninguna tabla guarda ese número
- **Y** la respuesta del perfil no incluye números de tarjeta

Criterio: SEG-4

#### Scenario: perfil-usuario-E07 — Tarjeta guardada como referencia de la pasarela
- **DADO** un usuario que ya pagó con tarjeta en la pasarela
- **CUANDO** abre los métodos de pago de su perfil
- **ENTONCES** ve, guarda o elimina la referencia de su tarjeta según fije la respuesta pendiente

SIN RESPUESTA — bloquea: perfil-usuario-E07 (DD-055: Nadir dijo que sí, se guarda solo la referencia, C-198; falta si el checkout de Conekta, o el de la
elegida si se firma el cambio (DD-079), permite reutilizarla; ADR-0010, punto 5: si no lo permite, la v1 cobra cada vez)

### Requirement: Notificaciones en el perfil
El perfil DEBE (MUST) mostrar si los avisos de la app están activos en el teléfono y, si no lo están, ofrecer activarlos.

Cita: A → `NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3` (Módulo A #15, "notificaciones"); D → `ADR-0008` (permiso de avisos en Android 13 o superior)

#### Scenario: perfil-usuario-E08 — Avisos apagados en el teléfono
- **DADO** un teléfono donde la app no tiene permiso de avisos
- **CUANDO** el usuario abre su perfil
- **ENTONCES** la sección de notificaciones dice que los avisos están apagados
- **Y** ofrece pedir el permiso o abrir los ajustes del sistema

### Requirement: Soporte por WhatsApp Business
La app DEBE (MUST) abrir desde Soporte el chat de WhatsApp Business de EL CLIENTE, con el número tomado de la configuración y nunca escrito en el código.

Cita: A → `NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3` (Módulo A #16, "Chat de WhatsApp Business")

#### Scenario: perfil-usuario-E09 — El usuario abre el chat de soporte
- **DADO** un número de soporte en la configuración
- **CUANDO** el usuario toca la opción de WhatsApp en Soporte
- **ENTONCES** el teléfono abre WhatsApp con un chat a ese número

Criterio: A43-6

Nota: el número real de EL CLIENTE es DD-082, abierta. No bloquea este escenario, que se prueba con un número de configuración; bloquea acreditarlo en producción.

#### Scenario: perfil-usuario-E10 — Sin número configurado
- **DADO** una configuración sin número de soporte
- **CUANDO** el usuario abre Soporte
- **ENTONCES** la opción de WhatsApp aparece como no disponible
- **Y** la app no abre un enlace vacío

### Requirement: Formulario de reporte
La app DEBE (MUST) ofrecer en Soporte un formulario de reporte con una descripción y, si el usuario quiere, uno de sus pedidos. La API DEBE (MUST) guardarlo ligado al usuario y rechazar un pedido que no sea suyo.

Cita: A → `NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3` (Módulo A #16, "formulario de reporte") y `NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3` (Separación por tipo de usuario)

#### Scenario: perfil-usuario-E11 — El usuario envía un reporte
- **DADO** un usuario con un pedido
- **CUANDO** envía un reporte con una descripción y ese pedido
- **ENTONCES** el reporte queda guardado con el usuario, el pedido, la descripción y la fecha
- **Y** la app confirma que se recibió, sin prometer un tiempo de respuesta

Criterio: A43-6

#### Scenario: perfil-usuario-E12 — Un reporte sin descripción se rechaza
- **DADO** un usuario con sesión iniciada
- **CUANDO** envía un reporte con la descripción vacía
- **ENTONCES** la API lo rechaza
- **Y** no se guarda ningún reporte

#### Scenario: perfil-usuario-E13 — No se reporta el pedido de otro usuario
- **DADO** un pedido que pertenece a otro usuario
- **CUANDO** el usuario envía un reporte ligado a ese pedido
- **ENTONCES** la API lo rechaza
- **Y** no se guarda ningún reporte

Criterio: SEG-3

#### Scenario: perfil-usuario-E17 — El reporte de una cuenta sin plaza
- **DADO** un usuario sin pedidos ni direcciones guardadas con plaza
- **CUANDO** envía un reporte con una descripción
- **ENTONCES** el reporte queda guardado con el usuario, la descripción y la fecha, sin plaza
- **Y** la app confirma que se recibió, como con cualquier otro reporte
- **Y** de las cuentas, solo la administración, que ve todas las plazas, lo recibe del servidor; ninguna otra, tampoco
  la de un socio de una plaza

El Módulo A #16 pide el "formulario de reporte" sin condiciones (`NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3`): un
reporte no se rechaza por no tener de dónde tomar su plaza. A quién le llega es perfil-usuario-E14 (DD-108).

#### Scenario: perfil-usuario-E14 — A quién le llega el reporte
- **DADO** un reporte guardado
- **CUANDO** llega
- **ENTONCES** lo recibe quien fije la respuesta pendiente, la administración o un correo de EL CLIENTE
- **Y** se le responde a la persona por el medio que fije esa misma respuesta

SIN RESPUESTA — bloquea: perfil-usuario-E14 (BR-25: a quién le llega el formulario de reporte y cómo se responde; DD-108)

### Requirement: Borrar la cuenta desde la app
La app DEBE (MUST) dejar al usuario borrar su cuenta desde el perfil, con una confirmación previa. Después, la cuenta DEBE (MUST) no poder iniciar sesión, y sus sesiones abiertas DEBEN (MUST) cerrarse.

Cita: D → `ADR-0005` ("Borrar la cuenta se puede desde la app, como piden las dos tiendas")

#### Scenario: perfil-usuario-E15 — El usuario borra su cuenta
- **DADO** un usuario con sesión iniciada en dos teléfonos
- **CUANDO** pide borrar su cuenta y lo confirma
- **ENTONCES** la cuenta queda borrada y ya no puede iniciar sesión, ni con correo y contraseña ni con Google o Apple
- **Y** sus tokens quedan revocados en los dos teléfonos

#### Scenario: perfil-usuario-E16 — Qué pasa con los datos de la cuenta borrada
- **DADO** un usuario con pedidos entregados y uno en curso
- **CUANDO** borra su cuenta
- **ENTONCES** sus datos se borran o se conservan, y su pedido en curso se resuelve, según fije la respuesta pendiente

SIN RESPUESTA — bloquea: perfil-usuario-E16 (DD-109; BR-09: qué se borra, qué se conserva para pedidos, pagos e indicadores, y qué pasa con un pedido en curso; EL CLIENTE es el responsable del tratamiento, `NSV-2026-001-v8.pdf:10:Cláusula X`; BR-09 no tiene fila en la deuda de decisión todavía)
