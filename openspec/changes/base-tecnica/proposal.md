## Why

La app y su servidor todavía no existen. El contrato pide "un solo programa que funciona tanto en iPhone como en
teléfonos Android" (`NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3`), un servidor que procese "cada pedido, cada inicio
de sesión y cada consulta de los usuarios" (`NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3`) y una base de datos con
"usuarios registrados, lavanderías, repartidores, pedidos, pagos e historial completo"
(`NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3`). Este cambio crea `servidor/` y `movil/` con lo mínimo para que el
registro (hito H1) arranque el lunes 23 de noviembre.

## What Changes

- Se crea `servidor/`: Laravel 13 con Sanctum y Filament 5 (ADR-0003), conectado al PostgreSQL con PostGIS local
  (ADR-0004), con una ruta que confirma que la API responde.
- La primera migración habilita PostGIS, y las migraciones corren desde cero sin pasos a mano (ADR-0004).
- El panel web de administración de Filament existe y pide iniciar sesión. En este cambio nadie puede entrar: el rol
  de administración llega con su cambio (ADR-0003, ADR-0005).
- Se crea `movil/`: React Native con Expo SDK 58, en TypeScript y con development builds (ADR-0002), con Jest,
  `jest-expo` y React Native Testing Library.
- La app declara como mínimos iOS 18 y Android 11 (ADR-0009).
- La dirección de la API, las llaves y las credenciales salen de la configuración del entorno. Solo existe el entorno
  local (ADR-0012, DD-070).
- El nombre del producto y el dominio salen de configuración, nunca de un texto escrito a mano (`CLAUDE.md` §5 y §9.3).

**Qué NO cambia:**

- No se crea ninguna tabla del negocio. Usuarios, plazas, lavanderías, pedidos y pagos llegan con su cambio
  (`registro-e-inicio-de-sesion`, `plazas-y-zonas` y los siguientes).
- No hay entorno de prueba ni de producción mientras DD-070 siga abierta (ADR-0012).
- Las compilaciones firmadas, el envío a las tiendas y las comunicaciones cifradas van en `publicacion` (hito H11).
- No hay mapas, avisos, pagos ni correo.
- La integración continua ya existe (`.github/workflows/ci.yml`). Con este cambio, sus trabajos `servidor` y `movil`
  dejan de saltarse.

**Fuera del contrato:**

- Nada. Para este cambio nadie pidió algo sin cita.

## Capabilities

### New Capabilities

- `base`: el servidor, la base de datos y la app única, creados y en verde, con la configuración por entorno y la
  marca fuera del código.

### Modified Capabilities

- Ninguna.

## Impact

- **`servidor/`:** proyecto nuevo, con `composer.json`, `.env.example`, la primera migración y
  `tests/Feature/Base/`.
- **`movil/`:** proyecto nuevo, con `package.json`, la configuración de Expo y `__tests__/base/`.
- **Base de datos:** PostgreSQL 17 con PostGIS 3.5 en Docker, en la máquina de Arthur (C-166). La extensión se
  habilita en la primera migración.
- **Dependencias:** Laravel 13, Sanctum y Filament 5; Expo SDK 58 con `expo-dev-client`, Jest, `jest-expo` y React
  Native Testing Library. Las versiones exactas se confirman al crear los proyectos (grado E).
- **ADRs:** ADR-0002, ADR-0003, ADR-0004, ADR-0009 y ADR-0012.
- **Integración continua:** empiezan a correr los trabajos `servidor` y `movil` de `ci.yml`.
