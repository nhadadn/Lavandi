## Why

La app "se publicará de forma simultánea" en App Store y Google Play (`NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3`) y
se acepta "compilada y funcional […] entregada para su publicación" y con la "Pasarela Conekta [que] procesa
transacciones exitosamente en modo producción" (`NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3`). "Toda la información
que viaja entre el teléfono del usuario y el servidor va codificada" (`NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3`).
Si la publicación se atrasa por causas ajenas, el pago 2 se acredita con la app "desplegada en ambiente de prueba"
(`NSV-2026-001-v8.pdf:7:Cláusula IV`). Es lo que falta de F-T04 y F-T12, y F-T08: hito H11.

## What Changes

- Un entorno de prueba y uno de producción, además del local, cada uno con su servidor, su base y sus credenciales;
  producción en cuentas a nombre de EL CLIENTE (ADR-0012).
- El servidor solo atiende por https y las compilaciones de prueba y producción solo hablan con direcciones https
  (SEG-1).
- Perfiles de compilación `preview` y `production` de EAS, cada uno con su API, su proyecto de Firebase y su llave de
  mapas (ADR-0002, ADR-0012).
- El identificador de la app sale de la configuración y la compilación de producción se detiene si trae el marcador.
- Compilaciones de producción firmadas para Android y para iPhone, con las firmas guardadas en EAS (A43-1).
- Envío a App Store Connect y a Google Play Console del cliente, liberación simultánea en las dos tiendas y la dirección
  del aviso de privacidad declarada en cada ficha (A43-1).
- La pasarela con las llaves de producción del cliente y su webhook registrado en https, y una transacción real (A43-4).
  La pasarela en producción es Conekta mientras el cliente no firme el cambio (`CLAUDE.md` §2-bis, punto 3; ADR-0010),
  o la elegida si se firma. Stripe y Mercado Pago son candidatas (C-192); el cliente dio un OK expreso y la firma sigue
  pendiente (C-216, DD-079).
- La validación del A.4.3 en el ambiente de prueba, para el hito alterno del pago 2.

**Qué NO cambia**

- Las versiones mínimas iOS 18 y Android 11 y el nombre del producto desde la configuración: los fija `base-tecnica`
  (ADR-0009).
- El cobro, los webhooks idempotentes y su firma: los especifica `pago` (ADR-0010). Aquí solo se pasa a producción.
- Los avisos: los especifica `avisos` (ADR-0008). Aquí solo el proyecto de Firebase de cada entorno.
- Las cuentas de desarrollador, la de la pasarela, el dominio y el alojamiento los contrata EL CLIENTE a su nombre; EL
  PRESTADOR solo pide accesos de colaborador (`NSV-2026-001-v8.pdf:27:ANEXO B, Sección B.2`;
  `NSV-2026-001-v8.pdf:34:ANEXO B, Sección B.11`). Los trámites del B.10 son suyos.
- La revisión de las tiendas no depende de EL PRESTADOR (`NSV-2026-001-v8.pdf:5:Cláusula II`). Se atienden hasta 2
  intentos de corrección de rechazos (`NSV-2026-001-v8.pdf:23:ANEXO A, Sección A.7`); los demás van a la bolsa de horas
  o a Orden de Cambio (`NSV-2026-001-v8.pdf:7:Cláusula IV`).
- El mantenimiento posterior y las versiones nuevas son de la suscripción del ANEXO C
  (`NSV-2026-001-v8.pdf:40:ANEXO C, Sección C.5`).
- Ninguna función nueva entra en las dos semanas antes del envío (`vault/formas-de-trabajo/sprint-y-sesion-diaria.md`).

**Fuera del contrato** (posibles Órdenes de Cambio, `NSV-2026-001-v8.pdf:8:Cláusula IV`)

- Otra pasarela en lugar de Conekta (C-180): el contrato la nombra en el criterio de aceptación. Las candidatas son
  Stripe y Mercado Pago (C-192); el cliente dio un OK expreso y la firma sigue pendiente (C-216). Por las Cláusulas IV y
  XIV, el OK no sustituye la firma: hasta que se firme, no se construye nada de ninguna de las dos. DD-079.
- Efectivo sin OXXO, si la pasarela no genera fichas de OXXO: cambia lo que dice el contrato y queda por escrito.
  DD-056.

## Capabilities

### New Capabilities

- `publicacion`: los entornos de prueba y producción, las comunicaciones cifradas, las compilaciones firmadas, el envío
  y la publicación simultánea en las dos tiendas, y la pasarela en producción.

### Modified Capabilities

- Ninguna.

## Impact

- `servidor/`: configuración por entorno leída de variables de entorno; https obligatorio; prueba de salud del entorno
  desplegado; llaves de producción de la pasarela y registro de su webhook.
- `movil/`: `eas.json` con los perfiles `preview` y `production` y sus perfiles de envío; `app.config` con el
  identificador, la API, Firebase y la llave de mapas de cada perfil.
- Base de datos: ninguna tabla nueva. Las migraciones de los demás cambios corren en prueba y en producción sobre
  PostgreSQL con PostGIS (ADR-0004); las tablas que usa están en `design.md`, "Tablas y migración".
- Dependencias: EAS Build y EAS Submit (ADR-0002); el hosting de DD-070; servicios en cuentas del cliente.
- ADRs: ADR-0002, ADR-0004, ADR-0008, ADR-0009, ADR-0010 (vigente, Conekta; si se firma el cambio, lo reemplaza el ADR
  de la elegida, DD-079), ADR-0012 y ADR-0016.
- Depende de `base-tecnica`, `pago`, `avisos` y `textos-legales`, y de que estén terminados los cambios del recorrido
  del A.4.3.
