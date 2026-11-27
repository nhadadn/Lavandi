## ADDED Requirements

### Requirement: Consulta de usuarios y de su historial

El panel de administración DEBE (MUST) permitir a la administración consultar a los usuarios registrados, filtrar los usuarios activos de un periodo y abrir el historial de pedidos de cada uno. Usuarios activos son las personas distintas que usaron la aplicación en el periodo, como los define el glosario del contrato.

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #3); A → `NSV-2026-001-v8.pdf:46:ANEXO D, Sección D.5`

#### Scenario: administracion-E01 — Ver el historial de un usuario

- **DADO** un usuario con tres pedidos de fechas distintas y otro usuario con un pedido
- **CUANDO** la administración abre la ficha del primero en la gestión de usuarios
- **ENTONCES** ve sus tres pedidos, cada uno con su folio, fecha, lavandería, estado y monto
- **Y** no ve el pedido del otro usuario

Criterio: A43-6

#### Scenario: administracion-E02 — Filtrar los usuarios activos de un periodo

- **DADO** un usuario que usó la aplicación ayer y otro que no la usa desde hace dos meses
- **CUANDO** la administración filtra los usuarios activos del último mes
- **ENTONCES** la lista trae al primero y no al segundo

Criterio: A43-7

SIN RESPUESTA — bloquea: administracion-E02 (BR-33; DD-111)

### Requirement: Bloqueo de usuarios

El panel DEBE (MUST) permitir a la administración bloquear a un usuario y levantar el bloqueo. Un usuario bloqueado no puede iniciar sesión por ninguna vía ni seguir usando la sesión que tenía abierta.

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #3)

#### Scenario: administracion-E03 — Bloquear a un usuario

- **DADO** un usuario con la sesión abierta en la app
- **CUANDO** la administración lo bloquea
- **ENTONCES** la siguiente petición de la app con esa sesión se rechaza
- **Y** el usuario no puede iniciar sesión con correo y contraseña, con Google ni con Apple

Criterio: A43-6

#### Scenario: administracion-E04 — Levantar el bloqueo

- **DADO** un usuario bloqueado
- **CUANDO** la administración levanta el bloqueo
- **ENTONCES** el usuario puede iniciar sesión otra vez

#### Scenario: administracion-E05 — Bloquear a un usuario con un pedido activo

- **DADO** un usuario con un pedido en estado Recolectado
- **CUANDO** la administración lo bloquea
- **ENTONCES** el pedido y su cobro siguen la regla que fije EL CLIENTE para ese caso

SIN RESPUESTA — bloquea: administracion-E05 (DD-016)

### Requirement: Monitoreo de los pedidos activos

El panel DEBE (MUST) mostrar en una sola vista todos los pedidos activos de todas las plazas, con su folio, su estado y su plaza, y refrescarla sola cada 30 segundos mientras está abierta. Un pedido activo es el que ya se confirmó y todavía no se entrega ni se cancela.

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #4); D → ADR-0007

#### Scenario: administracion-E06 — Ver todos los pedidos activos

- **DADO** un pedido Confirmado en una plaza, uno En proceso en otra plaza y uno Entregado
- **CUANDO** la administración abre el monitoreo de pedidos
- **ENTONCES** ve los dos primeros con su folio, su estado y su plaza
- **Y** no ve el entregado

Criterio: A43-6, A43-7

#### Scenario: administracion-E07 — El monitoreo se refresca solo

- **DADO** el monitoreo abierto con un pedido Recolectado
- **CUANDO** la lavandería lo pasa a En proceso
- **ENTONCES** el monitoreo muestra En proceso en 30 segundos o menos, sin recargar la página

Criterio: A43-6

#### Scenario: administracion-E08 — Un pedido cancelado sale del monitoreo

- **DADO** un pedido activo en el monitoreo
- **CUANDO** el pedido se cancela
- **ENTONCES** deja de aparecer entre los activos

SIN RESPUESTA — bloquea: administracion-E08 (DD-016; BR-22: el estado de cancelado todavía no existe)

### Requirement: Mapa de los pedidos activos

