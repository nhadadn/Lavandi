## ADDED Requirements

### Requirement: Registro con correo y contraseña

La persona DEBE (MUST) poder crear su cuenta con su correo y una contraseña. Toda cuenta creada así DEBE (MUST) quedar
con el rol de usuario, sin importar lo que traiga la petición. Un correo que ya tiene cuenta y los datos inválidos se
rechazan.

Cita: A → `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3` (Módulo A #2)

#### Scenario: registro-E01 — Cuenta nueva con el rol de usuario

- **DADO** que no existe ninguna cuenta con el correo `persona@ejemplo.com`
- **CUANDO** la app envía al servidor ese correo y una contraseña que cumple la regla configurada
- **ENTONCES** se crea la cuenta con el rol de usuario
- **Y** la respuesta trae un token de sesión
- **Y** si la petición trae otro rol, la cuenta queda igual con el rol de usuario

Criterio: A43-6 (en parte: F-A02)

#### Scenario: registro-E02 — El correo que ya tiene cuenta se rechaza

- **DADO** una cuenta con el correo `persona@ejemplo.com`
- **CUANDO** alguien intenta registrarse con ese mismo correo
- **ENTONCES** el servidor rechaza el registro con un error en el campo del correo
- **Y** no se crea otra cuenta

#### Scenario: registro-E03 — Los datos inválidos se rechazan

- **DADO** la ruta de registro del servidor
- **CUANDO** llega un correo mal formado, una contraseña vacía o una contraseña que no cumple la regla configurada
- **ENTONCES** el servidor rechaza el registro con el error de cada campo
- **Y** no se crea la cuenta

#### Scenario: registro-E04 — La pantalla de registro crea la cuenta y muestra los errores

- **DADO** la pantalla de registro de la app
- **CUANDO** la persona escribe su correo y su contraseña y el servidor crea la cuenta
- **ENTONCES** la app guarda la sesión y entra
- **Y** si el servidor rechaza el registro, la app muestra cada error junto a su campo y no entra

### Requirement: Contraseña que nadie puede leer

La contraseña DEBE (MUST) guardarse con el hash de Laravel, nunca en texto legible, y ninguna respuesta del servidor
DEBE (MUST) incluir la contraseña ni su hash.

Cita: A → `NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3` (Protección de contraseñas); D → ADR-0005

#### Scenario: registro-E05 — La contraseña se guarda con hash

- **DADO** una cuenta creada con la contraseña P
- **CUANDO** se lee su fila en la base de datos
- **ENTONCES** el campo de la contraseña no contiene P
- **Y** es un hash con el que P se valida

Criterio: SEG-2

#### Scenario: registro-E06 — Ninguna respuesta muestra la contraseña

- **DADO** una cuenta con contraseña
- **CUANDO** el servidor responde al registro, al inicio de sesión o a la consulta de la propia cuenta
- **ENTONCES** la respuesta no incluye la contraseña ni su hash

Criterio: SEG-2

### Requirement: Inicio de sesión con correo y contraseña

La persona DEBE (MUST) poder iniciar sesión con su correo y su contraseña. El servidor DEBE (MUST) verificar las dos
antes de emitir un token de sesión de Sanctum, y no emitirlo si no coinciden.

Cita: A → `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3` (Módulo A #2) y `NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3`
(Control de acceso); D → ADR-0005

#### Scenario: registro-E07 — Entra con su correo y su contraseña

- **DADO** una cuenta con el correo X y la contraseña P
- **CUANDO** la app envía X y P al servidor
- **ENTONCES** el servidor responde con un token de sesión nuevo

#### Scenario: registro-E08 — Las credenciales incorrectas no abren sesión

- **DADO** una cuenta con el correo X
- **CUANDO** llega X con una contraseña distinta, o llega un correo que no tiene cuenta
- **ENTONCES** el servidor no emite token
- **Y** responde que las credenciales no son válidas

#### Scenario: registro-E09 — La pantalla de inicio de sesión

- **DADO** la pantalla de inicio de sesión de la app
- **CUANDO** la persona escribe su correo y su contraseña y el servidor las acepta
- **ENTONCES** la app guarda la sesión y entra
- **Y** si el servidor las rechaza, la app muestra el error y no entra

### Requirement: Sesión que se conserva sin volver a escribir la contraseña

El servidor DEBE (MUST) reconocer la sesión por su token de Sanctum hasta que la persona la cierre. La app DEBE (MUST)
guardar la sesión de forma segura y conservarla al volver a abrirse, sin pedir otra vez el correo ni la contraseña.

Cita: A → `NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3` (Control de acceso); D → ADR-0005 y ADR-0018 (dónde se guarda)

#### Scenario: registro-E10 — El token abre la consulta de la propia cuenta

- **DADO** una cuenta con la sesión iniciada
- **CUANDO** la app consulta los datos de la propia cuenta con su token
- **ENTONCES** recibe su correo y su rol
- **Y** sin token, o con un token revocado, el servidor responde que no hay sesión

#### Scenario: registro-E11 — Al volver a abrir la app, sigue dentro

- **DADO** que la persona inició sesión y cerró la app
- **CUANDO** vuelve a abrirla
- **ENTONCES** entra con su sesión, sin que se le pida el correo ni la contraseña
- **Y** si el servidor ya no reconoce el token, la app lo borra y la lleva a la entrada

#### Scenario: registro-E32 — Al reinstalar la app, la sesión no sobrevive

- **DADO** que la persona inició sesión y vio la bienvenida, y después desinstaló la app
- **CUANDO** la vuelve a instalar y la abre
- **ENTONCES** no hay sesión y la app empieza en la bienvenida, en iPhone igual que en Android
- **Y** la sesión anterior no se usa, aunque el almacén cifrado del teléfono la haya conservado

Cita: C → C-229 (Arthur) · A → `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3` (Módulo A #1, la primera apertura de
bienvenida-E01)

#### Scenario: registro-E12 — Cerrar la sesión revoca el token

- **DADO** una cuenta con la sesión iniciada
- **CUANDO** la app pide cerrar la sesión
- **ENTONCES** el token de esa sesión deja de servir

#### Scenario: registro-E13 — Cerrar la sesión la borra del teléfono

- **DADO** la app con la sesión iniciada
- **CUANDO** la persona cierra la sesión
- **ENTONCES** la app pide al servidor revocar el token
- **Y** lo borra del teléfono y la lleva a la entrada

### Requirement: Autorización en un solo componente, con el rol de usuario

El servidor DEBE (MUST) decidir quién puede ver o tocar cada dato en un solo componente de autorización, que en este
cambio conoce solo el rol de usuario. Cada cuenta DEBE (MUST) acceder solo a lo que le corresponde, y una cuenta de
usuario MUST NOT entrar al panel de administración.

Cita: A → `NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3` (Separación por tipo de usuario); D → ADR-0005

#### Scenario: registro-E14 — Toda ruta protegida pasa por el componente

- **DADO** la lista de rutas de la API
- **CUANDO** la prueba la recorre
- **ENTONCES** toda ruta que no sea pública exige sesión y pasa por el componente de autorización
- **Y** las únicas rutas públicas son la de salud y las de registro, inicio de sesión, Google, Apple y recuperación de
  la contraseña

Criterio: SEG-3 (en parte: el rol de usuario)

#### Scenario: registro-E15 — Una cuenta de usuario no entra al panel

- **DADO** una cuenta con el rol de usuario
- **CUANDO** intenta entrar al panel de administración con su correo y su contraseña
- **ENTONCES** el panel le niega la entrada

Criterio: SEG-3 (en parte: el rol de usuario)

### Requirement: Inicio de sesión con Google

La app DEBE (MUST) ofrecer iniciar sesión con Google, con `react-native-nitro-google-signin`. El servidor DEBE (MUST)
validar el `idToken` contra Google y contra el cliente OAuth configurado antes de emitir la sesión.

Cita: A → `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3` (Módulo A #2, "con opción de Google Sign-In"); D → ADR-0005 · C → C-224 (registro-E18) y C-228 (el correo verificado)

#### Scenario: registro-E16 — Entra con Google

- **DADO** un `idToken` de Google válido, emitido para el cliente configurado, con un correo que Google da por
  verificado y que no tiene cuenta
- **CUANDO** la app lo envía al servidor
- **ENTONCES** se crea la cuenta con el rol de usuario y la respuesta trae un token de sesión
- **Y** cuando esa misma cuenta de Google vuelve a entrar, recibe una sesión de la misma cuenta, sin crear otra

Criterio: A43-6 (en parte: F-A02)

#### Scenario: registro-E17 — Un idToken no válido se rechaza

- **DADO** un `idToken` vencido, con firma no válida, emitido para otro cliente o con un correo que Google no da por
  verificado (C-228)
- **CUANDO** la app lo envía al servidor
- **ENTONCES** el servidor lo rechaza y no emite sesión

#### Scenario: registro-E18 — Google con un correo que ya tiene cuenta con contraseña

- **DADO** una cuenta creada con correo y contraseña
- **CUANDO** la persona entra con una cuenta de Google que tiene ese mismo correo y Google lo da por verificado
- **ENTONCES** la cuenta de Google se liga a la cuenta existente y la respuesta trae un token de sesión de esa cuenta
- **Y** no se crea otra cuenta
- **Y** las sesiones que la cuenta tenía abiertas se cierran y su contraseña queda anulada: quien la había registrado
  ya no entra con ella, y el dueño del correo puede crear otra con la recuperación (DD-132, decisión 23)
- **Y** si Google no da el correo por verificado, el servidor rechaza la entrada y no liga nada

#### Scenario: registro-E19 — El botón de Google en la app

- **DADO** la pantalla de registro o la de inicio de sesión
- **CUANDO** la persona toca "Continuar con Google" y Google devuelve el `idToken`
- **ENTONCES** la app lo envía al servidor, guarda la sesión y entra
- **Y** si la persona cierra la ventana de Google sin elegir cuenta, se queda en la pantalla sin mensaje de error

### Requirement: Inicio de sesión con Apple, solo en iPhone

En iPhone, la app DEBE (MUST) ofrecer iniciar sesión con Apple, con `expo-apple-authentication`, y el servidor DEBE
(MUST) validar el token de identidad con `socialiteproviders/apple`. En Android no se ofrece.

Cita: D → ADR-0005, que se apoya en la regla 4.8 de la App Store (grado E, `SIN VERIFICAR`); BR-14

#### Scenario: registro-E20 — En Android no se ofrece Apple

- **DADO** la app en Android
- **CUANDO** se muestran las pantallas de registro y de inicio de sesión
- **ENTONCES** ofrecen el correo con la contraseña y Google
- **Y** no ofrecen Apple

#### Scenario: registro-E21 — En iPhone entra con Apple

- **DADO** la app en iPhone
- **CUANDO** la persona toca "Continuar con Apple" y Apple devuelve el token de identidad
- **ENTONCES** el servidor lo valida, crea o encuentra la cuenta con el rol de usuario y emite la sesión

SIN RESPUESTA — bloquea: registro-E21 (DD-069, BR-14). Además de la cuenta de Apple, que la App Store exija este
inicio de sesión cuando la app ofrece Google es grado E sin verificar (ADR-0005); se confirma antes de publicar.

### Requirement: Recuperación de la contraseña por correo

La persona DEBE (MUST) poder pedir un enlace para crear una contraseña nueva. El servidor DEBE (MUST) mandarlo por
correo a la cuenta, con un vencimiento configurable; con el enlace vigente, la contraseña nueva reemplaza a la
anterior. En local, el correo se captura sin enviarse.

Cita: A → `NSV-2026-001-v8.pdf:30:ANEXO B, Sección B.5` ("al recuperar una contraseña"); D → ADR-0005 y ADR-0018

#### Scenario: registro-E22 — Pide el enlace

- **DADO** una cuenta con el correo X
- **CUANDO** la persona pide recuperar la contraseña con X
- **ENTONCES** el servidor manda a X un correo con un enlace para crear una contraseña nueva, que vence en el plazo
  configurado
- **Y** en local el correo queda capturado, sin enviarse
- **Y** el correo lleva el nombre del producto que dice la configuración

#### Scenario: registro-E23 — Un correo sin cuenta no recibe nada

- **DADO** que no hay ninguna cuenta con el correo Y
- **CUANDO** alguien pide recuperar la contraseña con Y
- **ENTONCES** el servidor no manda ningún correo
- **Y** la respuesta es la misma que cuando la cuenta existe

#### Scenario: registro-E24 — Crea su contraseña nueva

- **DADO** un enlace de recuperación vigente para la cuenta con el correo X
- **CUANDO** se envía al servidor con una contraseña nueva que cumple la regla configurada
- **ENTONCES** la contraseña nueva queda guardada con hash
- **Y** la anterior deja de servir para iniciar sesión
- **Y** el enlace ya no sirve otra vez
- **Y** las sesiones abiertas de la cuenta se cierran

Criterio: SEG-2

#### Scenario: registro-E25 — El enlace vencido o ya usado se rechaza

- **DADO** un enlace de recuperación vencido o ya usado
- **CUANDO** se envía al servidor con una contraseña nueva
- **ENTONCES** el servidor lo rechaza
- **Y** la contraseña de la cuenta no cambia

#### Scenario: registro-E26 — La pantalla de recuperación en la app

- **DADO** la pantalla de inicio de sesión
- **CUANDO** la persona toca "¿Olvidaste tu contraseña?", escribe su correo y lo envía
- **ENTONCES** la app lo manda al servidor
- **Y** le dice que revise su correo

#### Scenario: registro-E27 — El correo sale de verdad fuera de local

- **DADO** el entorno de prueba o el de producción
- **CUANDO** el servidor manda el correo de recuperación
- **ENTONCES** el correo llega al buzón de la persona por el proveedor elegido, desde el dominio del cliente

SIN RESPUESTA — bloquea: registro-E27 (DD-071; además DD-070, el entorno de prueba, y DD-023, el dominio)

### Requirement: Límite de intentos

El servidor DEBE (MUST) limitar los intentos de inicio de sesión y las peticiones de recuperación de la contraseña,
por correo y por dirección, con el límite en configuración.

Cita: A → `NSV-2026-001-v8.pdf:10:Cláusula X` (medidas de seguridad técnicas razonables) · D → ADR-0018 · C → C-223

#### Scenario: registro-E30 — Demasiados intentos de inicio de sesión

- **DADO** el límite configurado de 5 intentos por minuto
- **CUANDO** llega un intento más que el límite, con el mismo correo y desde la misma dirección, dentro del mismo
  minuto
- **ENTONCES** el servidor responde que hay demasiados intentos y no revisa la contraseña
- **Y** pasado el minuto vuelve a aceptar intentos

#### Scenario: registro-E31 — Demasiadas peticiones de recuperación

- **DADO** el límite configurado de 5 peticiones por minuto
- **CUANDO** llega una petición más que el límite, con el mismo correo y desde la misma dirección, dentro del mismo
  minuto
- **ENTONCES** el servidor responde que hay demasiados intentos y no manda otro correo

### Requirement: Enlaces a los documentos legales en el registro

La pantalla de registro DEBE (MUST) mostrar los enlaces al aviso de privacidad y a los términos y condiciones que
entregue EL CLIENTE, con sus direcciones tomadas de la configuración. Cómo se recaba el consentimiento lo decide EL
CLIENTE, que es el responsable del tratamiento.

Cita: A → `NSV-2026-001-v8.pdf:31:ANEXO B, Sección B.7` y `NSV-2026-001-v8.pdf:10:Cláusula X`

#### Scenario: registro-E28 — Los enlaces salen de la configuración

- **DADO** las direcciones del aviso de privacidad y de los términos en la configuración de la app
- **CUANDO** se muestra la pantalla de registro
- **ENTONCES** muestra los dos enlaces con esas direcciones
- **Y** el código no trae ninguna dirección ni texto legal escrito a mano

#### Scenario: registro-E29 — La constancia de que aceptó

- **DADO** la pantalla de registro
- **CUANDO** la persona crea su cuenta
- **ENTONCES** no está definido si debe aceptar el aviso y los términos para registrarse, ni si el servidor guarda la
  constancia

SIN RESPUESTA — bloquea: registro-E29 (DD-080)

Mientras tanto, el servidor no guarda ninguna constancia: `usuarios` no tiene columna para ella, porque los
consentimientos son de EL CLIENTE (`NSV-2026-001-v8.pdf:10:Cláusula X`). La prueba que lo fija lleva registro-E29 en
su nombre (`servidor/tests/Feature/Registro/MigracionTest.php`).

### Requirement: Cada rol de la app consulta su cuenta y cierra su sesión

La API DEBE (MUST) dejar que una cuenta con un rol de la app —usuario, socio lavandería o socio repartidor— consulte su
cuenta, con su rol, y cierre su sesión. Con el rol la app sabe a qué módulo entra la cuenta. La administración entra por
el panel y NO DEBE (MUST NOT) usar estas rutas de la app.

Cita: A → `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3` (una sola app con los módulos del usuario, la lavandería y el
repartidor) · D → ADR-0019 (la autorización en un solo componente) y decisión 31 del acta del 2026-11-20 (C-236)

#### Scenario: registro-E33 — Una cuenta de lavandería consulta su cuenta y cierra su sesión

- **DADO** una cuenta de socio lavandería con la sesión iniciada
- **CUANDO** la app consulta su cuenta
- **ENTONCES** recibe su correo y su rol, `lavanderia`
- **Y** cuando la app cierra la sesión, el token deja de servir
- **Y** una cuenta de administración recibe 403 en las dos rutas, sin datos
- **Y** una cuenta de administración que inicia sesión en la app con su correo y su contraseña recibe 403, sin token
