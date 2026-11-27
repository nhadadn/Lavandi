## Context

- ADR-0011 ya decidió cómo se mide: GA4 y Meta Pixel entran por un contenedor de GTM, Search Console se verifica por
  DNS y PageSpeed se mide con GTM cargado. Su criterio de reversión: si no se llega a 70 después de optimizar imágenes
  y cargar GTM en diferido, se pasa a un sitio estático.
- Las cuentas de producción van a nombre de EL CLIENTE (`NSV-2026-001-v8.pdf:34:ANEXO B, Sección B.11`; RN-31). Local y
  prueba pueden usar cuentas de EL PRESTADOR mientras tanto (ADR-0012).
- La integración continua ya prevé Lighthouse como alerta temprana y PageSpeed Insights sobre la dirección publicada al
  acreditar (`vault/formas-de-trabajo/integracion-continua.md`).
- La trazabilidad prevé Playwright interceptando las llamadas a GA4 y al Pixel (A42-2) y la prueba de http a https
  (A42-3) (`vault/formas-de-trabajo/trazabilidad-criterio-test.md`).
- El dominio no está comprado (DD-023) y el alojamiento no está decidido (DD-070): todo lo que pide la dirección
  publicada espera.

## Goals / Non-Goals

**Goals:**

- Acreditar A42-2, A42-3 y A42-4, y dejar verificable por presencia la preparación para buscadores (O-09).
- Que cambiar de cuenta de prueba a la del cliente sea cambiar configuración, no código.

**Non-Goals:**

- Diseñar campañas, reportes de métricas o tableros de analítica (`NSV-2026-001-v8.pdf:25:ANEXO A, Sección A.8`).
- La ficha del negocio en Google Maps y las páginas por ciudad.
- Un aviso de consentimiento de cookies (ver DD-118).
- La redirección a https del panel y de la API (`publicacion`).

## Decisions

No hay decisión técnica nueva: el cambio aplica ADR-0011 y ADR-0012, sin repetirlos. La redirección de http a https
usa el mismo mecanismo que `publicacion` (publicacion-E04); lo implementa la primera tarea que llegue y la otra solo lo
prueba en sus rutas.

**Cómo quedó implementado.** Las lecturas de abajo las tomó Claude por delegación de Arthur (C-236), con la mínima que
cumple cada escenario; se pueden rehacer sin tocar los requisitos.

- **El contenedor (tarea 1.1, 2026-11-22):** `MEDICION_GTM` en el `.env` de cada entorno, leído por
  `config/medicion.php` y validado por `App\Medicion\ContenedorDeEtiquetas`: solo vale "GTM-" con letras mayúsculas y
  números, porque va dentro de un script; cualquier otra cosa cuenta como ninguno y la página no mide (medicion-E02). El
  cargador es `resources/views/publico/medicion.blade.php`, que el diseño base de las páginas públicas pone una vez en
  la cabecera, así que lo llevan la portada, el aviso de privacidad, los términos y cualquier página pública que llegue.
  Sin el marco `<noscript>` de la instalación de GTM: GA4 y Meta Pixel necesitan JavaScript, así que sin él no mide
  ninguno de los eventos de la spec.
- **El plan de eventos (tarea 1.2, 2026-11-22; pregunta 1):** la página pone tres eventos en la capa de datos del
  contenedor (`dataLayer`), y las etiquetas del contenedor los mandan a GA4 y a Meta Pixel:

  | Qué pasa | En la capa de datos | A GA4 | A Meta Pixel |
  |---|---|---|---|
  | Se abre una página pública (medicion-E03) | `gtm.js`, el de la instalación de GTM | `page_view` | `PageView` |
  | Se toca el botón de App Store o el de Google Play (medicion-E04) | `descarga`, con `tienda`: `app_store` o `google_play` | `descarga`, con `tienda` | `Descarga` (personalizado), con `tienda` |
  | Se envía completo el formulario de interés o el de contacto (medicion-E05) | `formulario_enviado`, con `formulario`: `interes` o `contacto` | `formulario_enviado`, con `formulario` | `Lead` (estándar), con `formulario` |

  Ningún evento lleva otro dato. El de descarga lo pone `resources/js/portada.js` al tocar cualquier elemento con
  `data-tienda`, tenga o no el enlace de su tienda: también antes de publicar la app se toca el botón. El del formulario
  lo pone el servidor en la portada de vuelta de un envío completo, con el aviso `formulario_enviado` de la sesión que
  deja `FormularioDeLaPortadaController`; un envío rechazado no pasa por ahí. Quien configure el contenedor de EL
  CLIENTE (tarea 1.3) no usa variables que lean los campos de los formularios: la spec no admite lo que la persona
  escribió en ningún evento.
