---
estado: vigente
grado: D
fuente: acta-2026-11-12-traspaso-del-proyecto.md C-146; matriz-de-criterios-stack.md (pesos C-142); modelo-multi-plaza.md (B); verificaciones-fase-6.md, sección del 2026-11-12 (E)
fecha: 2026-11-12
dueño: Arthur Naranjo
adr: ADR-0004
supersede:
---
# ADR-0004 — La base de datos es PostgreSQL

## Contexto

- **Qué pide el contrato (A):**
  - el modelo multi-plaza: crecer a otras plazas sin rediseñar la base de datos (O-26, `modelo-multi-plaza.md`);
  - búsqueda de lavanderías por cercanía y radio de servicio;
  - mapa de calor por colonia en el panel;
  - indicadores con exportación;
  - base de datos y respaldos dentro del rango del ANEXO B.4: de 259 a 864 MXN al mes la base de datos y de 86 a
    259 los respaldos (RN-22).
- **El backend:** Laravel (`ADR-0003`), que con Eloquent trabaja igual con MySQL y con PostgreSQL.

## Criterios y pesos

Los de la matriz `vigente` (C-142), con la escala del `ADR-0002`. Los puntajes los propuso Claude.

## Alternativas evaluadas

| Candidato | Familiaridad (30) | Velocidad (25) | Proveedor (15) | Tiempo real (10) | Costo (10) | Multi-plaza (10) | **Total** |
|---|---|---|---|---|---|---|---|
| MySQL 8 | 5 | 4 | 5 | 4 | 4 | 4 | **89** |
| **PostgreSQL** | 3 | 4 | 5 | 4 | 4 | 5 | **79** |

**Por qué cada puntaje:**

- **Familiaridad.** MySQL es la base que Arthur usa en App_Ittob. PostgreSQL le es menos familiar, aunque el SQL y
  Eloquent son casi iguales.
- **Velocidad.** Empate.
  - PostgreSQL, con PostGIS, resuelve mejor el radio de servicio, las zonas y el mapa de calor por colonia.
  - MySQL 8 tiene funciones espaciales suficientes, como `ST_Distance_Sphere`.
- **Proveedor.** Las dos son abiertas y estándar.
- **Tiempo real.** Ninguna lo decide.
- **Costo.** Empate: la base administrada de 1 GiB en DigitalOcean cuesta lo mismo con cualquiera de las dos
  (15.15 USD al mes, E).
- **Multi-plaza.** PostgreSQL con PostGIS modela mejor zonas y polígonos.

## Decisión

**La base de datos es PostgreSQL**, con PostGIS disponible para lo geoespacial.

**La decidió Arthur Naranjo el 2026-11-12 (C-146), contra la recomendación de Claude.** Claude recomendó MySQL 8 y
la matriz le da 89 contra 79. La diferencia viene casi entera de la familiaridad. En la sesión no se declaró el
motivo. Las ventajas de PostgreSQL que aparecieron al evaluar fueron tres: PostGIS para el radio, las zonas y el mapa
de calor, el modelo de zonas y polígonos de las plazas, y que Laravel Cloud ofrece PostgreSQL administrado (sobre
Neon). `SIN RESPUESTA — motivo declarado por Arthur`: se agrega aquí cuando lo diga.

## Consecuencias

- **Positivas:**
  - Lo geoespacial (radio, zonas y colonias) se resuelve en la base de datos con PostGIS, no a mano.
  - El modelo multi-plaza queda más holgado.
- **Negativas, y qué se hace con ellas:**
  - **Es menos familiar para Arthur.** Se mitiga con Eloquent y con los agentes. Cualquier SQL a mano se revisa
    contra PostgreSQL.
  - **App_Ittob usa MySQL,** así que los hábitos y fragmentos de allá no siempre sirven aquí.
- **Condiciona:**
  - El hosting (DD-070): Laravel Cloud tiene PostgreSQL sobre Neon y DigitalOcean lo tiene administrado.
  - Los respaldos: `pg_dump` y los respaldos administrados.
  - Las migraciones: PostGIS se habilita desde la primera.
  - ADR-0009: búsquedas por radio.

## Criterio de reversión

Se reabre si en las dos primeras semanas de desarrollo la falta de familiaridad con PostgreSQL cuesta más de 10 h
medidas, o si el hosting elegido en DD-070 no ofrece PostgreSQL con PostGIS dentro del rango del ANEXO B.4.

## Costo estimado de revertir

- **Antes de tener datos de producción:** bajo. Eloquent abstrae casi todo, y se reescriben las consultas espaciales
  y las migraciones de PostGIS.
- **Con datos de producción:** sube, porque hay que migrar datos.

## Trazabilidad

- **Obligaciones:** O-16, O-21, O-22 y O-26.
- **Restricciones:** RN-22.
- **Notas:** `modelo-multi-plaza.md`.
- **ADRs relacionados:** ADR-0003, ADR-0009 y ADR-0012.
