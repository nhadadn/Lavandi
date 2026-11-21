## Why

El contrato pide instalar "Google Analytics, el medidor de Facebook e Instagram (Meta Pixel), el administrador de
etiquetas de Google y la consola de búsqueda de Google", y preparar el sitio para buscadores con títulos, mapa del sitio
e información estructurada (`NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2`), optimizado "con prioridad en teléfonos
celulares" (`NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.2`). Tres criterios de la landing dependen de esto: GA4 y Meta
Pixel registran eventos, el candado activo en la dirección acordada y 70 o más en PageSpeed Insights
(`NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.2`). Son F-T16 y F-T17 del inventario, hito H10.

## What Changes

- Un solo contenedor de Google Tag Manager en cada página pública, por el que entran GA4 y Meta Pixel; su identificador
  sale de la configuración de cada entorno (ADR-0011).
- Registro de cada visita, del toque en los botones de descarga y del envío completo de los formularios, sin datos
  personales en los eventos (A42-2).
- Título y descripción propios en cada página pública.
- Mapa del sitio con todas las páginas públicas, declarado en el archivo de robots, y verificación de Search Console por
  DNS (ADR-0011).
- Información estructurada que dice de qué trata el negocio y en qué zona opera: las plazas activas del territorio
  inicial.
- Imágenes y contenido optimizados para teléfono, con Lighthouse en la integración continua como alerta temprana y
  PageSpeed Insights sobre la dirección publicada para acreditar (A42-4).
- La portada en `lavandi.mx` con el candado activo y http redirigido a https (A42-3).

**Qué NO cambia**

- Las 12 secciones y su contenido: son del cambio `landing`. Aquí solo se miden.
- Las cuentas de medición, el dominio y el alojamiento son de EL CLIENTE y a su nombre
  (`NSV-2026-001-v8.pdf:34:ANEXO B, Sección B.11`; `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.2`). EL PRESTADOR "asiste
  en su configuración y puesta en marcha" con accesos de colaborador.
- Reportes de métricas o análisis de desempeño: el A.8 los excluye más allá de los indicadores del A.5
  (`NSV-2026-001-v8.pdf:25:ANEXO A, Sección A.8`).
- Pauta publicitaria, ficha del negocio en Google Maps y páginas por ciudad: no están en el A.2
  (`NSV-2026-001-v8.pdf:25:ANEXO A, Sección A.8`; `NSV-2026-001-v8.pdf:37:ANEXO C, Sección C.3`).
- La redirección a https del panel y de la API: va en `publicacion` (SEG-1). Aquí solo la de la landing.

**Fuera del contrato** (posibles Órdenes de Cambio, `NSV-2026-001-v8.pdf:8:Cláusula IV`)

- Medir los leads para la comisión del 30 % de C-72: es un acuerdo aparte del contrato, sin documento. DD-044.
- Un aviso o gestor de consentimiento de cookies: el A.2 no lo nombra. Si el aviso de privacidad del cliente lo exige,
  es posible Orden de Cambio. Va en DD-118.

## Capabilities

### New Capabilities

- `medicion`: la medición de visitas y acciones, la preparación para buscadores, la velocidad en teléfono y el candado
  de la landing.

### Modified Capabilities

- Ninguna.

## Impact

- `servidor/`: el contenedor de GTM en el diseño base de las vistas públicas; título y descripción por página; rutas del
  mapa del sitio y del archivo de robots; el bloque de información estructurada; la redirección a https de la landing;
  la optimización de imágenes.
- `movil/`: nada.
- Base de datos: nada nuevo. La información estructurada lee `plazas` y `zonas`, de `plazas-y-zonas` (`design.md`,
  "Tablas y migración").
- Dependencias: Lighthouse CI en la integración continua (`vault/formas-de-trabajo/integracion-continua.md`);
  Playwright para interceptar las llamadas a GA4 y a Meta Pixel (`vault/formas-de-trabajo/ciclo-openspec-tdd.md`).
  GTM, GA4, Meta Pixel y Search Console son servicios de terceros en cuentas del cliente.
- ADRs: ADR-0011 y ADR-0012.
- Depende de `landing` (las páginas que se miden) y de `textos-legales` (dos de las páginas públicas).
