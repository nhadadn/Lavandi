---
estado: vigente
grado: C
fuente: decisiones de Nadir del 2026-11-20 (C-202 a C-207 en acta-2026-11-20-decisiones-de-maquetas)
fecha: 2026-11-20
dueño: Nadir Hadad Navarrete
---
# Guía de arranque: cómo trabajamos en Lavandi

Para quien llega al repo. Diez minutos. El detalle está en [[colaboracion-en-el-vault]] y en `CLAUDE.md`.

## Quiénes somos

Nadir Hadad Navarrete y Arthur Naranjo, cada uno con Claude Code. Lo que cualquiera de los dos declara en
sesión es grado C, con su nombre en el acta (C-211). Aprobar envíos al cliente, dominio e IMPI, y lo que se
firma como EL PRESTADOR siguen siendo de Nadir.

Si usan la misma cuenta de Claude, la cuenta no distingue quién escribe: **al abrir cada sesión, Claude
pregunta quién eres**, y sin esa respuesta no asienta nada grado C (C-220). Lo recomendado es una cuenta
para cada uno.

## La idea en tres frases

1. **Todo vive en el repo.** Decisiones, specs, actas y hallazgos se guardan en `vault/`. Lo que no está
   ahí no existe para la otra persona.
2. **Claude administra el vault.** Archiva, enlaza, valida y sube los cambios solo, en cada sesión. No hay
   que pedírselo.
3. **Nada entra a `main` sin pull request** revisado por la otra persona y con la validación en verde.

## Primera vez

1. Clona el repo: `git clone https://github.com/nhadadn/Lavandi`.
2. Abre Obsidian → *Abrir carpeta como bóveda* → elige la carpeta `vault/`, no la raíz.
3. Opcional: instala el plugin de la comunidad *Obsidian Git* configurado **solo para traer** cambios
   cada pocos minutos. Nunca para hacer commit ni push automáticos.
4. Abre Claude Code en la raíz del repo. Lee `CLAUDE.md` solo, al empezar.

## Una tarea, de principio a fin

| Paso | Quién lo hace |
|---|---|
| Al abrir la sesión: traer lo último, listar tareas en curso, validar | El hook de inicio, solo |
| Abrir la nota de la tarea en `vault/meta/trabajo/` y la rama | Claude, al empezar |
| Trabajar: specs, decisiones, documentos | Tú y Claude |
| Archivar decisiones, actualizar índice y deuda, validar, commit y push | Claude, con la skill `cerrar-sesion`. Si se le olvida, el hook de cierre se lo devuelve |
| Abrir el pull request | Claude, cuando se lo pidas |
| Revisar el pull request | La otra persona, con ayuda del subagente `revisor-de-evidencia` |
| Unir a `main` | Una persona. Claude no aprueba ni une |

## Qué le puedes pedir a Claude con nombre

- **"La spec de F-A09"** → skill `nueva-spec`: crea la spec con su cita del contrato.
- **"Esto queda pendiente para el cliente"** → skill `nueva-decision`: abre la nota `DD-NNN`.
- **"Revisa el PR de …"** → subagente `revisor-de-evidencia`: busca afirmaciones sin grado ni cita, datos
  inventados y decisiones presentadas como contrato.
- **"Cierra"** → skill `cerrar-sesion`. Corre sola de todos modos al terminar.

## Lo que no se hace

- No se escribe directo en `main`.
- No se toca `legal/`. Claude tampoco: su configuración se lo prohíbe.
- No se reescribe un acta ya unida: se corrige con una nueva.
- No se inventan precios, pesos, plazos ni preferencias de usuarios. Se preguntan o se marcan `SIN RESPUESTA`.
- No se presenta una decisión técnica como si la pidiera el contrato.

## Quién lleva qué

`SIN RESPUESTA — bloquea: reparto de áreas entre las dos personas`. Propuesta: una persona cierra la Fase 6
(ADR de stack y pasarela) y la otra escribe las specs del Módulo A a partir de las maquetas de la
Propuesta 5. Lo decide Nadir y se anota aquí.

## Dónde está cada cosa

| Busco | Voy a |
|---|---|
| Las reglas que lee Claude | `CLAUDE.md` |
| Qué está pendiente de decidir | `vault/meta/deuda-de-decision.md` y `vault/meta/deuda/` |
| Quién está en qué | `vault/meta/trabajo/` |
| Qué pide el contrato, función por función | `vault/contrato/inventario-funcional.md` |
| Las maquetas | El lienzo "Lavandi" en claude.ai; [[encargo-revision-ux-y-maquetas-modulo-a]] lo explica |
| Qué significa C, DD, BR, F-A09… | [[glosario]] |
| El mapa de todo | [[indice]] |
