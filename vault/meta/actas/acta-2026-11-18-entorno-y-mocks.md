---
estado: vigente
grado: C
fuente: sesión de Arthur Naranjo con Claude Code del 2026-11-18, después del cierre de la Fase 8
fecha: 2026-11-18
dueño: Arthur Naranjo
---
# Acta — 2026-11-18 — Entorno local y mocks

Participantes: Arthur Naranjo · Claude Code.
Fase(s) de la sesión: preparación del sprint 1. El cimiento cerró en [[acta-2026-11-18-fase-8-instrumental]].

Continúa la numeración de [[acta-2026-11-18-fase-8-instrumental]], que llega hasta C-163.

## Declaraciones

Arthur respondió a los cuatro pasos para preparar el sprint 1:

- C-164. **C (Arthur).** Al punto 1, la cuenta de Apple para probar en iPhone (DD-069): "Ok, esperamos."
- C-165. **C (Arthur).** Al punto 2, la cuenta de GitHub del repositorio de código (DD-073) y quién abre las cuentas
  de prueba (DD-072): "Pues la misma que tiene Nadir." Cuando Claude le preguntó a qué se aplicaba, respondió:
  "Ok, si hablamos de GitHub, pues el repo ya está; o si no, ¿a qué te refieres? Porque en las respuestas dices
  fork (el cual no entiendo esa jerga), ¿o a qué cuentas te refieres?"

  La pregunta no estaba clara. Claude la volvió a explicar sin jerga, y Arthur respondió con C-169 y C-170.
- C-166. **C (Arthur).** Al punto 3, instalar lo que falta del entorno local: "Ok, instálalo."
- C-167. **C (Arthur).** Al punto 4, la primera spec: "Habría que construir los mocks, ¿verdad?… Mmm, ok, hazlos,
  pero no pongas Lavandi; pon algo así: mocks Desarrollo Temsa L, así tal cual, para identificarlo yo. Y vamos
  viendo todos los mocks dentro de ese; me interesa ir viendo todo desde los mocks." Sobre dónde guardarlos, eligió
  "Dentro del repo de Lavandi", la opción recomendada.
- C-168. **C (Arthur).** "XAMPP no lo uso ya; en App_Ittob y Temsa uso el `npm run dev` y `php artisan serve`."
- C-169. **C (Arthur).** A quién crea el repositorio nuevo del código: "Pero no hay que generar uno nuevo; hay que
  seguir en este de Lavandi." **Reemplaza a C-156:** el código vive en este repositorio, en `movil/` y `servidor/`.
  Cierra DD-073.
- C-170. **C (Arthur).** Con qué correo se abren Firebase, Google Cloud, Conekta en modo prueba y Expo: "Con el
  tuyo", la opción recomendada, que en la pregunta quería decir el correo de Arthur. Las abre Arthur; antes de
  producción pasan a cuentas del cliente (ADR-0012). Cierra DD-072.
- C-171. **C (Arthur).** Invocó Claude Design (`/design`) preguntando si había acceso, y aclaró: "Ok, los mocks, si
  los vas a guardar local, la carpeta que sí diga mocks; pero me refería a que, si usabas Claude Design para
  generarlos con /design, entonces ahí sí le pusieras Desarrollo Temsa L, por favor." El diseño se creó en Claude
  Design con ese nombre: https://claude.ai/artifact/NxESkTp9RKZ4zRFJGDCd85.
- C-172. **C (Arthur).** "Ok, ya vi lo que dijiste. Ojo: tengo este video que explica lo que es Lavandi, y me
  pasaron su inventario con precios." Son un video promocional de 61 s y seis fotos de una libreta de precios,
  llegados por WhatsApp el 2026-11-14. Se archivan en `cliente/recibido/2026-11-14/` y la libreta se transcribe en
  [[lista-de-precios-recibida-2026-11-14]].
- C-173. **C (Arthur).** Sobre la libreta: "Los títulos grandes serían el grupo de servicios, y lo de debajo, sus
  servicios con precio como tal."
