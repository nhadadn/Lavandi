---
estado: borrador
grado: mixto
fuente: plantillas/plantilla-sprint.md (B); sprints/plan-de-sprints.md (B); NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #2, :20:A.3 (Control de acceso y Medidas de seguridad) y :6:Cláusula III (A); ADR-0003, ADR-0005, ADR-0013 y ADR-0014 (D); mocks/usuario/a02-registro.html y a02-inicio-sesion.html (B); deuda-de-decision.md DD-053, DD-069 y DD-071 (B); acta-2026-11-17-fase-7-formas-de-trabajo.md C-157 y C-158 (C); supuestos.md S-006 (S)
fecha: 2026-11-19
dueño: Arthur Naranjo
---
# Sprint 01 — del 2026-11-23 al 2026-11-29

Primer sprint de la app (hito H1 de [[plan-de-sprints]]). **Objetivo:** que una persona se registre con su correo y
su contraseña, inicie sesión y, al volver a abrir la app, siga dentro. Es la primera mitad del cambio
`registro-e-inicio-de-sesion`.

**Comprometido:** 10 h. **Capacidad:** unas 14 h (C-134). La hora que sobra de las 11 queda de margen, o adelanta la
tarea siguiente de la lista.

## Tareas comprometidas

| # | Tarea (spec y escenarios) | Función (inventario) | Tamaño | Agente | Rama | Estado |
|---|---|---|---|---|---|---|
| 1 | Servidor: registro con correo y contraseña para el rol de usuario; inicio y cierre de sesión con token de Sanctum; contraseña guardada con hash (SEG-2); correo repetido y datos inválidos rechazados. Incluye la fábrica de usuarios para los tests | F-A02, F-T07, F-T09 | M (5 h) | `implementador-servidor` | `feat/registro-correo` | por hacer |
| 2 | App: pantallas de registro y de inicio de sesión contra la API, con los errores a la vista, y la sesión que se conserva al volver a abrir la app. Incluye crear las piezas de formulario con los tokens (ADR-0013) | F-A02, F-T07 | M (5 h) | `implementador-movil` | `feat/registro-pantallas` | por hacer |

Estados: `por hacer` · `en curso` · `en revisión` · `terminada` · `sale del sprint`.

**Lo que viene en el sprint 02:**
- el inicio de sesión con Google en Android (ADR-0005);
- la recuperación de la contraseña por correo (F-T13, `NSV-2026-001-v8.pdf:30:ANEXO B, Sección B.5`);
- el inicio de sesión con Apple, cuando haya cuenta (DD-069).

## Definition of Ready

| # | Lo que cumple | Lo que le falta |
|---|---|---|
| 1 | Tamaño M. Criterio: SEG-2 y A43-6, Módulo A en parte. No depende de DD-053, que solo toca a los socios. Sin pantalla | **La spec `registro-e-inicio-de-sesion` aprobada** (tarea 5 del [[sprint-00]]). Los escenarios los numera el especificador |
| 2 | Tamaño M. Diseño: los mocks `mocks/usuario/a02-registro.html` y `a02-inicio-sesion.html`. El registro no tiene componente en el design system (`contrato/inventario-funcional.md`, sección 8), así que la tarea incluye crear las piezas | **La misma spec.** Además, `movil/` creado y en verde (tarea 3 del sprint 00) |

**Lo que no entra, y por qué:**
- **La verificación del correo:** el contrato no la pide (`:16:A.3 #2`) y ningún ADR la decide. Entra solo si se
  escribe su ADR (`CLAUDE.md` §2-bis.4).
- **El proveedor de correo** (DD-071) no hace falta todavía. Este sprint no manda correos, y en local se revisan sin
  enviarlos.
- **Los socios:** su registro espera a DD-053 y va en H5.

## Lo que entró a mitad del sprint, y lo que salió a cambio

## Cierre

- **Horas:** 10 comprometidas vs. reales, en [[registro-de-horas]]. Es el primer dato para recalibrar S-006 al cerrar
  el sprint 02.
- **Criterios del A.4 que cambiaron de estado:** SEG-2.
- **PR semanal hacia Nadir:** C-158.
- **Reporte semanal al cliente:** el primero. Lo redacta el `redactor-cliente` y lo envía Nadir
  (`NSV-2026-001-v8.pdf:6:Cláusula III`; protocolos pendientes de aprobar, DD-074).
- **Demostración:** registro, inicio de sesión y reapertura en un Android, grabada.
- **Qué cambiamos:**
