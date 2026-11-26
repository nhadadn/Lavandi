---
estado: vigente
grado: B
fuente: auditoría de solo lectura de arthur/feat/h3-concilio en 9d2497775966ef2d8c0c0f204b2f67cfb8432fbd (PR #4); .github/workflows/, herramientas/ci/, servidor/phpunit.xml, los 498 escenarios de openspec/changes/*/specs contra servidor/tests, movil/__tests__ y movil/.maestro, README.md, servidor/README.md y vault/formas-de-trabajo/ (entorno-local, correr-la-app-en-local, integracion-continua, trazabilidad-criterio-test); resultado de la corrida 45550228403 de GitHub Actions en ese SHA leído con gh; sin correr pruebas, por decisión de Nadir Hadad Navarrete en sesión del 2026-11-24
fecha: 2026-11-25
dueño: Nadir Hadad Navarrete
---
# Auditoría Lavandi — Segmento 9: pruebas, CI y DX

**Objeto:** rama `arthur/feat/h3-concilio` (PR #4) · SHA `9d24977` · fecha de corte 2026-11-24.

**Qué NO se revisó:**
- **La auditoría no corrió pruebas** (`CORRER_TESTS = no`). Lo que aquí se dice de resultados viene de la CI del PR #4
  en el SHA auditado: corrida `45550228403`, `headSha 9d24977…`, evento `pull_request`, `conclusion: success`, del
  2026-11-25. La corrida es del commit de prueba de la fusión con `main`, que GitHub arma para un `pull_request`.
- **No se midió cobertura de líneas.** La CI no la mide (S9-01), y la auditoría no la calculó.
- **La cobertura por escenario es por mención.** Un escenario cuenta como "con prueba" si su identificador aparece en
  algún archivo de prueba. Eso no garantiza que la prueba compruebe lo que dice el escenario (S9-01).
- **No se intentó montar el entorno desde cero.** La reproducibilidad se juzga leyendo las guías.

**Veredicto: LISTO CON RESERVAS.**
- **La CI es seria, y en este SHA todo está en verde:**
  - en el servidor, 1323 pruebas más 9 de barrido contra fugas, sobre PostgreSQL con PostGIS real;
  - en la app, 1282 pruebas, con tipos, lint y Expo Doctor;
  - 87 pruebas de navegador en los tres motores, Lighthouse en teléfono, y las reglas de marca y secretos.
- **Hay escenarios con prueba:** 448 de 498 tienen una que los nombra. De los 50 que no, 44 están en una tarea abierta
  bloqueada por una decisión y 6 están declarados pendientes.
- **Las reservas:**
  - no se mide cobertura y el análisis estático no corre (S2-01);
  - las pruebas que la trazabilidad promete para A43-2 y A43-4 no existen;
  - la compilación de la app no se puede ni empezar, porque la cuenta de Expo sigue pendiente;
  - el README de la raíz dice que todavía no hay código.

### Resumen
- **CI en verde en el SHA auditado** (S9-06). Corre en cada `push` y `pull_request`, con 9 jobs y todos en `pass`.
- **Escenarios:** 448 de 498 (90 %) nombrados en una prueba. Nombrado no es probado: hay pruebas que fijan lo que todavía
  falta, como el 409 de pago-E14 (S9-01).
- **Lo que la CI no hace** (S9-02):
  - no compila la app;
  - no corre Maestro;
  - no prueba contra el sandbox de Conekta;
  - no tiene una prueba del ciclo completo del pedido, aunque la trazabilidad la promete.
- **La cuenta de Expo sigue pendiente** (S9-03). Sin ella no hay `eas build`, y eso refuerza S8-01.
- **DX:**
  - el servidor se puede montar con su README;
  - la app solo con una guía del vault, con rutas de la máquina de Arthur;
  - el README de la raíz sigue en "Fase de cimiento" (S9-04).

### Hallazgos
| ID | Severidad | Área | Hallazgo | Evidencia (grado + ruta:línea) | Impacto | Recomendación |
|---|---|---|---|---|---|---|
| S9-01 | Media | Medición de pruebas | **No hay cobertura medida, y la cobertura por escenario se infiere por nombre.** El servidor corre con `coverage: none` y Jest sin `--coverage`. La única medida posible es que el identificador del escenario aparezca en una prueba: 448 de 498. Pero hay pruebas que nombran un escenario para fijar que falta, no que funciona: "pago-E01 y pago-E14: un pedido sin cobro todavía no se paga: responde 409". Contadas por nombre, inflan lo cubierto | B: `.github/workflows/ci.yml:74-78` (`coverage: none`), `:275-276` (`npx jest --ci`); `servidor/tests/Feature/Pago/CrearCheckoutTest.php:206`; conteo con el script de la sección "Cobertura por escenario" | Un "90 % de escenarios con prueba" puede contar como implementado algo que solo está documentado como pendiente | Que cada tarea marque si su prueba acredita el escenario o solo fija su estado pendiente. Agregar `--coverage` a Jest y `pcov` al servidor, aunque sea solo como reporte |
| S9-02 | Media | Trazabilidad | **Las pruebas que la trazabilidad promete para A43-2 y A43-4 no existen.** Para A43-2 promete un "test del ciclo completo del pedido" en el servidor y un flujo de Maestro en el teléfono. No hay ninguno de los dos: el único flujo de Maestro mide un cambio de estado. Para A43-4 promete webhooks contra el sandbox: no existe `servidor/tests/Integracion/` y la tarea 3.3 de `pago` está abierta. La guía de la CI dice que Maestro corre "en la máquina de Arthur, antes del cierre de cada sprint". En el vault solo hay evidencia de estados-E17 | B: `vault/formas-de-trabajo/trazabilidad-criterio-test.md:45,47`; `vault/formas-de-trabajo/integracion-continua.md:53`; `movil/.maestro/estados/` (un solo flujo); `servidor/phpunit.xml:8-13` (solo `Unit` y `Feature`); `openspec/changes/pago/tasks.md:42`; `vault/evidencias/estados/` | A43-2 y A43-4 no tienen camino de acreditación ejercitado. Es el mismo corte de S6-01 y S6-04, visto desde el plan | Escribir ya la prueba del ciclo completo, que hoy falla en el cobro, y el flujo de Maestro del recorrido. Las dos marcan dónde está el corte |
| S9-03 | Media | Compilación | **La cuenta de Expo sigue pendiente, así que no se puede correr `eas build`.** La guía del entorno marca `eas-cli` instalado y la cuenta de Expo "⏳", a cargo de Arthur con su correo (C-170). Esto explica por qué no hay ninguna compilación de EAS (S8-01). Además, la cuenta sería de Arthur, no de EL CLIENTE | B: `vault/formas-de-trabajo/entorno-local.md:36`. C: C-170. A: cuentas a nombre de EL CLIENTE (`NSV-2026-001-v8.pdf:34:ANEXO B, Sección B.11`, RN-20) | A dos semanas del envío a las tiendas, la herramienta de compilación de producción no tiene cuenta | Abrir la cuenta de Expo esta semana y decidir con Nadir si va a nombre de EL CLIENTE, como las demás (B.11) |
| S9-04 | Baja | DX | **El README de la raíz está desactualizado y no lleva al código.** Dice "No hay código de producto todavía, a propósito" y su tabla no lista `servidor/` ni `movil/`. `servidor/README.md` sí deja montar el servidor. `movil/` no tiene README: su arranque está en `vault/formas-de-trabajo/correr-la-app-en-local.md`, con rutas de la máquina de Arthur (`C:\xampp\php`, el SDK de otro proyecto). PostGIS va en un contenedor descrito en prosa, sin `docker-compose` | B: `README.md:14-20,31-33`; `servidor/README.md:7-21`; `vault/formas-de-trabajo/entorno-local.md:18,25,35`; sin `docker-compose*` ni `Dockerfile` en el repositorio | Quien llegue, o EL CLIENTE al recibir el código (Cláusula IX: se ceden `movil/` y `servidor/`, no el vault), no tiene cómo montar la app sin el vault | Actualizar el README raíz, agregar `movil/README.md` con su arranque y un `compose.yaml` para PostGIS dentro de `servidor/` |
| S9-05 | Baja | Trazabilidad | **Tres escenarios dicen "SIN RESPUESTA", pero el código ya aplica la recomendación.** Son autorizacion-E04 a E06, bloqueados por DD-053, que sigue abierta desde el 2026-10-02. El código ya hace lo que esa decisión recomienda: una cuenta tiene un rol; el repartidor espera su alta; la app abre el módulo del rol. Y lo prueba con otros identificadores (registro-E33, pedidos-lavanderia-E03, repartidor-E01) | B: `openspec/changes/autorizacion-por-rol/specs/*/spec.md:51-76`; `openspec/changes/autorizacion-por-rol/tasks.md:31-33`; `movil/__tests__/sesion/modulo-por-rol.test.tsx:1-10`; `movil/app/repartidor/_layout.tsx:72-81`. C: DD-053 en `vault/meta/deuda-de-decision.md:65` (dueño: Nadir) | La spec y el código dicen cosas distintas. La alta de la lavandería (S1-01 e) sigue colgando de esa misma decisión | Nadir: cerrar DD-053, o asentar que la recomendación vale para la v1. Luego, que los tres escenarios apunten a sus pruebas |
| S9-06 | Info | Positivos | **La CI es seria y está en verde en el SHA auditado.** Corre en cada `push` y `pull_request`, y se salta lo que no existe. **servidor:** `composer validate --strict`, Pint, migraciones desde cero sobre `postgis/postgis:17-3.5`, barrido contra fugas aparte (9 pruebas, 244 aserciones) y 1323 pruebas (13 055 aserciones). **movil:** `tsc`, ESLint, 1282 pruebas en 79 suites, Expo Doctor 20/20. **landing:** 87 pruebas de Playwright en Chromium, Firefox y WebKit, y Lighthouse en teléfono. **reglas:** `verificar_marca.py`, `verificar_secretos.py` y 5 pruebas de los verificadores. **vault:** `validar.py` y `verificar_vault.py` | B: `.github/workflows/ci.yml:7-9,12-25,27-42,44-104,111-247,249-279`; `.github/workflows/validar-vault.yml`, `vault.yml`; corrida `45550228403`, con los jobs `servidor`, `movil`, `landing-navegadores`, `landing-velocidad`, `reglas`, `tokens`, `detectar`, `validar` y `verificar` en `pass` | — | Conservar. Proteger `main` para que exija estos checks (S0-03) |

**Ya reportados, que tocan este segmento:**
- **S0-02:** el PR #2 se fusionó sin CI.
- **S0-03:** `main` no tiene protección de rama.
- **S2-01:** PHPStan nunca corre. No hay `phpstan.neon` ni Larastan en `composer.json`, y el paso depende de que el
  archivo exista (`ci.yml:92-94`).
- **S6-04:** ninguna prueba recorre el pedido completo.
- **S7-04:** Lighthouse mide con contenido de prueba.
- **S8-01:** la CI no compila la app.
- **S8-04:** la evidencia de la CI ajusta lo que los segmentos anteriores daban como "NO VERIFICADO".

### Workflows (`.github/workflows/`)
| Workflow · job | Cuándo | Qué lo hace fallar | Resultado en `9d24977` |
|---|---|---|---|
| `ci` · `detectar` | Siempre | — (decide qué jobs corren) | pass |
| `ci` · `reglas` | Siempre | Marca o dominio en el código; secretos; pruebas de los verificadores | pass (5 pruebas) |
| `ci` · `servidor` | Existe `servidor/composer.json` | `composer validate`, Pint, PHPStan (**omitido**: no hay `phpstan.neon`), migraciones, barrido, pruebas | pass (9 + 1323) |
| `ci` · `landing-velocidad` | Ídem | Lighthouse en teléfono < 0.70 | pass |
| `ci` · `landing-navegadores` | Ídem | Playwright en Chromium, Firefox o WebKit | pass (87) |
| `ci` · `movil` | Existe `movil/package.json` | `tsc`, ESLint, Jest, Expo Doctor | pass (1282) |
| `ci` · `tokens` | Existe `herramientas/tokens/package.json` | Tokens generados distintos de los versionados | pass |
| `Validar vault` · `validar` | PR, y `push` a `main` | Frontmatter, enlaces e identificadores | pass |
| `vault` · `verificar` | Siempre | Frontmatter y vocabularios del tablero | pass |

**Lo que no corre en la CI, según la guía y el workflow:**
- la compilación nativa o de EAS;
- Maestro;
- el sandbox de Conekta;
- PageSpeed sobre el sitio publicado;
- cobertura;
- PHPStan, mientras no exista `phpstan.neon`.

La guía lo declara en `vault/formas-de-trabajo/integracion-continua.md:49-58`, salvo la cobertura y la compilación.

### Cobertura por escenario
**Cómo se contó:** se tomaron los `#### Scenario: <id>` de `openspec/changes/*/specs/*/spec.md`, sin `archive/`, y se
buscó cada identificador en `servidor/tests/`, `movil/__tests__/` y `movil/.maestro/`. "En tarea abierta" quiere decir
que el identificador está en una línea `- [ ]` de su `tasks.md`.

| Capacidad | Escenarios | Con prueba | Sin prueba | Sin prueba, en tarea abierta |
|---|---|---|---|---|
| administracion | 26 | 23 | 3 | 3 |
| agenda-de-recoleccion | 12 | 10 | 2 | 2 |
| alta-de-lavanderias | 31 | 27 | 4 | 4 |
| autorizacion-por-rol | 14 | 11 | 3 | 0 (S9-05) |
| avisos | 21 | 19 | 2 | 2 |
| base-tecnica | 11 | 11 | 0 | — |
| bienvenida | 6 | 5 | 1 | 1 |
| direcciones | 24 | 19 | 5 | 5 |
| estados-del-pedido | 24 | 20 | 4 | 4 |
| exportacion | 12 | 12 | 0 | — |
| historial-de-la-lavanderia | 8 | 8 | 0 | — |
| historial-y-calificacion | 14 | 13 | 1 | 1 |
| indicadores | 24 | 23 | 1 | 1 |
| landing | 26 | 26 | 0 | — |
| lavanderias-cercanas | 22 | 20 | 2 | 2 |
| medicion-de-la-landing | 15 | 15 | 0 | — |
| modelo-de-datos | 17 | 17 | 0 | — |
| pago | 20 | 16 | 4 | 1 |
| pedidos-de-la-lavanderia | 18 | 15 | 3 | 3 |
| perfil-de-lavanderia | 18 | 16 | 2 | 2 |
| perfil-y-soporte | 17 | 16 | 1 | 1 |
| plazas-y-zonas | 16 | 15 | 1 | 1 |
| publicacion | 16 | 9 | 7 | 7 |
| registro-e-inicio-de-sesion | 33 | 31 | 2 | 2 |
| repartidor | 31 | 31 | 0 | — |
| resumen-y-confirmacion | 15 | 14 | 1 | 1 |
| textos-legales | 7 | 6 | 1 | 1 |
| **Total** | **498** | **448** | **50** | **44** |

**Los 6 sin prueba que no están en una tarea abierta:**
- autorizacion-E04 a E06 (S9-05);
- pago-E12, E13 y E20 (el efectivo y el vencimiento), declarados pendientes de DD-056 y DD-012 en
  `openspec/changes/pago/tasks.md:54-59`.

**`publicacion`** tiene la proporción más baja: 7 de 16 sin prueba. Es la capacidad del envío a las tiendas y su
compilación (S8-01).

### Verificadores del proyecto
| Verificador | Qué hace | En la CI | Resultado |
|---|---|---|---|
| `herramientas/ci/verificar_marca.py` | Marca o dominio escritos en `movil/` o `servidor/` (CLAUDE.md §5), con `terminos-de-marca.txt` | `reglas` | pass en la CI; 0 en local en este SHA (segmento 0) |
| `herramientas/ci/verificar_secretos.py` | `.env`, llaves privadas, llaves de Conekta, archivos de firma | `reglas`, con 5 pruebas propias (`test_verificar_secretos.py`) | pass; 0 en local (segmento 0) |
| `herramientas/validar-vault/validar.py` | Frontmatter, enlaces e identificadores del vault | `Validar vault` | pass |
| `herramientas/ci/verificar_vault.py` | Frontmatter y vocabularios | `vault` | pass |

### ¿Se reproduce el entorno desde el README?
| Parte | Guía | ¿Basta? |
|---|---|---|
| Servidor | `servidor/README.md`: requisitos (PHP 8.4, Composer 2, PostgreSQL 17 con PostGIS 3.5), arranque y pruebas | Sí, si uno ya tiene PostGIS. La CI hace exactamente eso |
| Base de datos | `vault/formas-de-trabajo/entorno-local.md:35`: un contenedor descrito en prosa | A medias: no hay `compose.yaml` |
| App | No hay `movil/README.md`. `vault/formas-de-trabajo/correr-la-app-en-local.md`: web en Chrome, APK local, Expo Go en iPhone | Solo con el vault, y con rutas de una máquina concreta |
| Compilación para las tiendas | `movil/eas.json` | No: falta la cuenta de Expo (S9-03) |
| README raíz | `README.md` | No: dice que no hay código (S9-04) |

### Preguntas abiertas
**Para Arthur:**
- ¿La prueba del ciclo completo del pedido (A43-2) entra en este sprint, aunque falle en el cobro?
- ¿Cuándo se abre la cuenta de Expo, y a nombre de quién?
- ¿Se agrega `phpstan.neon` (S2-01) y un reporte de cobertura, aunque no bloqueen?

**Para Nadir:**
- DD-053 lleva abierta desde el 2026-10-02 y el código ya aplica su recomendación. ¿La cierras o la asientas para la v1?
- ¿La cuenta de Expo va a nombre de EL CLIENTE, como las demás cuentas del B.11?

**Para EL CLIENTE, vía Nadir:** ninguna nueva en este segmento.

### Comandos ejecutados
```bash
# En el worktree de solo lectura del SHA 9d24977
ls .github/workflows; cat .github/workflows/ci.yml .github/workflows/validar-vault.yml .github/workflows/vault.yml
ls servidor | grep -iE 'phpstan|phpunit'; grep -n 'phpstan\|larastan' servidor/composer.json
grep -nE 'testsuite|directory' servidor/phpunit.xml
ls herramientas/ci; cat README.md servidor/README.md
grep -nE 'PHP|PostGIS|EAS|Expo' vault/formas-de-trabajo/entorno-local.md
sed -n '47,58p' vault/formas-de-trabajo/integracion-continua.md
grep -nE '^\| A4' vault/formas-de-trabajo/trazabilidad-criterio-test.md
python <scratchpad>/cobertura.py <worktree>   # escenarios contra pruebas, por capacidad

# La CI del PR #4 en el SHA auditado
gh pr checks 4 -R nhadadn/Lavandi
gh run view 45550228403 -R nhadadn/Lavandi --json headSha,conclusion
gh run view 45550228403 -R nhadadn/Lavandi --log --job <id> | grep -E 'Tests:|passed'
```
