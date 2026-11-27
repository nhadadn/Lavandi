## REMOVED Requirements

### Requirement: La administración en la app

**Reason**: Arthur rechazó que la app mande a la administración a una página web: "El administrador debe iniciar
sesión. Eso del panel es algo tonto, ya que en sí esta app es una app móvil, no una app web". Eligió "Todo el Módulo D
en la app" (C-247; decisión 69 de `acta-2026-11-20-modelo-y-pasarela.md`), que revoca la decisión 56 y la parte web de
C-140. La Cláusula I pone el panel de administración dentro de la "Aplicación única para iPhone y Android"
(`NSV-2026-001-v8.pdf:3:Cláusula I`). Cómo se construye lo decide ADR-0022.

**Migration**: Lo reemplazan administracion-app-E01, E02 y E09 de la capacidad `administracion-en-la-app`: la cuenta
de administración inicia sesión en la app, con un token de administración, y entra a su módulo. Se quitan el aviso
"Las cuentas de administración entran por el panel", el botón "Abrir el panel" (`movil/src/acceso/BotonDelPanel.tsx`) y
la variable `EXPO_PUBLIC_PANEL_URL`. La prueba `movil/__tests__/administracion/abrir-el-panel.test.tsx` se reemplaza por
`movil/__tests__/administracion/entrada.test.tsx`. La tarea 6.2 de `administracion` queda en administracion-app-E08.

## MODIFIED Requirements

### Requirement: Cuenta de administración

El servidor DEBE (MUST) crear la cuenta de administración del dueño del negocio con un comando, en cualquier entorno,
también en producción. No lleva pantalla, porque el Módulo D #3 da consulta de usuarios, historial y bloqueo, no alta.
El comando pide la contraseña dos veces, con la entrada oculta; nunca la recibe como argumento ni la escribe en la
salida. Aplica la regla de la contraseña de ADR-0018 (8 caracteres como mínimo) y la guarda con hash. Crea una cuenta
nueva con el rol de administración y ninguno más. Rechaza, sin cambiar nada, un correo no válido, dos contraseñas que no
coinciden o una corta, y un correo que ya tiene cuenta, de cualquier rol, aunque esté borrada: no le agrega el rol a una
cuenta que ya existe, para no decidir "una cuenta, dos roles" (DD-053).

Cita: A → `NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3` ("Los cuatro módulos (usuario, socio lavandería, socio
repartidor y administración) accesibles y funcionales" y "Panel de administración muestra datos reales de pedidos y
usuarios activos"); A → `NSV-2026-001-v8.pdf:44:ANEXO D, Sección D.1` ("Pantalla de control reservada al dueño del
negocio"); A → `NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3` (protección de contraseñas); A →
`NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #3); D → ADR-0005, ADR-0018 y ADR-0022 (decisión 1); C →
decisión 68 de `acta-2026-11-20-modelo-y-pasarela.md` (C-245) y C-247

No se corre en el entorno de prueba ni en producción mientras no estén el alojamiento (DD-070) y el correo que dé EL
CLIENTE. No manda correo, invitación ni enlace.

#### Scenario: administracion-E26 — La cuenta de administración se crea con un comando

- **DADO** un entorno de producción sin cuentas de administración
- **CUANDO** se corre `php artisan administracion:crear-cuenta {correo}` con un correo y una contraseña válida, escrita dos veces
- **ENTONCES** existe la cuenta con ese correo, con solo el rol de administración y la contraseña guardada con hash, que no aparece en la salida
- **Y** esa cuenta entra al panel `/admin` (autorizacion-E03)
- **Y** si inicia sesión en la app con su correo y su contraseña, recibe su token de administración y la app abre su módulo (administracion-app-E01 y E02)
- **Y** con un correo que ya tiene cuenta, de cualquier rol o borrada, el comando falla sin tocar esa cuenta
- **Y** con un correo no válido, o una contraseña corta o que no coincide al repetirla, el comando falla sin crear ninguna cuenta

Criterio: A43-6, A43-7
