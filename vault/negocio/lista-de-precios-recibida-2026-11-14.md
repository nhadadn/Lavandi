---
estado: borrador
grado: B
fuente: libreta de precios de EL CLIENTE, seis fotos entregadas por WhatsApp el 2026-11-14 y transmitidas por Arthur Naranjo (cliente/recibido/2026-11-14/; acta-2026-11-18-entorno-y-mocks, C-172 y C-173); lectura de Claude, por confirmar. Grado B porque las fotos están en el repo; la lectura de la libreta manuscrita no es una declaración, y no sube a C hasta que Nadir o Arthur la confirmen en un acta (revisión de Nadir del PR #2)
fecha: 2026-11-18
dueño: Arthur Naranjo
---
# Lista de precios recibida el 2026-11-14

Transcripción de una libreta escrita a mano con precios de lavandería y tintorería. Llegó en seis fotos por
WhatsApp el 2026-11-14 y Arthur la pasó el 2026-11-18 (`vault/meta/actas/acta-2026-11-18-entorno-y-mocks.md:C-172`).
Las fotos están en `cliente/recibido/2026-11-14/`, con su índice: [[cliente/recibido/2026-11-14/indice|índice de la tanda]].

**Es una lectura de Claude, por confirmar.** Nadie la ha cotejado todavía contra la libreta. La libreta no dice de
qué lavandería es (DD-066), y esta nota no decide nada: ni precio, ni unidad, ni catálogo.

## Cómo se leyó

- **La estructura la dio Arthur** (C-173): "Los títulos grandes serían el grupo de servicios, y lo de debajo, sus
  servicios con precio como tal." Hay dos grupos, **Tintorería** y **Lavandería y planchaduría**. Dentro de cada uno,
  en tinta azul, las categorías de prenda (Pantalones, Sacos…), y en tinta negra sus variantes (Normal, Seda o lino…)
  con su precio.
- **Seis páginas de una agenda.** Las páginas traen letras impresas, que sirven para ordenarlas (tabla de abajo).
- **Cifras desplazadas.** En varias categorías los números van una línea más abajo que su variante. Se emparejaron en
  orden: en cada categoría hay tantas cifras como variantes. En Lavandería, la cuenta de $40 por kilo confirma el
  emparejamiento.
- **La letra.** El 7 lleva travesaño (se ve en "$117"). El 1 lleva un gancho arriba y muchas veces parece un 7 sin
  travesaño. El 4 se cierra arriba y a veces parece 7 o 9. En Lavandería por peso, esos dígitos se decidieron con la
  cuenta de $40 por kilo, y la columna "Nota" dice cuándo. En Tintorería y en los artículos por pieza no hay cuenta
  que los compruebe: se leyeron por la forma del trazo.
- **Ortografía.** Se pusieron acentos y se corrigieron palabras comunes. Cuando la libreta escribe otra cosa, la nota
  lo dice ("Sweters", "Sodadera", "piesas", "Tuallas", "calsetines"). Las palabras que no se reconocen se dejan como
  están: "maquinon", "Mayón".
- **Marcadores.** `(dudoso)` es la mejor lectura de una cifra que no se lee con seguridad. `[ilegible]` es lo que no
  se puede leer. Lo tachado en la libreta se anota "tachado", sin precio.
- Cada columna de números se recortó y amplió con Pillow, fuera del repositorio, para leerla.

| Foto | Letras de la página | Grupo |
|---|---|---|
| `…2.38.06 PM.jpeg` | AB | Tintorería, 1 de 3 |
| `…2.38.08 PM.jpeg` | CD | Tintorería, 2 de 3 |
| `…2.38.09 PM.jpeg` | EF | Tintorería, 3 de 3 |
| `…2.38.10 PM.jpeg` | No se ven; es la página de enfrente de EF | Lavandería y planchaduría, por peso, 1 de 2 |
| `…2.38.10 PM (1).jpeg` | MN | Lavandería y planchaduría, por peso, 2 de 2 |
| `…2.38.11 PM.jpeg` | OP | Lavandería y planchaduría, artículos por pieza |

## Tintorería

Precio por prenda, en la columna que la libreta titula "Precio normal" ("Precios normales" en la página CD). No hay
otra columna de precio en las fotos. El orden es el de la libreta: la página AB va de Pantalones a Traje de dama;
la CD, de Traje de caballero a Abrigos; la EF, de Gabardinas a Almohada.

| Categoría | Variante | Peso | Precio | Nota |
|---|---|---|---|---|
| Pantalones | Normal | — | $117 | |
| Pantalones | Seda o lino | — | $155 | |
| Pantalones | Piel | — | $236 | |
| Sacos | Normal | — | $133 | |
| Sacos | 3/4 | — | $155 | |
| Sacos | Largo delgado | — | $182 | |
| Sacos | Grueso (maquinon) | — | $225 | "maquinon", así en la libreta |
| Suéteres | Normal | — | $155 | La libreta dice "Sweters" |
| Suéteres | Normal largo | — | $165 | |
| Suéteres | Angora largo | — | $192 | |
| Faldas | Normal | — | $117 | |
| Faldas | Gamuza | — | $246 | |
| Faldas | Seda o lino | — | $295 (dudoso) | El dígito de en medio se parece al 4 de "246": podría ser 245 |
| Faldas | Tableada | — | $155 | |
| Faldas | Tableada larga | — | $165 | |
| Faldas | Normal larga | — | $155 | |
| Vestidos | Normal | — | $198 | |
| Vestidos | Seda o lino | — | $246 | |
| Vestidos | Terciopelo | — | $245 | |
| Vestidos | De noche | — | $290 | "desde" |
| Vestidos | 1a. comunión | — | $306 | "desde" |
| Vestidos | XV años | — | $603 | "desde" |
| Vestidos | Novias | — | $754 | "desde" |
| Traje de dama | Normal | — | $198 | |
| Traje de dama | 3 piezas | — | $230 | La libreta dice "piesas" |
| Traje de dama | Bordado | — | $273 | |
| Traje de dama | Pedrería | — | $273 | |
| Traje de dama | Seda o lino | — | $273 | |
| Traje de caballero | Normal | — | $198 | Una llave tapa parte del encabezado: se lee "Traje caballer…" |
| Traje de caballero | 3 piezas | — | $230 | |
| Camisas | Normal | — | $117 | |
| Camisas | De vestir | — | $117 | |
| Camisas | Seda | — | $144 | |
| Blusas | Normal | — | $117 | |
| Blusas | Pedrería | — | $144 | |
| Blusas | Seda o lino | — | $144 | |
| Chaleco | Normal (delgado) | — | $144 | |
| Chaleco | Pluma/grueso | — | $192 | |
| Chaleco | Piel o gamuza | — | $230 | |
| Sudadera | Normal | — | $155 | |
| Sudadera | Gruesa | — | $225 | |
| Chamarras | Delgadas sin forro | — | $168 | "S/ forro" |
| Chamarras | Delgada con forro | — | $190 | "c/ forro" |
| Chamarras | Gruesa corta | — | $234 | |
| Chamarras | Gruesa 3/4 | — | $256 | |
| Chamarras | Gruesa o piel corta | — | $317 | |
| Chamarras | Gruesa o piel larga | — | $350 | |
| Chamarras | Mangas piel | — | $234 | |
| Chamarras | Pluma corta | — | $234 | |
| Chamarras | Pluma 3/4 | — | $256 | |
| Abrigos | Normal | — | $267 | |
| Abrigos | Largo | — | $311 | |
| Abrigos | Peluche | — | $267 | |
| Abrigos | Terciopelo | — | $267 | "Tercio pelo" |
| Gabardinas | Normal | — | $267 | |
| Gabardinas | Largo | — | $311 | |
| Batas | Trabajo | — | $155 | |
| Batas | Casa/baño | — | $155 | |
| Batas | Seda | — | $225 | |
| Varios | Overol | — | $225 | |
| Varios | Palazzo | — | $215 | |
| Varios | Pants | — | $165 | Después de "Pants" hay una palabra tachada, `[ilegible]`. En Lavandería el mismo artículo dice "Pants conjunto" |
| Varios | Pijama | — | $165 | |
| Varios | Pijama de seda | — | $176 | |
| Varios | Bufanda | — | $101 | |
| Varios | Cachucha | — | $106 | |
| Varios | Corbata | — | $90 | |
| Varios | Capa/gabán o chal | — | $198 | |
| Varios | `[ilegible]` | — | tachado | Renglón entero tachado, nombre y precio |
| Colchas | Individual | — | $196 | |
| Colchas | Matrimonial | — | $225 | |
| Colchas | K.S. | — | $256 | "K.S.", así en la libreta |
| Colchas | Queen size | — | $246 | |
| Cobertores | Individual | — | $196 | |
| Cobertores | Matrimonial | — | $223 | |
| Cobertores | K.S. | — | $255 | |
| Cobertores | Queen size | — | $246 (dudoso) | El primer dígito es pequeño, va subrayado y también parece 9 |
| Cobertores | Cubrecolchón | — | $289 | |
| Cortinas | Gruesa | — | $107 por m² | La libreta escribe "m2": precio por metro cuadrado |
| Cortinas | Ahulada | — | $87 por m² | |
| Cortinas | Delgada | — | $89 por m² | |
| Cortinas | Ahulada con forro | — | $90 por m² | |
| — | Almohada | — | sin precio | Última línea de la página EF, en tinta azul sobre otra escritura. No tiene precio |

**83 renglones:** 81 con precio, 1 tachado y 1 sin precio.

## Lavandería y planchaduría

### Por prenda, con peso

Dos páginas: la de enfrente de EF, de Pantalones a Blusa, y la MN, de Chalecos a Del hogar. Cada prenda trae su peso
y su precio. La columna "¿Da $40/kg?" divide el precio entre el peso. Es una cuenta de Claude sobre esta lectura, no
una regla que el cliente haya dicho (acta del 2026-11-18, "Datos de la sesión").

| Categoría | Variante | Peso | Precio | ¿Da $40/kg? | Nota |
|---|---|---|---|---|---|
| Pantalones | Vestir | 0.350 kg | $14.00 | Sí | |
| Pantalones | Lino o seda | 0.300 kg | $12.00 | Sí | |
| Pantalones | Mezclilla | 0.600 kg | $24.00 | Sí | |
| Pantalones | Gabardina | 0.400 kg | $16.00 | Sí | |
| Sacos | — | — | tachado | — | La categoría entera está tachada, sin variantes ni cifras |
| Suéteres | Normal | 0.550 kg | $22.00 | Sí | La libreta dice "Sweters" |
| Suéteres | Normal largo | 0.800 kg | $32.00 | Sí | |
| Faldas | Normal | 0.350 kg | $14.00 | Sí | El 1 se ve como 7; la cuenta da 14 |
| Faldas | Seda o lino | 0.250 kg | $10.00 | Sí | |
| Faldas | Normal larga | 0.350 kg (dudoso) | $14.00 | Sí | El último dígito del peso está enmendado. El precio se ve "74.00"; la cuenta da 14 |
| Vestidos | Normal | 0.500 kg | $20.00 | Sí | |
| Vestidos | Seda o lino | 0.400 kg | $16.00 | Sí | |
| Traje de dama | Normal | 0.500 kg | $20.00 | Sí | |
| Traje de dama | 3 piezas | 0.520 kg | $21.00 | **No** (40.38) | A 40 el kilo serían 20.80. La libreta dice "piesas" |
| Traje de dama | Bordado | 0.480 kg | $19.00 (dudoso) | **No** (39.58) | Se ve "79.00". A 40 el kilo serían 19.20 |
| Traje de dama | Seda o lino | 0.300 kg | $12.00 | Sí | Se ve "72.00"; la cuenta da 12 |
| Camisas | Normal | 0.325 kg | $13.00 | Sí | |
| Camisas | Vestir | 0.325 kg | $13.00 | Sí | |
| Camisas | Seda o lino | 0.300 kg | $12.00 | Sí | Se ve "72.00"; la cuenta da 12 |
| Camisas | Franela o lana | 0.350 kg | $14.00 | Sí | El 1 se ve como 7; la cuenta da 14 |
| Camisas | Mezclilla | 0.380 kg | $15.20 | Sí | |
| Blusa | Normal | 0.280 kg | $12.00 | **No** (42.86) | Cifras claras. A 40 el kilo serían 11.20 |
| Blusa | Seda o lino | 0.260 kg | $11.00 | **No** (42.31) | Cifras claras. A 40 el kilo serían 10.40 |
| Chalecos | Normal (delgado) | 0.100 kg | $4.00 | Sí | |
| Chalecos | Mezclilla | 0.250 kg | $10.00 | Sí | |
| Chalecos | Pluma/grueso | 0.150 kg | $6.00 | Sí | Pesa menos que el de mezclilla; así está escrito |
| Sudadera | Normal | 0.800 kg (dudoso) | $32.00 | Sí | Los dos últimos dígitos del peso están remarcados. La libreta dice "Sodadera" |
| Batas | Trabajo | 0.100 kg (dudoso) | $4.00 (dudoso) | Sí, solo con esta lectura | Peso y precio enmendados. El peso también se lee 0.200 kg, y con él la cuenta da 20 |
| Batas | Casa/baño | 1.300 kg (dudoso) | $48.00 (dudoso) | **No** (36.92) | El 3 del peso y el 8 del precio están remarcados. Con 1.200 kg daría 40; a 40 el kilo, 1.300 kg serían 52.00 |
| Batas | Seda | 0.800 kg | $32.00 | Sí | |
| Varios | Overol | 1.00 kg | $40.00 | Sí | El 4 se ve como 7; la cuenta da 40 |
| Varios | Palazzo | 0.350 kg | $14.00 | Sí | Se ve "74.00"; la cuenta da 14 |
| Varios | Pants conjunto | 0.450 kg | sin precio | No aplica | La casilla del precio está vacía. A 40 el kilo serían 18.00 |
| Varios | Pijama | 0.300 kg | $12.00 | Sí | Se ve "72.00"; la cuenta da 12 |
| Varios | Pijama de seda | 0.200 kg | $8.00 | Sí | |
| Varios | Bufandas | 0.100 kg | $4.00 | Sí | |
| Varios | `[ilegible]` | — | tachado | — | Nombre tachado; el renglón no tiene peso ni precio |
| Varios | Capa, gabán o chal | 1.00 kg | $40.00 | Sí | |
| Varios | Mayón | 0.250 kg | $10.00 | Sí | "Mayón", así en la libreta |
| Ropa de bebé | Mameluco | 0.250 kg | $10.00 | Sí | Se ve "70.00"; la cuenta da 10 |
| Ropa de bebé | Pantalón | 0.660 kg (dudoso) | $26.60 (dudoso) | **No** (40.30) | El primer decimal del precio está remarcado; con 26.40 daría 40. El último dígito del peso se junta con "kg" |
| Ropa de bebé | Pañalero | 0.660 kg (dudoso) | $26.50 | **No** (40.15) | A 40 el kilo serían 26.40. El último dígito del peso se junta con "kg" |
| Ropa de bebé | Conjunto borrega | 0.250 kg | $10.00 | Sí | Se ve "70.00"; la cuenta da 10 |
| Ropa de bebé | Playerita | 0.660 kg (dudoso) | $26.50 | **No** (40.15) | A 40 el kilo serían 26.40. El último dígito del peso se junta con "kg" |
| Del hogar | Toallas | 0.700 kg | $28.00 | Sí | La libreta dice "Tuallas" |
| Del hogar | Manteles | 0.500 kg | $20.00 | Sí | |
| Del hogar | Sábanas (conjunto) | 1.00 kg | $40.00 | Sí | |
| Del hogar | Microfibras | 0.200 kg | $8.00 | Sí | La libreta dice "Micro, Fibras" |
| Del hogar | Toalla de manos | 0.500 kg | $20.00 | Sí | La libreta dice "Tualla de manos" |

**49 renglones:** 47 con peso y 2 tachados (la categoría Sacos y un renglón de Varios). De los 47:

- **38 dan $40 por kilo** con la mejor lectura. Uno de ellos, Batas de trabajo, depende de dos cifras enmendadas.
- **8 no dan $40 por kilo:** Traje de dama 3 piezas y Bordado, las dos Blusas, Batas de casa/baño, y Pantalón,
  Pañalero y Playerita de bebé. Siete se apartan menos de 1 peso del precio a $40 por kilo; Batas de casa/baño, 4 pesos.
  Por qué, no se sabe: no se corrige ninguna.
- **1 no tiene precio:** Pants conjunto.

### Artículos por pieza

Página OP, también titulada "Lavandería y planchaduría". Los artículos no llevan categoría en azul ni peso: cada uno
tiene su precio en el mismo renglón. No hay cuenta que compruebe estas cifras.

| Categoría | Variante | Peso | Precio | Nota |
|---|---|---|---|---|
| Chamarras | — | — | $172.00 | |
| Tenis | — | — | $100.00 (dudoso) | El primer dígito se ve como un 7 sin travesaño, que en esta letra es como se escribe el 1 (igual que el "$10.00" del mameluco, que la cuenta confirma). Calzado: DD-046 |
| Cobertor | Individual | — | $202.00 | |
| Cobertor | Matrimonial | — | $172.00 | Así está escrito: cuesta menos que el individual |
| Cobertor | Queen size | — | $192.00 | |
| Cobertor | King size | — | $202.00 | |
| Planchado | Docena | — | $140.00 | |
| Planchado | Por pieza | — | $18.00 | |
| Ganchos | — | — | $9.00 | |
| Frazadas | — | — | $80.00 | |
| Frazada | Para bebé | — | $40.00 | |
| Colchas | — | — | $80.00 | |
| Carga | Media docena | — | $78.00 (dudoso) | El primer dígito lleva travesaño y se lee 7. Nada lo comprueba |
| Cachucas | — | — | $40.00 | Así en la libreta; en Tintorería, "Cachucha" |
| Ropa interior, calcetines | Por par | — | $5.00 | La libreta dice "calsetines" |

**15 renglones**, todos con precio.

## Lo que conviene confirmar con quien escribió la libreta

1. Las cifras marcadas `(dudoso)` y los dos renglones `[ilegible]` de Varios.
2. Las 8 filas de Lavandería que no dan $40 por kilo: si son así o si hay un error de copia.
3. El precio de Pants conjunto en Lavandería y el de Almohada en Tintorería, que están en blanco.
4. **Si falta una hoja.** Por las letras de la agenda (AB, CD, EF, MN, OP), entre la página de enfrente de EF y la MN
   podría haber una hoja que no se fotografió. No se sabe si tiene precios.
5. Si hay otro precio además del "normal" de Tintorería. En las fotos no aparece.

## Lo que esto toca

Esta sección no decide nada. Dice qué notas y qué filas pendientes se cruzan con la libreta.

1. **El contrato habla de kilos y de tres tipos de servicio.** El perfil de la lavandería muestra "precios por kilo"
   (F-A05, `vault/contrato/inventario-funcional.md:68`) y la selección de servicio ofrece "lavado estándar, lavado con
   planchado y lavado en seco" (F-A06, `vault/contrato/inventario-funcional.md:69`). La fuente es
   `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3`, numerales 5 y 6, recogida en RN-16
   (`vault/contrato/restricciones-no-negociables.md:33`). BR-16 deja abiertos el catálogo por socio, la unidad del
   lavado en seco y cómo se estima el precio antes de pesar (`vault/contrato/brecha-ejecutivo-tecnico.md:34`).
   La libreta, en cambio, cobra **por prenda** en sus dos grupos, **por pieza** en la página OP y **por metro
   cuadrado** en las cortinas. Que en Lavandería 38 de 47 precios den $40 por kilo es una cuenta, no un precio por
   kilo publicado. Tampoco está decidido cómo se corresponden sus dos grupos con los tres tipos del contrato. El
   2026-11-05, cobrar por prenda quedó entre las ideas del cliente que no están en el contrato
   (`vault/meta/actas/acta-2026-11-05-resultados-de-la-recalibracion.md:44`, C-129).
2. **DD-010 y DD-012.** DD-010 es el método de precio: quién lo fija, sobre qué unidad, cómo se estima antes de
   pesar y cómo entran el envío y la comisión. DD-012 es cuándo se cobra: al pedir con un estimado, al pesar, o con
   autorización y captura. El peso por prenda de Lavandería tiene la forma de lo que el cliente escribió en la guía
   del 2026-11-05: "Al pedir, con un tabulador de pesos por prenda"
   (`vault/cliente/comunicaciones/2026-11-05-reporte-del-formulario.md:122`; es una marca en papel que todavía no es
   grado C). Ese mismo día Nadir declaró: "Aún no hay precios" (C-131). Si esta libreta es ese tabulador, no se sabe.
3. **DD-046, el calzado.** La página OP cobra "Tenis" a $100.00 (dudoso). El lavado de calzado no está en el
   contrato: si se quiere, va por Orden de Cambio.
4. **DD-066, de quién es la libreta.** La pregunta sigue abierta: ¿es la lista de la lavandería propia del cliente?
   La libreta no lo dice. Es la pregunta 2 del acta del 2026-11-18.
5. **Es la lista de una sola lavandería.** El contrato pone los precios en el perfil de cada lavandería (F-A05), así
   que en la plataforma cada socio tiene los suyos. **Quién los fija no está decidido:** BR-46 lo deja abierto (el
   socio, la plataforma o una banda con piso y techo; `vault/contrato/brecha-ejecutivo-tecnico.md:64`), y la decisión
   P1 de `vault/negocio/metodo-de-precio.md:35` sigue "abierta". En la guía del 2026-11-05 el cliente escribió
   "Estandarización de precios (los manejaría Lavandi)"
   (`vault/cliente/comunicaciones/2026-11-05-reporte-del-formulario.md:115`; marca en papel, todavía no grado C).
   Si esta lista es el precio de un socio, una referencia o el tabulador de Lavandi, depende de DD-066 y de BR-46.
6. **DD-076, el beneficio por más prendas.** El video de la misma tanda promete un beneficio mayor "entre más
   prendas agregues". La libreta no trae descuentos por volumen. Lo único parecido es el planchado: $140.00 la
   docena, cuando doce piezas sueltas a $18.00 serían 216.00.
7. **Los mocks.** Los mocks de Claude Design usan los precios de esta libreta (acta del 2026-11-18, decisión 7). Si la
   lectura cambia al confirmarla, hay que corregirlos allí.
