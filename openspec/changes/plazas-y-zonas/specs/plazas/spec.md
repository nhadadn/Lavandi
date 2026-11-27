## ADDED Requirements

### Requirement: Ficha de la plaza

El servidor DEBE (MUST) guardar cada plaza como un área operativa con nombre, zona horaria y estado (activa o no),
sin atarla a un estado ni a un municipio. La zona horaria es obligatoria: las ventanas de recolección son de una hora
local y la República tiene más de un huso.

Cita: A → `NSV-2026-001-v8.pdf:4:Cláusula I` y `NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #7`; D → ADR-0004

#### Scenario: plazas-E01 — La plaza guarda su zona horaria

- **DADO** una plaza de prueba con una zona horaria distinta de la del territorio inicial
- **CUANDO** se guarda y se pide la hora local de un instante
- **ENTONCES** el instante queda guardado como absoluto
- **Y** se presenta en la hora de esa plaza

#### Scenario: plazas-E02 — Sin zona horaria válida no hay plaza

- **DADO** una ficha de plaza sin zona horaria, y otra con una zona horaria que no existe
- **CUANDO** se intenta guardar cada una
- **ENTONCES** las dos se rechazan
- **Y** no queda ninguna plaza nueva

#### Scenario: plazas-E15 — Una vigencia termina después de empezar

- **DADO** una plaza de prueba
- **CUANDO** se guarda un parámetro de la plaza cuya vigencia termina antes de empezar, o en el mismo instante
- **ENTONCES** la base lo rechaza
- **Y** un parámetro sin fin de vigencia, o con fin posterior al inicio, se guarda

Decisión: DD-134, punto 5 (C-234). Impedir que dos vigencias de la misma clave se traslapen espera al primer cambio
que agregue un parámetro.

### Requirement: Zonas de servicio de cada plaza

El servidor DEBE (MUST) guardar las zonas de servicio como polígonos geográficos, cada uno ligado a su plaza. Una zona
sin plaza no existe.

Cita: A → `NSV-2026-001-v8.pdf:4:Cláusula I`; D → ADR-0004 y ADR-0009

#### Scenario: plazas-E03 — Una zona siempre es de una plaza

- **DADO** una plaza de prueba
- **CUANDO** se guarda una zona con su polígono y su plaza, y otra zona sin plaza
- **ENTONCES** la primera queda guardada con su polígono
- **Y** la segunda se rechaza

#### Scenario: plazas-E12 — Dos zonas no se enciman

- **DADO** una plaza de prueba con una zona guardada
- **CUANDO** se carga con el comando un archivo con una zona que comparte área con esa zona, sea de la misma plaza o
  de otra, o un archivo con dos zonas que comparten área entre sí
- **ENTONCES** la carga se rechaza y el mensaje nombra las dos zonas
- **Y** no queda guardado nada del archivo
- **Y** dos zonas que solo se tocan en el borde sí se cargan

Decisión: DD-134, punto 1 (C-234). Sin encimados, un punto cae a lo más en una zona.

### Requirement: Resolver un punto

El servidor DEBE (MUST) decir, para un punto con latitud y longitud, en qué zona activa y en qué plaza activa cae, o
que no cae en ninguna. Una plaza o una zona que no está activa no da servicio. La respuesta depende del punto, nunca
de la cuenta de quien pregunta.

Cita: A → `NSV-2026-001-v8.pdf:4:Cláusula I` y `NSV-2026-001-v8.pdf:38:ANEXO C, Sección C.3`; D → ADR-0004

#### Scenario: plazas-E04 — Un punto dentro de una zona

- **DADO** una plaza de prueba activa con una zona
- **CUANDO** se resuelve un punto dentro de esa zona
- **ENTONCES** el servidor devuelve esa zona y esa plaza

#### Scenario: plazas-E05 — Un punto fuera de toda zona

- **DADO** una plaza de prueba activa con una zona
- **CUANDO** se resuelve un punto fuera de todas las zonas
- **ENTONCES** el servidor responde que el punto no cae en ninguna plaza

#### Scenario: plazas-E06 — Una plaza que no está activa no da servicio

- **DADO** una plaza de prueba que no está activa, con su zona
- **CUANDO** se resuelve un punto dentro de esa zona
- **ENTONCES** el servidor responde que no hay plaza activa para ese punto

#### Scenario: plazas-E13 — Una zona que no está activa no da servicio

- **DADO** una plaza de prueba activa con dos zonas, una activa y otra que no lo está
- **CUANDO** se resuelve un punto dentro de cada zona
- **ENTONCES** el de la zona activa resuelve a esa zona y a su plaza
- **Y** el de la zona que no está activa responde que no hay servicio para ese punto

Decisión: DD-134, punto 2 (C-234).

#### Scenario: plazas-E07 — El usuario no pertenece a una plaza

- **DADO** un usuario registrado y dos plazas de prueba activas, cada una con su zona
- **CUANDO** se resuelve un punto de cada plaza
- **ENTONCES** cada punto resuelve a su plaza
- **Y** la cuenta del usuario no guarda ninguna plaza

### Requirement: Abrir una plaza es cargar datos