- **El contenedor de prueba (tarea 1.2):** no hay contenedor de prueba en una cuenta de EL PRESTADOR, así que las
  pruebas de navegador sirven `tests/Browser/Soporte/contenedor-de-prueba.js` en lugar del `gtm.js` de Google, con las
  etiquetas del plan de arriba, e interceptan las peticiones hacia GA4 y Meta Pixel: ninguna sale a internet. Manda con
  cada evento todo lo que la página puso en la capa de datos, para que un dato personal ahí hiciera fallar la prueba.
  Las pruebas son `tests/Browser/Medicion/Eventos.spec.js`, con Playwright desde Node, como las de `landing` (su
  pregunta 7), y `tests/Feature/Medicion/EventosDeLaPortadaTest.php` para la parte del servidor. Las dos corren en la
  integración continua; las de navegador, en el trabajo `landing-navegadores` (2026-11-24). La configuración del
  servidor de las pruebas de navegador ahora trae el contenedor de prueba y enlaces de prueba de las tiendas
  (`tests/Browser/Soporte/configuracion.js`); por eso landing-E13, que revisa que el mapa no pida nada a otro servidor,
  sirve vacío ese contenedor y lo deja fuera de su cuenta.
- **Títulos y descripciones (tarea 2.1, 2026-11-22):** el título de la portada es `APP_NAME`, y el de cada documento
  legal, su nombre y `APP_NAME` ("Aviso de privacidad · …"), como ya estaban: ninguno inventa un texto. La descripción
  de cada página sale de `LANDING_DESCRIPCION_PORTADA`, `LANDING_DESCRIPCION_AVISO_DE_PRIVACIDAD` y
  `LANDING_DESCRIPCION_TERMINOS` (`landing.descripciones`), que aprueba EL CLIENTE (DD-117). Mientras falten, la misma
  lectura que el pie legal de `landing`: fuera de producción, un marcador de contenido de prueba propio de cada página;
  en producción, ninguna descripción, porque no se inventa y las páginas legales no admiten texto de prueba
  (textos-legales-E02). medicion-E07 se acredita en producción cuando lleguen los textos de todas las páginas públicas.
  La página para pedir el borrado de la cuenta (textos-legales-E06, 2026-11-24) suma la suya,
  `LANDING_DESCRIPCION_BORRADO_DE_CUENTA`, con su título "Eliminar tu cuenta · …", y entra al mapa del sitio
  (`MapaDelSitio::PAGINAS`, medicion-E08).
- **Mapa del sitio y robots (tarea 2.1):** dos rutas públicas, `GET /sitemap.xml` y `GET /robots.txt`
  (`BuscadoresController`), declaradas en `Autorizacion::rutasSinSesion()`. Las direcciones salen de los nombres de las
  rutas, así que van en el dominio de `APP_URL` aunque la consulta llegue por otro. El `public/robots.txt` fijo se
  borró: el servidor web lo serviría antes que la ruta, y no sabe el dominio. En producción, el archivo de robots no le
  cierra nada a ningún buscador y declara el mapa del sitio (medicion-E08).
- **Solo producción se deja indexar (tarea 2.3, 2026-11-24; pregunta 3):** en todo entorno cuyo `APP_ENV` no sea
  `production`, `MapaDelSitio::robots()` responde `User-agent: *` y `Disallow: /`, sin declarar el mapa del sitio
  (medicion-E15; adenda del 2026-11-24 de ADR-0012). `BuscadoresTest` lo prueba cambiando el entorno de la aplicación:
  en el de pruebas, en local y en dos nombres que no son de ninguno (`prueba` y `staging`), porque la regla no nombra el
  del entorno de prueba en internet. La prueba del archivo de robots de medicion-E08 corre con la configuración de
  producción (el entorno y solo https) y también lleva medicion-E15. Que el entorno de prueba en internet responda
  cerrado se comprueba cuando exista (DD-070).
