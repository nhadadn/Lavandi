## ADDED Requirements

### Requirement: Las doce secciones del A.2

La landing DEBE (MUST) mostrar en una portada pública, sin iniciar sesión, las 12 secciones del A.2 en su orden, de
"Hero / Encabezado" a "Footer legal", y DEBE (MUST) verse correctamente en Chrome, Safari y Firefox.

Cita: A → `NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2` y `NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.2`

#### Scenario: landing-E01 — Las 12 secciones en escritorio, en los tres navegadores

- **DADO** la portada con contenido de prueba en cada sección
- **CUANDO** se abre en Chromium, WebKit y Firefox con ancho de escritorio
- **ENTONCES** las 12 secciones existen, son visibles y aparecen en el orden del A.2: encabezado, cómo funciona,
  beneficios para usuarios, descarga la app, lavanderías partner, beneficios para socios, cobertura, testimonios,
  preguntas frecuentes, prensa, contacto y soporte, y pie legal
- **Y** ningún elemento se sale del ancho de la pantalla

Criterio: A42-1

#### Scenario: landing-E02 — Las 12 secciones en ancho de teléfono

- **DADO** la misma portada
- **CUANDO** se abre en Chromium, WebKit y Firefox con ancho de teléfono
- **ENTONCES** las 12 secciones son visibles, en el mismo orden, una debajo de otra
- **Y** la página no se desplaza a lo ancho

Criterio: A42-1

#### Scenario: landing-E03 — Acreditación con el contenido de EL CLIENTE

- **DADO** los textos, las imágenes y el logo que entregue EL CLIENTE, cargados en la portada
- **CUANDO** se abre en Chrome, Safari y Firefox reales
- **ENTONCES** las 12 secciones se ven correctamente con ese contenido
- **Y** las capturas, con fecha, quedan en `vault/evidencias/landing/`

Criterio: A42-1

SIN RESPUESTA — bloquea: landing-E03 (DD-117, DD-023)

### Requirement: Encabezado, paso a paso y beneficios para usuarios

La landing DEBE (MUST) mostrar el encabezado con logo, tagline, botón principal de descarga e imagen con el mockup de la
app; el paso a paso Pide → Recogemos → Lavamos → Entregamos; y tres pilares para usuarios —comodidad, precio y
confianza— con iconos animados.

Cita: A → `NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2`

#### Scenario: landing-E04 — El encabezado

- **DADO** el logo, el tagline y la imagen con el mockup cargados como contenido
- **CUANDO** alguien abre la portada
- **ENTONCES** el encabezado muestra el logo, el tagline, la imagen y un botón principal de descarga
- **Y** el botón lleva a la sección "Descarga la app"
- **Y** el nombre del producto y el logo salen de la configuración, nunca escritos en la vista (Lavandi, leído de la configuración)

Contenido configurable: lo entrega EL CLIENTE (DD-117). El mockup necesita pantallas terminadas de la app.

#### Scenario: landing-E05 — El paso a paso

- **CUANDO** alguien abre la portada
- **ENTONCES** "¿Cómo funciona?" muestra cuatro pasos, en este orden: Pide, Recogemos, Lavamos y Entregamos

#### Scenario: landing-E06 — Los tres pilares para usuarios

- **CUANDO** alguien abre la portada
- **ENTONCES** "Beneficios para usuarios" muestra tres pilares titulados comodidad, precio y confianza
- **Y** cada pilar lleva su icono animado

Contenido configurable: el texto de cada pilar lo entrega EL CLIENTE (DD-117). El pilar "precio" no publica
cifras mientras DD-010 siga abierta.

#### Scenario: landing-E07 — Iconos quietos con movimiento reducido

- **DADO** un navegador con la preferencia de movimiento reducido activa
- **CUANDO** alguien abre la portada
- **ENTONCES** los iconos de los pilares se muestran sin animación

Regla transversal "Movimiento reducido" de `vault/design-system/componentes.md`.

### Requirement: Botones de descarga

La sección "Descarga la app" DEBE (MUST) mostrar los botones de App Store y de Google Play con un mockup de pantalla.
Cada botón DEBE (MUST) llevar al enlace de su tienda que se configure, y ninguno a una dirección vacía o rota.

Cita: A → `NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2`

#### Scenario: landing-E08 — Con los enlaces configurados

- **DADO** un enlace de App Store y uno de Google Play configurados
- **CUANDO** alguien toca el botón de App Store
- **ENTONCES** va al enlace configurado de App Store
- **Y** el botón de Google Play va al suyo

