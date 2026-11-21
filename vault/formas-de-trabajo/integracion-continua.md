---
estado: vigente
grado: D
fuente: prompt-maestro-01-fundacion-v5.md, Fase 7 (B); .github/workflows/vault.yml y ci.yml (B); herramientas/ci/ (B); acta-2026-11-18-entorno-y-mocks.md C-169 (C); ADR-0002, ADR-0003, ADR-0004, ADR-0012 y ADR-0013 (D); CLAUDE.md §4, §5 y §9 (B)
fecha: 2026-11-18
dueño: Arthur Naranjo
---
# Integración continua

Hay dos flujos en este mismo repositorio (C-169): uno para el vault y otro para el código. Los dos corren con
GitHub Actions en cada push y en cada pull request. **Un pull request con la integración continua en rojo no se
fusiona.**

## La del vault

Corre desde el 2026-11-17.

Archivo: `.github/workflows/vault.yml`.

| Paso | Falla si… |
|---|---|
| Frontmatter de las notas (`herramientas/ci/verificar_vault.py`) | Una nota de `vault/` no tiene frontmatter, le falta `estado`, `grado`, `fuente`, `fecha` o `dueño`, o su estado no es `borrador`, `vigente` ni `superseded` (CLAUDE.md §4) |
| Vocabularios del tablero (`herramientas/tablero/construir_tablero.py`) | `estado-del-proyecto.md` tiene un estado fuera de su vocabulario o una fila sin estado, o la deuda de decisión repite un id |

Al cerrar la Fase 7 pasan las 138 notas. Para que pasaran se corrigió una:
`cliente/comunicaciones/2026-09-20-respuesta-al-feedback-del-manual.md` decía `estado: enviada`, que no está en el
vocabulario. Ahora dice `vigente`, y el envío se conserva en su campo `enviada:`.

## La del código

Archivo: `.github/workflows/ci.yml`, con sus verificaciones en `herramientas/ci/`. Hasta el 2026-11-18 esperaba en un
esqueleto para un repositorio aparte; con C-169 pasó a la raíz de este. Ya corre: las reglas pasan sobre todo el
repositorio (0 apariciones de la marca en el código y 0 secretos versionados, comprobado el 2026-11-18).

**Pasa en verde sin código.** Cada trabajo se salta solo si su carpeta todavía no existe. Así la integración
continua existe desde el primer commit, como pide el método.

| Trabajo | Corre cuando | Falla si… |
|---|---|---|
| **reglas** | Siempre | La marca o el dominio aparecen escritos a mano en `movil/` o en `servidor/` (CLAUDE.md §5), o se versiona un `.env`, una llave privada, un almacén de firma o la configuración de Firebase (ADR-0012) |
| **servidor** | Existe `servidor/composer.json` | `composer.json` no es válido; el estilo no sigue Pint; el análisis estático encuentra errores (cuando exista `phpstan.neon`); las migraciones no corren desde cero sobre PostgreSQL con PostGIS (ADR-0004); o falla un test |
| **movil** | Existe `movil/package.json` | Hay un error de tipos de TypeScript, un error de ESLint o un test que falla, o las dependencias no son compatibles con la versión de Expo (`expo-doctor`) |
| **tokens** | Existe `herramientas/tokens/package.json` | `theme.ts` o las variables CSS no coinciden con lo que genera `tokens.json` (ADR-0013) |

**Las versiones** (PHP 8.4, Node 22 y PostGIS 17-3.5) se confirman en el sprint 1, al crear los proyectos (E).

## Lo que no corre en la integración continua, y dónde se comprueba

| Qué | Por qué no corre aquí | Dónde se comprueba |
|---|---|---|
| El recorrido en el teléfono con Maestro (A43-2) | Necesita un teléfono o un emulador con la app compilada | En la máquina de Arthur, antes del cierre de cada sprint que toque el recorrido |
| El tiempo de 60 s en teléfonos reales (A43-3) | Se mide con dos teléfonos y el servidor arriba | En el entorno de prueba, al acreditar |
| PageSpeed Insights sobre la dirección publicada (A42-4) | Necesita el sitio publicado | Al acreditar. Mientras tanto, Lighthouse CI sirve de alerta temprana cuando exista la landing |
| Una transacción real de Conekta (A43-4) | Necesita la cuenta del cliente en producción | Al acreditar, con su comprobante |

Estas pruebas tienen su fila en [[trazabilidad-criterio-test]].
