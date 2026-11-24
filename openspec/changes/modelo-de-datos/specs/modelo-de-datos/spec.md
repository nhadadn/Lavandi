## ADDED Requirements

### Requirement: Todo identificador empieza con id_

Toda llave primaria autoincremental DEBE (MUST) llamarse `id_<tabla>`. Toda llave foránea DEBE (MUST) llevar el mismo
nombre que la llave a la que apunta. Ninguna columna de una tabla del negocio DEBE (MUST) terminar en `_id`: el
identificador público se llama `id_publico` y los que asigna la pasarela, `id_pasarela_<cosa>`. Tablas y columnas DEBEN
(MUST) ir en minúsculas y en `snake_case`.

Cita: D → ADR-0016, regla 5 (C-190, C-194)

#### Scenario: modelo-E01 — Una llave primaria con otro nombre no pasa

- **DADO** una tabla del negocio `zonas` cuya llave primaria se llama `id`
- **CUANDO** corre la prueba de esquema
- **ENTONCES** falla y nombra la tabla `zonas` y la llave esperada, `id_zonas`

#### Scenario: modelo-E02 — Una columna que termina en _id no pasa

- **DADO** una tabla del negocio con una columna `pedido_id`
- **CUANDO** corre la prueba de esquema
- **ENTONCES** falla y nombra la tabla y la columna

#### Scenario: modelo-E03 — Una llave foránea con otro nombre no pasa

- **DADO** una tabla `pedidos` con una llave foránea `id_cliente` que apunta a `usuarios.id_usuarios`
- **CUANDO** corre la prueba de esquema
- **ENTONCES** falla y pide el nombre `id_usuarios`

#### Scenario: modelo-E04 — Un nombre con mayúsculas no pasa

- **DADO** una tabla del negocio con una columna `Id_Pedidos`
- **CUANDO** corre la prueba de esquema
- **ENTONCES** falla y pide el nombre en minúsculas, `id_pedidos`

### Requirement: La plaza en cada tabla de operación

Toda tabla de operación DEBE (MUST) llevar `id_plazas`. Cuando su tabla padre también la lleva, la llave foránea DEBE
(MUST) ser compuesta con la plaza, de modo que la base rechace una fila hija de otra plaza. Las tablas sin plaza son
las de la lista cerrada del diseño: las del framework, los catálogos, `plazas`, las de cuentas y las de la pasarela.
`id_plazas` es no nula, salvo en las tablas de la lista de plaza opcional del diseño.

Cita: A → `NSV-2026-001-v8.pdf:4:Cláusula I`; D → ADR-0016, regla 4

#### Scenario: modelo-E05 — Una tabla de operación sin plaza no pasa

- **DADO** una tabla nueva que no está en la lista de tablas sin plaza y no tiene `id_plazas`
- **CUANDO** corre la prueba de esquema
- **ENTONCES** falla y nombra la tabla

#### Scenario: modelo-E06 — La base rechaza una fila de otra plaza

- **DADO** una lavandería de la plaza A
- **CUANDO** se intenta guardar un pedido de la plaza B con esa lavandería
- **ENTONCES** la base de datos rechaza la fila por su llave compuesta, aunque el código no lo haya validado

### Requirement: Tipos de dinero, horas y mapas

El dinero DEBE (MUST) guardarse en `numeric(12,2)`, los porcentajes en `numeric(5,2)` y las cantidades en
`numeric(8,2)`. Los instantes DEBEN (MUST) ser `timestamptz`; las horas del día de un horario, `time`; y los puntos y
polígonos, `geography` con SRID 4326. Ninguna columna DEBE (MUST) ser `real` ni `double precision`.

Cita: D → ADR-0016, regla 7; ADR-0004

#### Scenario: modelo-E07 — Un monto con decimales flotantes no pasa

- **DADO** una tabla del negocio con una columna `monto` de tipo `double precision`
- **CUANDO** corre la prueba de esquema
- **ENTONCES** falla y pide `numeric(12,2)`

#### Scenario: modelo-E08 — Una hora sin zona horaria no pasa

- **DADO** una tabla del negocio con una columna `pagado_en` de tipo `timestamp` sin zona horaria
- **CUANDO** corre la prueba de esquema
- **ENTONCES** falla y pide `timestamptz`

### Requirement: Fecha de alta y de cambio en cada tabla

Toda tabla del negocio DEBE (MUST) tener `created_at` y `updated_at` en `timestamptz`, salvo las tablas de enlace sin
llave propia (`rol_usuario` y `actividad_usuarios`).

Cita: A → `NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3` ("historial completo"); D → ADR-0016, regla 7

