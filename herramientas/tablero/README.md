# Tablero del proyecto

El tablero es una página de claude.ai, de tipo Dashboard, que muestra el estado del proyecto en un
vistazo: <https://claude.ai/artifact/T2tZpPePdPhccB4GmFRjbK>

**Es un espejo del vault.** No guarda nada propio ni se edita a mano. Todo lo que muestra
sale de notas del vault, y cada cifra dice de cuál: en el tablero, el panel de fuentes lo enseña al
pulsar cualquier número.

## De dónde sale cada cosa

| En el tablero | Sale de |
|---|---|
| Fases, actividades del calendario, obligaciones, pagos, trámites y lo que sigue: **su estado** | `vault/meta/estado-del-proyecto.md` |
| Las actividades del calendario, sus semanas, los pagos y los trámites: **qué son** | `vault/contrato/calendario-contractual.md` |
| Las obligaciones: **qué son** | `vault/contrato/obligaciones-ejecutivas.md` |
| Las decisiones pendientes | `vault/meta/deuda-de-decision.md` |
| Los riesgos | `vault/riesgos-supuestos/riesgos.md` |
| Las actas | `vault/meta/actas/` |
| Las horas necesarias y disponibles | `vault/contrato/inventario-funcional.md`, sección 7 |

`construir_tablero.py` lee todo eso y escribe un JSON por fuente en `salida/`. Se versionan: así queda en
git qué mostraba el tablero en cada corte.

## Cómo se actualiza

Cada vez que una sesión cambia el estado del proyecto, en la misma sesión:

1. **Se cambia el vault.** Si cambia un estado, se cambia en `estado-del-proyecto.md` con su evidencia.
   Las decisiones, los riesgos y las actas se mantienen donde siempre.
2. **Se corre el generador** con la fecha del corte:

   ```bash
   python herramientas/tablero/construir_tablero.py 2026-10-09
   ```

   Si un estado no está en el vocabulario, o una actividad del calendario no tiene estado, el generador se
   detiene y dice cuál. No adivina.
3. **Se suben al tablero los JSON que cambiaron**: cada uno como archivo del tablero y, después, se
   actualiza su fuente con la dirección nueva, el nombre y la fecha (`updated`). Eso lo hace Claude con
   las herramientas del artifact.

Cada fuente está marcada para caducar a las **168 horas**, una semana. Si pasa una semana sin que nadie
actualice el tablero, el propio tablero lo marca como viejo.

**El corte manda.** Todo lo "vencido" se calcula contra la fecha del corte, no contra el día en que alguien
abre el tablero. Así el tablero nunca dice algo que el vault no decía en esa fecha.

## Quién puede verlo

**Es privado**: solo lo abre Nadir hasta que lo comparta desde el propio tablero. Cualquiera que pueda
abrirlo puede leer también sus archivos de datos.

**No se comparte con EL CLIENTE tal como está.** Muestra la deuda de decisión y el registro de riesgos, y
la regla del vault es que, si el cliente llega a ver un espejo, quedan fuera `negocio/` y
`riesgos-supuestos/` (`vault/meta/convenciones.md`, "Espejo Notion" y "Tablero del proyecto"). Para el
cliente haría falta otra versión, sin esas dos pestañas, y asentarlo en un acta antes de compartirla.

## Qué hay aquí

| Archivo | Qué es |
|---|---|
| `construir_tablero.py` | El generador: vault → `salida/*.json` |
| `salida/` | Los JSON del último corte, uno por fuente |
| `pagina.html` | **La página del tablero.** Es la fuente: se sube como `files/index.html` del artifact. Si el artifact se pierde, con esto y `salida/` se rehace entero |

Si cambia la página, se cambia aquí primero y después se sube.