Los enlaces reales existen hasta que la app esté publicada (`publicacion`); se prueba con enlaces de prueba.

#### Scenario: landing-E09 — Antes de publicar la app

- **DADO** que una de las tiendas no tiene enlace configurado
- **CUANDO** alguien abre la portada
- **ENTONCES** el botón de esa tienda se muestra sin enlace y con el texto que se configure
- **Y** ningún enlace de la portada apunta a una dirección vacía

Contenido configurable: qué dice el botón mientras tanto lo decide EL CLIENTE (DD-117).

### Requirement: Invitación y beneficios para lavanderías

La sección "Lavanderías partner" DEBE (MUST) invitar a las lavanderías a unirse, con el formulario de interés, y
"Beneficios para socios" DEBE (MUST) mostrar tres pilares: más clientes, pagos seguros y cero gestión de cobranza.

Cita: A → `NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2`

#### Scenario: landing-E10 — La invitación con su formulario

- **CUANDO** alguien abre la portada
- **ENTONCES** "Lavanderías partner" muestra el texto de invitación y el formulario de interés de lavanderías

Contenido configurable: el texto de invitación lo entrega EL CLIENTE (DD-117).

#### Scenario: landing-E11 — Los tres pilares para socios

- **CUANDO** alguien abre la portada
- **ENTONCES** "Beneficios para socios" muestra tres pilares titulados más clientes, pagos seguros y cero gestión de
  cobranza

Los títulos son del contrato. El texto que explica "cero gestión de cobranza" promete que la plataforma cobra por el
socio, y el modelo de liquidación no está decidido (DD-013): es contenido configurable y no se escribe hasta que esa
fila cierre.

### Requirement: Mapa de cobertura del territorio inicial

La sección "Cobertura" DEBE (MUST) mostrar un mapa interactivo con las zonas de servicio activas del TERRITORIO INICIAL
—el Estado de Durango y los municipios de Torreón y Matamoros, Coahuila—, dibujado con polígonos propios, sin teselas
ni servicios de mapas de pago.

Cita: A → `NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2` y `NSV-2026-001-v8.pdf:4:Cláusula I`; D → ADR-0009

#### Scenario: landing-E12 — Las zonas activas en el mapa

- **DADO** las zonas de servicio activas cargadas en el sistema por `plazas-y-zonas`
- **CUANDO** alguien abre la sección "Cobertura"
- **ENTONCES** el mapa dibuja cada zona activa
- **Y** se puede acercar y alejar
- **Y** al tocar o señalar una zona se ve su nombre

#### Scenario: landing-E13 — Sin servicios de mapas de pago

- **CUANDO** alguien abre la portada y usa el mapa de cobertura
- **ENTONCES** el navegador no hace ninguna petición a servicios de mapas de pago: ni teselas, ni la API de mapas para
  web, ni geocodificación

#### Scenario: landing-E14 — Una plaza que no está activa no se dibuja

- **DADO** una plaza cargada con estado no activa, con sus zonas
- **CUANDO** alguien abre la sección "Cobertura"
- **ENTONCES** el mapa no dibuja las zonas de esa plaza

### Requirement: Testimonios y prensa editables desde el panel

La landing DEBE (MUST) mostrar de 3 a 5 tarjetas de opiniones de usuarios beta, con marcador de posición en el
lanzamiento, y los logos de medios o menciones que estén activos. Testimonios, logos y su interruptor de activo DEBEN
(MUST) editarse desde el panel, solo por quien tenga permiso de administración.

Cita: A → `NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2`; D → ADR-0011

#### Scenario: landing-E15 — Solo los testimonios activos, hasta cinco

- **DADO** seis testimonios activos y uno inactivo, cargados en el panel con un orden
- **CUANDO** alguien abre la portada
- **ENTONCES** "Testimonios" muestra cinco tarjetas, de testimonios activos, en el orden fijado en el panel
- **Y** el testimonio inactivo no aparece

#### Scenario: landing-E16 — Marcadores de posición en el lanzamiento

- **DADO** menos de tres testimonios activos
- **CUANDO** alguien abre la portada
- **ENTONCES** "Testimonios" completa tres tarjetas con marcadores de posición

Contenido configurable: el texto del marcador lo decide EL CLIENTE (DD-117).

#### Scenario: landing-E17 — Solo los logos de prensa activos

