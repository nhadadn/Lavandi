## Why

El contrato pide: "Cada tipo de usuario accede únicamente a la información que le corresponde. Un socio lavandería no
puede consultar los datos de otro socio, ni los de los usuarios que no le han hecho pedidos"
(`NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3`), con los cuatro módulos "accesibles y funcionales conforme a A.3"
(`NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3`). El componente de autorización nace en `registro-e-inicio-de-sesion`
con el rol de usuario (`CLAUDE.md` §9.5). Este cambio lo completa con los roles de socio lavandería, socio repartidor
y administración, la tenencia y la plaza (ADR-0003), y la prueba contra fugas (BR-35). Es el hito H5 del plan de
sprints.

## What Changes

- Completar en `servidor/` el componente único de autorización con los roles de socio lavandería, socio repartidor y
  administración.
- Reglas de tenencia: la lavandería ve lo suyo y a los usuarios que le pidieron; el repartidor, sus pedidos asignados;
  el usuario, lo suyo, de cualquier plaza.
- Regla de plaza para los socios. La administración ve todas las plazas.
- El panel web de Filament solo admite el rol de administración (ADR-0005).
- Una prueba que falla si una ruta no declara su regla, y un barrido contra fugas por rol, tenencia y plaza que corre
  en la integración continua (SEG-3).
- Quedan escritos con `SIN RESPUESTA` la cuenta con más de un rol, el socio sin aprobar y la entrada a cada módulo en la
  app (DD-053).

**Qué NO cambia**

- El inicio de sesión, los tokens de Sanctum y la sesión del panel: `registro-e-inicio-de-sesion` (ADR-0005).
- El registro y la aprobación de los socios: `alta-de-lavanderias` y `repartidor` (F-B01, F-C01, F-D01 y F-D02).
- Qué datos del usuario trae un pedido para la lavandería o para el repartidor: `pedidos-de-la-lavanderia` y
  `repartidor`.
- Qué puede hacer una cuenta suspendida, dada de baja o bloqueada: `alta-de-lavanderias`, `repartidor` y
  `administracion`. Este componente solo aplica esa regla, en un solo lugar.
- Qué entra en la vista reducida de administración de la app: `administracion` (DD-040, cerrada por C-140).
- Quién asigna los pedidos al repartidor: DD-014.

**Fuera del contrato**

- Nada pedido ni mostrado para este cambio. Si se llegaran a pedir varias cuentas por lavandería, una administración
  por plaza o permisos finos dentro de la administración, el contrato no los nombra: serían Orden de Cambio
  (`NSV-2026-001-v8.pdf:8:Cláusula IV`).

## Capabilities

### New Capabilities

- `autorizacion`: quién puede ver o tocar cada dato, por rol, tenencia y plaza, en la API y en el panel, con la prueba
  contra fugas.

### Modified Capabilities

Ninguna.

## Impact

- `servidor/`: el componente de autorización, donde lo haya dejado `registro-e-inicio-de-sesion`; la regla de cada ruta
  de la API y de cada recurso del panel; las pruebas en `servidor/tests/Feature/Autorizacion/` y un sembrado de dos
  plazas para el barrido.
- `movil/`: nada que entre ahora. La entrada por rol (autorizacion-E06) espera a DD-053.
- Base de datos: ninguna tabla nueva. El rol de cada cuenta vive en `roles` y `rol_usuario`, de
  `registro-e-inicio-de-sesion`, y la plaza en la columna `id_plazas` de cada tabla de operación
  (`vault/arquitectura/modelo-de-datos.md`; detalle en `design.md`). `repartidores` y `pedido_asignaciones` nacen
  después, en `repartidor` (H8).
- Dependencias: ninguna nueva. Sanctum y Filament ya están (ADR-0003, ADR-0005).
- ADRs: ADR-0003, ADR-0004, ADR-0005 y ADR-0016 (modelo de datos). Falta el ADR del mecanismo del componente (ver
  `design.md`).
- Trazabilidad: SEG-3 y A43-6.
