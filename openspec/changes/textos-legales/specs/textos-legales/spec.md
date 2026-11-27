## ADDED Requirements

### Requirement: Páginas públicas del aviso de privacidad y de los términos

El sitio DEBE (MUST) publicar el aviso de privacidad y los términos y condiciones, cada uno en una dirección fija y
pública de `lavandi.mx` que se abre sin iniciar sesión, con el texto tal como EL CLIENTE lo entregue, sin redactarlo ni
cambiarlo.

Cita: A → `NSV-2026-001-v8.pdf:31:ANEXO B, Sección B.7`

#### Scenario: textos-legales-E01 — El documento cargado se publica

- **DADO** un documento de prueba cargado como aviso de privacidad y otro como términos
- **CUANDO** alguien sin sesión abre la dirección de cada uno
- **ENTONCES** ve el texto completo del documento cargado, sin cambios

#### Scenario: textos-legales-E02 — En producción no se publica un texto de ejemplo

- **DADO** el servidor con la configuración de producción y sin el documento de EL CLIENTE cargado
- **CUANDO** alguien abre la dirección del aviso de privacidad o la de los términos
- **ENTONCES** no ve ningún texto de ejemplo ni de prueba
- **Y** la página dice que el documento todavía no está disponible

#### Scenario: textos-legales-E03 — Los documentos de EL CLIENTE, publicados

- **DADO** el aviso de privacidad y los términos que entregó EL CLIENTE
- **CUANDO** se cargan en producción
- **ENTONCES** cada uno se ve completo en su dirección, antes del envío a las tiendas
- **Y** la captura, con fecha, queda en `vault/evidencias/textos-legales/`

SIN RESPUESTA — bloquea: textos-legales-E03 (DD-080)

### Requirement: Cambiar un documento sin cambiar el código

Cada documento DEBE (MUST) poder reemplazarse por la versión nueva que entregue EL CLIENTE sin modificar ni volver a
desplegar el código, y su dirección DEBE (MUST) seguir siendo la misma.

Cita: A → `NSV-2026-001-v8.pdf:31:ANEXO B, Sección B.7` y `NSV-2026-001-v8.pdf:10:Cláusula X`

#### Scenario: textos-legales-E04 — Una versión nueva

- **DADO** un aviso de privacidad publicado
- **CUANDO** se carga una versión nueva
- **ENTONCES** su dirección muestra la versión nueva y ya no la anterior
- **Y** la dirección es la misma de antes

### Requirement: Los documentos desde la app

La app DEBE (MUST) permitir abrir el aviso de privacidad y los términos vigentes en las mismas direcciones públicas del
sitio, tomadas de la configuración y no escritas en el código.

Cita: A → `NSV-2026-001-v8.pdf:31:ANEXO B, Sección B.7`

#### Scenario: textos-legales-E05 — Abrir el aviso y los términos

- **DADO** las direcciones del aviso de privacidad y de los términos configuradas
- **CUANDO** la persona toca el enlace al aviso de privacidad
- **ENTONCES** la app abre esa dirección y muestra el documento vigente
- **Y** el enlace a los términos hace lo mismo con la suya

### Requirement: La página para pedir el borrado de la cuenta

El sitio DEBE (MUST) publicar, en una dirección fija y pública, cómo pedir el borrado de la cuenta, sin decir qué datos
se conservan.

Cita: A → `NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3`; E → Google Play,
support.google.com/googleplay/android-developer/answer/13327111, consultada el 2026-11-24, SIN VERIFICAR; D → adenda
del 2026-11-24 de ADR-0005

#### Scenario: textos-legales-E06 — Cómo pedir el borrado de la cuenta

- **DADO** el correo de contacto y el WhatsApp de soporte configurados
- **CUANDO** alguien sin sesión abre la dirección de la página
- **ENTONCES** ve cómo borrar su cuenta desde la app y cómo pedirlo por ese correo o ese WhatsApp
- **Y** la página no dice qué datos se borran ni cuáles se conservan (DD-109)

#### Scenario: textos-legales-E07 — En producción, sin contacto configurado, no se inventa

- **DADO** la configuración de producción sin el correo ni el WhatsApp
- **CUANDO** alguien abre la página
- **ENTONCES** sigue diciendo cómo borrar la cuenta desde la app
- **Y** no muestra ningún contacto de ejemplo ni de prueba
