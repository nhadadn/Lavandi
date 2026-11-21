---
estado: superseded
grado: mixto
fuente: meta/convenciones.md, sección "Espejo Notion" (B); acta-2026-09-07-fundacion.md C-28 (C); búsqueda de enlaces a Notion en el vault, 2026-11-17 (B)
fecha: 2026-11-17
dueño: Arthur Naranjo
---
# Espejo a Notion

> **Superseded el 2026-11-20 (C-203):** Nadir retiró el espejo a Notion; el repo es la única copia del vault.
> DD-075 queda cerrada.

## Lo que ya está decidido

- **Qué se espeja:** todo `vault/`. `legal/` nunca (C-28; `meta/convenciones.md`).
- **La audiencia:** EL CLIENTE no consulta el Notion (C-28). Si algún día se le da acceso, quedan fuera también
  `negocio/` y `riesgos-supuestos/`, y el cambio se asienta antes en un acta.
- **Nada nace en Notion.** Un comentario escrito en Notion no es grado C hasta que se transcribe a un acta.

## Lo que esta fase tenía que definir: quién y cada cuánto

**Hoy el espejo no existe.** En el vault no hay ningún enlace a Notion (B, búsqueda del 2026-11-17). El tablero ya
cumple la función de ver el estado sin abrir el repositorio.

**Propuesta:**

- **Cada cuánto:** al cierre de cada sprint (los domingos) y al cierre de cada fase.
- **Quién:** Arthur, con Claude, mediante el conector de Notion. El conector necesita autorización en la cuenta de
  Arthur.
- **Cómo:** una página de solo lectura por nota. El encabezado de cada página dice la ruta del vault y el commit
  del que sale.

**Recomendación: no montarlo hasta que alguien lo vaya a consultar.** Hoy no lo lee nadie. Mantenerlo costaría
tiempo de cada cierre de sprint sin que nadie lo use.

`SIN RESPUESTA — bloquea: el espejo:` en qué espacio de Notion vive (de Nadir o de Arthur) y si Nadir lo quiere
consultar (DD-075).
