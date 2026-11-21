---
estado: vigente
grado: D
fuente: acta-2026-11-13-cierre-de-la-fase-6.md C-150; restricciones-no-negociables.md RN-25 (A); brecha-ejecutivo-tecnico.md BR-08 y BR-11 (B); acta-2026-09-29-sesion-tecnica-y-plantilla-landing.md C-95 (C); matriz-de-criterios-stack.md, fila de ADR-0011 (B); verificaciones-fase-6.md, sección del 2026-11-13 (E); ADR-0003 y ADR-0009 (D)
fecha: 2026-11-13
dueño: Arthur Naranjo
adr: ADR-0011
supersede:
---
# ADR-0011 — La landing vive en el mismo Laravel, con plantilla solo si su licencia permite entregarla

## Contexto

- **Qué pide el contrato (A, RN-25, A.4.2):**
  - 12 secciones que se vean bien en Chrome, Safari y Firefox;
  - HTTPS;
  - PageSpeed móvil de 70 o más;
  - dominio y alojamiento del cliente. El alojamiento "puede iniciar en un plan gratuito".
- **Lo que precisa la brecha (BR-11):**
  - GA4, Meta Pixel, GTM y Search Console;
  - que el cliente pueda editar los testimonios y los logos de prensa "activables";
  - que PageSpeed se mida **con la medición cargada**.
- **La plantilla (C-95):** Nadir quería comprar una plantilla, a evaluar aquí y no antes. Las condiciones están en la
  matriz:
  - una licencia que permita entregar el sitio al cliente (Cláusula IX, BR-08);
  - la misma tecnología que el resto;
  - las 12 secciones;
  - PageSpeed con la medición cargada;
  - edición de testimonios y prensa.

  Se estimó que ahorra de 15 a 20 horas.
- **Licencias (E):**
  - La licencia Regular de ThemeForest permite crear un producto final para un cliente y transferírselo.
  - La de Tailwind Plus, según fragmentos de su FAQ, permite "un sitio a la medida para un solo cliente". **No se
    pudo leer el texto completo.**
- **El dominio** (`{{DOMINIO}}`, O-11) no se ha comprado.
- **La ventana de la landing** son las semanas 10 a 12, que corren ahora.

## Criterios y pesos

Los de la matriz `vigente` (C-142).

## Alternativas evaluadas

| Alternativa | Familiaridad (30) | Velocidad (25) | Proveedor (15) | Tiempo real (10) | Costo (10) | Multi-plaza (10) | **Total** |
|---|---|---|---|---|---|---|---|
| **A. En el mismo Laravel (Blade y Tailwind), partiendo de una plantilla comprada cuya licencia permita entregarla, con contenido editable desde Filament** | 5 | 5 | 4 | 5 | 4 | 5 | **95** |
| B. En el mismo Laravel, sin plantilla | 5 | 4 | 5 | 5 | 5 | 5 | **95** |
| C. Sitio estático aparte, en un hosting gratuito | 4 | 3 | 4 | 5 | 5 | 5 | **81** |

**A y B empatan.** El desempate son las 15 a 20 horas que ahorra la plantilla (C-95), que en este proyecto son lo que
falta. Por eso se recomienda A, **con una condición:** la licencia se lee completa y se archiva antes de comprar.

**C** obliga a montar un sistema de contenido aparte para que el cliente edite testimonios y prensa.

## Decisión

- **La landing es parte de la aplicación Laravel del `ADR-0003`:** vistas Blade con Tailwind, servidas en el dominio
  del cliente.
- **Testimonios, logos de prensa y su interruptor de activo** se editan desde Filament.
- **La medición** (GA4 y Meta Pixel) entra por un contenedor de GTM. Search Console se verifica por DNS.
- **PageSpeed móvil de 70 o más** se mide con GTM cargado, que es como lo pide BR-11.
- **El mapa de cobertura** usa polígonos propios (`ADR-0009`).
- **La plantilla:** se puede comprar una, en Tailwind Plus o ThemeForest, solo si su licencia completa, leída con
  sesión iniciada, permite entregar el sitio al cliente. Esa licencia se archiva en el vault. Si no hay plantilla que
  lo cumpla, se sigue la opción B.

La aprobó Arthur Naranjo el 2026-11-13 (`acta-2026-11-13-cierre-de-la-fase-6.md`, C-150), con la opción que recomendó Claude.

## Consecuencias

- **Positivas:**
  - Una sola aplicación y un solo despliegue.
  - El cliente edita desde el mismo panel.
  - No hay un sistema de contenido más.
- **Negativas:**
  - La landing comparte servidor con la API: si el servidor cae, caen las dos.
  - Una plantilla comprada trae su propio estilo, y hay que adaptarlo a los tokens (`ADR-0013`).
- **Condiciona:**
  - La compra del dominio (O-11).
  - ADR-0012: la landing en prueba y en producción.
  - ADR-0013: las variables CSS de los tokens.

## Criterio de reversión

Se reabre si la landing no llega a PageSpeed móvil de 70 con la medición cargada después de optimizar imágenes y
cargar GTM en diferido. En ese caso se pasa a la opción C, con un sitio estático.

## Costo estimado de revertir

Bajo o medio. Las vistas se exportan a HTML estático, pero la edición de testimonios necesitaría otra vía.

## Trazabilidad

- **Obligaciones:** O-07 a O-11.
- **Restricciones:** RN-25, BR-08 y BR-11.
- **Antecedente:** C-95.
- **ADRs relacionados:** ADR-0003, ADR-0009, ADR-0012 y ADR-0013.
