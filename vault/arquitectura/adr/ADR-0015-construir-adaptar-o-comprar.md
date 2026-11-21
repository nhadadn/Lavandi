---
estado: vigente
grado: D
fuente: acta-2026-11-12-traspaso-del-proyecto.md C-139; planteamiento-fase-6.md, "Construir, adaptar o comprar" (E, 2026-10-09); investigacion-proyectos-base.md y cruce-enatega-inventario.md (E, 2026-10-28); referentes-de-codigo-abierto.md (E, 2026-10-28 y 2026-11-12)
fecha: 2026-11-12
dueño: Arthur Naranjo
adr: ADR-0015
supersede:
---
# ADR-0015 — Construir sobre piezas probadas, sin plantilla ni base ajena

## Contexto

Antes de elegir el framework había que decidir **de dónde sale el código**: escribirlo, adaptar algo que ya existe o
comprarlo (C-107, DD-063). Se decide antes de los pesos y solo contra las puertas, para no elegir primero y
justificar después (`planteamiento-fase-6.md`, paso 4).

Lo que hace necesaria esta decisión:

- **Una sola app** para iPhone y Android, con comportamiento idéntico (A: RN-01, RN-02).
- **Mapas de Google en Android y de Apple en iPhone** (A: RN-29).
- **Conekta** como pasarela (A: RN-28).
- **Costo de infraestructura** de 1,200 a 4,100 MXN al mes (A: RN-22).
- **Cesión al cliente** del código, los diseños y los contenidos con el pago íntegro (A: Cláusula IX).
- **Las horas.** La banda media pide 389 h de revisar, probar e integrar, con Claude escribiendo la mayor parte del
  código (S: S-006). El equipo es Arthur, con unas 14 h por semana y agentes en paralelo (C-134).

**La premisa que pesa más:** una plantilla ahorra sobre todo escribir código, que en este proyecto es lo barato. No
ahorra lo caro, que es entender, probar e integrar. Si una plantilla encajara perfecto, ahorraría como mucho de 40
a 54 % de las horas, y ninguna encaja perfecto (`planteamiento-fase-6.md`, aritmética sobre S-006).

## Criterios

Esta decisión **no usa la matriz ponderada**. Se evalúa contra las puertas eliminatorias (`planteamiento-fase-6.md`):

| Puerta | Qué exige | Origen |
|---|---|---|
| G-1 | Una sola base de código para iPhone y Android | A: RN-01, RN-02 |
| G-2 | Google Maps en Android y Apple Maps en iPhone | A: RN-29 |
| G-3 | Conekta, con tarjeta; OXXO Pay según R-028 | A: RN-28 |
| G-4 | Caber en 1,200 a 4,100 MXN al mes | A: RN-22 |
| G-5 | El código se entrega al cliente, corre en cuentas a su nombre y se puede modificar después | A: Cláusula IX; O-30 |

G-5 la propuso Claude el 2026-10-09 y nadie la había confirmado. **La decisión no depende de ella:** sin G-5, las
alternativas que la fallan siguen fallando G-2 o G-3.

## Alternativas evaluadas

Calificaciones grado E, de los productos tal como se venden o se publican. "Falla" quiere decir que habría que
reescribir esa parte, no que sea imposible.