- C-174. **C (Arthur).** "Oye, el branch, bueno, ya lo publiqué, pero no debería llamarse así; las ramas sí
  respétalas con Lavandi. La carpeta sí, solo te pedí que la llamaras así, pero ya no; cámbialo a Lavandi (solo
  lo de Claude Design que se mantenga igual)." **Reemplaza en parte a C-167:** la rama pasa a `mocks/lavandi`, la
  carpeta local a `mocks-lavandi/` y el nombre visible en los mocks locales a Lavandi. El diseño de Claude Design
  conserva "Desarrollo Temsa L".
- C-175. **C (Arthur).** "La carpeta mocks Desarrollo Temsa L elimínala y deja la de mocks nada más, por favor." Y
  enseguida: "Sí, que quede todo en mocks, por favor." **Corrige a C-174 en el nombre de la carpeta:** es `mocks/`,
  no `mocks-lavandi/`. Lo que quedaba en la carpeta vieja (`index.html`, `LEEME.md` y las propuestas 02 y 03 de
  Arthur) se movió a `mocks/` sin sobrescribir nada, y la carpeta vieja se borró vacía.
- C-176. **C (Arthur).** Pidió agregar al diseño de Claude Design la "PROPUESTA 2" con las imágenes de GPT de
  `mocks/Lavandi - Propuesta 01` y `03`, después de renombrar su primer título a "PROPUESTA 1". Autorizó el push:
  "Sí" (`mocks/lavandi` subida al fork y la rama vieja borrada de ahí).
