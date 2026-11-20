---
estado: vigente
grado: C
fuente: pedido de Nadir del 2026-11-20 en sesión (prompt de revisión UX/UI y orden posterior de generar maquetas; C-132 a C-136 en acta-2026-11-20-decisiones-de-maquetas)
fecha: 2026-11-20
dueño: Nadir Hadad Navarrete
---
# Encargo — Revisión UX/UI y maquetas convergidas del Módulo A

Documento que gobierna la revisión y la producción de las maquetas. Se archiva conforme a CLAUDE.md §0. Se
archivó **después** de la primera pasada de la revisión, no antes: el prompt llegó con cuatro marcadores sin
rellenar (`{RUTA_ARTEFACTO}`, `{RUTA_MOCKS_P4}`, `{NIVEL_DETALLE}`, `{FOCO_PRIORITARIO}`).

## Qué pidió Nadir

1. **Revisión** (primer mensaje). Con rol de Lead UX/UI móvil: auditar el Módulo A · Usuario, F-A01 a F-A17, sobre
   cuatro propuestas de maqueta (P1 interactivas; P2 imágenes de GPT 01 y 03; P3 identidad de la Fase 3; P4 37
   pantallas fijas), en tres entregables: (A) auditoría de lógica de negocio y fricción por flujo, (B) convergencia
   de las cuatro propuestas en una dirección visual, (C) backlog priorizado con las decisiones abiertas aparte.
2. **Dónde están las pantallas** (segundo mensaje): el lienzo de diseño "Lavandi" en claude.ai, que reúne P1 a P4.
3. **Maquetas** (tercer mensaje): con sus respuestas a las decisiones abiertas (C-132 a C-135) y su acuerdo con el
   resto de las recomendaciones (C-136), generar los mocks.

## Reglas que puso el encargo

- Solo se afirma lo visto, citando pantalla o archivo; lo no visible se marca `NO AUDITABLE`.
- Etiquetas `[VISTO]`, `[INFERIDO]`, `[RECOMENDACIÓN]`, `[REQUIERE DECISIÓN]`.
- Sin métricas de uso ni datos de negocio inventados; se usan marcadores (`[FOLIO]`, `$ —`).
- Un mock orienta; no decide ni amplía el contrato. Las decisiones abiertas no se resuelven por cuenta propia.
- Alcance: solo Módulo A. Idioma español, tono directo.
- Criterios: una mano y zona del pulgar, menos toques, un objetivo por pantalla, objetivos táctiles ≥ 44, texto
  corrido a 4.5:1, paridad iPhone/Android, identidad "El tiempo de vuelta" con tipografías vigentes Nunito y
  Source Sans 3.

## Qué se produce

| Pieza | Dónde queda |
|---|---|
| Informe de la revisión (dos pasadas) | En la sesión del 2026-11-20. No se archivó en el vault |
| Maquetas convergidas, "Propuesta 5" | Lienzo "Lavandi" en claude.ai, artboards `P5-*.dc.html`, debajo de la Propuesta 4 |
| Decisiones de Nadir | [[acta-2026-11-20-decisiones-de-maquetas]] |
| Pasarelas candidatas (grado E) | `vault/arquitectura/pasarelas-candidatas.md` |

## Límites

- Las maquetas no son código de producto (CLAUDE.md §5) ni se envían a EL CLIENTE sin aprobación de Nadir.
- Donde C-133 choca con el contrato (Conekta, RN-10 y RN-28), la maqueta dice "pasarela por definir" y no nombra
  proveedor.
- Ningún monto, peso ni plazo de la maqueta es propuesta de negocio: van como `$ —`, `— kg` o entre corchetes.