- **Información estructurada (tarea 2.1):** un bloque JSON-LD en la cabecera de la portada, con el vocabulario de
  schema.org: `Service`, con `serviceType` "Lavandería a domicilio", `name` y `provider` con `APP_NAME`, `url` con la
  dirección del sitio, y `areaServed` con cada plaza activa como `Place` y sus zonas activas en `containsPlace`. Sin
  logo, teléfono, domicilio, correo ni coordenadas: no están configurados o no los pide la spec. Los nombres van con los
  signos de HTML escapados, para que ninguno cierre el script.
- **Velocidad en teléfono (tarea 3.1, 2026-11-22):**
  - *GTM en diferido:* el cargador pone en la capa de datos el `gtm.js` y, si toca, el evento del formulario al leer la
    página, pero pide el `gtm.js` de Google hasta el evento `load`, o enseguida si la página ya cargó. Lo que pasó antes
    espera en la capa de datos y el contenedor lo procesa al llegar; la visita sale igual (medicion-E03). No se usó la
    carga al primer toque o desplazamiento, porque perdería la visita de quien no toca nada.
  - *Imágenes:* el logo por omisión es ahora `marca/logotipo-192.png`, el mismo símbolo reducido a 192 píxeles, el
    triple de lo más que se muestra (64), con paleta: 4 KB contra los 17.5 KB del original de 1024, que sigue en
    `marca/logotipo.png`. Lighthouse dejó de marcar "imágenes de tamaño adecuado". Las dos imágenes de EL CLIENTE se
    piden como conviene en teléfono: la del encabezado con `fetchpriority="high"`, porque es la más grande al abrir; la
    de descarga con `loading="lazy"`; las dos con `decoding="async"`. Su peso y su tamaño son de su contenido (DD-117):
    conviene recibirlas al tamaño en que se muestran.
  - *Lighthouse CI (pregunta 4):* `servidor/lighthouserc.json`, en el trabajo `landing-velocidad` de
    `.github/workflows/ci.yml`. Sirve la portada como las pruebas de navegador, con los estilos compilados y el
    contenido de `PortadaDePrueba`, y la mide tres veces en perfil de teléfono; falla si la mediana baja de 0.70 en
    rendimiento. Los informes quedan en el disco de la máquina (`upload.target: filesystem`), sin subirlos a ningún
    servicio. Versión: `@lhci/cli@0.15.1`, con Lighthouse 12.6.1 (E, npm, consultado el 2026-11-22: la 0.15.1 salió el
    2025-06-25; la 0.16.x tiene menos de tres semanas), por `npx` con la versión fija, sin entrar a `package.json`.
    Medido en la máquina de Arthur el 2026-11-22, sin contenedor: 0.99 en las tres corridas; con la red frenada a
    1500 ms y 150 kbps, 0.55, y la verificación falla.
  - *El contenedor de prueba:* la variable del repositorio `MEDICION_GTM_PRUEBA` pone el contenedor en la medición de
    Lighthouse. Ese contenedor, con etiquetas de GA4 y Meta Pixel de prueba, va en una cuenta de EL PRESTADOR mientras
    tanto (ADR-0012) y lo crea Arthur; sin la variable, Lighthouse mide la portada sin medición, y medicion-E11 solo se
    cumple a medias. Lo que acredita es medicion-E12, con el contenedor del cliente.
  - *Lo que depende del alojamiento (DD-070):* Lighthouse marca la falta de compresión del texto y la respuesta lenta
    del documento, que son del servidor de desarrollo de PHP; en prueba y en producción las da el servidor web.
