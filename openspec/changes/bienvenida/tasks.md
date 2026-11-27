Cada tarea se hace en rojo, verde y refactor: primero el test que falla, con el identificador del escenario en su
nombre; luego el código mínimo que lo pone en verde; luego la limpieza.

## 1. App: bienvenida e informativas (sprint 03)

- [x] 1.1 bienvenida-E01, bienvenida-E03, bienvenida-E04 y bienvenida-E05: la pantalla de bienvenida y las tres
  informativas, con su contenido en un archivo aparte, que al terminar llevan a registrarse o iniciar sesión — test:
  `movil/__tests__/bienvenida/bienvenida.test.tsx` — tamaño S
- [x] 1.2 bienvenida-E02: la bienvenida solo la primera vez; con la sesión abierta, entrada directa (C-225; era la 2.1,
  bloqueada por DD-092) — test: `movil/__tests__/bienvenida/cuando-se-muestra.test.tsx` — tamaño S
- [x] 1.3 Ajuste visual de la bienvenida, las tres informativas y la entrada a las imágenes de
  `mocks/Lavandi - Propuesta 04/Usuario/` (C-230), sin "Saltar" ni "Ya tengo cuenta" (C-225) — test: los de las
  tareas 1.1 y 1.2, en verde — tamaño S
- [x] 1.4 bienvenida-E04 y bienvenida-E02: la entrada desaparece y su lugar lo toma el inicio de sesión del mock, con
  el logotipo en un archivo de nombre neutro y la pila reiniciada al llegar (C-237; DD-131, punto 3) — test:
  `movil/__tests__/navegacion/inicio-de-sesion.test.tsx` y los de las tareas 1.1 y 1.2 — tamaño S

## 2. Bloqueadas: no entran a un sprint hasta que se responda su fila

- [ ] 2.2 bienvenida-E06: cargar el contenido final de EL CLIENTE. Bloqueada por DD-081 — test:
  `movil/__tests__/bienvenida/bienvenida.test.tsx` — tamaño S

## Horas

- **Que se pueden hacer hoy:** 2 tareas S = 4 h. **El plan:** 2 h en el sprint 03. La 1.2 suma 2 h: C-225 la
  desbloqueó el 2026-11-20.
- **Bloqueadas:** 1 tarea S = 2 h, que no está en el plan.
