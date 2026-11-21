---
estado: borrador
grado: D
fuente: brecha-ejecutivo-tecnico.md BR-21 (B); inventario-funcional.md F-T13 (B); verificaciones-fase-6.md, secciones del 2026-11-12 y 2026-11-13 (E); ADR-0003 (D)
fecha: 2026-11-13
dueño: Arthur Naranjo
adr: ADR-0014
supersede:
---
# ADR-0014 — Correo automático con Resend

## Contexto

- **Qué correos manda el sistema (B):**
  - la confirmación del pedido con folio (BR-21, A.3 #11);
  - la recuperación de contraseña (F-T13; `ADR-0005`);
  - los avisos que fijen las specs.
- **El volumen de arranque es bajo:** el arranque es en frío (R-005).
- **Laravel 13 trae transporte oficial** para Resend y para Amazon SES (E).
- **El correo sale del dominio del cliente** (`{{DOMINIO}}`), que todavía no se compra (O-11).

## Criterios y pesos

Los de la matriz `vigente` (C-142). Datos grado E del 2026-11-12 y del 2026-11-13.

## Alternativas evaluadas

| Alternativa | Familiaridad (30) | Velocidad (25) | Proveedor (15) | Tiempo real (10) | Costo (10) | Multi-plaza (10) | **Total** |
|---|---|---|---|---|---|---|---|
| **A. Resend:** gratis hasta 100 al día y 3,000 al mes; el plan Pro cuesta 20 USD al mes por 50,000 | 4 | 5 | 4 | 5 | 5 | 5 | **91** |
| B. Amazon SES: 0.16 USD por cada mil desde el 2026-07-21; hay que salir del sandbox, que AWS responde en hasta 96 horas | 3 | 4 | 4 | 5 | 5 | 5 | **80** |

**A gana por velocidad:** se configura en minutos y no espera la salida del sandbox. **B** es más barato a escala,
pero suma una cuenta de AWS a nombre del cliente y un trámite.

## Decisión (propuesta)

- **El correo automático sale por Resend,** con el transporte oficial de Laravel, desde el dominio del cliente, con
  SPF y DKIM configurados.
- **Una cuenta por entorno de envío real:** prueba y producción (`ADR-0012`). En local, el correo se captura sin
  enviarse.

**Pendiente:** Arthur lo dejó pendiente el 2026-11-13 (`acta-2026-11-13-cierre-de-la-fase-6.md`, C-151; DD-071). La recomendación sigue siendo Resend.

## Consecuencias

- **Positivas:**
  - Sin costo en el arranque.
  - Se configura rápido.
  - Cambiar de proveedor es cambiar el transporte de Laravel.
- **Negativas:**
  - **Pasar de 100 correos al día corta el envío,** porque Resend responde 429 y no reintenta. Se vigila el volumen,
    y se pasa al plan Pro o a SES antes de llegar al límite.
  - Depende de que el dominio exista (O-11).
- **Condiciona:** la compra del dominio y su DNS, y ADR-0012 (cuentas a nombre del cliente).

## Criterio de reversión

Se reabre si el volumen diario pasa de 80 correos de forma sostenida. Entonces se pasa a Resend Pro o a Amazon SES,
lo que cueste menos en ese momento.

## Costo estimado de revertir

Bajo: es configuración de Laravel y DNS.

## Trazabilidad

- **Obligaciones:** O-13 (confirmación del pedido) y O-20.
- **Restricciones:** BR-21 y F-T13.
- **ADRs relacionados:** ADR-0003, ADR-0005 y ADR-0012.
