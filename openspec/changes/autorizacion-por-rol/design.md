## Context

- **Lo que pide el contrato (A):** cada tipo de usuario accede solo a lo que le corresponde, y un socio lavandería no ve
  a otro socio ni a los usuarios que no le han pedido (`NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3`). No dice cómo
  (BR-35).
- **Lo que ya decidieron los ADRs (D):**
  - ADR-0003: `api` concentra la autorización por rol, tenencia y plaza, y el panel de Filament vive en el mismo
    Laravel.
  - ADR-0005: la app usa tokens de Sanctum; el panel, sesión web con rol de administración.
  - ADR-0004: PostgreSQL, con el modelo multi-plaza.
- **De dónde viene el "un solo componente":** `vault/arquitectura/c3-api.md` (borrador, grado D) y `CLAUDE.md` §9.5. El
  componente nace en `registro-e-inicio-de-sesion` con el rol de usuario; aquí se completa.
- **DD-040 está cerrada** (C-140): panel web con el Módulo D completo más una vista reducida en la app. Qué entra en esa
  vista es de `administracion`.
- **DD-053 sigue abierta:** cómo llega cada rol a su módulo y si una cuenta puede tener dos roles.

## Goals / Non-Goals

**Goals:**

- Que las reglas de rol, tenencia y plaza vivan en un solo lugar de `servidor/`, también para el panel.
- Que ninguna ruta se publique sin regla (autorizacion-E01).
- Un barrido contra fugas que corra en cada cambio (autorizacion-E14, SEG-3).

**Non-Goals:**

- El inicio de sesión y la sesión (`registro-e-inicio-de-sesion`).
- Qué campos del usuario ve cada rol dentro de un pedido (`pedidos-de-la-lavanderia`, `repartidor`).
- Lo que puede hacer una cuenta suspendida, dada de baja o bloqueada (`alta-de-lavanderias`, `repartidor`,
  `administracion`).
- Varias cuentas por lavandería, una administración por plaza o permisos finos dentro de la administración: el contrato
  no los nombra.

## Decisions

No hay decisión nueva tomada aquí; lo sostienen ADR-0003, ADR-0004, ADR-0005 y ADR-0016:

1. **Toda decisión de acceso se toma en `servidor/`.** La app solo esconde lo que el servidor ya rechaza, porque un
   cliente móvil no puede custodiar el aislamiento (`c2-contenedores.md`; ADR-0003).
2. **El panel de Filament usa el mismo componente** que la API, con el rol de administración (ADR-0005).
3. **La plaza viene de la fila:** toda tabla de operación lleva `id_plazas` (ADR-0016, regla 4; ADR-0004;
   `CLAUDE.md` §9.5). `usuarios` no tiene plaza; la tiene su dirección (`direcciones.id_plazas`). Por eso la plaza
   restringe a los socios y no al usuario (autorizacion-E08).
4. **Las pruebas corren sobre PostgreSQL real** (`ciclo-openspec-tdd.md`), con un sembrado de dos plazas.
5. **La API identifica cada fila por su `id_publico`,** nunca por la llave interna (ADR-0016, regla 5). Las pruebas
   contra fugas piden los datos ajenos por `id_publico`.

## Tablas y migración

Este cambio no crea tablas ni migraciones (`vault/arquitectura/modelo-de-datos.md`, columna "Nace en"). Lee:

| Tabla | Qué usa el componente | La crea |
|---|---|---|
| `usuarios`, `roles`, `rol_usuario` | El rol de cada cuenta (`roles.codigo`: `usuario`, `lavanderia`, `repartidor`, `administracion`). `rol_usuario` ya admite varios roles por cuenta; que la v1 permita uno lo impone el código (DD-053) | `registro-e-inicio-de-sesion` |
| `lavanderias` | La tenencia del socio lavandería (`id_usuarios`), su plaza (`id_plazas`) y su `estado` (autorizacion-E05) | `perfil-de-lavanderia`; el alta real, `alta-de-lavanderias` |
| `pedidos` y sus hijas | De quién es el pedido (`id_usuarios`), qué lavandería lo atiende (`id_lavanderias`), qué repartidor lo reparte (`id_repartidores`) y su plaza | `resumen-y-confirmacion` |
| `direcciones` | La dueña (`id_usuarios`) | `direcciones` |
| `pagos` | El cobro, por su pedido (`id_pedidos`) y su plaza | `pago` |
| `calificaciones` | Lo que el perfil de la lavandería hace público | `perfil-de-lavanderia` |
| `repartidores`, `pedido_asignaciones` | La tenencia del socio repartidor (`id_usuarios`), su plaza, su `estado` y sus pedidos asignados | `repartidor` (H8), **después de este cambio** |

