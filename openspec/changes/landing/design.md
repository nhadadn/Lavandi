## Context

- La landing vive en el mismo Laravel que la API y el panel: vistas Blade con Tailwind, servidas en `lavandi.mx`
  (ADR-0011, sobre ADR-0003). Consume las variables CSS que genera el adaptador de tokens (ADR-0013), solo en modo
  claro (C-141).
- Testimonios, logos de prensa y su interruptor de activo se editan desde Filament (ADR-0011).
- El mapa de cobertura se dibuja con polígonos propios, sin teselas de pago (ADR-0009, decisión 5;
  `vault/arquitectura/presupuesto-de-mapas.md`, decisión 1). Las zonas y el estado de cada plaza los carga
  `plazas-y-zonas` con PostGIS (ADR-0004; `vault/arquitectura/modelo-multi-plaza.md`). Cómo se trazan las zonas
  (BR-02) lo resuelve ese cambio.
- No hay mocks de la landing (`mocks/LEEME.md`, "Qué falta") ni componentes del design system para sus secciones
  (`vault/contrato/inventario-funcional.md`, sección 8). Rigen las reglas transversales de
  `vault/design-system/componentes.md`: el color nunca solo, el foco visible y el movimiento reducido.
- Los textos y las imágenes son insumo de EL CLIENTE (`NSV-2026-001-v8.pdf:6:Cláusula III`, inciso B). Hoy no existen.

## Goals / Non-Goals

**Goals:**

- Las 12 secciones del A.2 presentes y bien renderizadas en los tres motores, primero con contenido de prueba (A42-1).
- Que el contenido del cliente entre sin programar: el A.7 cubre "Ajustes menores de contenido —textos, colores e
  imágenes— que no requieran programación nueva" (`NSV-2026-001-v8.pdf:23:ANEXO A, Sección A.7`).
- Un mapa de cobertura que no genere costo por visita.

**Non-Goals:**

- La medición, los buscadores, la velocidad y el candado (`medicion-de-la-landing`).
- El texto de los documentos legales (`textos-legales`).
- Cargar o trazar las zonas (`plazas-y-zonas`).
- Diseñar las pantallas de las secciones: resuelta (pregunta 1).
- Páginas por ciudad, ficha de Google Maps o cualquier contenido fuera del territorio inicial
  (`NSV-2026-001-v8.pdf:4:Cláusula I`).

## Decisions

No hay decisión técnica nueva: el cambio aplica ADR-0003, ADR-0004, ADR-0009, ADR-0011 y ADR-0013, sin repetirlos.

La spec toma cuatro lecturas mínimas del A.2. No son decisiones técnicas ni vienen del contrato; Arthur las puede
cambiar sin tocar los requisitos:

- El botón principal del encabezado lleva a la sección "Descarga la app", porque los enlaces de las tiendas no existen
  hasta publicar (landing-E04).
- Con más de 5 testimonios activos se muestran 5, en el orden del panel; con menos de 3, se completan con marcadores
  de posición (landing-E15 y landing-E16).
- La sección de prensa sigue presente sin logos activos, para que las 12 secciones estén siempre (landing-E18).
- Un botón de tienda sin enlace se muestra sin enlace, nunca con uno vacío (landing-E09).

Al implementar se tomó una quinta lectura, del mismo tipo (2026-11-22, por delegación de Arthur, C-236):

- **Los campos de los formularios, mientras DD-119 no los diga:** lo mínimo para responder, todos obligatorios. El de
  interés pide el nombre de quien escribe, el de la lavandería y el correo; el de contacto, nombre, correo y mensaje
  (landing-E10, landing-E21 y landing-E23). Un envío completo no se rechaza; mientras landing-E22 siga bloqueado, no se
  entrega, no se guarda ni se registra, y la portada le dice a quien lo envió que no se entregó, en vez de una
  confirmación que no sería cierta.

### Cómo quedó implementado (tareas 1.1 a 5.3, 2026-11-22)

Sin decisiones técnicas nuevas: cada punto aplica un ADR o responde una pregunta abierta de abajo con su opción sin
dependencias.

- **Portada:** `GET /` sirve `portada.index`, con el diseño base `publico.layout`, donde `medicion-de-la-landing` pone
  su contenedor. Cada sección lleva `data-seccion`; los títulos de las secciones, los cuatro pasos y los títulos de los
  seis pilares son los del contrato y viven en la vista.
