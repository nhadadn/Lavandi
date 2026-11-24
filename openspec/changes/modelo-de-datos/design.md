## Context

El ADR-0016 fijó el modelo de datos y Arthur lo aprobó el 2026-11-20 (C-193). Las tablas, sus columnas y el cambio que
crea cada una están en `vault/arquitectura/modelo-de-datos.md`. Este cambio no crea tablas del negocio: hace que las
reglas comunes se cumplan solas, con una prueba que falla cuando una migración las rompe. Lo construye una persona con
agentes (C-132), así que la prueba sustituye a la revisión a ojo de cada migración.

## Goals / Non-Goals

**Goals:**

- Una prueba de esquema que, después de migrar, revisa las tablas del negocio contra las reglas de la spec.
- Que cada modelo de Eloquent use estos nombres sin repetir código en cada clase.
- Que la base, y no solo el código, rechace borrar el historial y mezclar plazas.

**Non-Goals:**

- Crear tablas del negocio: cada cambio crea las suyas.
- Integrar la pasarela: Conekta, la del contrato, entra con `pago`; el posible cambio a Stripe o Mercado Pago es
  DD-079.
- Definir la máquina de estados del pedido: es DD-123.

## Decisions

1. **La prueba lee los catálogos de PostgreSQL.** `information_schema.columns`, `table_constraints`,
   `referential_constraints` y `pg_index` dicen nombres, tipos, llaves y reglas de borrado. Corre después de
   `migrate:fresh`, en el trabajo `servidor` de la integración continua. Cada escenario negativo crea una tabla de
   prueba dentro de una transacción que se deshace: se comprueba que la prueba la rechaza sin ensuciar la base.
   *Alternativa descartada:* revisar los archivos de migración con expresiones regulares, que no ve lo que de verdad
   quedó en la base.
2. **Una lista cerrada de excepciones, en un solo archivo** (`servidor/config/modelo.php`):
   - **Del framework:** `migrations`, `jobs`, `job_batches`, `failed_jobs`, `cache`, `cache_locks`, `sessions`,
     `personal_access_tokens`, `password_reset_tokens`, y las de PostGIS (`spatial_ref_sys`). Quedan fuera de toda
     regla.
   - **Del framework, además:** las de la exportación de Filament (`exports`, `imports`, `failed_import_rows`) y
     `notifications`. Sus llaves hacia el usuario se adaptan a `usuarios.id_usuarios`, porque la tabla `users` de
     Laravel no se crea (grado E, sin verificar en Filament 5).
   - **Sin plaza, por diseño:** los cuatro catálogos, `plazas`, `usuarios`, `rol_usuario`, `cuentas_externas`,
     `dispositivos`, `actividad_usuarios`, `eventos_pasarela`, `metodos_pago_guardados` y `movimientos_pasarela`.
   - **Plaza opcional:** `avisos_masivos` (vacía, el aviso es para todas las plazas), `comisiones` (vacía, junto con
     la lavandería, en la comisión global, que es una para todas las plazas: `administracion`, Open Questions 4),
     `reportes_soporte` (vacía si
     la cuenta no tiene de dónde tomarla: el reporte se guarda igual, perfil-usuario-E17), `repartidores` (vacía solo si `estado` es `pendiente`, con su `CHECK`) y `direcciones`
     (vacía si el punto no cae en ninguna zona).

   Una tabla nueva que no esté en la lista debe llevar `id_plazas`. Agregarla a la lista es una decisión visible en el
   diff.
3. **Reglas de tipo por nombre de columna.** El dinero se reconoce por su nombre: `monto`, `monto_*`, `precio`,
   `precio_*` y `envio`. Los porcentajes, por `porcentaje` y `*_porcentaje`; las cantidades, por `cantidad_*`. Los
   instantes son lo que termina en `_en` o `_at` o empieza con `vigente_` o `ventana_`; las horas del día, lo que
   empieza con `hora_`, van en `time`. Así los nombres del modelo son también su
   contrato de tipos.
4. **El historial se protege con triggers.** Una función `impedir_cambio()` lanza un error en `BEFORE DELETE` sobre
   `pedidos`, `pedido_eventos`, `pagos` y `reembolsos`, y en `BEFORE UPDATE` sobre `pedido_eventos`. Cada uno se crea en
   la migración de su tabla. Es la forma en que la base cumple "no se borran nunca" (ADR-0016, regla 6). `TRUNCATE` y
   `DROP` no disparan los triggers, así que las pruebas pueden recrear la base.
   *Alternativa descartada:* confiar solo en el código, que un comando de consola o un error de un agente pueden
   saltarse.
5. **Una clase base para los modelos, `App\Models\Modelo`.** `getForeignKey()` devuelve su llave primaria (`id_<tabla>`),
   así que `hasMany` encuentra la foránea sin nombrarla. Cada modelo sigue declarando `$table` y `$primaryKey` (la spec
   lo verifica), y cada `belongsTo` nombra su llave, porque Eloquent la deduce como `<relación>_id`.
6. **Una llave hacia una tabla que nace después.** La columna entra opcional y sin llave foránea, y el cambio que
   crea la tabla destino agrega la llave en su migración: `repartidor` la agrega en `pedidos.id_repartidores` y en
   `calificaciones.id_repartidores`; `historial-y-calificacion`, en `calificaciones.id_pedidos`. La prueba modelo-E10
   solo revisa las llaves que ya existen.
7. **Las tablas de enlace no tienen modelo de Eloquent.** `rol_usuario` se maneja con `belongsToMany` y
   `actividad_usuarios` con el constructor de consultas; por eso no entran en modelo-E16 ni llevan `created_at`.
8. **El identificador público usa el `HasUlids` de Laravel.** `uniqueIds()` devuelve `['id_publico']` y
   `getRouteKeyName()` devuelve `id_publico`, así que las rutas de la API reciben el ULID y nunca el `bigint`.

## Risks / Trade-offs

- [Un agente agrega una tabla a la lista de excepciones para que la prueba pase] → La lista vive en un solo archivo,
  y el revisor revisa cualquier cambio en ella contra `modelo-de-datos.md`.
- [Las reglas por nombre no reconocen un monto con otro nombre, como `total`] → La regla de "ningún `real` ni
  `double precision`" lo atrapa de todos modos. Un nombre de dinero nuevo se agrega a la regla en este cambio.
- [Los triggers estorban si alguien quiere corregir un pedido a mano] → Se corrige con un evento nuevo o una fila de
  ajuste, nunca borrando: es lo que pide el historial completo.
- [Eloquent espera `id`] → La clase base y la prueba modelo-E16 lo cubren (ADR-0016, consecuencias).

## Migration Plan

No hay datos todavía. La prueba de esquema y la clase base entran antes que la primera tabla del negocio
(`registro-e-inicio-de-sesion`), así que esa tabla ya nace revisada. Para revertir, basta quitar la prueba; no hay
nada que mover.

## Open Questions

- Los valores de la columna `pasarela` (`CHECK`): `conekta`, la del contrato, y la que se elija si el cliente firma el
  cambio de pasarela (DD-079), que entra en una migración hacia adelante.
- La lista de estados internos de `estados_pedido` es DD-123; no cambia ninguna regla de este cambio.
