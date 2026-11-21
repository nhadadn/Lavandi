---
estado: vigente
grado: D
fuente: acta-2026-11-13-cierre-de-la-fase-6.md C-152; brecha-ejecutivo-tecnico.md BR-09 y BR-29 (B); restricciones-no-negociables.md RN-22 (A); verificaciones-fase-6.md, sección del 2026-11-12 (E); ADR-0003 (D); DD-070 (B)
fecha: 2026-11-13
dueño: Arthur Naranjo
adr: ADR-0006
supersede:
---
# ADR-0006 — Fotos y evidencias en almacenamiento S3 compatible, privado

## Contexto

- **Qué se guarda:** fotos y evidencias.
  - Las "evidencias de entrega" del Módulo C (BR-29). En qué momentos y con qué formato lo decide la spec.
  - Las fotos de perfil de las lavanderías.
- **Qué pide el contrato (A):**
  - presupuesta el almacenamiento **aparte** del servidor: de 86 a 432 MXN al mes (RN-22, B.4);
  - EL PRESTADOR es encargado de datos personales, con "medidas técnicas razonables" (BR-09).
- **Qué ya está decidido:** el backend es Laravel (`ADR-0003`), y su `Storage` habla S3 de fábrica. Dónde corre se
  decide en DD-070.

## Criterios y pesos

Los de la matriz `vigente` (C-142). Costos grado E del 2026-11-12.

## Alternativas evaluadas

| Alternativa | Familiaridad (30) | Velocidad (25) | Proveedor (15) | Tiempo real (10) | Costo (10) | Multi-plaza (10) | **Total** |
|---|---|---|---|---|---|---|---|
| **A. S3 compatible del mismo proveedor del hosting:** los buckets de Laravel Cloud (0.02 USD por GB) o DigitalOcean Spaces (5 USD al mes por 250 GiB) | 4 | 5 | 4 | 5 | 5 | 5 | **91** |
| B. Cloudflare R2 en una cuenta aparte (0.015 USD por GB, 10 GB gratis, descarga sin costo) | 4 | 4 | 5 | 5 | 5 | 5 | **89** |
| C. Disco del propio servidor | 5 | 5 | 2 | 5 | 5 | 5 | descartada |

**C se descarta.** El contrato presupuesta el almacenamiento aparte del servidor (B.4). Además, un disco local se
pierde o se complica con el servidor, y amarra los archivos a esa máquina.

**A y B casi empatan.** A gana porque no suma otra cuenta a nombre del cliente. Las dos hablan S3, así que pasar de
una a otra es mover archivos, no reescribir.

## Decisión

- **Los archivos se guardan con el `Storage` de Laravel, con driver S3,** en el almacenamiento del mismo proveedor
  que se elija en DD-070.
- **Los buckets son privados.** La app y el panel ven las fotos con URLs firmadas que vencen.
- **La app comprime las fotos antes de subirlas.** El tamaño y la calidad los fija la spec.

La aprobó Arthur Naranjo el 2026-11-13 (`acta-2026-11-13-cierre-de-la-fase-6.md`, C-152), con la opción que recomendó Claude.

## Consecuencias

- **Positivas:** el costo cabe holgado en el rango del B.4, la interfaz es estándar y las fotos no quedan públicas.
- **Negativas:** depende de DD-070 para saber el proveedor concreto.
  - Retención y borrado: la política la fija la spec de datos personales (BR-09).
- **Condiciona:** ADR-0012 (un bucket por entorno) y la spec de evidencias (BR-29).

## Criterio de reversión

Se reabre si el costo real del almacenamiento pasa de 432 MXN al mes, o si el proveedor elegido en DD-070 no ofrece
almacenamiento S3.

## Costo estimado de revertir

Bajo. Se copian los objetos al otro bucket y se cambia la configuración.

## Trazabilidad

- **Obligaciones:** O-15 (evidencias) y O-20 (seguridad).
- **Restricciones:** RN-22, BR-09 y BR-29.
- **Pendientes:** DD-070.
- **ADRs relacionados:** ADR-0003 y ADR-0012.