El servidor DEBE (MUST) dar de alta una plaza y sus zonas cargando un archivo de datos con un comando, sin migraciones
de esquema ni código nuevo. No hay pantalla para hacerlo, porque el Módulo D no la enumera.

Cita: A → `NSV-2026-001-v8.pdf:4:Cláusula I`, `NSV-2026-001-v8.pdf:37:ANEXO C, Sección C.3` y `NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D`

#### Scenario: plazas-E08 — Una plaza nueva sin una sola migración

- **DADO** el sistema con todas sus migraciones aplicadas
- **CUANDO** se carga con el comando el archivo de una plaza de prueba nueva, activa, con una zona
- **ENTONCES** un punto dentro de esa zona resuelve a la plaza nueva
- **Y** la tabla de migraciones tiene las mismas filas que antes de la carga
- **Y** volver a cargar el mismo archivo no duplica la plaza ni su zona

Sostiene O-26, que no tiene criterio en el A.4; el criterio propio está en `vault/arquitectura/modelo-multi-plaza.md`
(grado D). La prueba del recorrido completo en la plaza nueva se completa cuando exista el pedido.

#### Scenario: plazas-E14 — Al recargar, la zona que ya no viene se desactiva

- **DADO** una plaza de prueba cargada con dos zonas activas
- **CUANDO** se vuelve a cargar su archivo con una sola de las dos
- **ENTONCES** la zona que ya no viene queda guardada, pero no activa
- **Y** un punto dentro de ella responde que no hay servicio
- **Y** si una carga posterior la vuelve a traer activa, se reactiva sin duplicarse

Decisión: DD-134, punto 3 (C-234). No se borra porque las filas de operación que vengan pueden apuntar a ella.

#### Scenario: plazas-E16 — Una plaza de prueba en tierra para probar desde el teléfono

- **DADO** un entorno que no es producción
- **CUANDO** se siembran los datos de prueba
- **ENTONCES** queda, además de las plazas de prueba en el océano, una plaza de prueba con una zona sobre la ciudad de
  Durango, con su nombre de prueba
- **Y** en producción ese sembrado no corre

Decisión: DD-134, punto 7 (C-234). Es solo para probar desde un teléfono; no es el territorio inicial (DD-093,
DD-094) y no cuenta para plazas-E10.

### Requirement: Toda fila de operación lleva su plaza

El servidor DEBE (MUST) exigir, en cada tabla que describe operación (`zonas`, `parametros_plaza`, `lavanderias`,
`lavanderia_servicios` con su precio, `lavanderia_horarios`, `lavanderia_fotos` y `calificaciones`, y las que vengan:
`pedidos`, `repartidores`, `comisiones`), una plaza obligatoria, `id_plazas`, ligada a la tabla `plazas`. Las tablas
sin plaza quedan fuera, en la lista cerrada de `modelo-de-datos`: las del framework, los catálogos, `plazas`, las de
cuentas y las de la pasarela.

Cita: A → `NSV-2026-001-v8.pdf:4:Cláusula I`; D → ADR-0004 y ADR-0016

#### Scenario: plazas-E09 — La prueba de esquema exige la plaza

- **DADO** el esquema con todas sus migraciones aplicadas
- **CUANDO** la prueba revisa cada tabla que no está en la lista de tablas sin plaza de `modelo-de-datos`
  (`servidor/config/modelo.php`: las del framework, los catálogos, `plazas`, `usuarios`, `rol_usuario`,
  `cuentas_externas`, `dispositivos`, `actividad_usuarios`, `eventos_pasarela`, `metodos_pago_guardados` y `movimientos_pasarela`)
- **ENTONCES** cada una tiene su columna `id_plazas` con llave foránea a `plazas`, no nula salvo en las cinco de
  plaza opcional (`avisos_masivos`, `comisiones`, `reportes_soporte`, `repartidores` y `direcciones`)
- **Y** si una tabla nueva no la tiene ni está en la lista, la prueba falla

### Requirement: Territorio inicial cargado

El servidor DEBE (MUST) traer, como datos cargados con el comando de apertura, las plazas y zonas del TERRITORIO
INICIAL —todos los municipios del Estado de Durango y los de Torreón y Matamoros, Coahuila— y ninguna zona fuera de él.

Cita: A → `NSV-2026-001-v8.pdf:4:Cláusula I`

#### Scenario: plazas-E10 — Los puntos del territorio inicial tienen plaza

- **DADO** el territorio inicial cargado
- **CUANDO** se resuelve un punto del centro de Durango capital, de Gómez Palacio, de Lerdo, de Torreón y de Matamoros,
  Coahuila
- **ENTONCES** cada uno resuelve a una zona de una plaza activa

SIN RESPUESTA — bloquea: plazas-E10 (DD-093: plazas del territorio inicial; DD-094: zonas del
territorio inicial)

#### Scenario: plazas-E11 — Nada fuera del territorio inicial

- **DADO** el territorio inicial cargado
- **CUANDO** se resuelve un punto de Saltillo, Coahuila; de Monterrey, Nuevo León, y de Matamoros, Tamaulipas
- **ENTONCES** ninguno resuelve a una plaza

SIN RESPUESTA — bloquea: plazas-E11 (DD-093: plazas del territorio inicial; DD-094: zonas del
territorio inicial)