- **Contenido de EL CLIENTE:** en `servidor/config/landing.php`, con sus variables `LANDING_*` en `.env.example`.
  Vacío, cada lugar muestra un marcador "Contenido de prueba: …", marcado con `data-de-prueba` para que la
  acreditación (landing-E03) compruebe que no queda ninguno. El nombre es `APP_NAME`; el logo, `LANDING_LOGO`, con el
  símbolo aprobado (C-63) en `public/marca/logotipo.png`; el WhatsApp, `SOPORTE_WHATSAPP`, el mismo de la app.
- **Tokens (ADR-0013):** `servidor/resources/css/tokens.css` sale de `tokens.json` con la salida CSS de
  `herramientas/tokens/generar.js`, el mismo script que genera `theme.ts`: variables `--ds-*`, solo modo claro y, con
  movimiento reducido, las duraciones en 0. Lo vigila el trabajo `tokens` de la integración continua, que falla si el
  archivo no es el que genera `tokens.json` (2026-11-24).
- **Letras:** Nunito y Source Sans 3, en woff2 del subconjunto latino, servidas desde `servidor/resources/fuentes/`
  con su licencia SIL Open Font License: ninguna petición a otro servidor.
- **Iconos:** los de Heroicons que ya trae Filament. Los de los pilares para usuarios flotan y se quedan quietos con
  movimiento reducido.
- **Plantilla (pregunta 2):** no se compró; se siguió la opción B de ADR-0011, sin plantilla.
- **Mapa (pregunta 3):** SVG propio, dibujado en el servidor con las zonas activas de las plazas activas, simplificadas
  por PostGIS (`ST_SimplifyPreserveTopology`, 0.0005 grados) y proyectadas en equirectangular. Acercar, alejar,
  arrastrar y el nombre de la zona, en `resources/js/portada.js`, sin dependencias.
- **Preguntas frecuentes (pregunta 4):** con la lectura mínima de ADR-0011, en un archivo JSON cuya ruta es
  `LANDING_PREGUNTAS_FRECUENTES`; se muestran a lo más ocho, en `details` y `summary`.
- **Prensa (tarea 4.2, 2026-11-24):** los logos se editan en el panel, en el recurso `LogosPrensa`, solo por la
  administración (`Autorizacion::logosPrensaDelPanel()`; landing-E19): el nombre del medio, que es también el texto
  alternativo, la imagen, el interruptor de activo y el orden, que se cambia arrastrando. No se borran: ningún
  escenario lo pide, y un logo que ya no debe salir se retira desactivándolo (landing-E17); el borrado de los
  testimonios sí tiene el suyo, landing-E26. La imagen se sube al disco privado de fotos del entorno
  (`config('fotos.disco')`), en la carpeta `prensa`, con los tipos y el peso de `config/fotos.php`: hoy PNG, JPG o WEBP
  de hasta 5 MB, sin SVG. Eso es una restricción técnica del disco de fotos, no de landing-E17, y limita el formato en
  que EL CLIENTE entrega sus logos (DD-117). La portada muestra los activos, en su orden, y recibe cada imagen por la
  ruta pública `GET /prensa/{archivo}` (`portada.prensa.imagen`), que la adenda del 2026-11-24 de ADR-0006 abre solo
  para los logos de prensa: la lee del disco y la entrega con su tipo y con caché pública, sin URL firmada, sin sesión
  ni cookies, y responde 404 si el logo no existe o no está activo. La ruta lleva el nombre del archivo, nunca la llave
  interna (ADR-0016, regla 5), y la declara `Autorizacion::rutasSinSesion()`. La portada solo muestra los logos con la
  imagen suelta en la carpeta `prensa`, que es lo único que la ruta entrega; no revisa que cada archivo siga en el
  disco, porque con `fotos_s3` costaría una llamada al bucket por logo: el panel es la única vía de alta. Cada logo
  ocupa una caja fija, de tres veces su alto, para que la fila no se reacomode al cargar. Sin logos activos, la sección
  sigue con el texto de `LANDING_PRENSA_SIN_LOGOS` (`landing.prensa.sin_logos`) o, sin él, con su marcador de
  contenido de prueba (landing-E18). No enlaza a la nota del medio: el concilio del 2026-11-24 lo descartó
  (`acta-2026-11-20-modelo-y-pasarela.md`, decisión 63). Los logos reales son de EL CLIENTE (DD-117) y solo bloquean
  el contenido y la acreditación (tarea 7.1).