Como `repartidores` y `pedido_asignaciones` nacen en H8, la parte del repartidor de autorizacion-E02, autorizacion-E12
y autorizacion-E14, y todo autorizacion-E11, no se pueden probar en H5: se escriben con la lavandería en H5 y se
completan con el repartidor en H8.

## Implementación en el servidor

Lo que se decidió al implementar las tareas 1.1, 1.2, 1.3, 2.1, 2.2, 2.5, 3.2 y 4.1, por delegación de Arthur (C-236),
con lo mínimo que cumple cada escenario. El mecanismo lo fija ADR-0019: la clase `App\Autorizacion\Autorizacion` con el
middleware `autorizar:<rol>`, 403 al rol que no corresponde y 404 al dato ajeno. Ninguna decisión agrega una librería ni
un servicio externo, ni decide una fila abierta.

### La cobertura de reglas (tarea 1.1)

- **La prueba lee la tabla del enrutador,** no una lista escrita a mano (`servidor/tests/Feature/Autorizacion/Soporte/
  TablaDeRutas.php`). Cada ruta, como "MÉTODO uri" y sin HEAD, tiene una de tres reglas:
  - **componente:** en `api/`, la sesión de Sanctum y `autorizar:<rol>`;
  - **panel:** fuera de `api/`, la puerta del panel de Filament (`Filament\Http\Middleware\Authenticate`), que pregunta
    a `Autorizacion::puedeEntrarAlPanel()` por `Usuario::canAccessPanel()`;
  - **sin sesión:** declarada en `Autorizacion::rutasSinSesion()`, con su razón.

  Una ruta sin ninguna de las tres hace fallar `CoberturaDeReglasTest`, que la nombra. Recorre todas las rutas
  registradas: la API, la web de `routes/web.php`, el panel y las de Laravel, Sanctum, Livewire y Filament.
- **Las rutas sin sesión se declaran en el componente,** no en la prueba, porque la spec dice que "lo declaran
  expresamente" en él. Antes vivían en `tests/Feature/Registro/AutorizacionTest.php` (registro-E14), que ahora lee la
  misma declaración. **Una ruta nueva sin sesión, como el aviso de la pasarela de `pago`, se agrega ahí** con su razón;
  hasta entonces la prueba la nombra. Las de Livewire llevan un prefijo que sale de la llave de la aplicación, así que se
  arman con `Livewire::getUriPrefix()`.
- **Todo recurso, toda página y todo widget del panel** le niega la entrada a una cuenta de otro rol aunque pasara la
  puerta, con su propio `canAccess()` o `canView()` hacia el componente. Los tres de Filament que no muestran datos de la
  operación (la página de inicio, el widget de la cuenta y el de la versión) se declaran en `Autorizacion::panelSinDatos()`.

### El módulo de la app por rol: lectura provisional

Lo que aplica hoy la app no resuelve autorizacion-E06, que sigue `SIN RESPUESTA` (DD-053). `movil/src/sesion/modulos.ts`
abre el módulo del rol que da `servidor/` en la cuenta (`GET /api/cuenta` y `POST /api/sesion`): `usuario` al Módulo A,
`lavanderia` al B y `repartidor` al C; un rol sin módulo, como la administración, no abre sesión en la app. Es una
lectura provisional de "una sola app con los módulos" (`NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3`) con una cuenta de
un solo rol, y se prueba como registro-E33 (`movil/__tests__/sesion/modulo-por-rol.test.tsx`), no como E06. La puede
cambiar **DD-053**: si una cuenta puede tener dos roles, o cómo elige su módulo, cambia `moduloDelRol()` y esa prueba
pasa a E06. La seguridad no depende de esta pantalla: la sostiene autorizacion-E02 en `servidor/`.

