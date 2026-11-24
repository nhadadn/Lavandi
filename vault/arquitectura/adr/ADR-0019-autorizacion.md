---
estado: vigente
grado: D
fuente: acta-2026-11-20-modelo-y-pasarela.md C-236 (C) y decisión 21; DD-129; registro-E14 y `servidor/app/Autorizacion/` (B); adenda del 2026-11-22: decisión 35, por delegación de C-236 (C), y exportacion-E11
fecha: 2026-11-21
dueño: Arthur Naranjo
adr: ADR-0019
supersede:
---
# ADR-0019 — La autorización vive en una clase propia con su middleware, y un dato ajeno responde 404

## Contexto

- **Qué pide el contrato (A):** "separación por tipo de usuario: un socio lavandería no puede consultar datos de otro
  socio ni de usuarios que no le han hecho pedidos" (RN-09, `NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3`).
- **Qué pide el proyecto (B):** la autorización vive en un solo componente, con rol, tenencia y plaza (`CLAUDE.md` §9.5).
- **Lo que hay (B):** el registro creó `App\Autorizacion\Autorizacion` y el middleware `autorizar:<rol>`; toda ruta
  protegida lleva `auth:sanctum` y `autorizar:…` (registro-E14). Un rol que no corresponde responde 403.
- **Lo abierto:** el mecanismo y la respuesta ante un dato ajeno (DD-129).

## Criterios y pesos

Los de la matriz `vigente` (C-142): familiaridad 30, velocidad 25, proveedor 15, tiempo real 10, costo 10, multi-plaza 10.

## Alternativas evaluadas

| Alternativa | Familiaridad (30) | Velocidad (25) | Proveedor (15) | Tiempo real (10) | Costo (10) | Multi-plaza (10) | **Total** |
|---|---|---|---|---|---|---|---|
| **A. La clase propia y su middleware, como quedó en el registro,** con la tenencia y la plaza agregadas en la misma clase | 5 | 5 | 5 | 5 | 5 | 4 | **98** |
| B. Policies y Gates de Laravel, con la regla de que toda decisión pase por una sola clase | 4 | 3 | 5 | 5 | 5 | 4 | **83** |

A no obliga a rehacer lo que ya está probado; B reparte la regla en una Policy por modelo y hay que vigilar que no se
salte la clase única.

## Decisión

1. **La autorización es la clase `App\Autorizacion\Autorizacion` con el middleware `autorizar:<rol>`.** La tenencia
   (de quién es el dato) y la plaza se agregan a esa misma clase cuando las pida una spec (H5).
2. **Un rol que no corresponde responde 403.** Quien tiene la sesión sabe qué rol tiene.
3. **Un dato que existe pero es de otra persona, de otra lavandería o de otra plaza responde 404,** igual que uno que
   no existe: quien no debe verlo no se entera de que existe.

La tomó Claude por delegación de Arthur Naranjo (C-236), con la recomendación de DD-129.

## Consecuencias

- **Positivas:** nada que rehacer; una sola clase que revisar en la revisión de seguridad.
- **Negativas:** no se aprovechan las Policies de Filament en el panel; el panel llama a la misma clase.
- **Condiciona:** las specs de H5 en adelante (pedidos de la lavandería, repartidor, administración) prueban el 404
  del dato ajeno.

## Criterio de reversión

Si una spec necesita una regla que la clase no puede expresar sin duplicar código en más de dos lugares, se reabre.

## Costo estimado de revertir

Unas 5 horas: mover las reglas a Policies y cambiar el middleware por `can:`.

## Trazabilidad

- **Restricciones:** RN-09.
- **Pendientes:** DD-129, cerrada.
- **ADRs relacionados:** ADR-0003 y ADR-0018.

## Adenda del 2026-11-22: un adaptador donde Filament solo sabe preguntar a una Policy

**Contexto.** La descarga de una exportación del panel es una ruta de Filament (`filament/exports/{export}/download`)
que decide el acceso preguntando a una Policy del modelo `Export`; no tiene otro punto donde enchufar el componente.
exportacion-E11 pide que solo descargue quien la pidió.

**Decisión.**

- **2, precisión.** Donde una pieza de terceros solo sepa preguntar a una Policy, se admite una Policy adaptadora sin
  regla propia: su único cuerpo es llamar al componente (`App\Exportacion\PoliticaDeExportacion` llama a
  `Autorizacion::puedeDescargarLaExportacion()`). La regla sigue viviendo en una sola clase; la Policy no decide nada.
- La prueba de cobertura (autorizacion-E01) y el barrido (E14) siguen recorriendo esa ruta como cualquier otra.

**La tomó** Claude, por delegación de Arthur (C-236), en la decisión 35 de `acta-2026-11-20-modelo-y-pasarela.md`.
