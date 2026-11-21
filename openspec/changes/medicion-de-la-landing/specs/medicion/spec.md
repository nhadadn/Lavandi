## ADDED Requirements

### Requirement: Contenedor de etiquetas con las cuentas del cliente

Cada página pública de la landing DEBE (MUST) cargar un solo contenedor de Google Tag Manager, por el que entran
Google Analytics 4 y Meta Pixel. Su identificador DEBE (MUST) salir de la configuración de cada entorno, para que
producción use las cuentas a nombre de EL CLIENTE.

Cita: A → `NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2` y `NSV-2026-001-v8.pdf:34:ANEXO B, Sección B.11`; D → ADR-0011

#### Scenario: medicion-E01 — El contenedor en cada página

- **DADO** un identificador de contenedor configurado
- **CUANDO** alguien abre la portada, el aviso de privacidad o los términos
- **ENTONCES** cada página carga ese contenedor una sola vez
- **Y** ningún identificador de medición está escrito en el código

#### Scenario: medicion-E02 — Sin identificador no hay medición

- **DADO** un entorno sin identificador de contenedor, como el local
- **CUANDO** alguien abre la portada
- **ENTONCES** no se carga ningún código de medición de terceros
- **Y** la portada funciona igual

### Requirement: Visitas y acciones registradas en GA4 y Meta Pixel

La landing DEBE (MUST) registrar en Google Analytics 4 y en Meta Pixel cada visita y las acciones de conversión: el
toque en un botón de descarga y el envío completo de un formulario. Ningún evento DEBE (MUST) llevar los datos que la
persona escribió.

Cita: A → `NSV-2026-001-v8.pdf:14:ANEXO A, Sección A.2`, `NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2`,
`NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.2` y `NSV-2026-001-v8.pdf:10:Cláusula X`

#### Scenario: medicion-E03 — La visita

- **DADO** un contenedor de prueba con GA4 y Meta Pixel configurados
- **CUANDO** alguien abre la portada
- **ENTONCES** sale una petición de vista de página hacia GA4 y otra hacia Meta Pixel

Criterio: A42-2

#### Scenario: medicion-E04 — El toque en un botón de descarga

- **DADO** el mismo contenedor de prueba
- **CUANDO** alguien toca el botón de App Store o el de Google Play
- **ENTONCES** sale un evento de descarga hacia GA4 y hacia Meta Pixel que dice qué tienda fue

Criterio: A42-2

#### Scenario: medicion-E05 — Un formulario enviado, sin datos personales

- **DADO** el mismo contenedor de prueba
- **CUANDO** alguien envía completo el formulario de interés o el de contacto
- **ENTONCES** sale un evento hacia GA4 y hacia Meta Pixel que dice qué formulario fue
- **Y** el evento no lleva nombre, teléfono, correo ni ningún texto que la persona escribió
- **Y** un envío rechazado por incompleto no genera evento

Criterio: A42-2

#### Scenario: medicion-E06 — Los eventos en los paneles del cliente

- **DADO** producción con el contenedor de la cuenta de EL CLIENTE
- **CUANDO** se hace una visita y se toca un botón de descarga
- **ENTONCES** los dos aparecen en el tiempo real de GA4 y en el administrador de eventos de Meta
- **Y** las capturas, con fecha, quedan en `vault/evidencias/medicion/`

Criterio: A42-2

SIN RESPUESTA — bloquea: medicion-E06 (DD-118, DD-023)

### Requirement: Títulos y descripciones de cada página

Cada página pública de la landing DEBE (MUST) tener su propio título y su propia descripción para buscadores.

Cita: A → `NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2`

#### Scenario: medicion-E07 — Título y descripción propios

- **CUANDO** un buscador lee la portada, el aviso de privacidad y los términos
- **ENTONCES** cada página tiene un título y una descripción que no están vacíos
- **Y** ninguna repite el título ni la descripción de otra

Contenido configurable: los textos los aprueba EL CLIENTE (DD-117).

### Requirement: Mapa del sitio

La landing DEBE (MUST) publicar un mapa del sitio con todas sus páginas públicas, con su dirección completa en
`lavandi.mx` tomada de la configuración, declarado en el archivo de robots para que la consola de búsqueda de Google lo
lea.

