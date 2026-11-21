---
estado: vigente
grado: B
fuente: estructura del repo; práctica establecida al archivar las capturas del 2026-09-18
fecha: 2026-09-30
dueño: Nadir Hadad Navarrete
---
# Recibido de EL CLIENTE

El buzón de entrada. **Todo lo que llega de EL CLIENTE se deja aquí**, sea lo que sea: capturas de
WhatsApp, fotos, PDF comentados, archivos de logotipo, notas de voz transcritas, correos.

Es el espejo de `cliente/comunicaciones/`, que guarda lo que **sale** hacia él.

## La regla, en cuatro líneas

1. **Una subcarpeta por fecha de recepción**, en formato `AAAA-MM-DD`. Si una tanda llega repartida en
   dos días, se usa el día en que llegó cada pieza.
2. **Los archivos entran tal cual, sin renombrar.** El nombre que les puso WhatsApp y su hora son parte
   de la evidencia. Si hay que ordenarlos, se hace en el índice, no cambiando los archivos.
3. **Nada se edita ni se sustituye aquí.** Si llega una versión corregida de algo, entra como archivo
   nuevo con su propia fecha. Lo que entró, se queda.
4. **Cada tanda lleva su `indice.md`**, que dice qué hay en cada archivo, de qué grado es y a qué acta,
   fila de deuda o riesgo fue a parar. **Ese índice lo escribe Claude**, no Nadir: la tarea de Nadir
   termina al soltar los archivos en la carpeta.

## Por qué existe esta carpeta y no se guarda cada cosa en su dominio

Lo que manda EL CLIENTE casi nunca viene clasificado: en un mismo mensaje caben un comentario del
manual, un ajuste del logotipo y una idea de la app. Pedirle a quien recibe el WhatsApp que decida el
dominio antes de guardar es la forma más segura de que no se guarde nada.

Así que el buzón es plano y por fecha. **La clasificación es trabajo posterior**, y queda en el índice
de la tanda. Lo que sí se mueve de sitio es el arte: cuando un archivo de logotipo se verifica y se
adopta, se copia a `marca/visual/logotipo/` con nombre propio, y el original se queda aquí como
referencia histórica.

## Si lo que llega es arte del logotipo

**Hay que pedir el vector.** El arte oficial de hoy (`marca/visual/logotipo/lavandi-simbolo.svg`) es una
**reconstrucción** hecha a partir del PNG del diseñador, verificada píxel a píxel: difiere el 1.34 % y
todo ese porcentaje está en los bordes, que es antialiasing. Esa reconstrucción existe porque no hubo
vector.

Si llega arte nueva solo en PNG o JPEG, hay que rehacer ese trabajo entero. Con un `.ai`, `.eps` o `.svg`
no hace falta. Conviene pedirlo en el mismo mensaje en que se reciba el cambio, cuando el diseñador
todavía tiene el archivo abierto.

Y un efecto que juega a favor: el manual define sus dibujos **una sola vez**, como `<symbol>`, y de ahí
salen las 18 páginas y el logotipo de la palabra. Cambiar el logotipo es cambiar esos símbolos y volver
a correr el generador: ver `herramientas/manual-de-identidad/README.md`.

## Una advertencia que ya pasó

La primera vez que se usó el buzón, diecinueve archivos cayeron en la copia de trabajo de la sesión y uno
en la carpeta del repo. Fue porque la carpeta que se abrió en el Explorador era la de la sesión. **La que
vale es siempre `D:\Proyectos\nsv-lavanderia\vault\cliente\recibido\`**, la que abre Obsidian.

## Tandas archivadas

| Fecha | Qué llegó | Índice |
|---|---|---|
| 2026-09-18 | Cuatro capturas del manual 1.0 y de la guía de tono, marcadas a mano. Evidencia primaria de C-76 a C-83 | `2026-09-18/indice.md` |
| 2026-09-30 | Doce fotos del manual 1.1 impreso con marcas a mano, y el logotipo de la palabra del diseñador en `.ai`, `.eps` y exportaciones. Llegaron por WhatsApp el 21 y el 29. Evidencia de C-98 a C-102 | `2026-09-30/indice.md` |
| 2026-11-05 | Fotos del formulario de prioridades y de la guía de la reunión, llenados a mano por el cliente. Se leyeron en `cliente/comunicaciones/2026-11-05-reporte-del-formulario.md` | **Sin índice** (se notó el 2026-11-18) |
| 2026-11-14 | Seis fotos de una libreta de precios (Tintorería y Lavandería y planchaduría) y un video promocional de 61 s, por WhatsApp. Evidencia de C-172 y C-173 | `2026-11-14/indice.md` |
| 2026-11-18 | Dos notas de voz de WhatsApp dirigidas a Nadir, sobre los pedidos de noche y un día para lavar. Evidencia de C-178 y C-179 | `2026-11-18/indice.md` |