El monitoreo DEBE (MUST) mostrar los pedidos activos en un mapa web, con un marcador en la dirección de recolección de cada pedido y otro en la de entrega cuando es distinta. El mapa no muestra la posición del repartidor.

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #4); D → ADR-0009

#### Scenario: administracion-E09 — Cada pedido activo en el mapa

- **DADO** dos pedidos activos con su dirección de recolección guardada con coordenadas
- **CUANDO** la administración abre el mapa del monitoreo
- **ENTONCES** el mapa trae un marcador por pedido en su dirección de recolección
- **Y** al elegir un marcador se ven el folio y el estado del pedido

Criterio: A43-6

#### Scenario: administracion-E10 — Entrega en otra dirección

- **DADO** un pedido activo cuya dirección de entrega es distinta de la de recolección
- **CUANDO** la administración abre el mapa del monitoreo
- **ENTONCES** el pedido tiene dos marcadores, uno marcado como recolección y otro como entrega

### Requirement: Porcentaje de comisión global y por socio

El panel DEBE (MUST) permitir a la administración fijar un porcentaje de comisión global, uno solo para todas las plazas, y un porcentaje propio para cada socio. Al socio con porcentaje propio le aplica el suyo; a los demás, de cualquier plaza, el global. El sistema no trae ningún porcentaje de fábrica: los valores los captura EL CLIENTE.

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #5)

#### Scenario: administracion-E11 — Fijar el porcentaje global

- **DADO** que no hay porcentaje global guardado
- **CUANDO** la administración guarda un porcentaje global
- **ENTONCES** ese porcentaje aplica a toda lavandería sin porcentaje propio, de cualquier plaza

Criterio: A43-6

#### Scenario: administracion-E12 — El porcentaje propio prevalece

- **DADO** un porcentaje global guardado
- **CUANDO** la administración guarda un porcentaje propio para una lavandería
- **ENTONCES** a esa lavandería le aplica el suyo y a las demás el global
- **Y** si la administración quita el porcentaje propio, a esa lavandería le vuelve a aplicar el global

#### Scenario: administracion-E13 — Porcentaje fuera de rango

- **DADO** un porcentaje vigente
- **CUANDO** la administración intenta guardar uno menor que 0 o mayor que 100
- **ENTONCES** el panel lo rechaza con un mensaje y el porcentaje vigente no cambia

#### Scenario: administracion-E14 — Comisión de un socio repartidor

- **DADO** un socio repartidor
- **CUANDO** la administración quiere fijarle un porcentaje de comisión
- **ENTONCES** aplica lo que decida EL CLIENTE sobre lo que la plataforma retiene al repartidor

SIN RESPUESTA — bloquea: administracion-E14 (DD-019)

### Requirement: Comisión de cada pedido

El servidor DEBE (MUST) calcular la comisión de cada pedido aplicando el porcentaje que le corresponde a su socio sobre la base que fije EL CLIENTE. Los reportes financieros y los indicadores usan esa misma comisión.

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #5 y #6); A → `NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5`

#### Scenario: administracion-E15 — Calcular la comisión de un pedido

- **DADO** una lavandería con porcentaje vigente y un pedido suyo con pago confirmado
- **CUANDO** se calcula la comisión del pedido
- **ENTONCES** la comisión es ese porcentaje aplicado a la base del pedido

SIN RESPUESTA — bloquea: administracion-E15 (DD-020: la base, subtotal del lavado o total con envío; DD-013)

#### Scenario: administracion-E16 — Un cambio de porcentaje y los pedidos anteriores

- **DADO** pedidos con pago confirmado bajo un porcentaje
- **CUANDO** la administración cambia ese porcentaje
- **ENTONCES** la comisión de los pedidos anteriores se conserva o se recalcula según decida EL CLIENTE

SIN RESPUESTA — bloquea: administracion-E16 (DD-020, por ampliar: desde cuándo aplica un cambio de porcentaje)

### Requirement: Ingresos y comisiones por periodo

El panel DEBE (MUST) mostrar, para el periodo que elija la administración, los ingresos, que son lo cobrado a los usuarios con pago confirmado por la pasarela, y las comisiones, por socio y en total. La fecha de un ingreso es la de la confirmación del pago, en la hora de su plaza.

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #6); D → ADR-0010