- C-177. **C (Nadir, por Arthur).** Sobre el kit que "trae el mock de cómo debe quedar": "Me dice que el 1 y 3
  deberían de ser", es decir, el Kit para sumar lavanderías (`cliente/kit-de-prospeccion/`) y la identidad de marca
  de Claude Design (`diseno/lavandi-identidad/`). Arthur: ponerlo como "PROPUESTA 3 (Nadir)"; "no es una propuesta,
  pero es ya ver los mocks que en teoría ya fueron aprobados". Se agregó al diseño la lámina 5 del kit (la app de la
  lavandería) y las seis láminas de identidad. La identidad usa Quicksand y Figtree, no las tipografías vigentes.
  **Grado C por confirmar:** lo transmitió Arthur y Nadir no lo ha asentado en su sesión (revisión de Nadir del PR #2).

## Decisiones tomadas en la sesión

| # | Decisión | Opciones consideradas | Quién decide | Nota resultante |
|---|---|---|---|---|
| 1 | Se instaló el entorno local: extensiones de PHP, Composer 2.11.2, PostgreSQL 17.5 con PostGIS 3.5.2 en Docker, `eas-cli` y `cloudflared` | — | Arthur (C-166) | [[entorno-local]] |
| 2 | Los mocks se construyen antes de la primera spec, dentro de este repositorio. **Nombres según C-174 y C-175:** carpeta `mocks/`, rama `mocks/lavandi` y nombre visible Lavandi. Antes eran `mocks Desarrollo Temsa L/` y `mocks/desarrollo-temsa-l` | Una carpeta aparte, fuera del repositorio | Arthur (C-167, C-174, C-175) | `mocks/LEEME.md` |
| 3 | Los mocks son páginas HTML navegables, sin compilación, con los tokens y los componentes del design system y un panel de anotaciones con la función, la cita y lo pendiente de cada pantalla. Se construyen por módulo; el primero es el del usuario (A) | Una app de Expo con datos simulados; Figma | Claude, dentro de C-167 ("hazlos"). Arthur lo corrige si prefiere otro formato | Ídem |
| 4 | La cuenta de Apple para probar en iPhone espera | — | Arthur (C-164) | DD-069 |
| 5 | El código vive en este repositorio. La integración continua del código y sus verificaciones pasan a la raíz, y el esqueleto se borra. Al entregar, solo se separan `movil/` y `servidor/` | Un repositorio aparte (C-156) | Arthur (C-169) | [[repositorios-ramas-y-revision]]; CLAUDE.md §9 |
| 6 | Las cuentas de prueba las abre Arthur con su correo | Abrirlas Nadir | Arthur (C-170) | DD-072; [[entorno-local]] |
| 7 | Los mocks también se hacen en Claude Design, en un diseño llamado "Desarrollo Temsa L", con el módulo del usuario en 18 pantallas navegables. Usan los tokens del design system, los textos del video del cliente y los precios de su libreta | Solo los mocks locales | Arthur (C-171) | https://claude.ai/artifact/NxESkTp9RKZ4zRFJGDCd85 |
| 8 | La libreta se transcribe como dos grupos de servicio, Tintorería (precio por prenda) y Lavandería y planchaduría (peso y precio por prenda), más una página de artículos por pieza | — | Arthur (C-173) | [[lista-de-precios-recibida-2026-11-14]] |

## Preguntas abiertas que salieron de la sesión

| # | Pregunta | Bloquea | Fila en `deuda-de-decision.md` |
|---|---|---|---|
| 1 | ¿En qué cuenta de GitHub se crea el repositorio nuevo del código, y quién abre las cuentas de servicio de prueba? | — | **Resuelta en la misma sesión** por C-169 y C-170: DD-072 y DD-073, cerradas |
| 2 | ¿De quién es la libreta de precios? ¿Es la lavandería propia del cliente? | Usar sus precios como referencia del catálogo | DD-066 |
| 3 | "Entre más prendas agregues, mayor será tu beneficio", con escalones de 2, 4, 8 y 16 prendas: ¿qué beneficio da cada escalón, y quién lo paga? | La pantalla de servicio y el cálculo del cobro | DD-076 |
| 4 | El video promete ver "en qué ubicación va tu pedido". El contrato no exige rastreo del repartidor (`contrato/inventario-funcional.md`): ¿se refiere al estado o al mapa? | El alcance del seguimiento | DD-077 |
| 5 | ¿Falta una hoja de la libreta? Por las letras de la agenda (AB, CD, EF, MN, OP), entre la página de enfrente de EF y la MN podría haber una que no se fotografió | Tener la lista completa | Se pregunta con la confirmación de la lista |
| 6 | La libreta cobra por prenda, por pieza y por m², y el contrato habla de precio por kilo. Cobrar por prenda ya estaba entre las ideas del cliente fuera del contrato (C-129). Además, el cliente marcó "Estandarización de precios (los manejaría Lavandi)" (2026-11-05, en papel): ¿precio por lavandería o único de la plataforma? | El modelo de precio del catálogo | BR-16, BR-46 y DD-010 |

## Datos de la sesión

- La libreta muestra que, en "Lavandería y planchaduría", el precio de cada prenda es su peso por **$40 el kilo**
  (pantalón de vestir: 0.350 kg, $14.00; sudadera: 0.800 kg, $32.00). Es un cálculo de Claude sobre la lectura de
  la libreta, no una regla que el cliente haya dicho. El detalle está en [[lista-de-precios-recibida-2026-11-14]].
- En la libreta aparecen "Tenis". El calzado está fuera del contrato (DD-046).
- **Los dos juegos de mocks cobran distinto.** El de Claude Design sigue la libreta: grupo de servicio, prendas con
  peso de referencia a $40 por kilo y tintorería por prenda. Los locales (`mocks/`) siguen el contrato: lavado
  estándar, con planchado y en seco, por kilo y con "$ —". Lavar por kilo con pesos de referencia es compatible con
  el contrato. La tintorería por prenda toca BR-16 (la unidad del lavado en seco), que sigue abierta. Cuál de los
  dos es la referencia lo decide Arthur antes de la spec del pedido.
- **Hallazgos del agente de los mocks** (medidos, grado B):
  - `design-system.md` declara 7.22:1 para `accion` sobre `superficie` y 11.23:1 para el foco; se midió 4.95:1 en
    los dos. Cumplen, pero el número está mal. `accion` sobre `superficie_hundida` da 4.09:1, por debajo de 4.5 para
    texto, y no está en su tabla.
  - El encabezado "Modo claro y oscuro, los dos desde la v1" sigue en `design-system.md`, aunque C-141 dejó solo el
    claro.
  - La lista de tiendas para pagar en efectivo no coincide entre DD-056 y el ADR-0010.
  - Los mocks locales escriben "Lavandi" porque Arthur lo pidió (C-174, C-175). No es código de producto: la regla
    de `{{MARCA}}` de CLAUDE.md §5 sigue valiendo para el código.