Cita: A → `NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2`; D → ADR-0011

#### Scenario: medicion-E08 — El mapa del sitio y el archivo de robots

- **CUANDO** se pide el mapa del sitio
- **ENTONCES** responde un XML con la portada, el aviso de privacidad y los términos
- **Y** cada dirección es absoluta y usa el dominio de la configuración
- **Y** el archivo de robots declara la dirección del mapa del sitio

#### Scenario: medicion-E09 — Search Console lee el mapa del sitio

- **DADO** la propiedad de `lavandi.mx` verificada por DNS en la cuenta de Search Console de EL CLIENTE
- **CUANDO** se envía el mapa del sitio
- **ENTONCES** la consola lo lee sin errores
- **Y** la captura, con fecha, queda en `vault/evidencias/medicion/`

SIN RESPUESTA — bloquea: medicion-E09 (DD-023, DD-118)

### Requirement: Información estructurada del negocio y su zona

La portada DEBE (MUST) incluir información estructurada que diga de qué trata el negocio, la lavandería a domicilio, y
en qué zona opera: las plazas activas del territorio inicial. El nombre, la dirección del sitio y los datos de contacto
DEBEN (MUST) salir de la configuración.

Cita: A → `NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2` y `NSV-2026-001-v8.pdf:4:Cláusula I`

#### Scenario: medicion-E10 — El negocio y su zona de servicio

- **DADO** las plazas activas del territorio inicial cargadas en el sistema
- **CUANDO** un buscador lee la portada
- **ENTONCES** encuentra un bloque de información estructurada válido con el tipo de servicio, el nombre de la
  configuración (Lavandi, leído de la configuración), la dirección del sitio y, como zona de servicio, las áreas de las plazas activas
- **Y** el bloque no incluye ningún dato que no esté configurado, como un teléfono o un domicilio

### Requirement: Velocidad en teléfonos

La portada DEBE (MUST) cargar con rapidez en teléfonos celulares, con imágenes y contenido optimizados, hasta obtener 70
puntos o más sobre 100 en PageSpeed Insights para teléfono, medidos con el contenedor de medición cargado.

Cita: A → `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.2` y `NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.2`; D → ADR-0011

#### Scenario: medicion-E11 — Alerta temprana con Lighthouse

- **DADO** la portada con su contenido y un contenedor de medición de prueba cargado
- **CUANDO** la integración continua la mide con Lighthouse en perfil de teléfono
- **ENTONCES** la puntuación de rendimiento es de 0.70 o más
- **Y** si baja de 0.70, la verificación falla

Criterio: A42-4

#### Scenario: medicion-E12 — PageSpeed Insights sobre la dirección publicada

- **DADO** la landing publicada en `lavandi.mx` con el contenedor de la cuenta de EL CLIENTE
- **CUANDO** se mide con PageSpeed Insights en teléfono
- **ENTONCES** obtiene 70 puntos o más
- **Y** la captura, con fecha, queda en `vault/evidencias/medicion/`

Criterio: A42-4

SIN RESPUESTA — bloquea: medicion-E12 (DD-023, DD-070)

### Requirement: Dirección acordada con candado

La landing DEBE (MUST) publicarse en la dirección web acordada, `lavandi.mx`, con el candado de seguridad activo: toda
petición por http DEBE (MUST) redirigirse a https.

Cita: A → `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.2`, `NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.2` y
`NSV-2026-001-v8.pdf:28:ANEXO B, Sección B.3`

#### Scenario: medicion-E13 — De http a https

- **DADO** el servidor con la configuración de producción
- **CUANDO** alguien pide la portada por http
- **ENTONCES** recibe una redirección permanente a la misma dirección por https, en el dominio de la configuración

Criterio: A42-3

#### Scenario: medicion-E14 — El candado en la dirección acordada

- **DADO** el dominio a nombre de EL CLIENTE y el alojamiento contratado
- **CUANDO** se abre `https://lavandi.mx` en Chrome, Safari y Firefox
- **ENTONCES** cada navegador muestra el candado, con un certificado válido para ese dominio
- **Y** las capturas, con fecha, quedan en `vault/evidencias/medicion/`

Criterio: A42-3

SIN RESPUESTA — bloquea: medicion-E14 (DD-023, DD-070)