- **Pie legal (tarea 6.1, 2026-11-22):** lo pone el diseño base, `publico.layout`, con `publico/pie-legal.blade.php`,
  así que lo llevan la portada y las páginas de textos-legales, y cualquier página pública que llegue después
  (landing-E24). Sus dos enlaces van por el nombre de la ruta, `textos-legales.aviso-de-privacidad` (`/aviso-de-privacidad`)
  y `textos-legales.terminos` (`/terminos`), con el título de cada documento como texto. El aviso de derechos de autor
  es "© {año} {titular}. Todos los derechos reservados.", con `LANDING_DERECHOS_TITULAR` y `LANDING_DERECHOS_ANIO`
  (`landing.derechos`; landing-E25). Una lectura del mismo tipo que las de arriba, por delegación de Arthur (C-236):
  mientras falte el titular o el año (DD-120), fuera de producción el pie lleva un marcador de contenido de prueba; en
  producción no lleva ninguno ni inventa el aviso, porque textos-legales-E02 no admite texto de prueba en las páginas de
  los documentos. Los demás marcadores de la portada no cambian.
- **Pruebas de navegador (pregunta 7):** Playwright desde Node, 1.57.0, en `servidor/tests/Browser/**/*.spec.js`, con
  `npx playwright test` desde `servidor/`. Su servidor de prueba compila los estilos, deja la base de pruebas desde cero
  con `tests/Browser/Soporte/PortadaDePrueba.php` y la sirve en un puerto propio. La integración continua las corre en
  el trabajo `landing-navegadores` de `.github/workflows/ci.yml`, en Chromium, Firefox y WebKit (2026-11-24).

## Tablas y migración

Este cambio crea dos tablas del modelo, que entraron a `vault/arquitectura/modelo-de-datos.md`, sección "Landing", con
"Nace en" `landing` (ADR-0016):

- `testimonios` (tarea 4.0): autor, texto, interruptor de activo (`false` por omisión) y orden.
- `logos_prensa` (tarea 4.0b, 2026-11-24; `acta-2026-11-20-modelo-y-pasarela.md`, decisión 63): el nombre del medio,
  que es también el texto alternativo, la imagen como ruta en el disco privado de fotos (adenda del 2026-11-24 de
  ADR-0006), interruptor de activo (`false` por omisión) y orden, calcada de `testimonios`.

Sin `id_plazas`, porque la portada es una para todo el sitio: las dos están en la lista `sin_plaza` de
`servidor/config/modelo.php`, como decisión visible (modelo-E05). Sin el trigger `impedir_cambio()`.

Lee, sin escribir:

| Tabla | Columnas que usa | Para qué | Nace en |
|---|---|---|---|
| `plazas` | `id_plazas`, `activa` | landing-E14: solo se dibujan las zonas de las plazas activas | `plazas-y-zonas` |
| `zonas` | `id_zonas`, `id_plazas`, `nombre`, `poligono`, `activa` | landing-E12: el polígono simplificado y el nombre de cada zona activa | `plazas-y-zonas` |
| `usuarios`, `rol_usuario`, `roles` | `rol_usuario.id_usuarios`, `rol_usuario.id_roles`, `roles.codigo` | landing-E19: solo `administracion` edita, con el componente de `autorizacion-por-rol` | `registro-e-inicio-de-sesion` |

Lo que esta spec guarda y el modelo todavía no tiene:

- **Los formularios de interés y de contacto** (landing-E22). Si van por correo, como recomienda DD-119, no guardan
  nada. Si se guardan para verlos en el panel, la tabla no existe en el modelo (pregunta abierta 5).
- **Preguntas frecuentes y demás textos.** Salen de la configuración y de un archivo de contenido (pregunta abierta 4).
  Si algún día se vuelven editables en Filament, tampoco tienen tabla en el modelo.
- **Los enlaces de las tiendas, el correo, el número de WhatsApp Business y el titular del aviso de derechos de autor**
  salen de la configuración, no de una tabla.

## Risks / Trade-offs

- [La landing comparte servidor con la API] → si el servidor cae, caen las dos. Lo aceptó ADR-0011.
- [Los polígonos del Estado de Durango completo pesan y bajan la velocidad en teléfono] → se simplifica la geometría en
  el servidor antes de mandarla; el umbral de 70 lo vigila `medicion-de-la-landing` (A42-4).