- **DADO** dos logos de prensa activos y uno inactivo
- **CUANDO** alguien abre la portada
- **ENTONCES** "Prensa / Reconocimientos" muestra los dos logos activos y no el inactivo

#### Scenario: landing-E18 — Prensa sin logos activos

- **DADO** que no hay ningún logo de prensa activo
- **CUANDO** alguien abre la portada
- **ENTONCES** la sección "Prensa / Reconocimientos" sigue presente, con el texto que se configure

Contenido configurable (DD-117). El A.2 dice "activables conforme se obtengan" y el A.4.2 exige las 12
secciones presentes: por eso la sección no se oculta.

#### Scenario: landing-E19 — Sin permiso no se edita

- **DADO** una persona con sesión iniciada que no tiene permiso de administración
- **CUANDO** intenta crear, editar o activar un testimonio o un logo de prensa
- **ENTONCES** el panel lo rechaza y nada cambia en la portada

### Requirement: Preguntas frecuentes

La sección "Preguntas frecuentes" DEBE (MUST) mostrar de 6 a 8 preguntas en un acordeón expandible: cada respuesta
empieza plegada y se despliega al tocar su pregunta.

Cita: A → `NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2`

#### Scenario: landing-E20 — El acordeón

- **DADO** siete preguntas con su respuesta cargadas como contenido
- **CUANDO** alguien abre la portada
- **ENTONCES** ve las siete preguntas con sus respuestas plegadas
- **Y** al tocar una pregunta se despliega su respuesta, y al tocarla otra vez se pliega
- **Y** lo mismo ocurre con el teclado, con el foco visible

Contenido configurable: preguntas y respuestas son de EL CLIENTE (DD-117).

### Requirement: Formularios de interés y de contacto

Los formularios de interés de lavanderías y de contacto DEBEN (MUST) rechazar un envío incompleto diciendo qué falta, y
entregar cada envío completo a su destino, con una confirmación para quien lo envió.

Cita: A → `NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2`

#### Scenario: landing-E21 — Envío incompleto

- **DADO** el formulario de interés o el de contacto
- **CUANDO** alguien lo envía con un campo obligatorio vacío o con un correo mal escrito
- **ENTONCES** el envío se rechaza y se señala el campo que falta
- **Y** no se entrega nada

#### Scenario: landing-E22 — Envío completo

- **DADO** un formulario de interés o de contacto lleno
- **CUANDO** alguien lo envía
- **ENTONCES** el envío llega a su destino con los datos que se escribieron y con el nombre del formulario del que viene
- **Y** quien lo envió ve una confirmación

SIN RESPUESTA — bloquea: landing-E22 (DD-119, que abre BR-41): a dónde llega cada formulario, qué campos
lleva y qué aviso de datos personales lo acompaña.

### Requirement: Contacto por correo y WhatsApp Business

La sección "Contacto & Soporte" DEBE (MUST) ofrecer el correo de contacto, un enlace a WhatsApp Business y el formulario
de contacto. El correo y el número DEBEN (MUST) salir de la configuración.

Cita: A → `NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2`

#### Scenario: landing-E23 — Correo y WhatsApp

- **DADO** un correo y un número de WhatsApp Business configurados
- **CUANDO** alguien toca el correo
- **ENTONCES** se abre un mensaje nuevo dirigido a ese correo
- **Y** al tocar WhatsApp se abre una conversación con ese número

Contenido configurable: el número de WhatsApp Business no existe todavía (DD-082) y el correo de contacto lo confirma
EL CLIENTE (DD-117).

### Requirement: Pie legal

Toda página pública de la landing DEBE (MUST) llevar un pie con enlaces al aviso de privacidad y a los términos de
servicio, y con el aviso de derechos de autor, cuyo titular y año salen de la configuración.

Cita: A → `NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2` y `NSV-2026-001-v8.pdf:31:ANEXO B, Sección B.7`

#### Scenario: landing-E24 — Los enlaces legales

- **CUANDO** alguien abre cualquier página pública de la landing
- **ENTONCES** el pie enlaza al aviso de privacidad y a los términos, en las direcciones que publica `textos-legales`

#### Scenario: landing-E25 — El aviso de derechos de autor

- **DADO** un titular y un año configurados
- **CUANDO** alguien abre cualquier página pública de la landing
- **ENTONCES** el pie muestra el aviso de derechos de autor con ese titular y ese año

Contenido configurable: quién aparece como titular es DD-120, porque los derechos se ceden hasta el pago
íntegro (`NSV-2026-001-v8.pdf:10:Cláusula IX`).
