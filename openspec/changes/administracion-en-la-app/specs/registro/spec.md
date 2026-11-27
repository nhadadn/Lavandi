## MODIFIED Requirements

### Requirement: Sesión que se conserva sin volver a escribir la contraseña

El servidor DEBE (MUST) reconocer la sesión por su token de Sanctum hasta que la persona la cierre. El token de la
cuenta de administración DEBE (MUST), además, vencer a los días de `acceso.dias_del_token_de_administracion` (30 por
omisión) contados desde que se abre la sesión (administracion-app-E01 y E05); los de los demás roles no vencen. La app
DEBE (MUST) guardar la sesión de forma segura y conservarla al volver a abrirse, sin pedir otra vez el correo ni la
contraseña.

Cita: A → `NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3` (Control de acceso); A → `NSV-2026-001-v8.pdf:10:Cláusula X`
(medidas de seguridad técnicas razonables); D → ADR-0005 y ADR-0018 (dónde se guarda); D → ADR-0022, decisión 2 (el
vencimiento del token de administración, que es técnico y no viene del contrato); C → C-247

#### Scenario: registro-E10 — El token abre la consulta de la propia cuenta

- **DADO** una cuenta con la sesión iniciada
- **CUANDO** la app consulta los datos de la propia cuenta con su token
- **ENTONCES** recibe su correo y su rol
- **Y** sin token, o con un token revocado, el servidor responde que no hay sesión
- **Y** con el token de una cuenta de administración que ya venció, también (administracion-app-E05)

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

### Requirement: Inicio de sesión con Google

La app DEBE (MUST) ofrecer iniciar sesión con Google, con `react-native-nitro-google-signin`. El servidor DEBE (MUST)
validar el `idToken` contra Google y contra el cliente OAuth configurado antes de emitir la sesión. Una cuenta de
administración NO DEBE (MUST NOT) entrar con Google, tenga o no Google ligado: el servidor la rechaza antes de ligar
nada y sin tocar su contraseña ni sus sesiones (administracion-app-E08).

Cita: A → `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3` (Módulo A #2, "con opción de Google Sign-In"); D → ADR-0005 · C → C-224 (registro-E18) y C-228 (el correo verificado) · D → ADR-0022, decisión 5 (la administración no entra con Google) · C → C-247

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
- **Y** nada de esto le pasa a una cuenta de administración, que también nace con correo y contraseña
  (administracion-E26): el servidor responde 422 en `id_token`, sin token, no liga la cuenta de Google, no anula la
  contraseña y no cierra sus sesiones (administracion-app-E08)

#### Scenario: registro-E19 — El botón de Google en la app

- **DADO** la pantalla de registro o la de inicio de sesión
- **CUANDO** la persona toca "Continuar con Google" y Google devuelve el `idToken`
- **ENTONCES** la app lo envía al servidor, guarda la sesión y entra
- **Y** si la persona cierra la ventana de Google sin elegir cuenta, se queda en la pantalla sin mensaje de error

### Requirement: Cada rol de la app consulta su cuenta y cierra su sesión

La API DEBE (MUST) dejar que una cuenta con un rol de la app —usuario, socio lavandería, socio repartidor o
administración— consulte su cuenta, con su rol, y cierre su sesión. Con el rol la app sabe a qué módulo entra la cuenta.
La cuenta de administración usa estas rutas con su token de administración (administracion-app-E01); un token suyo sin
la habilidad `administracion` NO DEBE (MUST NOT) abrirlas (administracion-app-E06). La administración no entra con
Google (administracion-app-E08).

Cita: A → `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3` (una sola app con los módulos del usuario, la lavandería y el
repartidor) y `NSV-2026-001-v8.pdf:3:Cláusula I` ("Aplicación única para iPhone y Android, con módulo de usuario, módulo
de socio lavandería, módulo de socio repartidor y panel de administración") · D → ADR-0019 (la autorización en un solo
componente) y ADR-0022 (decisiones 1, 5, 6 y 8, y la adenda del 2026-11-26 de la decisión 6) · C → C-247 y decisión 69
de `acta-2026-11-20-modelo-y-pasarela.md`, que reemplazan la decisión 31, la decisión 56 y la parte web de C-140; C →
C-245 (la adenda, por delegación de Arthur)

#### Scenario: registro-E33 — Una cuenta de lavandería consulta su cuenta y cierra su sesión

- **DADO** una cuenta de socio lavandería con la sesión iniciada
- **CUANDO** la app consulta su cuenta
- **ENTONCES** recibe su correo y su rol, `lavanderia`
- **Y** cuando la app cierra la sesión, el token deja de servir
- **Y** una cuenta de administración que inicia sesión en la app con su correo y su contraseña recibe su token de
  administración (administracion-app-E01)
- **Y** con ese token, la consulta de su cuenta trae su correo y su rol, `administracion`, y el cierre de la sesión lo
  revoca (administracion-app-E04)
- **Y** un token de una cuenta de administración sin la habilidad `administracion` recibe 403 en las dos rutas, sin
  datos (administracion-app-E06)
- **Y** una cuenta de administración que entra con Google recibe 422 en `id_token`, sin token y sin que su cuenta
  cambie (administracion-app-E08)
- **Y** del catálogo del territorio, la lista de municipios (`catalogos/municipios`) la lee también la administración
  con su token de administración, para cambiar el municipio de una lavandería (administracion-app-E14); las colonias por
  código postal siguen solo para el usuario, la lavandería y el repartidor
