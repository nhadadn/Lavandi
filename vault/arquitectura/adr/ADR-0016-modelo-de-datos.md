---
estado: vigente
grado: D
fuente: acta-2026-11-19-normativa-y-arranque.md C-187, C-189 y C-190, y acta-2026-11-20-modelo-y-pasarela.md C-192 a C-194 (C); NSV-2026-001-v8.pdf:4:Cláusula I y :20:ANEXO A, Sección A.3 (A); ADR-0004 (D)
fecha: 2026-11-19
dueño: Arthur Naranjo
adr: ADR-0016
supersede:
---
# ADR-0016 — El modelo de datos es relacional normalizado, multi-plaza y extensible por filas

## Contexto

- **El contrato obliga a crecer sin rediseñar la base:** la arquitectura debe permitir "su expansión a plazas
  adicionales de la República Mexicana sin necesidad de reescritura del sistema ni de rediseño de la base de datos"
  (`NSV-2026-001-v8.pdf:4:Cláusula I`). Es grado A.
- **El contrato dice qué guarda la base:** "usuarios registrados, lavanderías, repartidores, pedidos, pagos e
  historial completo"; y lo que no guarda: los números de tarjeta (`NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3`).
- **El motor ya está decidido:** PostgreSQL con PostGIS (ADR-0004, C-146).
- **Faltaba el modelo completo.** `modelo-multi-plaza.md` lo dejó como `ESPECIFICACIÓN PENDIENTE`, y las 26 specs del
  2026-11-19 definieron sus tablas cada una por su lado. Ya aparecieron huecos: ninguna spec creaba la tabla de
  horarios, y la de calificaciones solo contemplaba lavanderías.
- **Arthur pidió el modelo antes de implementar** (C-187), con esta condición: "apegarnos al contrato […] pero la base
  de datos hay que dejarla con la posibilidad y la escalabilidad: que quede mutable pero bien definida, normalizada,
  con una buena estructura y con buenas prácticas" (C-189).

## Criterios y pesos

Propuestos por Claude. Arthur los ajusta.

| Criterio | Peso | Por qué pesa así |
|---|---|---|
| Integridad de los datos | 30 | El contrato exige historial completo y separación entre socios (`:20`). Un dato inconsistente en un pago o en un pedido es un reclamo |
| Crecer sin rediseño | 25 | Obligación grado A (`:4:Cláusula I`), y lo que pidió Arthur (C-189) |
| Apego al contrato | 20 | `CLAUDE.md` §2-bis: no construir lo que el contrato no pide |
| Simplicidad con Laravel y Eloquent | 15 | Lo construye una persona, con agentes (C-132) |
| Velocidad de las consultas en tiempo real | 10 | La lista de pedidos se consulta cada 30 s (ADR-0007) |

## Alternativas evaluadas

Puntajes de 1 a 5.

| Alternativa | Integridad | Crecer | Apego | Simplicidad | Velocidad | Total ponderado | Notas |
|---|---|---|---|---|---|---|---|
| **A. Tablas por spec, sin modelo común** | 2 | 2 | 4 | 4 | 3 | 2.80 | Es lo que quedó el 2026-11-19. Cada spec acierta en lo suyo, pero las tablas compartidas quedan sin dueño y las reglas comunes se repiten o se contradicen |
| **B. Relacional normalizado, con catálogos, llaves compuestas de plaza e historial por filas** | 5 | 5 | 4 | 4 | 4 | **4.55** | Lo nuevo es una fila o una tabla nueva, nunca un cambio de lo existente. Dos excepciones escritas: la copia del precio y la dirección al confirmar, y el estado actual del pedido |
| **C. Esquema flexible con JSON** (`jsonb` para lo variable) | 2 | 4 | 3 | 3 | 3 | 2.95 | Cambia sin migraciones, pero la base deja de garantizar llaves, únicos y rangos. Se usa solo para los parámetros comerciales que el contrato deja abiertos (`parametros_plaza`) |

Cálculo de B: 5×0.30 + 5×0.25 + 4×0.20 + 4×0.15 + 4×0.10 = 4.55.

## Decisión

**El modelo de datos es el de `arquitectura/modelo-de-datos.md`: relacional, en tercera forma normal, multi-plaza
y extensible por filas.** Las reglas que lo hacen así:

1. Cada tabla existe por una función del contrato, con su cita. Lo excluido no tiene tabla. Lo que no está decidido
   nace cuando se responde su fila de la deuda.
