## ADDED Requirements

### Requirement: Entorno de prueba y entorno de producción

Además del local, la plataforma DEBE (MUST) tener un entorno de prueba y uno de producción separados, cada uno con su
servidor, su base y sus credenciales: prueba con el modo de prueba de la pasarela y el Firebase de prueba; producción
con los de producción, en cuentas a nombre de EL CLIENTE.

Cita: A → `NSV-2026-001-v8.pdf:7:Cláusula IV` y `NSV-2026-001-v8.pdf:34:ANEXO B, Sección B.11`; D → ADR-0012

#### Scenario: publicacion-E01 — Cada entorno con sus credenciales

- **DADO** la configuración del entorno de prueba
- **CUANDO** el servidor crea una orden de pago o manda un aviso
- **ENTONCES** usa las credenciales del modo de prueba de la pasarela y el proyecto de Firebase de prueba
- **Y** con la configuración de producción usa los de producción
- **Y** ninguna llave, token ni credencial está escrita en el código: todas salen de las variables del entorno

#### Scenario: publicacion-E02 — El entorno de prueba desplegado

- **DADO** el hosting que se decida
- **CUANDO** se despliega la rama principal al entorno de prueba
- **ENTONCES** las migraciones corren sobre PostgreSQL con PostGIS
- **Y** la API, el panel y la landing responden en su dirección de prueba, por https

SIN RESPUESTA — bloquea: publicacion-E02 (DD-070)

#### Scenario: publicacion-E03 — Producción en las cuentas de EL CLIENTE

- **DADO** las cuentas de EL CLIENTE, con Arthur como colaborador revocable
- **CUANDO** se despliega a producción
- **ENTONCES** el hosting, Firebase, Google Cloud, la pasarela, la organización de Expo y el correo de producción están a
  nombre de EL CLIENTE
- **Y** el mapa de cuentas, con fecha, queda en `vault/evidencias/publicacion/`

SIN RESPUESTA — bloquea: publicacion-E03 (DD-070, DD-084)

### Requirement: Comunicaciones cifradas

En prueba y en producción, toda la información entre la app, el panel, la landing y el servidor DEBE (MUST) viajar
cifrada: el servidor DEBE (MUST) atender solo por https, y las compilaciones de prueba y de producción DEBEN (MUST)
hablar solo con direcciones https.

Cita: A → `NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3`

#### Scenario: publicacion-E04 — El panel por https

- **DADO** el servidor con la configuración de producción
- **CUANDO** alguien pide el panel por http
- **ENTONCES** recibe una redirección permanente a la misma dirección por https
- **Y** ninguna página se sirve por http

Criterio: SEG-1

#### Scenario: publicacion-E05 — La API no atiende por http

- **DADO** el servidor con la configuración de producción
- **CUANDO** la app, o cualquiera, llama a la API por http
- **ENTONCES** la petición no se procesa y no se devuelve ningún dato

Criterio: SEG-1

#### Scenario: publicacion-E06 — La app solo habla por https

- **DADO** los perfiles de compilación `preview` y `production`
- **CUANDO** se lee la configuración de cada uno
- **ENTONCES** la dirección de la API empieza con `https://`
- **Y** el tráfico sin cifrar no está permitido en ninguno de los dos

Criterio: SEG-1

### Requirement: Compilaciones firmadas para las dos tiendas

La app DEBE (MUST) compilarse en la nube con un perfil por entorno, cada uno con su API, su Firebase y su llave de
mapas, y DEBE (MUST) producir compilaciones de producción firmadas para Android y para iPhone, con un identificador que
sale de la configuración y sin llaves de firma en el repositorio.

Cita: A → `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3` y `NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3`; D → ADR-0002

#### Scenario: publicacion-E07 — Un perfil por entorno

- **DADO** los perfiles `preview` y `production` de EAS
- **CUANDO** se lee la configuración de cada uno
- **ENTONCES** `preview` apunta a la API de prueba y `production` a la de producción
- **Y** cada uno usa el proyecto de Firebase y la llave de mapas de su entorno, entregados como secretos de EAS y no
  versionados

Criterio: A43-1

#### Scenario: publicacion-E08 — El identificador de la app sale de la configuración

- **DADO** el identificador de la app de iPhone y el nombre del paquete de Android tomados de la configuración
- **CUANDO** se prepara una compilación de producción con el marcador `{{MARCA}}` en lugar del identificador definitivo
- **ENTONCES** la compilación se detiene antes de empezar y dice que falta el identificador

El identificador definitivo depende de la marca (DD-023) y no se puede cambiar después de publicar.

Criterio: A43-1

#### Scenario: publicacion-E09 — La compilación de producción de Android

- **DADO** el perfil `production` y la firma de Android guardada en EAS
- **CUANDO** se compila para Android
- **ENTONCES** se obtiene un paquete firmado listo para Google Play
- **Y** el número de versión sube respecto de la compilación anterior

Criterio: A43-1

#### Scenario: publicacion-E10 — La compilación de producción de iPhone

- **DADO** el perfil `production` y la cuenta de desarrollador de Apple de EL CLIENTE
- **CUANDO** se compila para iPhone
- **ENTONCES** se obtiene una compilación firmada lista para App Store Connect
- **Y** el número de versión sube respecto de la compilación anterior

