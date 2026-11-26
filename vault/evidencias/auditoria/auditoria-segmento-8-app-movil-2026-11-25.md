---
estado: vigente
grado: B
fuente: auditoría de solo lectura de arthur/feat/h3-concilio en 9d2497775966ef2d8c0c0f204b2f67cfb8432fbd (PR #4); movil/app, movil/src, movil/app.config.ts, movil/eas.json, movil/.maestro, mocks/ y vault/design-system/canvas leídos directamente, contra vault/contrato/inventario-funcional.md (Módulos A a D) y los ADR-0002, ADR-0005, ADR-0008, ADR-0009 y ADR-0017; resultado de la CI del PR #4 en ese SHA leído con gh (corrida 45550228403); sin correr pruebas ni compilar, por decisión de Nadir Hadad Navarrete en sesión del 2026-11-24
fecha: 2026-11-25
dueño: Nadir Hadad Navarrete
---
# Auditoría Lavandi — Segmento 8: app móvil

**Objeto:** rama `arthur/feat/h3-concilio` (PR #4) · SHA `9d24977` · fecha de corte 2026-11-24.

**Qué NO se revisó:**
- **No se compiló ni se abrió la app.** El árbol no trae `node_modules/` ni las carpetas nativas `android/` e `ios/`:
  Expo las genera al compilar. Si compila se juzga con dos fuentes:
  - la CI del PR #4 en este SHA, que corre tipos, lint, pruebas y Expo Doctor, pero no compila;
  - la evidencia del vault de una compilación local de Android.
- **No se corrieron pruebas.** Sí se leyó el resultado de la CI del PR #4 en el SHA auditado (corrida `45550228403`,
  evento `pull_request`, 2026-11-25). Es evidencia de que la CI las pasó, no de que la auditoría las corrió (S8-04).
- **Pantallas contra mocks:** solo por nombre y función. No se compararon visualmente. Los mocks del Módulo A son HTML;
  los de B y C son lienzos del design system.
- El detalle de cada función (F-A, F-B, F-C) ya está en la matriz del segmento 1, la seguridad de la app en el segmento
  4 y los flujos en el segmento 6. Aquí solo se citan.

**Veredicto: NO ESTÁ LISTO.**
- **En Android, la app está bien hecha:**
  - los módulos A, B y C tienen sus pantallas, cada módulo protegido por el rol de la sesión;
  - los mapas son de Apple en iPhone y de Google en Android, como pide el contrato;
  - los permisos son los mínimos;
  - en este SHA, la CI pasa los tipos, el lint, 1282 pruebas en 79 suites y Expo Doctor.
- **Pero no está lista para las tiendas.**
  - iPhone sigue sin Google, sin Apple y sin avisos (S1-05).
  - El Módulo D no está en la app (S1-04).
  - Nunca se ha corrido una compilación de EAS, que es la que va a las tiendas (S8-01).
  - Si se corriera hoy, saldría con el ícono y el logotipo de la plantilla de Expo (S8-02).

### Resumen
- **Pantallas:** el Módulo A tiene las 17 funciones en 21 pantallas. El B tiene sus 6 funciones y el C sus 5. **El D no
  tiene pantalla:** solo hay un botón que abre el panel web (S1-04).
- **Mapas por plataforma (RN-29):** `react-native-maps` se usa sin proveedor, así que toma MapKit en iPhone y Google en
  Android. Sin la llave de Android, la app no monta el mapa en vez de cerrarse.
- **El camino a las tiendas no se ha probado** (S8-01). La CI no compila. La única compilación con evidencia es un APK
  local en un emulador de Android. No hay evidencia de ninguna compilación de EAS ni de iPhone.
- **Las compilaciones de EAS llevarían el ícono de la plantilla** (S8-02). El logotipo y el ícono de Lavandi solo los
  pone el script del APK local.
- **Dos correcciones:**
  - al segmento 6: sí hay un flujo de Maestro (S8-03);
  - a los segmentos 1 a 7: la CI del SHA auditado pasó las suites de la app (S8-04).

### Hallazgos
| ID | Severidad | Área | Hallazgo | Evidencia (grado + ruta:línea) | Impacto | Recomendación |
|---|---|---|---|---|---|---|
| S8-01 | Alta | Compilación / calendario | **El camino a las tiendas no se ha ejercitado nunca.** La CI corre `tsc`, ESLint, Jest y Expo Doctor, pero no compila. `eas.json` tiene los perfiles `preview` y `production`, y no hay constancia de que alguno haya corrido. La única compilación con evidencia es un APK local en un emulador Android 15. En iPhone, el único camino descrito es Expo Go, que no trae los módulos nativos (Google, Firebase, mapas con llave) | B: `.github/workflows/ci.yml:249-279`; `movil/eas.json`; `herramientas/apk-local/compilar.ps1`; `vault/evidencias/estados/estados-e17-cambio-en-60-segundos-2026-11-22.md:3` (emulador `lavandi`, Android 15); `movil/src/configuracion/entorno.ts:7` (Expo Go en iPhone, C-240). A: envío a tiendas en la semana 14 (`calendario-contractual.md:26`) | El primer `eas build --profile production` va a coincidir con la semana del envío, del 7 al 13 de diciembre. Cualquier falla de firma, de módulos nativos o de R8 cae sobre esa fecha. Se suma a lo que ya reportó S1-02 | Correr `eas build --profile preview` en Android y en iPhone esta semana, aunque sea con cuentas de prueba, y guardar la evidencia en el vault. Agregar a la CI una compilación nativa, al menos `expo prebuild` |
| S8-02 | Media | Marca / tiendas | **Una compilación de EAS saldría con el ícono y el logotipo de la plantilla.** `app.config.ts` apunta a `assets/icon.png`, el marcador neutro de la plantilla. `assets/marca/logotipo.png` también lo es. Solo el script del APK local los cambia por los de `vault/marca/`. EAS no tiene ese paso, y `vault/` no se cede con el código (Cláusula IX) | B: `movil/app.config.ts:163-165`; `movil/src/contenido/logotipo.ts:1-3`; `herramientas/apk-local/compilar.ps1:7-8,29-30`; `movil/eas.json` (sin paso de activos); `openspec/changes/base-tecnica/design.md:83-84`. A: `NSV-2026-001-v8.pdf:10:Cláusula IX` | La app que llegue a App Store y Google Play llevaría un ícono genérico. Además, la entrega del código a EL CLIENTE no tendría cómo poner su marca | Que el ícono y el logotipo entren a EAS como archivos del entorno (como `GOOGLE_SERVICES_JSON`) y que `app.config.ts` falle en `production` sin ellos, igual que ya falla sin el identificador definitivo |
| S8-03 | Info | Corrección | **Corrección a S6-04.** El segmento 6 dijo que la app no tiene pruebas de punta a punta, "ni Maestro, ni Detox". Sí hay un flujo de Maestro, pero cronometra un solo cambio de estado, y su pedido se prepara con el cobro simulado. Por eso tampoco ve el corte de S6-01. La conclusión de S6-04 no cambia | B: `movil/.maestro/estados/cambio-en-60-segundos.yaml:1-16`; `movil/.maestro/estados/preparar-pedido.ps1:5-8`; `vault/evidencias/estados/estados-e17-cambio-en-60-segundos-2026-11-22.md` (tres corridas entre 26.8 y 27.5 s, en emulador) | S6-04 sigue siendo Media | Se corrigió la fila S6-04 en el informe del segmento 6 y en el tablero |
| S8-04 | Info | Corrección de alcance | **La CI del PR #4, en el SHA auditado, pasó las suites de la app.** Fueron 79 suites y 1282 pruebas en verde, con `tsc`, ESLint y 20 de 20 revisiones de Expo Doctor. Todos los jobs del PR terminaron en `pass`, también `servidor`, `landing-navegadores` y `landing-velocidad`. Los segmentos 1 a 7 dijeron "NO VERIFICADO" porque la auditoría no corrió pruebas, y eso sigue siendo cierto. Pero sí hay evidencia de terceros de que las pruebas escritas pasan | B: corrida `45550228403` de GitHub Actions, `headSha` `9d2497775966ef2d8c0c0f204b2f67cfb8432fbd`, `conclusion: success` (`gh run view`); job `movil`: "Test Suites: 79 passed", "Tests: 1282 passed", "20/20 checks passed" | Lo CODIFICADO con prueba escrita puede contar como probado en la CI. Lo que no tiene prueba, o depende de un servicio real (Conekta, FCM, tiendas), sigue sin probar | Que el segmento 9 lea qué corre exactamente cada job, y que el consolidado ajuste el estado de las funciones con esta evidencia |
| S8-05 | Baja | Coherencia | **Los mocks dicen que el Módulo D "es web"** (DD-040, C-140). C-247 lo pasó a la app, y los mocks no lo reflejan. B y C no tienen mocks navegables, solo lienzos del design system | B: `mocks/LEEME.md:95-96`; `vault/design-system/canvas/` (`PedidoLavanderia`, `PedidoRepartidor`, `Lavanderia`, `Horario`). C: C-247 (S1-04) | Quien diseñe el Módulo D en la app arranca de una guía vieja | Actualizar `mocks/LEEME.md` cuando se decida el alcance del Módulo D en la app |
| S8-06 | Info | Positivos | **La app está bien construida para Android.** Cada módulo tiene sus rutas protegidas por el rol de la sesión. El mapa va sin proveedor (Apple en iPhone, Google en Android) y no se monta sin llave. Los permisos son los mínimos: sin ubicación en segundo plano, sin micrófono, sin Face ID, con textos propios. En EAS exige https y bloquea tráfico sin cifrar. No compila a producción con el identificador provisional. Marca e identificador salen del entorno. El token va en el almacén cifrado. Mínimos: iOS 18 y Android 11 | B: `movil/app/_layout.tsx:62-91`; `movil/app/lavanderia/_layout.tsx:73-82`; `movil/app/repartidor/_layout.tsx:72-81`; `movil/src/cercanas/MapaDeLavanderias.tsx:2-5`; `movil/src/cercanas/mapa.ts:8-11`; `movil/app.config.ts:17-18,109-134,184,194,217-241` | — | Conservar |

**Ya reportados, que tocan este segmento:**
- **S1-04:** el Módulo D no está en la app, aunque C-247 lo pasó ahí.
- **S1-05:** en iPhone no funcionan Apple, Google ni los avisos.
- **S1-07:** los socios no reciben avisos.
- **S1-08:** faltan elementos del Módulo A.
- **S1-09:** nada enlaza al registro del repartidor. `RUTA_DEL_REGISTRO_DEL_REPARTIDOR` está definida en
  `movil/src/repartidor/rutas.ts:8` y ninguna pantalla la usa.
- **S1-14:** el botón "Abrir el panel".
- **S4:** almacenamiento de tokens y MASVS.
- **S5-03:** no hay contrato de API compartido.

### Los cuatro módulos: pantallas contra contrato y mocks
Ruta base: `movil/app/`. Contrato: `NSV-2026-001-v8.pdf:16-19:ANEXO A, Sección A.3`.

**Módulo A, usuario.** Mocks: `mocks/usuario/`, 20 páginas. El estado de cada función está en el segmento 1.

| F | Mock | Pantalla de la app |
|---|---|---|
| F-A01 | `a01-bienvenida`, `a01-informativas` | `bienvenida.tsx`, `informativa/[numero].tsx` |
| F-A02 | `a02-registro`, `a02-inicio-sesion`, `a02-recuperar` | `registro.tsx`, `inicio-de-sesion.tsx`, `recuperar-contrasena.tsx`. Sin "Continuar con Apple": el mock la muestra en iPhone y la app no la tiene (`inicio-de-sesion.tsx:3`, DD-069) |
| F-A03, F-A04 | `a03-principal`, `a04-busqueda` | `inicio.tsx`, `lavanderias/index.tsx` |
| F-A05, F-A06 | `a05-lavanderia`, `a06-servicio` | `lavanderias/[id].tsx`, `pedido/servicio.tsx` |
| F-A07, F-A08 | `a07-fecha-hora`, `a08-direccion` | `pedido/fecha-y-hora.tsx`, `pedido/direccion.tsx`, `pedido/direccion-nueva.tsx` |
| F-A09 a F-A11 | `a09-resumen`, `a10-pago`, `a11-confirmacion` | `pedido/resumen.tsx`, `pedido/pago/[id].tsx`, `pedido/pago/procesando/[cobro].tsx`, `pedido/confirmacion/[id].tsx` |
| F-A12 | `a12-seguimiento` | `pedido/seguimiento/[id].tsx` |
| F-A13, F-A14 | `a13-historial`, `a14-calificacion` | `pedidos/index.tsx`, `pedido/calificacion/[id].tsx` |
| F-A15 a F-A17 | `a15-perfil`, `a16-soporte`, `a17-notificaciones` | `perfil/index.tsx`, `ayuda.tsx`, avisos en `movil/src/perfil-usuario/Notificaciones.tsx` |

**Módulo B, socio lavandería.** No tiene mocks navegables; hay lienzos en `vault/design-system/canvas/`.

| F | Pantalla de la app | Lienzo |
|---|---|---|
| F-B01 | `lavanderia/registro.tsx` (solo sin lavandería registrada, `lavanderia/_layout.tsx:80-82`) | `Lavanderia.dc.html` |
| F-B02, F-B03 | `lavanderia/pedidos/index.tsx`, `lavanderia/pedidos/rechazo/[id].tsx` | `PedidoLavanderia.dc.html` |
| F-B04, F-B05 | `lavanderia/historial.tsx`, `lavanderia/estadisticas.tsx` | — |
| F-B06 | `lavanderia/perfil.tsx` | `Horario.dc.html` |

**Módulo C, socio repartidor.**

| F | Pantalla de la app | Lienzo |
|---|---|---|
| F-C01 | `registro-de-repartidor.tsx`, sin enlace desde la app (S1-09), y `repartidor/espera.tsx` mientras no lo dan de alta (`repartidor/_layout.tsx:79-81`) | — |
| F-C02, F-C03 | `repartidor/pedidos/index.tsx`, `repartidor/pedidos/rechazo/[id].tsx`, `repartidor/pedidos/entrega/[id].tsx` | `PedidoRepartidor.dc.html` |
| F-C04, F-C05 | `repartidor/historial.tsx`, `repartidor/estadisticas.tsx` | — |

**Módulo D, administración.** No tiene pantallas en la app. Solo existe `movil/src/acceso/BotonDelPanel.tsx`, que abre
el panel web (S1-04 y S1-14). Las 7 funciones viven en Filament (segmento 7).

### Configuración de Expo (`movil/app.config.ts`)
| Tema | Lo que hay | Evidencia | Falta |
|---|---|---|---|
| Plataformas | iPhone y Android; sin iPad | `:162,170` | — |
| Versiones mínimas | iOS 18, Android 11 (API 30) | `:17-18,198-200` | — |
| Identidad | Nombre, `bundleIdentifier`, `package` y esquema, del entorno. `production` se detiene con el marcador de la marca | `:20-33,121-134,153-174` | Identificador definitivo, DD-088 |
| Transporte | Solo https y sin tráfico sin cifrar en `preview` y `production` | `:106-119,136-139,171,205` | — |
| Ícono y arranque | Marcadores de la plantilla | `:163-165`; sin `expo-splash-screen` | S8-02; splash nativo, DD-081 |
| Google Sign-In | Complemento solo si hay cliente de iPhone | `:35-51,209` | Cliente de iPhone, DD-069 |
| Avisos | Firebase solo en Android y solo con `GOOGLE_SERVICES_JSON` | `:62-82,187,245` | iPhone (avisos-E18) |
| Perfiles de EAS | `preview` (APK interno) y `production` (app bundle, versión remota con incremento) | `movil/eas.json` | No hay constancia de que hayan corrido (S8-01) |

### Mapas por plataforma (RN-29)
| Plataforma | Mapa | Llave | Sin llave |
|---|---|---|---|
| iPhone | Apple (MapKit): sin `provider` | No lleva (`app.config.ts:210-212`) | — |
| Android | Google | `GOOGLE_MAPS_ANDROID_API_KEY`, de los secretos de EAS (`app.config.ts:53-60`) | No monta el mapa, en vez de cerrar la app (`src/cercanas/mapa.ts:8-11`) |

Los usan `movil/src/cercanas/MapaDeLavanderias.tsx` y `movil/src/direcciones/MapaParaMarcar.tsx`. Cumple la restricción
del contrato.

### Permisos
| Permiso | iPhone | Android | Evidencia |
|---|---|---|---|
| Ubicación mientras se usa | Con texto propio | Sí | `app.config.ts:217-229`; `src/cercanas/permiso-de-ubicacion.json` |
| Ubicación en segundo plano | No | Bloqueada en el manifiesto | `:184,221-225` |
| Cámara y fotos | Con textos propios | Sí | `:234-241`; `src/alta-lavanderias/permisos-de-la-foto.json` |
| Micrófono | No | No | `:239` |
| Face ID | No | — | `:194` |
| Avisos | Pendiente | `POST_NOTIFICATIONS`, solo con Firebase | `:70,187` |

### ¿Compila?
| Camino | Evidencia | Estado |
|---|---|---|
| Tipos, lint, pruebas y Expo Doctor | CI del PR #4 en `9d24977`: `pass` (79 suites, 1282 pruebas, 20 de 20 revisiones) | **Pasa en la CI**; no lo corrió la auditoría |
| APK local de Android | `herramientas/apk-local/compilar.ps1`; Maestro en el emulador `lavandi`, Android 15, 2026-11-22 | Compiló y abrió, según la evidencia de Arthur |
| EAS `preview` y `production` | Solo la configuración (`movil/eas.json`) | **Sin evidencia** (S8-01) |
| iPhone | Expo Go para pruebas (C-240). No hay compilación nativa | **Sin evidencia** (S1-05, S8-01) |

### Preguntas abiertas
**Para Arthur:**
- ¿Ya corrió alguna vez `eas build`, en cualquier perfil o plataforma? Si sí, ¿dónde está la evidencia?
- ¿Cómo entran el ícono y el logotipo de EL CLIENTE a la compilación de EAS sin sacarlos del vault?
- ¿Cabe en la CI un `expo prebuild`, o una compilación de Android, para que una falla nativa aparezca antes de la semana
  14?

**Para Nadir:**
- La cuenta de Apple Developer y la de Google Play (B.10, S1-02): sin ellas no hay compilación firmada para las tiendas.
  ¿Tienen fecha?

**Para EL CLIENTE, vía Nadir:**
- Las cuentas de las tiendas, a su nombre (B.10 y B.11).
- La versión final del ícono y de la pantalla de arranque, si va a cambiar la de `vault/marca/`.

### Comandos ejecutados
```bash
# En el worktree de solo lectura del SHA 9d24977
cat movil/package.json movil/eas.json; cat -n movil/app.config.ts
ls -a movil; ls movil/assets movil/assets/marca; find movil/.maestro -type f
cat -n movil/app/_layout.tsx movil/app/lavanderia/_layout.tsx movil/app/repartidor/_layout.tsx
grep -rnE "provider|PROVIDER_|mapaDeGoogleEnAndroid|Platform.OS" movil/src/cercanas movil/src/direcciones
grep -rniE "apple" movil/src movil/app
grep -rn "RUTA_DEL_REGISTRO_DEL_REPARTIDOR" movil/src movil/app
sed -n '87,102p' mocks/LEEME.md; ls mocks/usuario vault/design-system/canvas
grep -niE "logotipo|icon" herramientas/apk-local/compilar.ps1
sed -n '249,280p' .github/workflows/ci.yml

# La CI del PR #4 en el SHA auditado
gh pr checks 4 -R nhadadn/Lavandi
gh run view 45550228403 -R nhadadn/Lavandi --json headSha,conclusion
gh run view 45550228403 -R nhadadn/Lavandi --log --job 138989437818 | grep -E "Tests:|Test Suites:|checks passed"
```