#### Scenario: modelo-E17 — Una tabla sin fecha de alta no pasa

- **DADO** una tabla del negocio sin `created_at`, o con `created_at` de tipo `timestamp` sin zona horaria
- **CUANDO** corre la prueba de esquema
- **ENTONCES** falla y nombra la tabla

### Requirement: Llaves foráneas con índice y sin borrado en cascada

Toda llave foránea DEBE (MUST) tener un índice que empiece con sus columnas y DEBE (MUST) declararse con
`ON DELETE RESTRICT`, para que borrar una fila padre nunca arrastre el historial.

Cita: A → `NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3` ("historial completo"); D → ADR-0016, regla 6

#### Scenario: modelo-E09 — Una llave foránea en cascada no pasa

- **DADO** una llave foránea declarada con `ON DELETE CASCADE`
- **CUANDO** corre la prueba de esquema
- **ENTONCES** falla y pide `ON DELETE RESTRICT`

#### Scenario: modelo-E10 — Una llave foránea sin índice no pasa

- **DADO** una llave foránea sin un índice que empiece con sus columnas
- **CUANDO** corre la prueba de esquema
- **ENTONCES** falla y nombra la llave

### Requirement: El historial no se borra

Las filas de `pedidos`, `pedido_eventos`, `pagos` y `reembolsos` NO DEBEN (MUST NOT) poder borrarse, y las de
`pedido_eventos` tampoco editarse: la base DEBE (MUST) rechazar la operación. Las cuentas, los socios y las direcciones
DEBEN (MUST) borrarse en suave, con `deleted_at`.

Cita: A → `NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3` ("historial completo"); D → ADR-0016, regla 6

#### Scenario: modelo-E11 — La base rechaza borrar un pedido

- **DADO** un pedido guardado
- **CUANDO** se intenta borrarlo con una sentencia `DELETE`
- **ENTONCES** la base de datos rechaza la operación y el pedido sigue ahí

#### Scenario: modelo-E12 — La base rechaza editar un evento del pedido

- **DADO** un evento guardado en `pedido_eventos`
- **CUANDO** se intenta cambiar su estado o su fecha
- **ENTONCES** la base de datos rechaza la operación

### Requirement: Ninguna columna guarda datos de tarjeta

Ninguna tabla DEBE (MUST) tener una columna para el número, el código de seguridad o la fecha de vencimiento de una
tarjeta. De una tarjeta guardada solo pueden existir la referencia de la pasarela, la marca y los últimos cuatro
dígitos.

Cita: A → `NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3` (Datos de tarjeta); D → ADR-0016

#### Scenario: modelo-E13 — Una columna para el número de tarjeta no pasa

- **DADO** una tabla con una columna `numero_tarjeta`, `cvv` o `vencimiento_tarjeta`
- **CUANDO** corre la prueba de esquema
- **ENTONCES** falla y nombra la columna

### Requirement: Los pagos no nombran a un proveedor

Ningún nombre de tabla ni de columna DEBE (MUST) contener el nombre de un proveedor de pagos. Lo que asigna la
pasarela se guarda en columnas `id_pasarela_<cosa>` y, donde haga falta saber cuál cobró, en una columna `pasarela`.

Cita: D → ADR-0016, regla 10 (C-192, DD-079)

#### Scenario: modelo-E14 — Un nombre con el proveedor no pasa

- **DADO** una tabla `eventos_stripe` o una columna `conekta_orden_id`
- **CUANDO** corre la prueba de esquema
- **ENTONCES** falla y nombra la tabla o la columna

### Requirement: Identificador público y modelos de Eloquent alineados con la base

Toda tabla que la API expone DEBE (MUST) tener `id_publico`, un ULID único, y la API DEBE (MUST) identificarla por
él: `usuarios`, `direcciones`, `lavanderias`, `repartidores`, `pedidos` y `pagos`. Cada modelo de Eloquent del negocio
DEBE (MUST) declarar su `$table` y su `$primaryKey`, iguales a los de la base.

Cita: A → `NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3` (separación por tipo de usuario); D → ADR-0016, regla 5

#### Scenario: modelo-E15 — Una fila nueva recibe su identificador público

- **DADO** una tabla expuesta por la API
- **CUANDO** se crea una fila nueva
- **ENTONCES** su `id_publico` es un ULID de 26 caracteres, distinto del de cualquier otra fila

#### Scenario: modelo-E16 — Un modelo que no coincide con la base no pasa

- **DADO** el modelo de Eloquent `Direccion`
- **CUANDO** la prueba compara su `$table` y su `$primaryKey` con la base
- **ENTONCES** son `direcciones` e `id_direcciones`, y si no coinciden la prueba falla