### La matriz de rol por módulo (tarea 1.2)

- **El módulo de una ruta sale de su prefijo:** el B, `api/lavanderia/` y `api/socio/`; el C, `api/repartidor/`; el A, las
  demás. Solo cuatro rutas son comunes a los tres roles de la app, y se nombran en la prueba: la cuenta, el cierre de la
  sesión (registro-E33) y las dos del catálogo del territorio (C-238). La prueba falla si una ruta de un módulo admite
  otro rol en su `autorizar:<rol>`, aunque el middleware la deje pasar como se le pidió.
- **Cada llamada corre en su propio punto de guardado de la base** y se deshace al terminar
  (`Soporte/LlamadasSinEfecto.php`): así se recorren también `DELETE api/cuenta` y `DELETE api/sesion` sin que una
  llamada cambie la siguiente.
- **Las respuestas se revisan sin el detalle de depuración** (`app.debug` apagado en la prueba), como salen en
  producción: un 403 trae solo `{"message": "Esta cuenta no tiene acceso."}`.

### El panel solo para la administración (tarea 1.3)

- **Se extiende `PanelSoloAdministracionTest`,** que nació con `alta-de-lavanderias` y ya probaba la contraseña, la
  sesión web en `/admin` y `/admin/lavanderias` y las lavanderías del componente. Se agrega el recorrido de cada ruta
  del panel desde la tabla de rutas: un recurso o una página nueva entra sola.
- **La ficha de cada recurso abre un registro que la administración ve en él,** tomado de la consulta del propio
  recurso, y la administración tiene que recibir 200 en cada página. Las otras cuentas, 403 sin el nombre de la
  lavandería.
- **Una cuenta por rol en todo el recorrido:** la puerta del panel guarda en la sesión la cuenta que entró
  (`AuthenticateSession`), y cambiar de cuenta en cada petición la cerraba.

### El barrido de rutas (tareas 2.1, 2.5 y 4.1)

- **Un solo recorrido para las tres** (`Soporte/BarridoDeRutas.php`): una cuenta llama a cada ruta de la API con su
  sesión, y en cada parámetro de la ruta pide, uno por uno, los datos que no le corresponden. Se espera 403 sin datos si
  la ruta no admite su rol, 404 sin datos si la admite y el parámetro es ajeno (ADR-0019), y ninguna respuesta con un
  dato ajeno. Los datos ajenos se buscan como texto en la respuesta: van en ASCII, para que el JSON no los escape.
