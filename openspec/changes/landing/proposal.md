## Why

El contrato obliga a un "Sitio web de presentación de la plataforma, optimizado para conversión (descarga de la app)"
(`NSV-2026-001-v8.pdf:14:ANEXO A, Sección A.2`) con 12 secciones (`NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2`). Se
acepta con "Las 12 secciones descritas en A.2 presentes y correctamente renderizadas en Chrome, Safari y Firefox"
(`NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.2`), y la landing "en dominio activo" es parte del hito del pago 2
(`NSV-2026-001-v8.pdf:8:Cláusula VI`). Son F-L01 a F-L12 del inventario funcional, hito H10 del plan de sprints.

## What Changes

- Una portada pública en `servidor/`, con las 12 secciones del A.2 en su orden (F-L01 a F-L12), que se ve bien en
  Chrome, Safari y Firefox, en escritorio y en teléfono (A42-1).
- Encabezado con logo, tagline, botón principal de descarga e imagen con el mockup de la app; paso a paso
  Pide → Recogemos → Lavamos → Entregamos; tres pilares para usuarios con iconos animados.
- Botones de App Store y Google Play, con su enlace tomado de la configuración y sin enlaces rotos antes de publicar.
- Invitación a lavanderías con formulario de interés, y los tres pilares para socios.
- Mapa interactivo de cobertura con las zonas activas del territorio inicial, con polígonos propios y sin mapas de pago
  (ADR-0009).
- Testimonios y logos de prensa editables desde el panel, con su interruptor de activo (ADR-0011).
- Preguntas frecuentes en acordeón, de 6 a 8.
- Formulario de contacto, correo y WhatsApp Business de la configuración, y pie legal con los enlaces al aviso de
  privacidad, a los términos y el aviso de derechos de autor.
- Todo texto del cliente entra como contenido configurable, nunca escrito en la vista. La marca y el dominio son
  Lavandi y `lavandi.mx`.

**Qué NO cambia**

- La medición, la preparación para buscadores, la velocidad y el candado: van en `medicion-de-la-landing` (F-T16 y
  F-T17, A42-2 a A42-4).
- El texto del aviso de privacidad y de los términos: va en `textos-legales` (F-T18). Aquí solo el pie que los enlaza.
- Las zonas de servicio: las carga `plazas-y-zonas`. La landing solo las dibuja.
- Las 12 plantillas de redes sociales, parte del mismo entregable (`NSV-2026-001-v8.pdf:3:Cláusula I`): no son código
  (A41-3, DD-050, DD-085).
- Páginas por ciudad y ficha del negocio en Google Maps: son parte de abrir una ciudad nueva
  (`NSV-2026-001-v8.pdf:37:ANEXO C, Sección C.3`); para el territorio inicial, el A.2 no las pide.
- No se inventa ningún texto del cliente (`NSV-2026-001-v8.pdf:6:Cláusula III`, inciso B): mientras no llegue, cada
  sección lleva contenido de prueba marcado como tal y A42-1 no se acredita.
- La sección 7, Cobertura, se construye aunque el formulario en papel del 2026-11-05 la marque "No"
  (`vault/cliente/comunicaciones/2026-11-05-reporte-del-formulario.md:49`): quitarla también es cambio de alcance
  (`NSV-2026-001-v8.pdf:8:Cláusula IV`).

**Fuera del contrato** (posibles Órdenes de Cambio, `NSV-2026-001-v8.pdf:8:Cláusula IV`)

- Prometer que se ve en un mapa dónde va el pedido (video del 2026-11-14): el contrato pide seguimiento por estados
  (`NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3`, Módulo A #12). DD-077.
- Prometer un beneficio por llevar más prendas (video del 2026-11-14). DD-076.
- Publicar precios, un tabulador por prenda o un precio único con envío incluido (guía de la reunión, libreta y kit
  para sumar lavanderías): el A.2 nombra el pilar "precio", no una lista, y el A.3 dice "precios por kilo". DD-010.
- Anunciar lavado de calzado (libreta, C-83). DD-046.
- Anunciar efectivo contra entrega (guía de la reunión). DD-056.
- Un distintivo de "lavandería fundadora" en la sección de socios (kit para sumar lavanderías; guía, pregunta 14). Sin
  fila.

## Capabilities

### New Capabilities

- `landing`: las 12 secciones públicas del sitio, su contenido editable, el mapa de cobertura y sus formularios.

### Modified Capabilities

- Ninguna.

## Impact

- `servidor/`: rutas web públicas; vistas Blade con Tailwind y las variables CSS que genera el adaptador de tokens
  (ADR-0011, ADR-0013); recursos de Filament para testimonios y prensa (ADR-0011); lectura de las zonas activas con
  PostGIS (ADR-0004).
- `movil/`: nada.
- Base de datos: testimonios y logos de prensa, con su interruptor de activo y su orden, necesitan dónde guardarse, y
  el modelo de datos todavía no tiene esas tablas (`vault/arquitectura/modelo-de-datos.md`, "Lo que no tiene tabla
  todavía"); ver `design.md`, "Tablas y migración". Las plazas y las zonas se leen de `plazas-y-zonas`.
- Dependencias: Playwright para las pruebas en Chromium, WebKit y Firefox
  (`vault/formas-de-trabajo/ciclo-openspec-tdd.md`); una plantilla comprada solo si su licencia completa permite
  entregarla (ADR-0011). Ningún servicio de mapas de pago (ADR-0009).
- ADRs: ADR-0003, ADR-0004, ADR-0009, ADR-0011 y ADR-0013.
- Depende de `plazas-y-zonas` (las zonas), `autorizacion-por-rol` (quién edita en el panel) y `textos-legales` (las
  páginas que enlaza el pie). `medicion-de-la-landing` mide los botones y los formularios de aquí.
