---
estado: vigente
grado: B
fuente: acta-2026-11-20-modelo-y-pasarela.md, C-246 a C-248 y decisión 69; ADR-0022; openspec/changes/administracion-en-la-app/
fecha: 2026-11-26
dueño: Arthur Naranjo
rama: feat/h4-admin-en-app
situacion: terminada
toca: openspec/changes/administracion-en-la-app/, servidor/ (API de la administración), movil/app/administracion/, mocks/Lavandi - Propuesta 06 - Administracion/, vault/arquitectura/adr/ADR-0022-administracion-en-la-app.md
---
# La administración completa dentro de la app móvil, y el video final

**Para qué.** Arthur vio en el emulador que la administración lo mandaba a una página web y pidió que todo el Módulo D
viva en la app, con su propio inicio de sesión (C-247, decisión 69). El contrato pone el panel de administración dentro de
la "Aplicación única para iPhone y Android" (`NSV-2026-001-v8.pdf:3:Cláusula I`), y su contenido es el del Módulo D
(`:19:ANEXO A, Sección A.3`) y el del A.5 (`:22`). Pidió además el video final con el emulador, con un pedido de prueba
que cruce cliente, recolector y administrador (C-246, C-247).

**Toca.** La API de la administración en `servidor/`, el módulo `movil/app/administracion/`, la spec
`administracion-en-la-app` y el ADR-0022. Ninguna otra tarea en curso toca lo mismo.

**Lo hecho.**
- **El diseño técnico.** El ADR-0022 y la spec, con 63 escenarios `administracion-app-E`, los escribieron un arquitecto
  y un especificador. Tres jueces los revisaron, se corrigieron sus 5 bloqueantes y se unieron en `227c8f4`.
- **Los mocks, la Propuesta 6,** por concilio (C-248):
  - tres diseñadores propusieron, con tres enfoques: operación primero, tablero primero y consistencia con los otros
    módulos;
  - tres jueces eligieron 3 de 3 la de consistencia, y se le injertó lo mejor de las otras;
  - el verificador final pidió tres correcciones y aprobó la segunda versión.

  Son 70 pantallas, en `mocks/Lavandi - Propuesta 06 - Administracion/` (unidas en `72f16a6`) y en el lienzo:
  https://claude.ai/artifact/PDFYFzpi5iXKw5zzAHx3rY#artboard-P6-01-inicio-de-sesion (versión 27).
- **Las tomas del video** del cliente, la lavandería y el repartidor, con el pedido de prueba SJN67S4E:
  `notas-locales/video-final-tomas/`.

- **El lote 1** (A01 a A04): la sesión de la administración, su grupo de rutas, las reglas que pasaron de Filament a
  clases de dominio y la entrada del módulo en la app. Aprobados los cuatro, sin bloqueantes; unidos en `c998b7a`.
- **La spec al pie de la letra de la Propuesta 6** (`1acadf4`): lo que los mocks dejaron en "Lo que queda abierto" de
  su `NOTAS.md` quedó fijado en la spec y en el design.md antes de las pantallas.

**Lo que se cerró el 2026-11-26.**
- **Los 24 paquetes.** Están unidos en `feat/h4-admin-en-app`, cada uno con dos revisores, su corrección y su
  re-verificación.
- **Los contratos de la API.** Al grabar la app real contra el servidor real aparecieron cuatro diferencias que el
  servidor simulado escondía. Están corregidas, con pruebas de contrato que las detectan
  (`servidor/tests/Contratos/administracion/`, `movil/__tests__/administracion/contratos.test.ts`).
- **Las tareas 3.3, 4.3 y 10.3.** La 4.3 se resolvió con la decisión 70.
- **La spec.** Tiene 59 de 80 tareas hechas y probadas; las 21 abiertas esperan una DD de EL CLIENTE o de Nadir.
- **Las pruebas.** La app pasa 2,039 de 2,039 y el servidor 1,483 de 1,483 en la suite completa, más los contratos.
- **El video final.** Está en `notas-locales/lavandi-video-final-2026-11-26.mp4`, con el capítulo del administrador.
- **El PR.** https://github.com/nhadadn/Lavandi/pull/6

**Lo que sigue en otra tarea.**
- **El tablero de la administración** (C-249): el inicio pasa a ser un tablero, por concilio.
- **Los puntos técnicos de la auditoría de Nadir al PR #4.**

**Al terminar.** Terminada, con el PR #6 y el video.