Criterio: A43-1

SIN RESPUESTA — bloquea: publicacion-E10 (DD-017, DD-084)

### Requirement: Envío y publicación simultánea en las dos tiendas

La compilación de producción DEBE (MUST) enviarse a App Store Connect y a Google Play Console, en las cuentas de EL
CLIENTE, con la dirección pública del aviso de privacidad declarada en cada ficha, y DEBE (MUST) liberarse en las dos
tiendas al mismo tiempo.

Cita: A → `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3`, `NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3` y
`NSV-2026-001-v8.pdf:31:ANEXO B, Sección B.7`

#### Scenario: publicacion-E11 — El envío a las dos tiendas

- **DADO** las cuentas de desarrollador de EL CLIENTE, con Arthur como colaborador, y las compilaciones de producción
- **CUANDO** se envían con EAS Submit
- **ENTONCES** la de iPhone queda en App Store Connect y la de Android en Google Play Console, listas para revisión
- **Y** el registro de cada envío, con fecha, queda en `vault/evidencias/publicacion/`

Criterio: A43-1

SIN RESPUESTA — bloquea: publicacion-E11 (DD-017, DD-084, DD-121, DD-109)

#### Scenario: publicacion-E12 — Ninguna tienda libera antes que la otra

- **DADO** el perfil de envío de Android
- **CUANDO** se envía una compilación a Google Play Console
- **ENTONCES** queda como borrador, sin publicarse sola
- **Y** se libera a mano, el mismo día que en App Store, cuando las dos revisiones estén aprobadas

Criterio: A43-1

#### Scenario: publicacion-E13 — La ficha declara el aviso de privacidad

- **DADO** el aviso de privacidad publicado en su dirección fija (`textos-legales`)
- **CUANDO** se llena la ficha de cada tienda
- **ENTONCES** la ficha declara esa dirección, tomada de la configuración
- **Y** la captura de cada ficha queda en `vault/evidencias/publicacion/`

SIN RESPUESTA — bloquea: publicacion-E13 (DD-080, DD-121)

### Requirement: La pasarela en modo producción

En producción, el cobro DEBE (MUST) usar la cuenta de la pasarela de EL CLIENTE en modo producción, con sus llaves leídas
del entorno y su webhook registrado en una dirección https, y DEBE (MUST) procesar con éxito una transacción real. La
pasarela es la que nombra el contrato en este criterio mientras el cliente no firme el cambio, o la elegida si se firma
(DD-079).

Cita: A → `NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3` y `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3`; D → ADR-0010

#### Scenario: publicacion-E14 — Llaves y webhook de producción

- **DADO** el servidor con la configuración de producción
- **CUANDO** se crea una orden de pago
- **ENTONCES** va al modo producción de la pasarela con las llaves del entorno
- **Y** la dirección del webhook de producción es https y valida la firma, como en el modo de prueba (ADR-0010)

Criterio: A43-4

La pasarela es Conekta, la del contrato (ADR-0010); con la elegida, si se firma el cambio (DD-079). Cómo se valida su
firma lo confirma `pago` en el sandbox de Conekta (tarea 3.1).

#### Scenario: publicacion-E15 — Una transacción real

- **DADO** la cuenta de la pasarela de EL CLIENTE dada de alta y en modo producción
- **CUANDO** se paga un pedido real desde la app de producción
- **ENTONCES** la pasarela procesa la transacción con éxito y el webhook confirma el pago
- **Y** el comprobante, con fecha, queda en `vault/evidencias/publicacion/`

Criterio: A43-4

SIN RESPUESTA — bloquea: publicacion-E15 (DD-084, DD-012, DD-056). La transacción es con Conekta, la pasarela vigente;
con la elegida, si se firma el cambio (DD-079). Pagar desde la app depende además de pago-E02, en `pago`.

### Requirement: Validación en el ambiente de prueba

La app compilada DEBE (MUST) quedar desplegada en el entorno de prueba y validada conforme a los criterios del A.4.3, con
evidencia fechada, para acreditar el hito del pago 2 si la publicación se atrasa por causas ajenas a EL PRESTADOR.

Cita: A → `NSV-2026-001-v8.pdf:7:Cláusula IV`; D → ADR-0012

#### Scenario: publicacion-E16 — El hito alterno del pago 2

- **DADO** el entorno de prueba desplegado y las compilaciones `preview` instaladas en un Android y en un iPhone
- **CUANDO** se recorre el pedido completo —elegir lavandería, agendar, pagar en el modo de prueba de la pasarela y dar
  seguimiento— y se revisan los demás criterios del A.4.3
- **ENTONCES** cada criterio queda con su evidencia, con fecha, en un acta de validación en
  `vault/evidencias/publicacion/`

Criterio: A43-1, y los demás del A.4.3 en el ambiente de prueba

SIN RESPUESTA — bloquea: publicacion-E16 (DD-070; en iPhone, DD-069 y DD-084). El pago en el modo de prueba es con
Conekta, la pasarela vigente; con la elegida, si se firma el cambio (DD-079). Pagar desde la app depende además de
pago-E02, en `pago`.
