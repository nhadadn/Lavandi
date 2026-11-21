Cada tarea se hace en rojo, verde y refactor: primero el test que falla, con el identificador del escenario en su
nombre; luego el código mínimo que lo pone en verde; luego la limpieza. Las dos tareas corren en paralelo, cada una
en su worktree, y terminan con su trabajo de la integración continua en verde.

## 1. Servidor

- [ ] 1.1 base-E01, base-E02, base-E03, base-E08 y base-E10: crear `servidor/` con Laravel 13, Sanctum y Filament 5, sin la tabla `users`, el modelo `User` ni la fábrica que
  trae Laravel (su lugar es `usuarios`, de `registro-e-inicio-de-sesion`),
  conectado al PostGIS local; la primera migración habilita PostGIS; el panel pide iniciar sesión y no deja entrar a
  nadie; `.env.example` sin secretos; nombre y dirección desde configuración — test:
  `servidor/tests/Feature/Base/ArranqueTest.php`, `servidor/tests/Feature/Base/PanelTest.php` y
  `servidor/tests/Feature/Base/ConfiguracionTest.php` — tamaño S

## 2. App

- [ ] 2.1 base-E04, base-E05, base-E06, base-E07 y base-E09: crear `movil/` con Expo SDK 58 en TypeScript, con
  development build, Jest, `jest-expo` y React Native Testing Library; mínimos iOS 18 y Android 11; dirección de la API,
  identificador de la app y nombre desde configuración — test: `movil/__tests__/base/arranque.test.tsx` y
  `movil/__tests__/base/configuracion.test.ts` — tamaño S

## Horas

- **Este archivo:** 2 tareas S = 4 h.
- **El plan:** 6 h para el hito H0 (`vault/formas-de-trabajo/sprints/plan-de-sprints.md`). Las 2 h que faltan son
  la spec (tarea 1 del sprint 00, 1 h) y la instalación de las skills externas (tarea 4, 1 h), que no tienen test y
  por eso no están aquí.
