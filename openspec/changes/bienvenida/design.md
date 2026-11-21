## Context

- Es F-A01, en el sprint 03, del 7 al 13 de diciembre de 2026, con 2 h en el plan
  (`vault/formas-de-trabajo/sprints/plan-de-sprints.md`).
- Parte de `base` (la app y el nombre desde configuración) y de `registro` (las pantallas a las que lleva).
- Los textos y las imágenes son de EL CLIENTE (DD-081, límite sugerido el 7 de diciembre). Los mocks
  `mocks/usuario/a01-bienvenida.html` y `a01-informativas.html` traen relleno: orientan, no son el contenido.

## Goals / Non-Goals

**Goals:**

- La bienvenida y las tres informativas, con su contenido en un archivo aparte, listas para recibir el de EL CLIENTE
  sin tocar las pantallas.

**Non-Goals:**

- Redactar los textos o elegir las imágenes: son de EL CLIENTE.
- El ícono y la pantalla de arranque nativa.
- Decidir cuándo se vuelve a mostrar la bienvenida (bienvenida-E02).

## Decisions

No hay decisión técnica no trivial. Lo sostienen ADR-0002 (la app) y ADR-0013 (los tokens del design system). El
nombre del producto sale de la configuración que deja `base`. Ninguno de los seis componentes del design system cubre
estas pantallas (`vault/contrato/inventario-funcional.md`, sección 8): se arman con los tokens.

## Tablas y migración

No crea ni usa tablas: todo vive en `movil/` y el servidor no cambia. No hay migración, ni prueba de esquema de
`modelo-de-datos` que correr. Las pantallas a las que lleva (registrarse o iniciar sesión) usan las tablas de
`registro-e-inicio-de-sesion`, no esta.

## Risks / Trade-offs

- [El contenido de EL CLIENTE no llega para el 7 de diciembre] → Las pantallas se construyen con contenido
  provisional y F-A01 no se acredita (DD-081). No detiene otra tarea.
- [El contenido vive en la app y cambiarlo pide una versión nueva] → Es lo mínimo que cumple. Un ajuste de texto o de
  imagen es un "ajuste menor de contenido" del soporte (`NSV-2026-001-v8.pdf:23:ANEXO A, Sección A.7`). Servirlo
  desde el servidor sería construir de más.

## Open Questions

1. **Cuándo se muestran la bienvenida y las informativas:** solo la primera vez, en cada apertura sin sesión o también
   con la sesión abierta. Bloquea bienvenida-E02. Pregunta de pantalla para Arthur (DD-092).
2. **Los atajos del mock:** "Saltar" en las informativas y "Ya tengo cuenta" desde la primera pantalla. El contrato no
   los nombra y el mock los marca "sin fila". ¿Se ponen? Pregunta de pantalla para Arthur.
3. **Qué lleva cada pantalla:** el mock pone un lema y una ilustración en la bienvenida, y título, texto e ilustración
   en cada informativa. Es estructura de pantalla, no contenido; la confirma Arthur y la llena EL CLIENTE.
4. **La "pantalla de bienvenida" del contrato** ("Splash & Onboarding") puede leerse como la pantalla de arranque
   nativa o como una pantalla propia, que es lo que dibuja el mock. Esta spec sigue al mock; si Arthur la lee como la
   nativa, cambia el requisito 1.
