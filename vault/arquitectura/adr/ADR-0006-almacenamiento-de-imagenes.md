---
estado: vigente
grado: D
fuente: acta-2026-11-13-cierre-de-la-fase-6.md C-152; brecha-ejecutivo-tecnico.md BR-09 y BR-29 (B); restricciones-no-negociables.md RN-22 (A); verificaciones-fase-6.md, sección del 2026-11-12 (E); ADR-0003 (D); DD-070 (B); adenda del 2026-11-24: acta-2026-11-20-modelo-y-pasarela.md, decisiones 49 y 63, del concilio del 2026-11-24, por delegación de Arthur (C-236, C-245) (C); NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2, NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3, NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3 y NSV-2026-001-v8.pdf:29:ANEXO B, Sección B.4 (A); servidor/config/fotos.php:31 (B); ADR-0011, ADR-0012 y ADR-0016 (D)
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

## Adenda del 2026-11-24: el disco de cada entorno y los logos de prensa de la portada

**Contexto.**

- El B.4 presupuesta el almacenamiento aparte del servidor (`NSV-2026-001-v8.pdf:29:ANEXO B, Sección B.4`). Este ADR
  descartó el disco del propio servidor (alternativa C) por el B.4 y por razones prácticas; el A.3, que guarda las
  imágenes "en un servicio de almacenamiento en la nube" (`NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3`,
  Almacenamiento de imágenes), va en el mismo sentido.
- Hoy `servidor/config/fotos.php:31` usa `fotos_local`, un disco local privado que sirve cada foto con la URL firmada
  de Laravel, en todo entorno que no sea producción. Este ADR no lo anotaba (`perfil-de-lavanderia/design.md`, Open
  Questions).
- El A.2 pide en la portada "Logos de medios o menciones, activables conforme se obtengan"
  (`NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2`), y ADR-0011 los pone en Filament, con su interruptor de activo. Este
  ADR solo preveía URLs firmadas que vencen.

**Decisión.**

1. **El disco de cada entorno.**
   - `fotos_local`, el disco local privado con la URL firmada de Laravel, solo se usa en la máquina local y en las
     pruebas automáticas.
   - El entorno de prueba en internet y producción usan `fotos_s3`, con un bucket privado por entorno. Lo condiciona la
     sección Consecuencias de este mismo ADR ("ADR-0012 (un bucket por entorno)"). ADR-0012 no menciona buckets, así
     que no es la fuente de esta regla.
   - El valor por omisión de `servidor/config/fotos.php:31` cambia cuando exista el bucket del entorno de prueba
     (DD-070). Hoy no cambia código.
   - La librería de S3 y el bucket siguen en `perfil-de-lavanderia`, tarea 2.1 (DD-070).
   - **Alternativa descartada:** `fotos_local` también en el entorno de prueba. Contradice este ADR y arriesga
     acreditar el A.4.3 (`NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3`) sobre un disco que producción no usa: la
     validación conforme al A.4.3 se documenta en el entorno de prueba (ADR-0012).
2. **Los logos de prensa de la portada** (landing-E17).
   - Guardan su imagen en el disco privado de fotos del entorno, el de la decisión 1.
   - La portada pública la recibe por una ruta pública del servidor que la lee del disco y la entrega con caché, sin
     URL firmada: una URL que vence no sirve en una página pública.
   - La ruta identifica la imagen por el nombre de su archivo, nunca por la llave interna (ADR-0016, regla 5), y
     responde 404 si el logo no existe o no está activo.
   - **Solo aplica a los logos de prensa.** Las fotos de las lavanderías, las de perfil y las evidencias siguen solo
     con URL firmada para la app y el panel.

**La asentó** el concilio del 2026-11-24 por delegación de Arthur Naranjo (C-236, C-245), en las decisiones 49 (el
disco de cada entorno) y 63 (los logos de prensa) de `acta-2026-11-20-modelo-y-pasarela.md`.

**Trazabilidad:** perfil-lavanderia-E03 y E04; landing-E17; DD-070.