- **Un parámetro que el barrido no conoce hace fallar la prueba,** que lo nombra: una ruta nueva con un parámetro nuevo,
  como un cobro, no pasa sin revisar. Hoy los parámetros son `{pedido}`, `{direccion}`, `{lavanderia}` y
  `{codigo_postal}`. El perfil de una lavandería es público para el usuario (Módulo A #5); para el socio, una lavandería
  ajena es un dato ajeno.
- **El cuerpo y la consulta también llevan los datos ajenos** (`recogida`, `entrega`, `pedido`, `direccion`), con un
  pedido que pasa la validación, para que una ruta que los reciba en el cuerpo también se revise.
- **Cada barrido tiene su prueba de que detecta:** una ruta de prueba que devuelve un dato ajeno hace fallar el barrido,
  que nombra la ruta, el rol y el dato.
- **E07 reusa** confirmacion-E12 y direcciones-E14, que prueban el detalle de cada ruta, y recorre todas. **El cobro**
  se agrega cuando `pago` cree `pagos` y sus rutas.

### El sembrado de dos plazas (tareas 3.2 y 4.1)

- **Vive en las pruebas** (`Soporte/DosPlazasDePrueba.php`), no en `database/seeders/`: es un dato de prueba, no de
  desarrollo. En cada plaza hay dos **cadenas**: un usuario con su dirección guardada, una lavandería con su cuenta de
  socio y un repartidor activo, con dos pedidos del usuario a esa lavandería, uno en curso (aceptado por la lavandería y
  por el repartidor) y uno entregado. Las cuatro cadenas no se cruzan, así que lo de una no le corresponde a nadie de
  las otras, y hay una cuenta de administración.
- **Cada dato que identifica a alguien lleva la marca de su cadena** (`BarridoUsuarioUnoA`, `BarridoCalleDosB`,
  `barrido.repartidor.unob@ejemplo.com`), para buscarlo en las respuestas.
- **E13 junta las dos plazas** en el componente (`*DelPanel()`), en las listas del panel, en el monitoreo y en la ficha
  del usuario; el detalle de cada pantalla lo prueban `alta-de-lavanderias`, `repartidor` y `administracion`.

### El barrido contra fugas en la integración continua (tarea 4.1)

- **Cada rol entra con la cuenta de la cadena UnoA,** y la administración con la suya, y pide lo de las otras tres
  cadenas. Lo ajeno depende del rol: al usuario, el perfil de las lavanderías es público y la otra plaza no le es ajena
  (E08); a los socios, una lavandería ajena y la otra plaza sí lo son (E12); a la administración, que no tiene módulo en
  la app, todo lo de la app le es ajeno.
- **Corre en su propio paso** de `.github/workflows/ci.yml`, "Barrido contra fugas", con las pruebas del grupo
  `barrido-contra-fugas` de PHPUnit: `CoberturaDeReglasTest` (E01) y `BarridoContraFugasTest` (E14). El paso "Tests"
  corre las demás con `--exclude-group`, así que no se repiten. En local, `php artisan test` corre todas.
  Recomendación de Claude: el paso propio, para que una fuga o una ruta sin regla se vea sola en la integración
  continua, y no entre las casi mil pruebas.

## Risks / Trade-offs

- [Una ruta nueva sin regla expone datos] → autorizacion-E01 y autorizacion-E14 corren en cada pull request.
- [Filament consulta los modelos directamente y puede saltarse las reglas de la API] → El mecanismo del componente
  tiene que servir también al panel (ver Open Questions); autorizacion-E03 y autorizacion-E13 lo prueban.
- [Las rutas del Módulo B llegan en el mismo hito, y las del Módulo C en H8] → autorizacion-E09 y autorizacion-E10 se
  escriben con la primera ruta del Módulo B; autorizacion-E11, cuando `repartidor` cree `repartidores` y
  `pedido_asignaciones`. El cambio no se puede archivar completo antes de H8.
- [DD-053 abre cuentas con más de un rol] → La tabla no cambia: `rol_usuario` ya lo admite (ADR-0016). Cambian el
  código del componente y autorizacion-E04. Cuesta poco si se decide antes del sprint 11.
- [Responder "prohibido" confirma que el dato existe] → Se decide junto con el mecanismo (Open Questions).

## Open Questions

- **Necesita ADR: el mecanismo del componente** (propuesta: ADR-0017, autorización; el ADR-0016 es el modelo de datos). Opciones:
  - (a) las Policies y Gates de Laravel por recurso, más ámbitos de consulta que filtran por tenencia y plaza;
  - (b) solo Policies, comprobadas en cada controlador;
  - (c) un servicio propio.

  Recomendación de Claude: (a), porque Filament aplica las Policies de Laravel a sus recursos (grado E, `SIN
  VERIFICAR` en Filament 5) y porque, con los ámbitos, una consulta que olvide su regla no devuelve nada. **Si
  `registro-e-inicio-de-sesion` ya fijó el mecanismo, este cambio lo sigue.** Lo decide Arthur antes de
  autorizacion-E01.
- **Qué responde `servidor/` a un acceso denegado:** 403, o 404 para no confirmar que el dato existe. Va en el mismo ADR.
- **Cómo se guarda el rol de una cuenta:** resuelta por el modelo de datos (C-193): `rol_usuario`, que crea
  `registro-e-inicio-de-sesion`. Si una cuenta puede tener más de uno sigue en DD-053, y solo toca el código.
- **¿Se archiva en H5 lo de la lavandería y la administración, y se abre otro cambio para el repartidor en H8?** Lo
  decide Arthur.