#### Scenario: administracion-E17 — Ingresos de un periodo

- **DADO** pagos confirmados por la pasarela en marzo y en abril, y una orden de marzo sin confirmar
- **CUANDO** la administración pide los ingresos de marzo
- **ENTONCES** ve la suma de los pagos confirmados en marzo
- **Y** no cuenta los de abril ni la orden sin confirmar

Criterio: A43-6

#### Scenario: administracion-E18 — Reembolsos y ajustes en los ingresos

- **DADO** un pago confirmado que después tuvo un reembolso o un ajuste al pesar
- **CUANDO** la administración pide los ingresos del periodo
- **ENTONCES** el reembolso o el ajuste se refleja según el modelo de cobro y la política de cancelación

SIN RESPUESTA — bloquea: administracion-E18 (DD-012, DD-016)

#### Scenario: administracion-E19 — Comisiones del periodo, por socio y en total

- **DADO** pedidos con pago confirmado de dos lavanderías en el periodo
- **CUANDO** la administración pide las comisiones del periodo
- **ENTONCES** ve la comisión de cada lavandería y la suma de las dos

SIN RESPUESTA — bloquea: administracion-E19 (DD-020; depende de administracion-E15)

### Requirement: Conciliación con la pasarela

El panel DEBE (MUST) conciliar, para el periodo que elija la administración, los pagos que registró la plataforma contra los movimientos de la pasarela, y señalar cada diferencia: un movimiento de la pasarela sin pedido, un pedido pagado sin movimiento en la pasarela o un monto que no coincide.

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #6: "Ingresos por periodo, comisiones y conciliación con Conekta"); D → ADR-0010

La pasarela es Conekta mientras el cliente no firme el cambio (DD-079, C-192, C-216); si lo firma, se concilia con la
elegida.

#### Scenario: administracion-E20 — Periodo sin diferencias

- **DADO** tres pedidos con pago confirmado en el periodo y los mismos tres movimientos en la pasarela (simulada)
- **CUANDO** la administración concilia el periodo
- **ENTONCES** el reporte marca los tres como conciliados

Cita: D → `ADR-0010`, segunda adenda del 2026-11-22: los movimientos salen de la API de la pasarela, por la misma interfaz neutral del cobro, cuando la administración concilia un periodo, y se guardan en `movimientos_pasarela`, una fila por orden, sin plaza propia (decisión 34 de `acta-2026-11-20-modelo-y-pasarela.md`; cierra DD-112)

Criterio: A43-6

#### Scenario: administracion-E21 — Periodo con diferencias

- **DADO** un movimiento de la pasarela sin pedido, un pedido pagado sin movimiento y un pedido cuyo monto no coincide con su movimiento (simulado)
- **CUANDO** la administración concilia el periodo
- **ENTONCES** el reporte señala cada uno con su tipo de diferencia

Cita: D → `ADR-0010`, segunda adenda del 2026-11-22: un movimiento sin pedido es justo una diferencia, y la plaza de un movimiento es la del pedido con el que cuadra (decisión 34 de `acta-2026-11-20-modelo-y-pasarela.md`; cierra DD-112)

#### Scenario: administracion-E22 — La comisión que retiene la pasarela

- **DADO** movimientos de la pasarela con su comisión por transacción
- **CUANDO** la administración concilia el periodo
- **ENTONCES** la comisión de la pasarela se muestra y se descuenta según quién la absorba

SIN RESPUESTA — bloquea: administracion-E22 (DD-013: quién absorbe la comisión de la pasarela)

### Requirement: Avisos masivos

El panel DEBE (MUST) permitir a la administración escribir un aviso con título y texto y mandarlo al celular de todas las cuentas de la app o de un segmento, para promociones o para avisar de un mantenimiento. Los avisos salen por el servicio gratuito de Google (FCM).

Cita: A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #7 y "Avisos al celular"); D → ADR-0008

#### Scenario: administracion-E23 — Aviso global

- **DADO** tres cuentas con un dispositivo registrado para avisos y una cuenta sin dispositivo
- **CUANDO** la administración manda un aviso global con título y texto
- **ENTONCES** el servidor manda el aviso por FCM a los tres dispositivos (FCM simulado)
- **Y** la cuenta sin dispositivo no genera ningún envío