| Alternativa | G-1 | G-2 | G-3 | G-4 | G-5 | Notas |
|---|---|---|---|---|---|---|
| **OP-01 · Construir sobre piezas probadas** | pasa | incierto | incierto | pasa | pasa | G-2 y G-3 se resuelven al elegir el framework (ADR-0002 y ADR-0010), no son defectos de la opción |
| OP-02 · Plantilla de lavandería de CodeCanyon | incierto | falla | falla | incierto | falla | Casi siempre son tres apps separadas. Con la licencia de Envato el autor conserva la propiedad |
| OP-03 · Plataforma de reparto (6amMart) | incierto | falla | falla | incierto | falla | Marketplace de productos, sin lavandería. La app de tienda exige activarse con el código de compra |
| OP-04 · Marca blanca (CleanCloud, Cents) | incierto | incierto | falla | falla | falla | Para un solo negocio. No entrega código |
| OP-05 · Agencias de "clones" | incierto | incierto | incierto | incierto | incierto | Nada se puede comprobar antes de pagar |
| OP-06 · Enatega o Fleetbase como base | incierto | incierto | falla | incierto | falla | El backend de Enatega es de pago y Fleetbase es AGPL |
| Híbrido: apps de Enatega con servidor propio | pasa | incierto | falla | incierto | pasa, con aviso MIT | Aporta cerca del 20 % del trabajo: 40 a 70 h netas, que en el escenario pesimista se vuelven pérdida (`cruce-enatega-inventario.md`). Además **elige React Native sin pasar por la matriz** |
| Comprar el servidor de Enatega | — | — | falla | incierto | incierto | Rango citado de 15,000 a 30,000 USD (E). La propiedad del código solo se sabría por escrito |
| OP-08 propuesto · Shita | pasa | incierto | falla | incierto | **dudosa** | Son las apps de Enatega renombradas, y su LICENSE quitó el aviso original. El servidor no tiene pruebas (`referentes-de-codigo-abierto.md`, revisión del 2026-11-12). **Se descarta** |
| OP-07 · Comprar una plantilla para estudiarla | no aplica | no aplica | no aplica | no aplica | no aplica | Opcional y fuera de esta decisión. No se hace por ahora: cuesta horas de lectura |

## Decisión

**Lavandi se construye sobre piezas probadas (OP-01).** El código del producto se escribe para la especificación de
Lavandi. Lo común (esqueleto del proyecto, inicio de sesión, base de datos, archivos, avisos, mapas y pagos) se toma
de librerías y servicios ya hechos, con licencias que permitan cederlo. No se adopta ninguna plantilla ni base ajena.

La decidió Arthur Naranjo el 2026-11-12 (`acta-2026-11-12-traspaso-del-proyecto.md`, C-139), sobre la recomendación
escrita en `planteamiento-fase-6.md`. El traspaso lo acordaron Arthur y Nadir entre los dos (C-143).

Los proyectos abiertos se usan **como referencia de reglas, no de código**. LaundryEase aporta el ciclo del pedido, la
cancelación y el envío; OwnFleet, la lavandería que reparte con su gente; Enatega, las pantallas de tres lados
(`referentes-de-codigo-abierto.md`). Si se llega a copiar algo literal con licencia MIT, se conserva su aviso y se
anota en el ADR del componente.

## Consecuencias

- **Positivas:**
  - Todo el código propio se puede ceder al cliente.
  - Ninguna activación ni servidor de terceros condiciona el producto.
  - El framework sale de la matriz, no de una plantilla.
  - Las specs mandan sobre el código.
- **Negativas:** lo propio hay que escribirlo completo. La estimación de horas no baja por esta decisión.
  - Se mitiga con tres cosas: tomar hechas las piezas comunes, delegar la escritura en agentes (Fase 8) y construir
    por etapas (camino B de la recalibración).
- **Condiciona:**
  - ADR-0002 a 0014: cada uno elige qué pieza probada se usa, con los pesos del 2026-09-25 (C-142).
  - La prueba de 2 a 3 días con Enatega que proponía el cruce ya no hace falta.
  - Shita no se arranca.

## Criterio de reversión

Se reabre si pasa cualquiera de estas dos cosas:

1. **Al cerrar la segunda semana de desarrollo, el ritmo real cae en la banda conservadora o peor:** 629 h o más
   para todo el alcance, medido como se dice en `contrato/inventario-funcional.md` §7.
   - En ese caso se evalúa adoptar piezas grandes y concretas, por ejemplo el panel de administración de Enatega,
     que es lo más aprovechable (38 %).
2. **Enatega ofrece por escrito la cesión completa de su servidor a un precio que quepa en el proyecto.**

## Costo estimado de revertir

- **Antes de la segunda semana de desarrollo es bajo:** se pierde el esqueleto y poco más, porque las specs y la
  máquina de estados se conservan.
- **Después crece con cada módulo escrito.** Cambiar de base a mitad del desarrollo obliga a portar lo hecho a la
  estructura ajena, con sus pruebas.

## Trazabilidad

- **Obligaciones:** O-12 a O-20 (la app y sus módulos), O-30 (cesión).
- **Restricciones:** RN-01, RN-02, RN-22, RN-28, RN-29 y la Cláusula IX.
- **Cierra:** DD-063.
- **ADRs relacionados:** ADR-0002 a ADR-0014.
