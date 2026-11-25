---
estado: vigente
grado: B
fuente: auditoría de solo lectura de la rama arthur/feat/h3-concilio en 9d2497775966ef2d8c0c0f204b2f67cfb8432fbd (PR #4, que contiene el PR #3) contra origin/main en 7956604d9554beb9feb4e36e4fb434174cc377b2; git log, gh pr view, gh run list, gh api y manifiestos en ese SHA; la pidió Nadir Hadad Navarrete en sesión el 2026-11-24
fecha: 2026-11-24
dueño: Nadir Hadad Navarrete
---
# Auditoría Lavandi — Segmento 0: Inventario y proceso

**Objeto:** rama `arthur/feat/h3-concilio` (PR #4) · SHA `9d24977` (`9d2497775966ef2d8c0c0f204b2f67cfb8432fbd`) ·
base `origin/main` en `7956604` · fecha de corte 2026-11-24.

**Qué NO se revisó:** el contenido del código (segmentos 2 a 8), el avance contra el contrato (segmento 1) y las
suites de pruebas: **no se corrieron** por decisión de Nadir en esta sesión. Todo lo que dependa de un test queda
"NO VERIFICADO: no se corrieron las suites". El verde de la CI se reporta como lo informa GitHub, no como prueba propia.

**Veredicto: LISTO CON RESERVAS** (para el proceso, no para el producto).
- Los commits cumplen el formato acordado y la CI del PR #4 está en verde en su último SHA.
- El flujo de revisión que el propio repositorio documenta no se está siguiendo: el trabajo llega a `main` de Nadir en
  dos PR de 453 y 573 commits, sin pasar por el `main` del fork ni por cierres de sprint.
- `main` no tiene protección de rama y ya entró un PR (el #2) sin CI ejecutada.

### Resumen

- El PR #4 (`9d24977`) contiene completo al PR #3 (`0b6c413` es su ancestro); los dos apuntan a `main`. Revisar los
  dos por separado duplica el trabajo.
- Rango auditado: 573 commits (470 sin merge, 103 de merge), 1 232 archivos, +171 533 / −672 líneas, del 2026-11-20 al
  2026-11-24. GitHub muestra "100 commits" en los PR #3 y #4 porque su API corta en 100: el número real lo da `git`.
- 470 de 470 asuntos siguen `tipo(alcance): descripción` en español; los tipos `vault:`, `openspec:` y `wip(fase-N):`
  están documentados.
- 12 commits del rango tienen como autor `Claude <noreply@anthropic.com>` y el trailer `Claude-Session:`: son copias
  reescritas de los 12 del PR #1 de Nadir, que ya están en `main`. Al fusionar, ese historial queda duplicado.
- Stack real: Laravel 13.43.0, Filament 5.11.4, Sanctum 4.5.0, PHP ^8.3, PHPUnit 12.5.45; Expo 58.0.26, React Native
  0.88.1, React 19.3.0, TypeScript 6.0.3, Jest 30.5.2. No hay `vendor/` ni `node_modules/` versionados. Socialite y
  `socialiteproviders/apple`, que ADR-0003 y ADR-0005 declaran, no están en `composer.json`.

### Hallazgos

| ID | Severidad | Área | Hallazgo | Evidencia (grado + ruta:línea) | Impacto | Recomendación |
|---|---|---|---|---|---|---|
| S0-01 | Media | Proceso | El flujo documentado no se sigue. Debería ser: cada cambio en su rama, PR al `main` del fork de Arthur, fusión con CI en verde y dictamen del revisor, y un solo PR por sprint del `main` del fork al `main` de Nadir. Lo que hay: `arthur/main` sigue en `7fc1e75` (2026-10-26); el fork de Arthur tiene 0 PR; los PR #3 y #4 van de una rama de función directo al `main` de Nadir; el rango no tiene ningún commit `Sprint N:` | B: `vault/formas-de-trabajo/repositorios-ramas-y-revision.md:82-89` (en `9d24977`); `git rev-parse arthur/main` = `7fc1e75`; `gh pr list -R AsberNaranjo/Lavandi --state all` sin resultados; `git log origin/main..9d24977 --format=%s \| grep -c '^Sprint '` = 0. C: C-158 | Nadir recibe 1 232 archivos y +171 533 líneas en una sola revisión. No hay evidencia en GitHub de que el revisor dictaminara cada cambio antes de integrarlo | Que Arthur elija una de dos y la asiente: seguir C-158 (PR internos en el fork y un PR por sprint) o corregir la nota para que diga el flujo real. Mientras tanto, Nadir revisa el PR #4 por lotes de commits, no como un diff único |
| S0-02 | Media | Proceso / CI | El PR #2 se fusionó sin CI ejecutada. Las tres corridas sobre su último SHA (`6c39276`) terminaron en `failure` con 0 jobs, una falla de arranque y no de pruebas. El PR no tiene reseñas en GitHub y lo fusionó Nadir | B: `gh run list` → corridas `44861806198`, `44861806137` y `44861806119` en `6c39276`, `failure`; `gh run view 44861806137` → `jobs: []`; `gh pr view 2` → `statusCheckRollup: []`, `reviews: 0`, `mergedBy: nhadadn`. Regla: `repositorios-ramas-y-revision.md:43` ("Siempre en verde") | La primera corrida en `main` después de la fusión (`7956604`, push) salió en verde. El riesgo es de proceso: GitHub no impide fusionar sin CI | Exigir checks obligatorios en `main` (ver S0-03). Antes de fusionar un PR, confirmar que sus checks corrieron, no solo que no hay rojos |
| S0-03 | Media | Proceso | `main` no tiene protección de rama. "Nadie escribe directo en `main`" y "Solo entra por pull request" se cumplen por costumbre, no porque GitHub lo impida | B: `gh api repos/nhadadn/Lavandi/branches/main/protection` → `404 Branch not protected`; regla en `CLAUDE.md:167` y `:213` (en `9d24977`) | Un push directo o una fusión con checks rojos o sin correr no se bloquea | Nadir, como dueño del repositorio: proteger `main` con PR obligatorio y los checks `ci`, `vault` y `Validar vault` como requeridos |
| S0-04 | Baja | Historial / atribución | 12 commits de la rama tienen autor y committer `Claude <noreply@anthropic.com>` y el trailer `Claude-Session: https://claude.ai/code/session_…`. Son copias de los 12 commits del PR #1 con el mismo árbol (`c8974b6` y `d7d150c` comparten el tree `3dc50c7`) a los que se les quitó solo el `Co-Authored-By`. Los originales ya están en `main`, con `Co-Authored-By: Claude` | B: `git log --author=noreply@anthropic.com origin/main..9d24977` → 12; `git range-diff c8974b6^..8333f09 d7d150c^..a6056a4` → la única diferencia es la línea `Co-Authored-By`; `git cat-file -p` de los dos. Regla: `CLAUDE.md:206`, `.claude/skills/git-lavandi/SKILL.md:20-21`. C: C-195, C-196 | Al fusionar el PR #4, `main` tiene los 12 commits dos veces. La regla de no atribución sigue sin cumplirse en el autor ni en el trailer, en las dos copias. El origen es del lado de Nadir (PR #1), no de Arthur | Decidir si la regla alcanza al campo autor y a `Claude-Session:`. Si sí, corregirlo en la configuración de las sesiones de Nadir; no reescribir `main`. Valorar quitar las 12 copias de la rama antes de fusionar |
| S0-05 | Baja | Proceso | El PR #4 contiene todo el PR #3 y los dos apuntan a `main`. El PR #4 lo dice ("Depende del PR #3 … Conviene unir primero el #3") | B: `git merge-base --is-ancestor 0b6c413 9d24977` → sí; 120 commits de diferencia; `gh pr view 4` (descripción, "Pendiente") | Si se revisan los dos completos, 453 commits se revisan dos veces | Revisar el #3, fusionarlo y revisar en el #4 solo `0b6c413..9d24977`; o cerrar el #3 y revisar solo el #4 |
| S0-06 | Media | Gobierno de decisiones | De las 72 filas de decisiones del acta, 48 dicen que decidió un agente: 28 "Concilio, 3 de 3", 1 "Concilio de agentes, 3 de 3, por delegación de Arthur" y 19 "Claude, por delegación de Arthur". La delegación existe y la declaró Arthur, y excluye lo reservado a Nadir y a EL CLIENTE | B: `vault/meta/actas/acta-2026-11-20-modelo-y-pasarela.md:213` (decisión 19) y tabla de decisiones (en `9d24977`). C: C-236, C-245 (`acta-2026-11-20-modelo-y-pasarela.md:81`, `:151-162`) | Esas decisiones técnicas cuentan como grado C de Arthur, pero no hay evidencia de que un humano revisara cada una. SIN EVIDENCIA de revisión humana individual | Que Arthur confirme por escrito, en bloque o una por una, las decisiones del concilio que tocan seguridad, datos y cobro, antes de fusionar el PR #4 |
| S0-07 | Baja | Stack vs ADR | ADR-0003 y ADR-0005 dicen que el inicio de sesión usa Socialite y `socialiteproviders/apple`. `composer.json` no trae ninguno de los dos. La adenda de ADR-0005 del 2026-11-21 cambia la validación de Google a `firebase/php-jwt`, pero ADR-0003 no se actualizó. El acceso con Apple está bloqueado por DD-069 y `expo-apple-authentication` no está en `movil/package.json` | B: `vault/arquitectura/adr/ADR-0003-backend-y-lenguaje.md:55,102`; `ADR-0005-autenticacion.md:54-56,101-106`; `servidor/composer.json` (`require`); `movil/app/inicio-de-sesion.tsx:3` (todo en `9d24977`) | Un ADR desactualizado. El acceso con Apple está NO INICIADO; su efecto en la publicación (regla 4.8 de la App Store, grado E en ADR-0005:18) toca a los segmentos 1 y 8 | Actualizar ADR-0003:55 y :102 con la adenda. Llevar DD-069 a los segmentos 1 y 8 |
| S0-08 | Baja | Stack | `servidor/composer.json` declara `"minimum-stability": "dev"` (con `prefer-stable`) y permite el plugin `pestphp/pest-plugin`, pero Pest no está instalado: las pruebas son PHPUnit | B: `servidor/composer.json` (bloques `config` y `minimum-stability`); `composer.lock` en `9d24977`: 0 paquetes con versión dev, alpha, beta o RC | Hoy ningún paquete inestable. Un `composer update` podría traer uno | `"minimum-stability": "stable"` y quitar el permiso del plugin de Pest |
| S0-09 | Baja | Estilo de commits | 176 de 470 asuntos pasan de 72 caracteres | B: `git log --no-merges --format=%s origin/main..9d24977 \| awk 'length>72' \| wc -l` | Solo legibilidad en `git log --oneline` | Ninguna urgente |
| S0-10 | Info | Tamaño de commits | Líneas cambiadas por commit (sin merges): mediana 184, p90 811, p99 2 492, máximo 19 146. 26 commits pasan de 1 000 líneas y 3 de 5 000. Los dos mayores son en su mayoría archivos de bloqueo y el esqueleto de Laravel: `e9eb3dd` (18 692 de `movil/package-lock.json`) y `95f8641` (10 825 de `servidor/composer.lock`). El mayor de código escrito a mano es `21d9861`, la landing: 4 126 líneas en 35 archivos | B: `git log --no-merges --numstat origin/main..9d24977`; `git show --numstat e9eb3dd 95f8641 21d9861` | Commits de buen tamaño para revisarse uno por uno; el problema está en el tamaño del PR (S0-01) | — |
| S0-11 | Info | Mezcla vault/código | 13 commits tocan `vault/` y `movil/` o `servidor/` a la vez (por ejemplo `30fd904`: 8 del vault y 9 de código). 231 commits tocan `openspec/` y código. 5 tocan `movil/` y `servidor/`. SIN EVIDENCIA de una regla que lo prohíba | B: análisis de `--numstat` por commit (comando abajo) | Al entregar, `movil/` y `servidor/` se separan del resto (`CLAUDE.md` §9.7); separar por ruta funciona igual con commits mixtos | — |
| S0-12 | Info | CI | El PR #4 tiene 9 checks en verde en `9d24977`. Hay dos flujos que validan el vault: `vault.yml` (`verificar_vault.py`, de Arthur) y `validar-vault.yml` (`validar.py`, del PR #1) | B: `gh pr view 4 --json statusCheckRollup`; `.github/workflows/vault.yml:21`, `validar-vault.yml:19` | Dos validadores del vault con reglas que pueden divergir | Se revisa en el segmento 9 (relacionado con DD-127) |

### Inventario de pull requests (`nhadadn/Lavandi`)

| PR | Autor | Rama → base | Commits (git) | Archivos | +/− | Estado | CI en el último SHA | Reseñas en GitHub |
|---|---|---|---|---|---|---|---|---|
| #1 | nhadadn | `nhadadn:claude/dazzling-gates-53oar8` → `main` | 12 | 35 | +1 176 / −63 | MERGED 2026-11-24 05:08 UTC por nhadadn | `validar` ✓ (`8333f09`) | 0 |
| #2 | AsberNaranjo | `AsberNaranjo:integracion/lavandi` → `main` | 42 (según GitHub) | 472 | +25 597 / −249 | MERGED 2026-11-24 05:09 UTC por nhadadn | **sin checks**; corridas en `6c39276` en `failure` con 0 jobs | 0 (1 comentario de nhadadn) |
| #3 | AsberNaranjo | `AsberNaranjo:feat/h2-lavanderias` → `main` | 453 | 1 194 | +165 499 / −511 | OPEN | 8 de 8 ✓ (`0b6c413`) | 0 |
| #4 | AsberNaranjo | `AsberNaranjo:feat/h3-concilio` → `main` | 573 (incluye los 453 del #3) | 1 232 | +171 533 / −672 | OPEN | 9 de 9 ✓ (`9d24977`) | 0 |

Las corridas iniciales del PR #3 (`65731ed`, `c3412f1`) quedaron en `action_required`: GitHub pide aprobación para la
primera corrida desde un fork. Las siguientes (`7d09b67`, `0b6c413`) salieron en verde.

### Commits del rango `origin/main..9d24977`

| Medida | Valor |
|---|---|
| Commits | 573 (470 sin merge, 103 de merge) |
| Autor | Arthur Naranjo 458 · `Claude <noreply@anthropic.com>` 12 (S0-04) |
| Por día (fecha de autor) | 11-20: 41 · 11-21: 121 · 11-22: 203 · 11-23: 5 · 11-24: 100 |
| Tipos | feat 239 · vault 61 · docs 55 · fix 38 · test 37 · openspec 13 · wip 12 · chore 5 · refactor 4 · ci 3 · style 2 · build 1 |
| Fuera del formato `tipo(alcance): …` | 0 |
| Asuntos en inglés (búsqueda heurística de verbos comunes) | 0 |
| `Co-Authored-By: Claude` / `Generated with Claude Code` | 0 en el rango · 86 commits en `main`, de 2026-09-07 a 2026-11-20 (12 de 2026-11-20, del PR #1) |
| `Claude-Session:` | 12 en el rango · 12 en `main` |
| Commits directos a `main` | 0 desde el 2026-11-12. La historia de primer padre de `main` tiene 74 commits sin merge de Nadir, del cimiento (del 2026-09-07 al 2026-11-12), antes de la regla C-202 |

### Stack real (en `9d24977`)

| Pieza | Declarado en el manifiesto | Resuelto en el lock | ADR |
|---|---|---|---|
| PHP | `^8.3` | plataforma `^8.3` | ADR-0003 |
| Laravel | `laravel/framework ^13.0` | 13.43.0 | ADR-0003 |
| Panel | `filament/filament ^5.0` | 5.11.4 (Livewire 4.4.8) | ADR-0003:52 cita 5.11.2 |
| Tokens de API | `laravel/sanctum ^4.0` | 4.5.0 | ADR-0005 |
| Token de Google | `firebase/php-jwt ^7.2` | 7.2.2 | adenda de ADR-0005 (2026-11-21) |
| Socialite / Apple | **no está** | — | ADR-0003:55, ADR-0005:56 (S0-07) |
| Pruebas del servidor | `phpunit/phpunit ^12.5.12` | 12.5.45 | — |
| Front del servidor | Vite ^7, Tailwind ^4, `@playwright/test` 1.57.0 | `servidor/package-lock.json` | ADR-0011 |
| Base de datos | PostgreSQL con PostGIS (no aparece en los manifiestos) | — | ADR-0004 |
| App | `expo ~58.0.26`, `react-native 0.88.1`, `react 19.3.0`, `expo-router ~58.0.32` | 58.0.26 / 0.88.1 / 19.3.0 / 58.0.32 | ADR-0002, ADR-0017 |
| Mapas | `react-native-maps 1.29.11` | 1.29.11 | ADR-0009 |
| Google Sign-In | `react-native-nitro-google-signin 2.3.1` | 2.3.1 | ADR-0005:36,54 |
| Avisos | `@react-native-firebase/app` y `messaging` 27.1.1 | 27.1.1 | ADR-0008 |
| Sesión en el teléfono | `expo-secure-store ~58.0.3` | 58.0.3 | ADR-0018 |
| Pruebas de la app | `jest ~30.5.2`, `jest-expo`, `@testing-library/react-native ^14.1.0`; Maestro en `movil/.maestro/` | 30.5.2 | — |
| Pasarela | sin SDK en ningún manifiesto; el servidor tiene `app/Cobro/PasarelaConekta.php` | — | ADR-0010 (el detalle va en el segmento 5) |
| Herramienta de tokens | `herramientas/tokens/` sin dependencias | — | ADR-0013 |

**Código escrito contra código generado o de terceros** (líneas agregadas en `origin/main...9d24977`):

| Grupo | Archivos | Líneas agregadas |
|---|---|---|
| Archivos de bloqueo (`movil/package-lock.json`, `servidor/composer.lock`, `servidor/package-lock.json`) | 3 | 34 149 |
| Generados por la herramienta de tokens (`movil/src/tema/theme.ts`, `servidor/resources/css/tokens.css`; llevan la marca de generado) | 2 | dentro de `movil/src` y `servidor/resources` |
| Esqueleto de Laravel (`servidor/config/*.php` de fábrica, `artisan`, `bootstrap/`, `welcome.blade.php`) | no se cuantificó por separado | no se cuantificó por separado |
| Pruebas: `servidor/tests` 43 628 (233 archivos) · `movil/__tests__` 23 722 (85) · `movil/.maestro` 506 (7) | 325 | 67 856 |
| Código de producto: `servidor/app` 26 115 (359) · `movil/src` 13 548 (158) · `movil/app` 3 981 (40) · `servidor/database` 3 481 (50) · `servidor/resources` 2 578 (47) · `servidor/routes` 295 (3) | 657 | 49 998 |
| Vault y specs: `vault` 8 986 (75, 13 binarios) · `openspec` 5 624 (79) | 154 | 14 610 |
| `vendor/` o `node_modules/` versionados | 0 | 0 |

De `servidor/config/`, los archivos de fábrica de Laravel (`cache`, `database`, `logging`, `mail`, `queue`, `session`)
tienen un solo commit cada uno. De los 24 archivos, 11 son de Laravel o publicados por un paquete (`app`, `auth`,
`cache`, `database`, `filesystems`, `logging`, `mail`, `queue`, `services`, `session`, `sanctum`), y los 13 restantes
son configuración propia del producto (`acceso.php`, `pasarela.php`, `pedidos.php`, `territorio.php` y otros).

### Preguntas abiertas

**Para Arthur**
1. ¿El flujo de C-158 (PR internos en el fork y un PR por sprint) sigue vigente? Si no, ¿qué lo reemplaza, y dónde
   queda el dictamen del agente revisor de cada cambio? (S0-01)
2. ¿Revisaste una por una las 48 decisiones del concilio y de Claude por delegación, o las aceptas en bloque? (S0-06)
3. ¿Las 12 copias de los commits del PR #1 en tu rama son a propósito? (S0-04)

**Para Nadir**
1. ¿Proteges `main` con PR obligatorio y checks requeridos? (S0-03)
2. ¿La regla de no atribución (C-195, C-196) alcanza al campo autor `Claude` y al trailer `Claude-Session:` de tus
   sesiones en la nube? (S0-04)
3. ¿Revisas el PR #3 y luego solo la diferencia del #4, o cierras el #3? (S0-05)

**Para EL CLIENTE:** ninguna en este segmento.

### Comandos ejecutados

```bash
git fetch --all --prune
git rev-parse arthur/feat/h3-concilio origin/main arthur/feat/h2-lavanderias arthur/main
git merge-base --is-ancestor arthur/feat/h2-lavanderias arthur/feat/h3-concilio
git merge-base --is-ancestor origin/main 9d24977
gh pr list -R nhadadn/Lavandi --state all
gh pr list -R AsberNaranjo/Lavandi --state all
gh pr view <n> -R nhadadn/Lavandi --json number,author,headRefName,baseRefName,state,mergedBy,commits,changedFiles,additions,deletions,reviews,comments,statusCheckRollup,headRefOid
gh run list -R nhadadn/Lavandi --limit 60
gh run view 44861806137 -R nhadadn/Lavandi --json jobs,conclusion
gh api repos/nhadadn/Lavandi/branches/main/protection
R=origin/main..9d2497775966ef2d8c0c0f204b2f67cfb8432fbd
git rev-list --count $R; git rev-list --count --no-merges $R; git rev-list --count --merges $R
git log --no-merges --format='%an <%ae>' $R | sort | uniq -c
git log --no-merges --date=format:'%Y-%m-%d' --format='%ad' $R | sort | uniq -c
git log --no-merges --format='%s' $R | sed -E 's/^([a-zA-Z-]+)(\([^)]*\))?(!)?:.*/\1/; t; s/.*/<NO-CC>/' | sort | uniq -c
git log --format='%B' $R | grep -ciE 'co-authored-by: *claude|generated with \[?claude'
git log --format='%B' $R | grep -c 'Claude-Session:'
git log --author='noreply@anthropic.com' --format='%h %s' $R
git range-diff c8974b6^..8333f09 d7d150c^..a6056a4
git cat-file -p c8974b6; git cat-file -p d7d150c
git log --first-parent --format='%h %ad %an | %s' --date=short origin/main
git log --no-merges --format='@@%h' --numstat $R   # tamaño y mezcla por commit, agregado con awk
git diff --numstat origin/main...9d24977           # líneas por carpeta
git show 9d24977:servidor/composer.json; git show 9d24977:servidor/composer.lock
git show 9d24977:movil/package.json; git show 9d24977:movil/package-lock.json
git show 9d24977:servidor/package.json
git grep -nE 'Socialite|socialiteproviders|php-jwt|Apple' 9d24977 -- vault/arquitectura/adr/
```
