## Why

El contrato no encarga redactar el aviso de privacidad ni los términos y condiciones: "Éste incorporará en el sitio web
y en la aplicación los documentos que EL CLIENTE le proporcione" (`NSV-2026-001-v8.pdf:31:ANEXO B, Sección B.7`). Las
dos tiendas exigen el aviso "publicado en una página web accesible antes de revisar la aplicación" (misma página), y el
B.10 lo pone publicado antes del envío a las tiendas (`NSV-2026-001-v8.pdf:34:ANEXO B, Sección B.10`). Es F-T18 del
inventario funcional, hito H10.

## What Changes

- Dos páginas públicas en `lavandi.mx`, cada una en una dirección fija que se abre sin iniciar sesión: el aviso de
  privacidad y los términos y condiciones, con el texto tal como lo entregue EL CLIENTE.
- En producción nunca se publica un texto de ejemplo como si fuera el aviso o los términos.
- Cada documento se reemplaza por su versión nueva sin modificar ni volver a desplegar el código, y su dirección no
  cambia.
- La app abre los dos documentos en esas mismas direcciones, tomadas de la configuración.

**Qué NO cambia**

- El consentimiento al registrarse: lo especifica `registro-e-inicio-de-sesion` (F-A02), con el enlace como dato de
  configuración (DD-080).
- El pie de la landing que enlaza estas páginas y el aviso de derechos de autor: van en `landing` (F-L12).
- La declaración de la dirección del aviso en las fichas de las tiendas: va en `publicacion`.
- Las obligaciones de EL CLIENTE como responsable de los datos —elaborar, publicar y mantener actualizado el aviso,
  obtener los consentimientos y atender los derechos ARCO— siguen siendo suyas (`NSV-2026-001-v8.pdf:10:Cláusula X`).
  Ningún flujo de ARCO entra a la plataforma.

**Fuera del contrato** (posibles Órdenes de Cambio, `NSV-2026-001-v8.pdf:8:Cláusula IV`)

- Redactar, completar o revisar el aviso de privacidad o los términos: "La elaboración del aviso de privacidad y de los
  términos y condiciones no forma parte del alcance de EL PRESTADOR" (`NSV-2026-001-v8.pdf:31:ANEXO B, Sección B.7`).
  DD-080.

## Capabilities

### New Capabilities

- `textos-legales`: la publicación del aviso de privacidad y de los términos y condiciones de EL CLIENTE en el sitio, su
  reemplazo por versiones nuevas y el acceso desde la app.

### Modified Capabilities

- Ninguna.

## Impact

- `servidor/`: dos rutas públicas y la carga del documento como contenido, no como código.
- `movil/`: los enlaces al aviso y a los términos, con las direcciones de la configuración.
- Base de datos o almacenamiento: el texto vigente de cada documento, que no tiene tabla en el modelo de datos. Dónde se
  guarda es una pregunta abierta de `design.md`. La constancia de aceptación, `usuarios.terminos_aceptados_en`, es de
  `registro-e-inicio-de-sesion` (`design.md`, "Tablas y migración").
- Dependencias: ninguna nueva.
- ADRs: ADR-0002, ADR-0003 y ADR-0011.
- La sirven `landing` (pie legal), `registro-e-inicio-de-sesion` (consentimiento) y `publicacion` (fichas de las
  tiendas).
