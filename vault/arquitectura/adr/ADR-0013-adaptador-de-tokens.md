---
estado: vigente
grado: D
fuente: acta-2026-11-13-cierre-de-la-fase-6.md C-152; design-system.md (B); tokens.json (B); acta-2026-11-12-traspaso-del-proyecto.md C-141 (C); ADR-0002 y ADR-0003 (D)
fecha: 2026-11-13
dueño: Arthur Naranjo
adr: ADR-0013
supersede:
---
# ADR-0013 — Un script propio convierte `tokens.json` para la app y para la web

## Contexto

- **La fuente única de colores, tipografías, espacios, radios, sombras y tiempos** es
  `design-system/tokens.json` (B). Su formato es propio: claves en español, `valor` y `$comentario`.
- **La consumen dos tecnologías:**
  - la app en React Native (`ADR-0002`);
  - la web en Laravel: el panel de Filament y, si así se decide en el ADR-0011, la landing (`ADR-0003`).
- **La v1 lleva solo modo claro (C-141).** El bloque `color.semantico.oscuro` se conserva sin usarse.
- **Accesibilidad:** el design system fija contrastes que no se renegocian, como Agua Profundo `#297899` a 4.51:1.
  Si un valor se copia a mano mal, ese contraste se rompe.

## Criterios y pesos

Los de la matriz `vigente` (C-142).

## Alternativas evaluadas

| Alternativa | Familiaridad (30) | Velocidad (25) | Proveedor (15) | Tiempo real (10) | Costo (10) | Multi-plaza (10) | **Total** |
|---|---|---|---|---|---|---|---|
| **A. Script propio** que lee `tokens.json` y genera `theme.ts` para la app y variables CSS para la web | 4 | 5 | 5 | 5 | 5 | 5 | **94** |
| B. Style Dictionary (Apache-2.0) | 2 | 3 | 4 | 5 | 5 | 5 | **72** |
| C. Copiar los valores a mano en cada proyecto | 5 | 4 | 5 | 5 | 5 | 5 | **94**, pero se descarta |

**C empata en puntos y se descarta.** Copiar a mano es justo lo que rompe el contraste de 4.51:1 sin que nadie lo
note: el valor diverge en silencio.

**B** obliga a convertir el formato propio de `tokens.json` al suyo y suma una herramienta que aprender, para un
archivo de este tamaño.

## Decisión

Un script corto en Node, porque la app ya lo tiene, lee `tokens.json` y genera dos salidas:

1. **`theme.ts` para la app,** solo con el modo claro y con nombres semánticos (`color.semantico.claro`) además de
   los de marca;
2. **un archivo de variables CSS para la web,** que consumen el tema de Filament y la landing.

Las salidas se generan, no se editan a mano, y se versionan junto al código que las usa. Una prueba revisa que los
pares de contraste del design system sigan cumpliendo después de generar.

La aprobó Arthur Naranjo el 2026-11-13 (`acta-2026-11-13-cierre-de-la-fase-6.md`, C-152), con la opción que recomendó Claude.

## Consecuencias

- **Positivas:**
  - Un cambio de color se hace en un solo lugar.
  - El contraste se comprueba solo.
  - Agregar el modo oscuro después es generar el segundo bloque.
- **Negativas:**
  - Hay que mantener un script de unas decenas de líneas.
- **Condiciona:**
  - La estructura de repositorios de la Fase 7: dónde vive el script y cómo llegan las salidas a cada proyecto.

## Criterio de reversión

Se reabre si `tokens.json` cambia de formato a uno estándar (Design Tokens del W3C). Entonces Style Dictionary
dejaría de pedir conversión.

## Costo estimado de revertir

Bajo: es un script aislado.

## Trazabilidad

- **Obligaciones:** O-02 (tokens) y O-03 (manual).
- **Notas:** `design-system.md` y `tokens.json`.
- **ADRs relacionados:** ADR-0002, ADR-0003 y ADR-0011.

## Adenda del 2026-11-21: las fuentes de la marca en la app

Las familias de `tokens.json`, Nunito para titulares y Source Sans 3 para cuerpo (`vault/marca/decision-visual.md`,
vigente), se cargan en la app con `expo-font`, que es parte del SDK de Expo (ADR-0002), y los archivos de los paquetes
`@expo-google-fonts/nunito` y `@expo-google-fonts/source-sans-3`, con licencia SIL Open Font License (grado E,
`SIN VERIFICAR` en esta sesión). La app no muestra su primera pantalla hasta que cargan, y si fallan sigue con la letra
del sistema. La monoespaciada del token se sustituye en la app por la del sistema. Es DD-131, punto 1; la tomó Claude
por delegación de Arthur Naranjo (C-236).
