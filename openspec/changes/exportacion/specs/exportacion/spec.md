## ADDED Requirements

### Requirement: Descarga de cada reporte en CSV y en Excel

El panel DEBE (MUST) ofrecer la descarga en CSV y en Excel (XLSX) de cada reporte: los 11 indicadores del A.5 y los reportes financieros de ingresos, comisiones y conciliación con la pasarela. El archivo trae las mismas cifras que muestra el panel para ese rango.

Cita: A → `NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5`; A → `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3` (Módulo D #6); D → ADR-0003

#### Scenario: exportacion-E01 — Descargar en CSV

- **DADO** el indicador de pedidos totales con datos en el rango
- **CUANDO** la administración pide la descarga en CSV
- **ENTONCES** recibe un archivo CSV con una fila por periodo y las mismas cifras que muestra el panel

Criterio: A44-2

#### Scenario: exportacion-E02 — Descargar en Excel

- **DADO** el mismo indicador y el mismo rango
- **CUANDO** la administración pide la descarga en Excel
- **ENTONCES** recibe un archivo XLSX con las mismas filas y cifras que el CSV

Criterio: A44-2

#### Scenario: exportacion-E03 — Todos los reportes se descargan y coinciden con el panel

- **DADO** datos sembrados para cada uno de los 14 reportes
- **CUANDO** se descarga cada reporte en CSV y en Excel
- **ENTONCES** cada archivo se genera y sus cifras coinciden con las del panel para el mismo rango

Criterio: A44-2

### Requirement: Cualquier rango de fechas

El panel DEBE (MUST) generar cada descarga para el rango de fechas que elija la administración, con las dos fechas incluidas y sin tope de duración.

Cita: A → `NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5`

#### Scenario: exportacion-E04 — El rango incluye sus dos extremos

- **DADO** pedidos de cada día del 1 al 31 de marzo
- **CUANDO** la administración descarga el rango del 10 al 20 de marzo
- **ENTONCES** el archivo trae los datos del 10 al 20, incluidos los dos días, y ninguno de fuera

Criterio: A44-2

#### Scenario: exportacion-E05 — Un rango de dos años

- **DADO** pedidos de dos años
- **CUANDO** la administración pide ese rango completo
- **ENTONCES** el panel acepta el rango y genera el archivo

#### Scenario: exportacion-E06 — Rango al revés

- **DADO** el formulario de descarga
- **CUANDO** la fecha de inicio es posterior a la de fin
- **ENTONCES** el panel rechaza la descarga con un mensaje

### Requirement: Acentos y eñes legibles

El panel DEBE (MUST) escribir el CSV de modo que, al abrirlo en Excel, los acentos y las eñes se lean como se capturaron.

Cita: A → `NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.4`

#### Scenario: exportacion-E07 — Acentos y eñes

- **DADO** una colonia con acento y eñe en los datos
- **CUANDO** se descarga el reporte en CSV y se abre en Excel
- **ENTONCES** el nombre de la colonia se lee con su acento y su eñe

Criterio: A44-2

### Requirement: Protección contra fórmulas inyectadas

El panel DEBE (MUST) escribir como texto, y no como fórmula, toda celda de texto que empiece con =, +, -, @, tabulador o retorno de carro, en el CSV y en el Excel. Las celdas numéricas siguen siendo números.

Cita: D → ADR-0003

#### Scenario: exportacion-E08 — Un texto que empieza con signo igual

- **DADO** un pedido cuya colonia capturada es "=1+1"
- **CUANDO** se descarga el mapa de calor en CSV y en Excel
- **ENTONCES** la celda queda como texto y Excel no la calcula

Criterio: A44-2

#### Scenario: exportacion-E09 — Los números siguen siendo números

- **DADO** una cifra negativa en un reporte
- **CUANDO** se descarga en CSV y en Excel
- **ENTONCES** la celda queda como número, sin el escape de las celdas de texto

### Requirement: Generación en segundo plano

El panel DEBE (MUST) generar las descargas en segundo plano, avisar en el panel cuando el archivo está listo y dejar que solo la cuenta que la pidió descargue el archivo.

Cita: D → ADR-0003; A → `NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3` (separación por tipo de usuario)

#### Scenario: exportacion-E10 — Aviso cuando el archivo está listo

- **DADO** un reporte de un rango largo
- **CUANDO** la administración pide la descarga
- **ENTONCES** el panel le dice que el archivo se está generando
- **Y** al terminar le avisa en el panel con el enlace de descarga

#### Scenario: exportacion-E11 — Solo quien la pidió la descarga

- **DADO** un archivo generado para una cuenta de administración
- **CUANDO** otra cuenta intenta abrir su enlace
- **ENTONCES** se le niega el archivo

#### Scenario: exportacion-E12 — La descarga caduca y se borra

- **DADO** una descarga generada hace más días que los de vida de la configuración, 7 por omisión, y otra más reciente
- **CUANDO** corre el borrado, programado una vez al día
- **ENTONCES** la vieja se borra con sus archivos y su enlace deja de servir
- **Y** la reciente se conserva

Los días no los fija el contrato: son configuración (`EXPORTACION_DIAS_DE_VIDA`), decidida por delegación (C-236) en
`design.md`, pregunta abierta 2. El borrado necesita que el hosting corra el programador (DD-070).
