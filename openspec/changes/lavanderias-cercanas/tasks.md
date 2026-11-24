Cada tarea va en rojo, verde y refactor: primero el test que falla, luego el código mínimo, luego la limpieza. Una
tarea sin test en verde no se marca. Tamaños de S-006: S = 2 h, M = 5 h, L = 12 h. Requiere `plazas-y-zonas` y la
tarea 1.1 de `perfil-de-lavanderia`, la migración que crea `lavanderias` y `calificaciones`; en el plan, ese cambio va
después (ver `design.md`, Open Questions). Este cambio no crea tablas.

## 1. Lavanderías de prueba y consulta de cercanas

- [x] 1.1 cercanas-E06, cercanas-E07, cercanas-E10, cercanas-E11 (`servidor/`): lavanderías de prueba sembradas en
  `lavanderias`, con plaza, `ubicacion` y `estado`; consulta por punto con distancia en línea recta sobre
  `lavanderias.ubicacion`, que identifica cada lavandería por su `id_publico`, detrás de la sesión y del componente de
  autorización — test: `servidor/tests/Feature/Cercanas/ConsultaDeCercanasTest.php` — tamaño M
- [x] 1.2 cercanas-E22 (`servidor/`): el sembrado de prueba del hito no corre en producción — test:
  `servidor/tests/Feature/Cercanas/DatosDePruebaTest.php` — tamaño S
- [x] 1.3 cercanas-E16, cercanas-E17, cercanas-E18 (`servidor/`): calificaciones de prueba de lavandería sembradas en
  `calificaciones`; filtros por distancia máxima y calificación mínima, combinables, con el promedio de las filas que
  tienen `id_lavanderias` — test: `servidor/tests/Feature/Cercanas/FiltrosTest.php` — tamaño S
- [ ] 1.4 cercanas-E08, cercanas-E09, cercanas-E19, cercanas-E20 (`servidor/`): límite de cercanía configurable, resto
  de "activa", disponibilidad y orden por defecto — test: `servidor/tests/Feature/Cercanas/DefinicionDeCercanaTest.php`
  — tamaño S. **No entra al sprint** mientras DD-083 y DD-078 sigan abiertas

## 2. Mapa y ubicación en la app

- [x] 2.1 cercanas-E01, cercanas-E02 (`movil/`): `react-native-maps` sin proveedor; la llave de Google para Android
  desde la configuración del entorno — test: `movil/__tests__/cercanas/mapa-por-plataforma.test.tsx` — tamaño M
- [x] 2.2 cercanas-E03, cercanas-E04 (`movil/`): permiso de ubicación mientras se usa, con su texto, sin segundo plano;
  consulta con la ubicación actual — test: `movil/__tests__/cercanas/permiso-de-ubicacion.test.tsx` — tamaño S
- [ ] 2.3 cercanas-E05 (`movil/`): qué consulta la app sin permiso de ubicación — test:
  `movil/__tests__/cercanas/permiso-de-ubicacion.test.tsx` — tamaño S. **No entra al sprint** mientras DD-083 siga
  abierta

## 3. Pantalla principal

- [x] 3.1 cercanas-E12, cercanas-E14, cercanas-E15 (`movil/`): un pin por lavandería, su tarjeta (componente 1 del
  design system) y el paso al perfil; el estado vacío; el acceso rápido a pedido nuevo — test:
  `movil/__tests__/cercanas/pantalla-principal.test.tsx` — tamaño M
- [ ] 3.2 cercanas-E13 (`movil/`): el estado de disponibilidad en la tarjeta — test:
  `movil/__tests__/cercanas/pantalla-principal.test.tsx` — tamaño S. **No entra al sprint** mientras DD-083 y DD-078
  sigan abiertas

## 4. Pantalla de filtros

- [x] 4.1 cercanas-E21 (`movil/`): filtros de distancia y calificación con los cortes de la configuración; sin
  resultados, el aviso y quitar filtros — test: `movil/__tests__/cercanas/pantalla-de-filtros.test.tsx` — tamaño S.
  Los filtros viven en la lista "Elige tu lavandería"; los cortes, en `movil/src/configuracion/filtros.ts`, son los de
  ejemplo del mock A04 hasta que DD-083 fije los valores

## Horas

- Listas: 23 h (1.1, 1.2, 1.3, 2.1, 2.2, 3.1 y 4.1). Bloqueadas: 6 h (1.4, 2.3 y 3.2). Total: 29 h.
- El plan da 22 h a este cambio (F-T03 M, F-A03 L y F-A04 M). Diferencia: +7 h. Las listas quedan 1 h arriba del plan;
  el resto es lo que espera DD-083 y DD-078.
- **Con el modelo de datos (C-193) las horas no cambian.** Las tareas 1.1 y 1.3 ya no crean tablas, pero conservan su
  tamaño: les quedan la consulta espacial, el sembrado y los filtros. La migración de `lavanderias` y `calificaciones`
  se cuenta en `perfil-de-lavanderia`, tarea 1.1.
