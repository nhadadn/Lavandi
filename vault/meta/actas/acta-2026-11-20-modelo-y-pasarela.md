---
estado: vigente
grado: C
fuente: sesión de Arthur Naranjo con Claude Code del 2026-11-19, pasada la medianoche
fecha: 2026-11-20
dueño: Arthur Naranjo
---
# Acta — 2026-11-20 — Modelo de datos aprobado y pasarela abierta

Participantes: Arthur Naranjo · Claude Code.
Continúa la numeración de [[acta-2026-11-19-normativa-y-arranque]], que llega hasta C-191.

## Declaraciones

- C-192. **C (Arthur), sobre la pasarela (DD-079).** "Ok, por mientras la opción hay que dejarla como que se puede
  usar Stripe o Mercado Pago; hay que continuar." Responde a C-191: Nadir lo pacta con el cliente por escrito.
- C-193. **C (Arthur), sobre las specs y el modelo de datos.** "Los specs ya me parecen bien con lo que te dije; si
  hay que reordenar lo demás, sería usar un /opsx:propose, re-proponer." Responde a la pregunta de si aprobaba el
  modelo de datos para meterlo en las specs (C-187).
- C-194. **C (Arthur), sobre los nombres de las columnas.** "Este ejemplo que me das, `pasarela_orden_id`, está mal:
  el id siempre debe ir de primero y nunca de último; o sea, debería y debe ser `Id_pasarela_orden`." Amplía C-190.
- C-195. **C (Arthur), sobre git.** "Sí, adelante. Igual desarrolla una skill que tenga el manejo y dominio total de
  git, para que realices los push correctamente; en las notas que realices de avances no generes ni apliques ningún
  comentario que diga by Claude, Claude Code, etc." Queda en `.claude/skills/git-lavandi/SKILL.md`, en `CLAUDE.md` §7
  y en `formas-de-trabajo/repositorios-ramas-y-revision.md`.
- C-196. **C (Arthur), sobre la historia ya subida.** "Lo de Nadir no hay tema; en lo mío sí. Así que así como te lo
  pido está bien de aquí en adelante." Ante la pregunta de qué limpiar, eligió solo la rama `arranque/lavandi` de su
  fork: 17 commits con atribución que no están en el repositorio de Nadir. Las ramas viejas (`mocks/lavandi`, las de
  las fases y `chore/claude-openspec-setup`) y el repositorio de Nadir quedan como están.
- C-208. **C (Arthur), sobre el pull request a Nadir.** "Ok, haz el PR, porfa." Responde a la propuesta de integrar
  primero el PR #1 que Nadir tenía abierto en su repositorio (`claude/dazzling-gates-53oar8`) y abrir uno solo.
- C-209. **C (Arthur).** "Hay temas de merge; si se va a hacer, haz merge sin que tenga conflictos, respetando y
  fusionando ambos lados, porfa."
- C-210. **C (Arthur).** "Sin que se pierdan datos."
- C-221 (Arthur Naranjo). "Trae los commits sin que se pierda info de A ni de B." Sobre los 7 commits que Nadir subió
  a su PR #1 después de abrirse el PR #2 (C-211 a C-220 de Nadir). C-211 a C-220 los numeró Nadir; esta sigue después.
- C-222 (Arthur Naranjo). A la pregunta de con qué se navega entre pantallas en la app: "expo-router (Recomendado)".
  Ver [[ADR-0017-navegacion-de-la-app]].
- C-223 (Arthur Naranjo). A la pregunta de si aprueba las reglas de sesión y recuperación: "Paquete recomendado
  (Recomendado)". El paquete: token en el almacenamiento cifrado del teléfono; contraseña de 8 caracteres mínimo y 5
  intentos por minuto, configurables; recuperación en una página web del servidor con un enlace de 60 minutos; la
  misma respuesta exista o no la cuenta; la contraseña nueva cierra las sesiones abiertas. Ver
  [[ADR-0018-sesion-y-recuperacion]].
- C-224 (Arthur Naranjo). Sobre DD-091, alguien con cuenta de correo y contraseña que entra con Google con el mismo
  correo: "Se unen si Google verificó el correo (Recomendado)". Si Google no lo da por verificado, se rechaza.