Criterio: A43-6

#### Scenario: administracion-E24 — Aviso a un segmento

- **DADO** cuentas de distintos segmentos con dispositivo registrado
- **CUANDO** la administración manda un aviso a un segmento
- **ENTONCES** solo lo reciben las cuentas de ese segmento

SIN RESPUESTA — bloquea: administracion-E24 (BR-32; DD-113)

### Requirement: La administración en la app

Una cuenta de administración que entra a la app DEBE (MUST) poder abrir desde ella el panel web, sin recibir token en el teléfono.

Cita: A → `NSV-2026-001-v8.pdf:3:Cláusula I` ("Aplicación única para iPhone y Android, con módulo de usuario, módulo de socio lavandería, módulo de socio repartidor y panel de administración"); A → `NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3` (los cuatro módulos accesibles); A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D); C → C-140 y decisión 56 de `acta-2026-11-20-modelo-y-pasarela.md` (C-245); D → adenda del 2026-11-22 de ADR-0010 (`expo-web-browser`, decisión 32)

#### Scenario: administracion-E25 — Una cuenta de administración en la app abre el panel

- **DADO** una cuenta de administración y la dirección del panel en la configuración de la app
- **CUANDO** inicia sesión en la app
- **ENTONCES** el servidor no le da token (registro-E33) y la app no guarda ninguna sesión
- **Y** la app muestra el aviso de que la administración entra por el panel, con un botón que abre el panel en el navegador del sistema dentro de la app
- **Y** sin la dirección del panel en la configuración, muestra el aviso sin el botón

Criterio: A43-6

### Requirement: Cuenta de administración

El servidor DEBE (MUST) crear la cuenta de administración del dueño del negocio con un comando, en cualquier entorno,
también en producción. No lleva pantalla, porque el Módulo D #3 da consulta de usuarios, historial y bloqueo, no alta.
El comando pide la contraseña dos veces, con la entrada oculta; nunca la recibe como argumento ni la escribe en la
salida. Aplica la regla de la contraseña de ADR-0018 (8 caracteres como mínimo) y la guarda con hash. Crea una cuenta
nueva con el rol de administración y ninguno más. Rechaza, sin cambiar nada, un correo no válido, dos contraseñas que no
coinciden o una corta, y un correo que ya tiene cuenta, de cualquier rol, aunque esté borrada: no le agrega el rol a una
cuenta que ya existe, para no decidir "una cuenta, dos roles" (DD-053).

Cita: A → `NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3` ("Los cuatro módulos (usuario, socio lavandería, socio repartidor y administración) accesibles y funcionales" y "Panel de administración muestra datos reales de pedidos y usuarios activos"); A → `NSV-2026-001-v8.pdf:44:ANEXO D, Sección D.1` ("Pantalla de control reservada al dueño del negocio"); A → `NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3` (protección de contraseñas); A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #3); D → ADR-0005 y ADR-0018; C → decisión 68 de `acta-2026-11-20-modelo-y-pasarela.md` (C-245)

No se corre en el entorno de prueba ni en producción mientras no estén el alojamiento (DD-070) y el correo que dé EL
CLIENTE. No manda correo, invitación ni enlace.

#### Scenario: administracion-E26 — La cuenta de administración se crea con un comando

- **DADO** un entorno de producción sin cuentas de administración
- **CUANDO** se corre `php artisan administracion:crear-cuenta {correo}` con un correo y una contraseña válida, escrita dos veces
- **ENTONCES** existe la cuenta con ese correo, con solo el rol de administración y la contraseña guardada con hash, que no aparece en la salida
- **Y** esa cuenta entra al panel `/admin` (autorizacion-E03)
- **Y** si inicia sesión en la app con su correo y su contraseña, recibe 403 sin token (registro-E33)
- **Y** con un correo que ya tiene cuenta, de cualquier rol o borrada, el comando falla sin tocar esa cuenta
- **Y** con un correo no válido, o una contraseña corta o que no coincide al repetirla, el comando falla sin crear ninguna cuenta

Criterio: A43-6, A43-7
