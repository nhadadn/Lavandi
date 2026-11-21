---
estado: borrador
grado: mixto
fuente: NSV-2026-001-v8.pdf:4:Cláusula I (A); obligaciones-ejecutivas.md O-26 (B); brecha-ejecutivo-tecnico.md BR-01, BR-02 (B)
fecha: 2026-09-11
dueño: Nadir Hadad Navarrete
---
# Modelo multi-plaza

La obligación más exigente de la arquitectura y **la única del contrato que quedó sin criterio de
aceptación**. Por eso esta nota existe: para escribirle uno.

## La obligación

> Arquitectura técnica escalable a plazas adicionales de la República *"sin necesidad de reescritura
> del sistema ni de rediseño de la base de datos"* — `NSV-2026-001-v8.pdf:4:Cláusula I` (O-26).

En `obligaciones-ejecutivas.md`, O-26 dice **"sin criterio en A.4"** y es la única marcada
`ESPECIFICACIÓN PENDIENTE` de forma explícita. Una obligación sin criterio no se puede acreditar ni
rechazar: es una discusión abierta en la semana 16. Este es el trabajo de cerrarla.

## Criterio de aceptación propuesto

> **Abrir una plaza nueva es cargar datos de configuración. Cero migraciones de esquema, cero
> despliegue de código nuevo.**

**Prueba que lo acredita**, para la tabla de trazabilidad criterio → test de la Fase 7:

- **Dado** un sistema desplegado y operando con el territorio inicial,
- **Cuando** se da de alta una plaza nueva cargando únicamente su ficha y sus zonas,
- **Entonces** un usuario con domicilio en esa plaza completa el recorrido entero del pedido
  —elegir lavandería, agendar, pagar, dar seguimiento— **sin que se haya ejecutado ni una migración
  de esquema ni un despliegue**.

Esa última cláusula es lo que hace la prueba falsable: se verifica mirando el historial de
migraciones y el de despliegues, que son observables y con fecha.

Grado D: lo propone Claude en la Fase 5. **No es grado A**: el contrato exige el resultado, no este
criterio. Presentarlo como contractual sería exactamente lo que `CLAUDE.md` §2 prohíbe.

## Qué es una "plaza"

BR-01 pregunta si es ciudad, zona metropolitana o estado. **El propio territorio inicial descarta las
tres**, y es el dato que resuelve la pregunta:

> *"la totalidad del Estado de Durango y los municipios de Torreón y Matamoros, Coahuila"*
> — `NSV-2026-001-v8.pdf:4:Cláusula I`

Un estado entero y dos municipios sueltos, en la misma frase. Cualquier definición atada a la
división política se rompe el primer día.

**Definición propuesta:** una plaza es un **área operativa**, formada por una o más zonas de
servicio, con su propia configuración comercial y horaria. No corresponde a ninguna unidad política
por obligación, aunque en la práctica suela coincidir.

Consecuencia práctica: el territorio inicial puede modelarse como dos plazas —Durango y La Laguna—
sin que el sistema sepa que una es un estado y la otra dos municipios. Esa decisión comercial es de
Nadir; el modelo aguanta ambas.

## Qué lleva la ficha de una plaza

| Dato | Por qué está | Grado |
|---|---|---|
| Identificador y nombre | Referencia estable | D |
| Conjunto de zonas de servicio | Define dónde se puede pedir (BR-02) | A: A.2 sección 7 |
| **Zona horaria** | Las ventanas de recolección son de 1 hora (RN-15). Una ventana es una hora **local**, y México tiene más de un huso | A: A.3 numeral 7 |
| Moneda | La expansión prevista llega a otros países (C-45). Cuesta un campo hoy y un rediseño después | C: C-45 |
| Comisión aplicable | El contrato la permite "por socio o global" (Módulo D numeral 5), y puede variar por plaza | A: p. 19 |
| Estructura de envío | El envío es un parámetro comercial, no una constante | A: p. 19; C: C-31 |
| Estado: activa o no | Una plaza se prepara antes de abrirse | D |

**Sobre la zona horaria.** Que el territorio inicial esté todo en el mismo huso no autoriza a
guardarlo como constante: es justo el tipo de supuesto que obliga al rediseño que O-26 prohíbe. Las
horas se guardan en un instante absoluto y se presentan en la zona de la plaza.
`SIN VERIFICAR` (grado E, por confirmar antes de fijar reglas de agenda): qué husos rigen hoy en
México y si hay municipios con horario distinto al de su estado.

## La regla que hace verdadero el criterio

**Toda fila que describa operación lleva su plaza.** Lavanderías, repartidores, pedidos, zonas,
precios y comisiones nacen con plaza. No es una columna que se agrega después: agregarla después
**es** el rediseño que el contrato prohíbe.

Tres precisiones que evitan errores caros:

1. **El usuario no pertenece a una plaza; su domicilio sí.** Una persona puede pedir en Durango y en
   Torreón la misma semana. Atar la cuenta a una plaza obliga a duplicar usuarios.
2. **El folio del pedido es único por plaza** (BR-21). Un contador global se vuelve un punto de
   contención y filtra el volumen del negocio a cualquiera que lea su folio.
3. **El aislamiento entre socios y el aislamiento entre plazas son la misma regla, con dos
   dimensiones.** RN-09 exige que un socio no vea a otro; la plaza añade la segunda. Conviene
   resolverlas juntas, con **una sola prueba automatizada anti-fuga** (BR-35), y no con dos
   mecanismos que puedan contradecirse.

## Qué NO significa multi-plaza

Para que la regla YAGNI no se use como coartada para construir de más:

| No significa | Por qué |
|---|---|
| Multi-inquilino con datos separados por cliente | Hay un solo cliente. `NO JUSTIFICADO` |
| Una base de datos por plaza | El contrato pide no rediseñar; no pide aislar infraestructura. Multiplicaría el costo contra el techo de RN-22 |
| Operar hoy fuera del territorio inicial | Está **excluido** (p. 4, Cláusula I). El modelo lo permite; la operación no lo hace. Cualquier "y también Saltillo" es Orden de Cambio |
| Soportar varios idiomas | Nadie lo pidió. `NO JUSTIFICADO` |

La distinción es la que importa: **el modelo nace multi-plaza, la operación nace en el territorio
inicial**. Lo primero es arquitectura; lo segundo, configuración.

## Lo que queda pendiente

| Marcador | Qué falta | Quién |
|---|---|---|
| `ESPECIFICACIÓN PENDIENTE` | Cómo se trazan las zonas: polígonos, radios, códigos postales o colonias (BR-02) | Fase 5, ligado al presupuesto de mapas |
| `ESPECIFICACIÓN PENDIENTE` | Si el territorio inicial es una plaza o dos | Nadir, decisión comercial |
| `SIN VERIFICAR` | Husos horarios vigentes en México (grado E) | Verificar antes de fijar reglas de agenda |
| Propuesto el 2026-11-19 | Cómo se expresa la plaza en el modelo concreto de datos: `id_plazas` en toda tabla de operación, con llave compuesta en las hijas | [[modelo-de-datos]], [[ADR-0016-modelo-de-datos]] (borrador) |
