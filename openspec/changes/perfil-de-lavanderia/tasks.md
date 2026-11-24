Cada tarea va en rojo, verde y refactor: primero el test que falla, luego el código mínimo, luego la limpieza. Una
tarea sin test en verde no se marca. Tamaños de S-006: S = 2 h, M = 5 h, L = 12 h. La tarea 1.1 requiere
`modelo-de-datos`, `registro-e-inicio-de-sesion` y `plazas-y-zonas`; las demás requieren además `lavanderias-cercanas`,
que a su vez lee las tablas de la tarea 1.1 (ver `design.md`, Open Questions).

## 1. Perfil en el servidor

- [x] 1.1 perfil-lavanderia-E14, perfil-lavanderia-E15, perfil-lavanderia-E16, perfil-lavanderia-E17 y la prueba de
  esquema de `modelo-de-datos` (modelo-E01 a modelo-E17) sobre las tablas nuevas (`servidor/`): migración de
  `tipos_servicio` y `unidades_medida` con su semilla, `lavanderias`, `lavanderia_servicios`, `lavanderia_horarios`,
  `lavanderia_fotos` y `calificaciones`, con las columnas, los únicos y los `CHECK` de `design.md` ("Tablas y
  migración"); modelos de Eloquent sobre `App\Models\Modelo`, con su `$table`, su `$primaryKey` y las llaves de cada
  relación. Necesita las preguntas abiertas de la fecha de la reseña y de `codigo` único de `design.md` — test:
  `servidor/tests/Feature/PerfilLavanderia/MigracionTest.php` y `servidor/tests/Feature/Modelo/` — tamaño M
- [x] 1.2 perfil-lavanderia-E01, perfil-lavanderia-E02, perfil-lavanderia-E05, perfil-lavanderia-E07,
  perfil-lavanderia-E08 (`servidor/`): servicios de prueba sembrados en `lavanderia_servicios`; consulta del perfil por
  `id_publico`, con descripción, servicios activos con su precio y su unidad, promedio, número de calificaciones y
  reseñas sin datos de quien califica; suspendida o dada de baja, sin perfil — test:
  `servidor/tests/Feature/PerfilLavanderia/PerfilTest.php` — tamaño M
- [ ] 1.3 perfil-lavanderia-E06 (`servidor/`): precio y unidad del lavado en seco — test:
  `servidor/tests/Feature/PerfilLavanderia/LavadoEnSecoTest.php` — tamaño S. **No entra al sprint** mientras DD-010
  siga abierta
- [ ] 1.4 perfil-lavanderia-E18 (`servidor/`): las calificaciones al repartidor no cuentan en el perfil de la
  lavandería — test: `servidor/tests/Feature/PerfilLavanderia/PerfilTest.php` — tamaño S. **No entra al sprint**
  mientras DD-052 siga abierta, ni antes de que existan `pedidos` y `repartidores`

## 2. Fotos

- [x] 2.1 perfil-lavanderia-E03, perfil-lavanderia-E04 (`servidor/`): disco S3 privado del entorno; fotos de prueba
  sembradas en `lavanderia_fotos`, con su orden; URLs firmadas que vencen — test:
  `servidor/tests/Feature/PerfilLavanderia/FotosTest.php` — tamaño M. El contrato está en `design.md`, "Contrato de la
  API: las fotos del perfil". En producción, el disco `fotos_s3` espera su librería y el hosting (DD-070)

## 3. Pantalla del perfil

- [x] 3.1 perfil-lavanderia-E09, perfil-lavanderia-E10 (`movil/`): fotos, descripción, servicios con precio por kilo,
  calificación y reseñas; sin calificaciones, el texto y ningún promedio — test:
  `movil/__tests__/perfil-lavanderia/pantalla-de-perfil.test.tsx` — tamaño M. Las fotos, con la tarea 2.1: arriba
  del perfil, en el orden del servidor, una a la vez con su contador, cada una desde su URL firmada; sin fotos, el
  perfil empieza con el nombre. La app no renueva las URLs: al volver a entrar pide el perfil otra vez
  (`movil/src/perfil/FotosDeLaLavanderia.tsx`)

## 4. Selección de servicio

- [x] 4.1 perfil-lavanderia-E11, perfil-lavanderia-E12, perfil-lavanderia-E13 (`movil/`): elegir un tipo de los que
  ofrece la lavandería y guardarlo en el pedido en curso; sin tipo no se avanza — test:
  `movil/__tests__/perfil-lavanderia/seleccion-de-servicio.test.tsx` — tamaño S. Un solo tipo mientras DD-098 siga
  abierta. El paso de fecha y hora (`movil/app/pedido/fecha-y-hora.tsx`) es provisional, solo con su encabezado,
  hasta que lo llene `agenda-de-recoleccion`

## Horas

- Listas: 22 h (1.1, 1.2, 2.1, 3.1 y 4.1). Bloqueadas: 4 h (1.3 y 1.4). Total: 26 h.
- El plan da 12 h a este cambio (F-T06 M, F-A05 M y F-A06 S). Diferencia: +14 h (antes de alinear con el modelo de
  datos eran +7). F-T06 y F-A06 coinciden con el plan; F-A05 se partió en servidor y app, y cada mitad pesa una M
  (+5 h); el lavado en seco suma 2 h bloqueadas.
- **Lo que suma el modelo de datos (C-193):** +7 h. La migración de las siete tablas con sus modelos, que antes se
  repartía entre este cambio y `lavanderias-cercanas` y ahora vive entera aquí, con los horarios que ninguna spec
  creaba (tarea 1.1, M, +5 h), y la calificación del repartidor, bloqueada por DD-052 (tarea 1.4, S, +2 h). Las tareas
  1.2 y 2.1 ya no crean tablas, pero conservan su tamaño.