- C-225 (Arthur Naranjo). Sobre las pantallas del registro y la bienvenida: "apegado al contrato". Lo dijo en lugar de
  elegir una de las opciones; la más cercana era "Lo mínimo del contrato". **La lectura en detalle es de Claude y la
  corrige Arthur:** el registro pide solo correo y contraseña (el nombre se pide en el perfil, Módulo A #15); al
  entrar se llega a un inicio provisional con "Cerrar sesión" hasta que existan el mapa (H2) y el perfil (H7); la
  bienvenida se muestra solo la primera vez y, con la sesión abierta, se entra directo (DD-092); la bienvenida es una
  pantalla propia con la estructura del mock; "Saltar" y "Ya tengo cuenta" no entran, porque no tienen cita.
- C-226 (Arthur Naranjo). "Ok /opsx:apply continúa con las fases, termina hasta la parte donde ya quede al menos
  terminada la parte de inicio de sesión." Alcance de la sesión: `bienvenida` y `registro-e-inicio-de-sesion`.
- C-227 (Arthur Naranjo). "Necesito que también generes bien con los agentes las bases de datos, el Docker está
  corriendo; agiliza los procesos, porfa, usa 5 agentes." Cada agente del servidor prueba contra su propia base en el
  contenedor `nsv-lavanderia-postgis`.
- C-228 (Arthur Naranjo, 2026-11-21). Sobre DD-128, la cuenta que se crea con Google cuando Google no verificó el
  correo: "la solución, sobre de ella". La solución propuesta: rechazar siempre el `idToken` cuyo correo Google no
  verificó, haya o no cuenta.
- C-229 (Arthur Naranjo, 2026-11-21). Sobre la sesión que en iPhone sobrevive a reinstalar la app: "en teoría, si se
  reinstala no debería sobrevivir la sesión", y luego "también la solución". La solución propuesta: al primer
  arranque después de instalar, la app borra del almacén cifrado la sesión y la marca de "ya vio la bienvenida",
  en iPhone y en Android.
- C-230 (Arthur Naranjo, 2026-11-21). Sobre el aspecto de las pantallas: "hay que usar los mocks, pero no estamos en
  la otra cuenta, así que por mientras corrige". El lienzo de Claude Design está en otra cuenta de claude.ai; mientras
  tanto, las pantallas se corrigen con las imágenes de `mocks/Lavandi - Propuesta 04/Usuario/`. **La lectura es de
  Claude y la corrige Arthur.**
- C-231 (Arthur Naranjo, 2026-11-21). "Y genérame las instrucciones para correr la app en un PDF." Ver
  [[correr-la-app-en-local]].
- C-232 (Arthur Naranjo, 2026-11-21). "Listo, ya tenemos la sesión que sí tiene los artifacts, así que usa los de
  https://claude.ai/artifact/PDFYFzpi5iXKw5zzAHx3rY, la propuesta 1 y 2. Igual, primero alimenta el 2 para que quede
  bien con lo del contrato y lo que ya había en el proyecto, porfa, y así continuar." La liga es el lienzo "Lavandi".
- C-233 (Arthur Naranjo, 2026-11-21). A la pregunta de cuáles son la propuesta 1 y la 2, porque el lienzo ya no tiene
  las originales: "1 = P4, 2 = P5 (Recomendado)". La 1 es la Propuesta 4 del lienzo, las imágenes de iPhone y Android;
  la 2 es la Propuesta 5, la convergida y navegable que hizo la sesión de Nadir. A si la 2 se corrige en el mismo
  lugar o en una copia al lado: "En el mismo lugar (Recomendado)".
- C-234 (Arthur Naranjo, 2026-11-21). Sobre DD-134, lo que dejó abierto `plazas-y-zonas`: "Acepto las 7
  (Recomendado)".
- C-235 (Arthur Naranjo, 2026-11-21). A si `codigo` es único en `tipos_servicio` y `unidades_medida`, como en `roles`:
  "Sí, único (Recomendado)".
- C-236 (Arthur Naranjo, 2026-11-21). "Te voy a dejar trabajando. Ya estás con la cuenta realmente que usa Lavandi.
  Necesitamos avanzar, necesitamos dejar esta aplicación lo más pronto posible. Tiene que estar terminada antes de
  diciembre, a lo mucho hasta el 15 de diciembre ya tendría que estar completa. Así que necesito que hagas todo lo que
  necesites. Tienes el punto para decidir. Si necesitas decidir, ejecuta, baja skills de consejo de agentes. […]
  Que sea Opus 5.5 y adelante. De preferencia, en cuanto regrese, como eso de las 4 o 5, quiero tener al menos una
  aplicación en un APK que pueda tomar, pasar a mi celular y empezar a trabajar con ella de manera local. O incluso que
  podamos hacer aquí la emulación de manera local. No te desvíes nada del contrato […]. Que también los agentes, si
  bajas un consejo de agentes, no se separen del contrato. No preguntes, gestionen. Actualicen; si no les gusta ni a
  ustedes mismos, vuelvan a checar los datos, testeen, dejen la aplicación lo más funcional posible." Y después: "Si se
  quedan sin tokens de sesión, esperen a un reset." **Lectura:** las decisiones que el método le da a Arthur las toma
  Claude por delegación, con la recomendación escrita y asentada aquí; las de EL CLIENTE y las de Nadir no se delegan.
- C-237 (Arthur Naranjo, 2026-11-21). "Cuando estoy checando aquí el localhost, hay una que dice 'App en
  desarrollo'. En lugar de tener eso, lo que viene es el mockup del login; ajústalo ya." "App en desarrollo" es el
  nombre de prueba de `movil/.env.example`, que la entrada muestra como título.
- C-238 (Arthur Naranjo, 2026-11-21, por la tarde). Sobre la dirección: "No sé si en el contrato venía algo así, pero
  lo ideal sería que fueran listas desplegables y fueran buscándose con el Maps de Google, o sea, para que pueda
  facilitar la dirección; obvio, si no viene, facilitarle al usuario escribirlo correctamente." Claude respondió que
  no está fuera del contrato: F-A08 pide "ingreso manual o desde mapa" sin decir cómo
  (`NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3`, Módulo A #8). Arthur aprobó la propuesta: el municipio en una lista
  con solo los del territorio (`:4:Cláusula I`), las colonias por código postal con el catálogo de Correos de México
  y el autocompletado de Google Places cuando exista la llave (DD-072). "1. ok".
- C-239 (Arthur Naranjo, 2026-11-21, por la tarde). Sobre los avisos: aprueba que el servidor llame a la API HTTP v1
  de FCM con `firebase/php-jwt` en lugar de `laravel-notification-channels/fcm`, que nombra ADR-0008 y que no se
  puede instalar junto a las dependencias del proyecto. "2. ok". Queda como adenda de ADR-0008.
- C-240 (Arthur Naranjo, 2026-11-21, por la noche). "Hay que ver cómo pasársela a un compa para que la pruebe en iOS."
  Sin la cuenta de Apple del cliente (DD-069) no hay compilación para iPhone; la vía es Expo Go (base-E11). En la misma
  sesión: "el icono de la app debe ser el de LAVANDI que tenemos" y "el pull request es en GitHub, recuerda, para que lo
  acepte Nadir".
- C-241 (Arthur Naranjo, 2026-11-22, al revisar el emulador). "Cuando recolectas no te pide que tomes foto a la
  recolección, y eso es sumamente necesario, debe ser tomar foto sin la posibilidad de que use una imagen de la
  galería, debe ser tomada al momento."
  - El contrato solo guarda "las evidencias de entrega" (`NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3`). Si la
    evidencia es obligatoria y si también se toma al recolectar es DD-110, que decide EL CLIENTE vía Nadir. Por eso no
    se construye todavía (`CLAUDE.md` §2-bis).
  - Queda como la propuesta de Arthur para responder DD-110: foto obligatoria al recolectar y al entregar, tomada en el
    momento con la cámara, sin galería.
  - La foto de entrega del repartidor ya se toma solo con la cámara (repartidor-E23).
- C-242 (Arthur Naranjo, 2026-11-22, por la noche). Pide subir la rama ("súbelo"). Transmite una pregunta de Nadir:
  "¿has seguido todas las instrucciones sin desviarte?".
  - La respuesta se sostiene con una auditoría del PR #3 contra el contrato, no de palabra:
    [[auditoria-pr-3-contra-el-contrato-2026-11-22]].
  - De ella sale la decisión 39.
- C-243 (Arthur Naranjo, 2026-11-23). "Lo de Conekta es un hecho que hay que cambiarlo y buscar una alternativa. Yo
  creo que si es del efectivo mejor usar la de spiral que también es en el OXXO no? o algo similar a Conekta."
  - Se lee "spiral" como Stripe, que cobra tarjeta y efectivo en OXXO
    ([[alternativas-de-pasarela-2026-11-18]], grado E); falta que Arthur lo confirme.
  - Coincide con C-180 (Arthur) y con C-198 (Nadir). Lo que falta es la Orden de Cambio firmada por las dos partes
    (Cláusula IV; DD-079). Mientras no se firme, Conekta sigue siendo la pasarela del contrato y lo de Stripe no se
    construye (`CLAUDE.md` §2-bis, punto 3).
  - En la misma sesión, Arthur pide probar la app en su teléfono Android por USB, y dice que falta probarla en un
    iPhone.
  - En la misma sesión: hacer otro recorrido en video, narrado con la voz de Jorge o la de Dalia, cuando haya avances
    concretos.
- C-244 (Arthur Naranjo, 2026-11-23, por la noche, dictado por voz). Antes de irse a dormir:
  - **La pasarela.** "Stripe, usarlo de Stripe, sería lo más óptimo. Nadie está de acuerdo, el cliente nomás faltaría
    convencerlo."
    - Confirma que "spiral" (C-243) es Stripe.
    - Por el contexto del dictado, "Nadie" se lee como "Nadir": Arthur dice que Nadir está de acuerdo y que falta
      convencer a EL CLIENTE. Es lo que Arthur dice de Nadir, no una declaración de Nadir.
    - El pago queda pendiente hasta la Orden de Cambio firmada (DD-079). No se construye Stripe.
  - **El mapa.** La llave de Google Maps se deja para el final.
  - **La guía para probar en iPhone con Expo Go** se hace escrita y en video.
  - **El trabajo.** "Continúa, apégate al contrato para ya terminar la aplicación." Pide:
    - ser autocrítico;
    - usar varios agentes, todos en Opus 5.5, que dialoguen como un concilio y voten;
    - usar los recursos necesarios.
  - **Su duda sobre los roles.** Si a un repartidor o a un cliente lo da de alta la administración o se registra él
    mismo. Se responde con el contrato: el Módulo C solo registra, y el alta, validación y autorización del repartidor
    corresponden a EL CLIENTE (`NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3, Módulo C`). El Módulo D gestiona el alta,
    la baja, la suspensión y la edición de lavanderías y repartidores (`:19:A.3, Módulo D`).
- C-245 (Arthur Naranjo, 2026-11-23, por la noche, enseguida de C-244). "Otra cosa que me faltó mencionar es los PRs.
  Ya sabes cómo hacerlos. [...] También velos haciendo."
  - **Los PR y el push.** Autoriza subir las ramas y abrir sus PR como el PR #3, sin preguntarle otra vez.
  - **La CI.** Pide que la CI corra las pruebas. Ya lo hace (`.github/workflows/ci.yml`) y pasa en el PR #3.
  - **Las specs.** "Todos los specs deben seguirse al pie de la letra, sí o sí."
  - **Sus decisiones.** "No pongas nada de lo que yo tenga que decidir. Ustedes decidan por la manera más óptima",
    dialogado por un consejo de agentes y "siempre y cuando se mantenga [...] firmemente en el contrato".
    - Amplía C-236 a todo lo que esperaba a Arthur, por ejemplo el catálogo de colonias y la hora de cada estado.
    - No alcanza lo que el contrato o el método reservan a Nadir o a EL CLIENTE.

## Decisiones tomadas en la sesión

| # | Decisión | Opciones consideradas | Quién decide | Nota resultante |
|---|---|---|---|---|
| 1 | El modelo de datos se aprueba y entra a las specs. ADR-0016 y `modelo-de-datos.md` pasan a `vigente` | Seguir en borrador | Arthur (C-193) | [[ADR-0016-modelo-de-datos]], [[modelo-de-datos]] |
| 2 | Todo identificador empieza con `id_`, nunca termina en `_id`. La llave primaria es `id_<tabla>`; la foránea, el nombre de la llave a la que apunta; el identificador público, `id_publico`; los de la pasarela, `id_pasarela_<cosa>` (`id_pasarela_orden`). En minúsculas, por la razón de la decisión 7 del 2026-11-19 | `pasarela_orden_id`, que propuso Claude | Arthur (C-190, C-194) | [[modelo-de-datos]] |
| 3 | El modelo y las specs no nombran a ningún proveedor de pagos: `eventos_pasarela`, `movimientos_pasarela` y una columna `pasarela` donde haga falta saber cuál cobró. La pasarela queda abierta entre Stripe y Mercado Pago. No se integra ninguna hasta que el cliente firme el cambio (Cláusula IV, p. 8) | Seguir nombrando a Conekta, como dice hoy el contrato | Arthur (C-192); la forma neutral la propuso Claude | DD-079, spec `pago` |
| 4 | Las specs se re-proponen: cada cambio nombra las tablas que crea y su migración, con C-188 en las de pago, confirmación y estados. Las reglas comunes del modelo van en un cambio nuevo, `modelo-de-datos` | Meter las reglas comunes en `base-tecnica` | Arthur (C-193); el cambio aparte lo propuso Claude | `openspec/changes/modelo-de-datos/` |
| 5 | Git se maneja con la skill `git-lavandi`. Ningún commit, PR ni nota de avance lleva atribución a Claude. A los commits que todavía no se habían subido se les quita esa línea antes del push. De lo ya subido, solo se limpia `arranque/lavandi` en el fork, con push forzado con `--force-with-lease`; el repositorio de Nadir y las ramas viejas no se tocan | Limpiar las cinco ramas del fork; no limpiar nada viejo | Arthur (C-195, C-196) | `.claude/skills/git-lavandi/SKILL.md` |
| 6 | Las dos líneas de trabajo del 2026-11-20 se integran en la rama `integracion/lavandi` y van a Nadir en un solo PR. Se conserva todo lo de las dos. Lo de Nadir se renumera a lo siguiente libre: sus C-132 a C-142 pasan a C-197 a C-207; su DD-067 es la misma pregunta que DD-079; sus DD-068 y DD-069 pasan a DD-124 y DD-125. Las reglas que chocaban se fusionan: el push, según quién trabaja, y las specs, con DD-126. Lo que hay que decidir entre los dos va como notas DD-126 y DD-127 | Abrir el PR de `arranque/lavandi` solo y dejarle los conflictos a Nadir | Arthur (C-208 a C-210) | `vault/meta/deuda/DD-126.md`, `vault/meta/deuda/DD-127.md` |
| 7 | Se traen a `integracion/lavandi` los 7 commits nuevos del PR #1. Sus tres primeros, escritos antes del PR #2, pasan a la numeración que reservó Nadir: C-143 a C-145 de su rama quedan como C-211 a C-213, y sus DD-068 y DD-069 como DD-124 y DD-125. Los 6 conflictos se resuelven con los dos lados. El acta de paleta y logotipo queda intacta (C-219), y `validar.py` usa `/` en las rutas también en Windows | Unir solo el PR #1 y dejar el #2 con conflictos | Arthur (C-221) | PR #2 |
| 8 | Corrige la decisión 3: La pasarela del contrato es Conekta mientras el cliente no firme el cambio (`CLAUDE.md` §2-bis, punto 3; ADR-0010 vigente); Stripe y Mercado Pago son candidatas (C-192), con el OK expreso del cliente (C-216) y la firma pendiente (DD-079). Conekta se integra detrás de la interfaz neutral; lo que no se construye es lo de Stripe o Mercado Pago. El esquema neutral se queda | Mantener la integración bloqueada hasta la firma, como decía la decisión 3 | Revisión de Nadir del PR #2 (C-216) y la normativa, §2-bis punto 3; la redacción es de Claude | `openspec/changes/pago/`, `openspec/changes/publicacion/`, DD-079 |
| 9 | La app navega con expo-router; las pantallas viven en `movil/app/` | React Navigation a mano; cambiar de pantalla con estado propio | Arthur (C-222) | [[ADR-0017-navegacion-de-la-app]] |
| 10 | Sesión y recuperación: el token en el almacenamiento cifrado del teléfono; contraseña de 8 caracteres mínimo; 5 intentos por minuto en el inicio de sesión y en la recuperación; la recuperación en una página web del servidor, con un enlace de 60 minutos y de un solo uso; la misma respuesta exista o no la cuenta; la contraseña nueva cierra las sesiones | Contraseña de 12 caracteres con letras y números; la recuperación dentro de la app | Arthur (C-223) | [[ADR-0018-sesion-y-recuperacion]]; registro-E30 y E31 |
| 11 | DD-091: entrar con Google con un correo que ya tiene cuenta liga la cuenta de Google a la existente si Google verificó el correo; si no, se rechaza | Rechazar siempre | Arthur (C-224) | registro-E18; DD-091 cerrada |
| 12 | Las pantallas de H1 se quedan en lo que dice el contrato: el registro sin nombre; un inicio provisional con "Cerrar sesión"; la bienvenida solo la primera vez y, con sesión, entrada directa; sin "Saltar" ni "Ya tengo cuenta" | Pedir el nombre al registrarse; la bienvenida en cada apertura sin sesión | Arthur (C-225), con la lectura de Claude | bienvenida-E02; DD-092 cerrada |
| 13 | DD-128: el servidor rechaza siempre el `idToken` de Google cuyo correo Google no verificó | Dejarlo como estaba; crear la cuenta sin ligarla nunca | Arthur (C-228) | registro-E16 y E17; DD-128 cerrada |
| 14 | Al reinstalar la app no sobrevive la sesión ni la marca de la bienvenida, en iPhone tampoco | Dejar que el llavero de iPhone las conserve | Arthur (C-229) | registro-E32; DD-131, punto 2 |
| 15 | Las pantallas de H1 se corrigen con las imágenes de la Propuesta 04 del repositorio mientras no se tenga el lienzo de la otra cuenta. Sin lo que no tiene cita: el nombre en el registro (C-225), la casilla de aceptación (DD-080) y los atajos de la bienvenida | Esperar al lienzo | Arthur (C-230), con la lectura de Claude | `registro-e-inicio-de-sesion` tarea 2.3; `bienvenida` tarea 1.3 |
| 16 | La Propuesta 5 del lienzo "Lavandi" se corrige en su lugar contra el contrato, los ADR vigentes, las specs y lo construido en el H1. Lo que no tiene cita sale, lo pendiente va en ámbar con su DD, y la numeración es la del repositorio. Se agrega el tablero "Entrada" (bienvenida-E04). El pedido queda en cuatro pasos y "Prendas" sale del flujo. La referencia visual de la app pasa a ser la Propuesta 4 y la 5 corregida | Una copia corregida al lado; dejarla como la hizo la sesión de Nadir | Arthur (C-232, C-233) | Lienzo "Lavandi", versión 21; [[DD-135]] y [[DD-136]] |
| 17 | DD-134 se cierra con las siete recomendaciones: zonas sin encimarse, la zona inactiva no da servicio, al recargar se desactiva la que no viene, las dos llaves en las tablas hijas, `CHECK` de vigencias, `APP_ENV` con DD-070 y una plaza de prueba en Durango | Responder solo el punto 4 | Arthur (C-234) | plazas-E12 a plazas-E16; DD-134 cerrada |
| 18 | `codigo` es único en `tipos_servicio` y `unidades_medida` | Buscar por la llave primaria | Arthur (C-235) | `modelo-de-datos.md`; `perfil-de-lavanderia` tarea 1.1 |
| 19 | Las decisiones que el método da a Arthur las toma Claude por delegación, cada una con su recomendación escrita, y se asientan como "Claude, por delegación de Arthur (C-236)". Las de EL CLIENTE y las de Nadir no se delegan. La meta es la app completa a más tardar el 15 de diciembre, y un APK local hoy | Seguir preguntando cada decisión | Arthur (C-236) | Esta acta; `vault/meta/trabajo/` |
| 20 | Al terminar la bienvenida, o sin sesión y con la bienvenida ya vista, la app abre el inicio de sesión del mock, con su enlace a registrarse: así ofrece registrarse o iniciar sesión (bienvenida-E04). Desaparece la entrada con el nombre al centro. El nombre de la app en local es el del `.env`, no el de `.env.example`. El logotipo va en un archivo de nombre neutro, que en el repositorio es el marcador y que la compilación local reemplaza; la marca sigue fuera del código (`CLAUDE.md` §5) | Dejar la entrada y solo cambiar el nombre | Arthur (C-237); la forma, Claude por delegación (C-236) | bienvenida-E04; `movil/app/` |
| 21 | La autorización es la clase propia con su middleware; un dato ajeno responde 404 (DD-129). El `idToken` de Google se valida con `firebase/php-jwt` (DD-130) | Policies y Gates de Laravel; `google/apiclient` | Claude, por delegación de Arthur (C-236) | [[ADR-0019-autorizacion]]; adenda de [[ADR-0005-autenticacion]] |
| 22 | DD-131: las fuentes de la marca con `expo-font`; la pila se reinicia al llegar al inicio de sesión; el gesto de iPhone se queda | Dejar la letra del sistema | Claude, por delegación de Arthur (C-236) | adenda de [[ADR-0013-adaptador-de-tokens]] |
| 23 | DD-132: al ligar Google a una cuenta que ya existía se cierran sus sesiones y se anula su contraseña | Pedir la contraseña; dejarlo | Claude, por delegación de Arthur (C-236) | registro-E18 |
| 24 | DD-133: entran el logotipo de Google en su botón, el alto de 52, el borde de los controles y el foco de 3; no entran el ojo de la contraseña ni su texto de ayuda | Todo lo del mock | Claude, por delegación de Arthur (C-236) | `vault/design-system/tokens.json`; pantallas de acceso |
| 25 | DD-135: las ocho recomendaciones. El plazo de las peticiones se queda en 15 s | Preguntar una por una | Claude, por delegación de Arthur (C-236) | [[DD-135]] |
| 26 | DD-101: el folio son ocho caracteres al azar del alfabeto de Crockford, únicos por plaza, asignados al confirmar. DD-123: el pedido guarda un solo estado operativo; nace en `por_pagar`, que el usuario no ve, y pasa a `confirmado` con el pago; `en_entrega` se ve como Listo; `rechazado` y `cancelado` son excepciones; cada estado lo siembra el cambio que lo usa. Lo que pasa con un pedido que nunca se paga sigue en DD-012 y DD-016 | Un consecutivo por plaza; solo los cinco estados del usuario con banderas aparte | Claude, por delegación de Arthur (C-236) | [[ADR-0020-folio-del-pedido]], [[ADR-0021-maquina-de-estados-del-pedido]] |
| 27 | Soporte (punto 7 de DD-135): el número de WhatsApp vive en la configuración del servidor. "La plaza de la cuenta" se precisa así, porque `usuarios` no lleva plaza: un reporte sin pedido va a la plaza del último pedido de la cuenta; sin pedidos, a la de su dirección guardada más reciente; sin ninguna, el servidor lo rechaza con un mensaje y la app ofrece el WhatsApp. La foto del perfil espera al disco privado del entorno | Dejar `id_plazas` vacía, contra plazas-E09 | Claude, por delegación de Arthur (C-236) | `openspec/changes/perfil-y-soporte/design.md` |
| 28 | perfil-usuario-E05 pierde su "Y" de abrir la edición de cada dirección: el contrato pide "direcciones guardadas" en el perfil (`NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3`, Módulo A #15; F-A15), sin edición, y ningún cambio la especificaba (`direcciones` y `perfil-y-soporte` se la pasaban uno al otro). El perfil muestra la lista (CLAUDE.md §2-bis, punto 4). Editar o borrar una dirección guardada, si EL CLIENTE lo pide, es posible Orden de Cambio | Especificar la edición en `direcciones` | Claude, por delegación de Arthur (C-236) | `openspec/changes/perfil-y-soporte/` |
| 29 | La dirección asistida (C-238): se asienta la adenda del 2026-11-21 de ADR-0009. La geocodificación sale del servidor; el municipio y la colonia van con listas propias del catálogo de SEPOMEX; la calle, con Google Places desde el servidor, apagado hasta DD-137. Sale `direcciones.municipio` y entra `id_municipios`; el código postal se pide y no se exige; el municipio y la colonia se eligen con una pieza nueva, `Selector`, sin dependencias nuevas. Se abren DD-137 (costo de Places), DD-138 (licencia de SEPOMEX) y DD-139 (cuánto se puede guardar el punto de Google) | Dejar la columna muerta; exigir el código postal; Places desde la app | Claude, por delegación de Arthur (C-236) | [[ADR-0009-mapas]]; `openspec/changes/direcciones/` |
| 30 | Se asienta la adenda del 2026-11-22 de ADR-0021: la lavandería acepta con un estado operativo nuevo, `aceptado`, que el usuario sigue viendo como Confirmado; las transiciones de la lavandería son confirmado → aceptado o rechazado, y aceptado → recolectado → en_proceso → listo → en_entrega; un estado operativo se le presenta al usuario como el paso de su mismo orden; cada cambio publica `EstadoDelPedidoCambiado`. Lo que esperan DD-022, DD-016 y DD-103 no se decide | Recolectado directo desde confirmado, sin aceptar | Claude, por delegación de Arthur (C-236) | [[ADR-0021-maquina-de-estados-del-pedido]] |
| 31 | `GET /api/cuenta` y `DELETE /api/sesion` son de toda cuenta con un rol de la app (usuario y socio lavandería; el repartidor cuando nazca): con el rol, la app sabe a qué módulo entra. La administración sigue fuera, porque entra por el panel (registro-E33). Cómo llega cada rol a su cuenta sigue siendo DD-053, de Nadir | Una ruta de cuenta por módulo | Claude, por delegación de Arthur (C-236) | `openspec/changes/registro-e-inicio-de-sesion/` |
| 32 | Se asienta la adenda del 2026-11-22 de ADR-0010. La tarjeta se escribe en el checkout de Conekta, en la vista de navegador del sistema presentada dentro de la app con `expo-web-browser`: el usuario paga dentro de la aplicación, como pide el contrato (`NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3`), y ninguna pantalla de la app tiene campos de tarjeta. "Procesando pago" consulta cada 5 s, hasta 3 minutos. Cierra la segunda parte de DD-055 y el punto 1 de DD-102, y desbloquea pago-E02. Si Nadir confirma que C-198 pide los campos de la tarjeta en la app, se pasa al formulario nativo de Conekta, como dice el criterio de reversión | El formulario nativo de `conekta-elements-react-native` 0.2.0, de dos días y sin Expo Go; abrir Safari o Chrome fuera de la app | Claude, por delegación de Arthur (C-236) | adenda de [[ADR-0010-pasarela-de-pago]]; `openspec/changes/pago/` |
| 33 | Se asienta la adenda del 2026-11-22 de ADR-0007: los widgets de indicadores se refrescan cada 30 s, con `indicadores.refresco` en `30s` por omisión. Cierra DD-116 y desbloquea indicadores-E22 | 10 s; 60 s; websocket solo para los indicadores | Claude, por delegación de Arthur (C-236) | adenda de [[ADR-0007-tiempo-real]]; `openspec/changes/indicadores/` |
| 34 | Se asienta la segunda adenda del 2026-11-22 de ADR-0010: los movimientos para conciliar salen de la API de la pasarela, por la misma interfaz neutral del cobro, cuando la administración concilia un periodo; se guardan en `movimientos_pasarela`, una fila por orden, sin plaza propia (la plaza es la del pedido con el que cuadra). Cierra DD-112 y desbloquea administracion-E20 y E21 | El reporte descargado del panel de Conekta; una tarea programada | Claude, por delegación de Arthur (C-236) | adenda de [[ADR-0010-pasarela-de-pago]]; `openspec/changes/administracion/` |
| 35 | Se asienta la adenda del 2026-11-22 de ADR-0019: donde una pieza de terceros solo sepa preguntar a una Policy, como la descarga de exportaciones de Filament, se admite una Policy adaptadora que solo llama al componente de autorización, sin regla propia (exportacion-E11) | Reescribir la ruta de descarga de Filament; abrir la descarga a toda la administración | Claude, por delegación de Arthur (C-236) | adenda de [[ADR-0019-autorizacion]] |
| 36 | Se asienta la adenda del 2026-11-22 de ADR-0008: Firebase se enlaza en la app solo cuando el entorno trae su archivo (`GOOGLE_SERVICES_JSON`); sin él, el APK local sale como antes y Expo Go no lo pide. Los complementos de las dos librerías no se listan; iPhone entra con la tarea 5.2 (DD-069) | Enlazar Firebase siempre, con un archivo falso; esperar las cuentas para escribir la app | Claude, por delegación de Arthur (C-236) | adenda de [[ADR-0008-avisos-push]]; `openspec/changes/avisos/` |
| 37 | DD-071: el correo automático sale por Resend, con el transporte oficial de Laravel; [[ADR-0014-correo-automatico]] pasa a vigente. Se pasa a Resend Pro o a Amazon SES si el volumen supera 80 correos al día. El envío real espera DD-070 y DD-023 | Amazon SES (sandbox de hasta 96 h y una cuenta de AWS a nombre del cliente) | Claude, por delegación de Arthur (C-236) | [[ADR-0014-correo-automatico]] |
| 38 | Se asienta la tercera adenda del 2026-11-22 de ADR-0010: `api` habla con Conekta por su API REST v2.3.0 con el cliente HTTP de Laravel, sin `conekta-php`; si se prefiere el SDK, solo cambia el interior de `PasarelaConekta` | El SDK `conekta-php` | Claude, por delegación de Arthur (C-236) | adenda de [[ADR-0010-pasarela-de-pago]]; `openspec/changes/pago/` |
| 39 | Se alinea con el contrato lo que la auditoría del PR #3 encontró y no depende de nadie más: la comisión global es una sola para todo el sistema (`:19:A.3, Módulo D #5`); el reporte de soporte se guarda aunque la cuenta no tenga plaza y lo ve solo la administración (`:17:A.3 #16`; no hay administración por plaza, que sería Orden de Cambio); se quitan `usuarios.terminos_aceptados_en` (Cláusula X) y el tipo de evidencia `recoleccion` (DD-110); el resumen deja de mostrar el renglón del envío (`:16:A.3 #9`); los comportamientos sin escenario reciben el suyo | Dejar la comisión por plaza con ADR-0016; rechazar el reporte sin plaza | Claude, por delegación de Arthur (C-236), con la regla de `CLAUDE.md` §2-bis | [[auditoria-pr-3-contra-el-contrato-2026-11-22]]; rama `fix/alineacion-al-contrato` |

**Lo que implica la decisión 3.** Mientras el cliente no firme, el contrato sigue diciendo Conekta (`CLAUDE.md` §2-bis,
punto 3). Lo que se escribe ahora es neutral: el cobro por checkout redirigido y confirmado por webhook, sin el nombre
de un proveedor. La integración con Stripe o con Mercado Pago es una tarea bloqueada por DD-079 hasta la firma.

## Preguntas abiertas que salieron de la sesión

| # | Pregunta | Bloquea | Fila en `deuda-de-decision.md` |
|---|---|---|---|
| 1 | ¿Stripe o Mercado Pago? Se elige con matriz y ADR, que reemplaza al ADR-0010, y el aviso firmado la nombra | La integración del pago y el aviso al cliente | DD-079 |
| 2 | Las decisiones de pantalla que dejó la corrección de la Propuesta 5: pedido activo en el inicio, la tarjeta de lavandería, las reseñas, la hora por estado, el permiso de avisos, los campos de la dirección, el número de WhatsApp y el paso de prendas | Cerrar la Propuesta 5 como referencia | [[DD-135]] |
| 3 | Lo que la Propuesta 5 traía fuera del contrato: ¿se le propone a EL CLIENTE como Orden de Cambio? | Nada del contrato | [[DD-136]] |