- ["Cero gestión de cobranza" puede prometer algo que el modelo de dinero no haga] → el título es del contrato; el
  texto que lo explica espera a DD-013.
- [Un marcador de posición puede leerse como una opinión real] → su texto lo decide EL CLIENTE (DD-117).
- [Los formularios recaban datos personales] → el aviso de privacidad y los consentimientos son de EL CLIENTE
  (`NSV-2026-001-v8.pdf:10:Cláusula X`); qué aviso acompaña al formulario va en DD-119.
- [El cliente marcó "No" en el mapa de cobertura en el formulario en papel del 2026-11-05, sin confirmar] → se
  construye porque el contrato lo pide; quitarlo sería Orden de Cambio.
- [Con `fotos_s3`, cada imagen de prensa que se pide sin caché hace varias llamadas al bucket: si existe, su tipo, su
  tamaño y su lectura] → la caché pública de un día las amortigua. Se mide cuando exista el bucket (DD-070); si pesa,
  se guardan el tipo y el tamaño al subir la imagen.
- [Al cambiar la imagen de un logo de prensa, el archivo anterior se queda en el disco privado, en la carpeta
  `prensa`] → no se filtra, porque la ruta solo entrega la imagen de un logo activo, pero los archivos sin logo se
  acumulan. La regla para retirarlos no se decide en la tarea 4.2.

## Open Questions

1. **¿Quién diseña las 12 secciones?** No hay mocks ni componentes del design system para ellas. Lo decide Arthur.
   *Resuelta el 2026-11-24 con la opción a* (`acta-2026-11-20-modelo-y-pasarela.md`, decisión 64; concilio por
   delegación de Arthur, C-236 y C-245): el diseño actual, que Claude armó con `tokens.json` y las reglas de
   `componentes.md` por delegación de Arthur (C-236), es el que se entrega, y EL CLIENTE solo pone el contenido
   (DD-117). No se hacen mocks de la landing antes de acreditar. El A.4.2 pide las 12 secciones "presentes y
   correctamente renderizadas" (`NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.2`), no un diseño aprobado; el A.1 no
   incluye maquetas de la landing (`NSV-2026-001-v8.pdf:14:ANEXO A, Sección A.1`), y las preferencias estéticas
   subjetivas no documentadas en el ANEXO A no son rechazo válido (`NSV-2026-001-v8.pdf:7:Cláusula IV`). El envío a EL
   CLIENTE sigue siendo de Nadir.
2. **¿Se compra una plantilla?** ADR-0011 lo permite solo si su licencia completa, leída con sesión iniciada, permite
   entregar el sitio, y esa licencia se archiva en el vault. Si no, se sigue sin plantilla. *Resuelta el 2026-11-22:*
   sin plantilla (opción B).
3. **Cómo se dibujan los polígonos.** Un SVG propio a partir del GeoJSON de las zonas no agrega dependencias y cabe en
   ADR-0009. Una librería de mapas para web o un fondo de calles de un tercero, aunque sea gratuito, es una dependencia
   nueva: **necesita ADR**. *Resuelta el 2026-11-22:* SVG propio.
4. **Preguntas frecuentes y demás textos: ¿archivo de contenido o editables en Filament?** ADR-0011 solo hace
   editables los testimonios y la prensa. Hacer editable lo demás **necesita que Arthur amplíe ADR-0011**. *Resuelta el
   2026-11-22 con la lectura mínima:* archivo de contenido y configuración.
5. **El destino de los formularios** (BR-41, DD-119). Si van por correo, usan el proveedor de ADR-0014, que
   sigue abierto (DD-071). Si se guardan para verlos en el panel, es una vista que el Módulo D no enumera
   (`NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3`).
6. **Protección contra envíos automáticos en los formularios.** El contrato no la nombra. Si se agrega, **necesita
   ADR**. *Sigue abierta:* no se agregó ninguna.
7. **Las pruebas de navegador.** Playwright desde Pest o desde Node. Las tareas usan `servidor/tests/Browser/Landing/`;
   la ruta se ajusta al confirmarlo (`vault/formas-de-trabajo/ciclo-openspec-tdd.md`, "Herramientas de prueba", que es
   propuesta). *Resuelta el 2026-11-22:* desde Node, con la dependencia de desarrollo `@playwright/test` que ya nombra
   `proposal.md`; las pruebas son `*.spec.js`. *Resuelta del todo el 2026-11-24:* la integración continua las corre en
   el trabajo `landing-navegadores`, en los tres motores.
