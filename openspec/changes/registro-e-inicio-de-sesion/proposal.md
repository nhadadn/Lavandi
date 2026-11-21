## Why

Sin cuenta no hay pedido. El contrato pide registro e inicio de sesión con "Correo y contraseña, con opción de Google
Sign-In" (`NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3`, Módulo A #2); un control de acceso que "mantiene su sesión
abierta de forma segura, sin necesidad de que escriba su contraseña cada vez", y contraseñas que "no se guardan en
texto legible" (`NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3`). También presupuesta el correo que el sistema envía "al
recuperar una contraseña" (`NSV-2026-001-v8.pdf:30:ANEXO B, Sección B.5`). Es el hito H1: la persona entra.

## What Changes

- Registro con correo y contraseña, solo para el rol de usuario. El correo repetido y los datos inválidos se
  rechazan.
- La contraseña se guarda con el hash de Laravel y ninguna respuesta la muestra (ADR-0005, SEG-2).
- Inicio de sesión con correo y contraseña, con token de Sanctum; consulta de la propia cuenta; cierre de sesión que
  revoca el token.
- La app conserva la sesión al volver a abrirse.
- El componente único de autorización nace aquí, con el rol de usuario: cada cuenta ve solo lo suyo y una cuenta de
  usuario no entra al panel (`CLAUDE.md` §9.5).
- Inicio de sesión con Google: la app con `react-native-nitro-google-signin`, y el servidor valida el `idToken`
  (ADR-0005).
- Inicio de sesión con Apple, solo en iPhone (ADR-0005). Queda escrito y bloqueado hasta que haya cuenta de Apple
  (DD-069).
- Recuperación de la contraseña por correo, con un enlace que vence (ADR-0005). En local el correo se captura sin
  enviarse; el envío real espera al proveedor (DD-071).
- La pantalla de registro muestra los enlaces al aviso de privacidad y a los términos, tomados de la configuración
  (DD-080).

**Qué NO cambia:**

- El registro de lavanderías y repartidores, y cómo llega cada rol a su módulo: DD-053, en los hitos H5 y H8.
- La pantalla de perfil, con el nombre, la foto y el borrado de la cuenta (ADR-0005): `perfil-y-soporte` (F-A15,
  hito H7).
- El contenido de los documentos legales y su lugar en el resto de la app y en la landing: `textos-legales` (F-T18).
- El bloqueo de usuarios desde la administración: `administracion` (F-D03).
- Las comunicaciones cifradas se acreditan en `publicacion` (F-T08, SEG-1).
- La bienvenida y las tres pantallas informativas: `bienvenida` (F-A01).

**Fuera del contrato:**

- **La verificación del correo al registrarse.** El contrato no la pide (`NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3`,
  Módulo A #2) y ningún ADR la decide. No entra; si se quiere, va con ADR (`CLAUDE.md` §2-bis.4) o como posible Orden
  de Cambio (`NSV-2026-001-v8.pdf:8:Cláusula IV`). Sin fila DD.
- **La verificación del teléfono por mensaje de texto.** Exclusión expresa: "No forma parte del alcance base"
  (`NSV-2026-001-v8.pdf:30:ANEXO B, Sección B.5`).
- **El inicio de sesión con Apple en Android.** ADR-0005 no lo ofrece; si el cliente lo pide, es otra decisión.

## Capabilities

### New Capabilities

- `registro`: registro e inicio de sesión del usuario con correo y contraseña, con Google y, en iPhone, con Apple; la
  sesión que se conserva; la recuperación de la contraseña por correo, y el componente de autorización con el rol de
  usuario.

### Modified Capabilities

- Ninguna.

## Impact

- **`servidor/`:** las tablas `roles`, `usuarios`, `rol_usuario` y `cuentas_externas` (ADR-0016), los tokens de
  Sanctum y los de recuperación; las rutas de la API de
  registro, sesión, Google, Apple y recuperación; el componente de autorización; el correo de recuperación y el
  formulario de la contraseña nueva (dónde vive es una pregunta abierta de `design.md`).
- **`movil/`:** las pantallas de registro, inicio de sesión y recuperación; el módulo de sesión; los botones de
  Google y de Apple; las piezas de formulario con los tokens del design system (ADR-0013).
- **Base de datos:** una migración con las cuatro primeras tablas del negocio, sobre las reglas de `modelo-de-datos`
  (`vault/arquitectura/modelo-de-datos.md`, columna "Nace en"). La cuenta no lleva plaza: el usuario no pertenece a
  una plaza, su domicilio sí (`vault/arquitectura/modelo-multi-plaza.md`).
- **Dependencias:** `react-native-nitro-google-signin` 2.3.1; `expo-apple-authentication` y
  `socialiteproviders/apple`, cuando DD-069 se cierre; el almacenamiento del token en el teléfono, cuando tenga ADR.
- **Cuentas:** el cliente OAuth de Google de prueba, a nombre de Arthur (DD-072, C-170).
- **ADRs:** ADR-0005, ADR-0003, ADR-0012, ADR-0013, ADR-0014 (en borrador, DD-071) y ADR-0016 (el modelo de datos).
