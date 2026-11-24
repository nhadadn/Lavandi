---
estado: vigente
grado: D
fuente: acta-2026-11-20-modelo-y-pasarela.md C-236 (C) y decisión 26; DD-101; BR-21; vault/arquitectura/modelo-multi-plaza.md (B); servidor/app/Pedidos/FolioDelPedido.php (B)
fecha: 2026-11-21
dueño: Arthur Naranjo
adr: ADR-0020
supersede:
---
# ADR-0020 — El folio del pedido: ocho caracteres al azar, únicos en su plaza

## Contexto

- **Qué pide el contrato (A):** la confirmación del pedido lleva "número de folio" (`NSV-2026-001-v8.pdf:16:ANEXO A,
  Sección A.3`, Módulo A #11).
- **Lo que no dice:** el formato ni el contador (BR-21, DD-101).
- **Lo que recomienda el proyecto (B):** `modelo-multi-plaza.md` y BR-21 piden un folio único por plaza que no revele el
  volumen del negocio.

## Criterios y pesos

Los de la matriz `vigente` (C-142): familiaridad 30, velocidad 25, proveedor 15, tiempo real 10, costo 10, multi-plaza 10.

## Alternativas evaluadas

| Alternativa | Familiaridad (30) | Velocidad (25) | Proveedor (15) | Tiempo real (10) | Costo (10) | Multi-plaza (10) | **Total** |
|---|---|---|---|---|---|---|---|
| **A. Ocho caracteres al azar del alfabeto de Crockford, únicos por `(id_plazas, folio)`**, con reintento si la base rechaza uno repetido | 5 | 5 | 5 | 5 | 5 | 5 | **100** |
| B. Un consecutivo por plaza, con secuencia | 5 | 4 | 5 | 5 | 5 | 4 | **92** |

B revela cuántos pedidos lleva la plaza y obliga a cuidar la secuencia de cada una. A no revela nada.

## Decisión

1. **El folio son ocho caracteres al azar del alfabeto de Crockford** (sin I, L, O ni U, que se confunden), en
   `App\Pedidos\FolioDelPedido`.
2. **La base lo garantiza** con el único `(id_plazas, folio)`. Si rechaza uno repetido, se propone otro, hasta cinco
   veces.
3. **Se asigna al confirmar el pedido,** nunca antes, y una sola vez: confirmar dos veces no cambia el folio
   (confirmacion-E06 y E13).

La tomó Claude por delegación de Arthur Naranjo (C-236), con la recomendación de BR-21. Cierra DD-101.

## Consecuencias

- **Positivas:** no revela el volumen; no hay secuencia que cuidar por plaza.
- **Negativas:** un folio no es fácil de dictar por teléfono. Ocho caracteres sin letras ambiguas lo hacen manejable.

## Criterio de reversión

Si EL CLIENTE pide un folio con formato propio, se cambia el generador: la columna y el único no cambian.

## Costo estimado de revertir

Unas 2 horas: otro generador y sus pruebas.

## Trazabilidad

- **Pendientes:** DD-101, cerrada; BR-21.
- **ADRs relacionados:** ADR-0004, ADR-0016 y ADR-0021.