2. Catálogos para los valores que son datos: roles, tipos de servicio, unidades de medida y estados del pedido.
   `CHECK` para los valores técnicos que solo cambian con el código.
3. Lo que se repite va en filas: servicios, direcciones, pagos, eventos y asignaciones de un pedido.
4. Toda tabla de operación lleva `id_plazas`, no nula salvo en cuatro tablas con una razón escrita en el modelo
   (`avisos_masivos`, `reportes_soporte`, `repartidores` pendientes y `direcciones` fuera de zona). En las tablas hijas,
   la llave foránea es compuesta con la plaza, para que no pueda diferir de la de su padre. Una llave hacia una tabla
   que nace después entra opcional y sin llave, y la agrega el cambio que crea esa tabla.
5. La llave interna es un `bigint` autoincremental llamado `id_<tabla>`, en minúsculas (`id_pedidos`,
   `id_tipos_servicio`), y la foránea lleva el nombre de la llave a la que apunta (C-190). Todo identificador empieza
   con `id_` y nunca termina en `_id` (C-194): lo que sale por la API usa un ULID público (`id_publico`), y lo que
   asigna la pasarela es `id_pasarela_<cosa>` (`id_pasarela_orden`).
6. Llaves foráneas con índice y `ON DELETE RESTRICT`. Borrado suave en cuentas, socios y direcciones; pedidos, pagos y
   eventos no se borran nunca.
7. Dinero en `numeric(12,2)`; instantes en `timestamptz` (y `time` para las horas del día de un horario); mapas en
   PostGIS `geography` con índice GiST. Toda tabla del negocio lleva `created_at` y `updated_at`, salvo las de enlace.
8. Tablas y columnas en español y en `snake_case`. Las del framework conservan su nombre, y cada modelo declara su
   `$table`.
9. Migraciones solo hacia adelante, una por cambio de OpenSpec; lo nuevo entra como opcional.
10. Ninguna tabla ni columna nombra a un proveedor de pagos: `eventos_pasarela`, `id_pasarela_orden`, y una columna
    `pasarela` donde haga falta saber cuál cobró (C-192). Cambiar de proveedor no toca la base.

**Decisión de Arthur Naranjo**, aprobada el 2026-11-20 (C-193).

## Consecuencias

- **Positivas:**
  - Abrir una plaza, sumar un rol, un servicio, una unidad o un pago de ajuste es insertar filas: cumple
    `:4:Cláusula I` sin rediseño.
  - La base impide por sí misma lo que el contrato prohíbe: un pedido con una lavandería de otra plaza, un número de
    tarjeta guardado o un pedido borrado.
  - Las 26 specs tienen un dueño claro para cada tabla.
- **Negativas, y qué se hace con ellas:**
  - **Más tablas que un diseño plano** (unas 30). Se mitiga con una migración por cambio y con el diagrama de la nota.
  - **Las llaves compuestas de plaza son menos comunes en Laravel.** Se escriben en la migración, y la prueba de
    esquema de `modelo-de-datos` las verifica (modelo-E06).
  - **El pluralizador de Laravel es inglés, y Eloquent espera `id` y `<singular>_id`.** Cada modelo declara su
    `$table` y su `$primaryKey`, y cada relación nombra sus llaves; el revisor lo comprueba.
- **Condiciona:**
  - El `design.md` y el `tasks.md` de cada spec, que nombran las tablas que crean y su migración.
  - La máquina de estados del pedido (DD-123), que fija los estados internos de `estados_pedido`.
  - El componente de autorización (DD-086, ADR-0017), que filtra por rol, tenencia y plaza sobre estas tablas.

## Criterio de reversión

Se reabre si una función del contrato no cabe sin modificar una tabla existente, o si una consulta de tiempo real
tarda más de 1 s con los datos de prueba de la plaza más grande.

## Costo estimado de revertir

Antes de la primera migración, nada. Después, una migración por tabla afectada; con datos reales, además, mover los
datos.

## Trazabilidad

- **Obligaciones que satisface:** O-26 (escalabilidad sin rediseño, `:4:Cláusula I`); la base de datos y las medidas
  de seguridad de la p. 20.
- **Restricciones que respeta:** RN-09 (separación entre socios) y RN-10 (datos de tarjeta).
- **Specs que dependen de él:** las 26 de `openspec/changes/`.
- **ADRs relacionados:** ADR-0004 (motor), ADR-0005 (acceso), ADR-0006 (fotos), ADR-0007 (tiempo real), ADR-0010
  (pasarela) y ADR-0017 (autorización, propuesto en DD-086).