- **De http a https (tarea 3.2, 2026-11-22):** el middleware global `App\Http\Middleware\SoloHttps`, el mismo que
  prueba `publicacion` en el panel y la API (publicacion-E04 y E05). Con `config('app.solo_https')`, una petición por
  http recibe una redirección 301 a la misma dirección, con su consulta, por https y en el dominio de `APP_URL`, sin
  procesarse. `app.solo_https` es verdadero en todo entorno que no sea `local` ni `testing`, y no tiene variable que
  lo apague: así prueba, cuyo nombre de entorno todavía no existe, y producción quedan cubiertos solos. Va al final del
  grupo global, después de `TrustProxies`. Si el alojamiento de DD-070 termina el https en un proxy, ese proxy se
  declara de confianza (`trustProxies`) al desplegar; sin eso, cada petición llegaría como http y se redirigiría sin
  fin. No se agregó el encabezado HSTS: la spec no lo pide.

## Tablas y migración

No crea tablas ni migraciones: ninguna tabla de `vault/arquitectura/modelo-de-datos.md` nace aquí (ADR-0016). Para la
zona de servicio de la información estructurada (medicion-E10) solo lee las plazas activas y sus zonas activas,
`plazas` (`id_plazas`, `nombre`, `activa`) y `zonas` (`id_plazas`, `nombre`, `activa`), que nacen en `plazas-y-zonas`.
El identificador del contenedor de GTM, el dominio y los datos de contacto salen de la configuración de cada entorno, y
ningún evento de medición se guarda en la base.

## Risks / Trade-offs

- [GTM y Meta Pixel bajan la velocidad en teléfono] → carga en diferido y criterio de reversión de ADR-0011.
- [Las cuentas del cliente no existen] → se prueba con un contenedor de prueba de EL PRESTADOR (ADR-0012) y se cambia
  el identificador antes de producción.
- [Medir sin consentimiento puede chocar con el aviso de privacidad del cliente] → la decisión legal es de EL CLIENTE,
  que es el responsable (`NSV-2026-001-v8.pdf:10:Cláusula X`); va en DD-118.
- [Search Console necesita un registro en el DNS del dominio del cliente] → espera a DD-023 y a los accesos de
  colaborador.
- [El inventario pesó F-T16 y F-T17 en 7 h] → las tareas suman 27 h; ver `tasks.md`.

## Open Questions

1. **El plan de eventos (BR-11).** La spec fija qué se mide —visita, descarga y formulario— pero no el nombre de cada
   evento. Se propone en la tarea 1.2 y lo aprueba Arthur. *Resuelta el 2026-11-22, por delegación de Arthur (C-236):*
   el plan de "Cómo quedó implementado".
2. **¿Se versiona en el repositorio la exportación del contenedor de GTM?** Sin ella, la configuración de etiquetas
   vive solo en la cuenta del cliente. Lo decide Arthur. *Recomendación de Claude, por delegación (C-236):* no en
   `servidor/`, porque trae los identificadores de GA4 y de Meta Pixel, y medicion-E01 no admite ninguno escrito en el
   código; la exportación, con fecha, va a `vault/evidencias/medicion/` con las capturas de la tarea 1.3, cuando exista
   el contenedor de EL CLIENTE. Mientras tanto, el plan de eventos de arriba dice qué etiquetas lleva.
3. **¿El entorno de prueba pide a los buscadores que no lo indexen?** No lo dicen el contrato ni ADR-0012. **Necesita
   decisión de Arthur, en ADR-0012.** *Resuelta el 2026-11-24 con la adenda de ADR-0012, que asentó el concilio por
   delegación de Arthur Naranjo (C-236, C-245; `acta-2026-11-20-modelo-y-pasarela.md`, decisión 65):* sí, y como regla
   por entorno: todo entorno que no sea producción responde `Disallow: /` en el archivo de robots (medicion-E15, tarea
   2.3). La comprobación en el entorno de prueba real espera a que exista (DD-070).
4. **Dónde corre Lighthouse CI:** un trabajo más en `.github/workflows/ci.yml`, con la portada levantada en la máquina
   de la integración continua. Versiones por confirmar (E). *Resuelta el 2026-11-22, por delegación de Arthur (C-236):*
   el trabajo `landing-velocidad`, con `@lhci/cli@0.15.1` (ver "Cómo quedó implementado").
5. **Consentimiento para la medición** (DD-118).
