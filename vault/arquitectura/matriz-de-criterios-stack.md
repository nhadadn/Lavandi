---
estado: vigente
grado: mixto
fuente: prompt-maestro-01-fundacion-v4.md:277-294 (C); restricciones-no-negociables.md (B); acta C-13 (C); pesos fijados en acta-2026-11-12-traspaso-del-proyecto.md C-142 (C)
fecha: 2026-11-12
dueño: Arthur Naranjo
---
# Matriz de criterios del stack — para que Nadir ajuste los pesos

> **Pesos fijados el 2026-11-12** por Arthur Naranjo, que lleva la Fase 6 desde el traspaso (C-132). Eligió la
> propuesta del 2026-09-25 (C-142): **familiaridad 30, velocidad 25, proveedor único y ruta de salida 15, tiempo real
> 10, costo 10, multi-plaza 10**. Está al final de esta nota.
>
> - La familiaridad se califica ahora con el perfil de Arthur (C-135): Laravel, web con Vite y PWA, y Android nativo
>   en Java con Gradle; sin experiencia en iPhone. Deja de calificarse con el de Nadir (C-89).
> - DD-040 se cerró por C-140: panel web, más una vista de administración en la app.
> - P-17, si falta un criterio como "gente en México que lo pueda mantener", no se planteó. Si se agrega, se reabre
>   esta nota.
>
> Siguiente paso: puntuar candidatos (paso 6) y escribir los ADR-0002 a 0014.

**Esta nota se escribe antes de mirar un solo candidato.** Es el orden que manda el método, y no es
ceremonia: fijar los pesos después de ver las opciones es elegir primero y justificar al rato.

> *"Stack abierto: construye la matriz desde cero. **Primero los criterios y sus pesos, después los
> candidatos** — nunca al revés."* — `prompt-maestro-01-fundacion-v4.md:278-279`

`estado: borrador` hasta que Nadir ajuste los pesos y responda DD-038. **Pasó a `vigente` el 2026-11-12: ver el recuadro de arriba.**

## Primero: lo que no se pondera, se cumple o se descarta

Cuatro restricciones son **eliminatorias**. Un candidato que falle una queda fuera aunque gane en
todo lo demás. Ponderarlas sería permitir que una buena puntuación compre el incumplimiento de una
obligación grado A.

| Puerta | Cita | Por qué es eliminatoria |
|---|---|---|
| **Una sola base de código** para iPhone y Android, con comportamiento idéntico | RN-01, RN-02 (p. 16, A.3) | El contrato lo compromete de forma expresa |
| **Mapas de Google en Android y de Apple en iPhone** | RN-29 (p. 19, A.3) | Descarta cualquier solución con un único SDK de mapa. Obliga a abstracción por plataforma |
| **Conekta** como pasarela, con tarjeta y OXXO Pay en producción | RN-28 (p. 16 y 19) | El contrato la nombra. Si no hay forma de integrarla, el candidato no sirve |
| **Costo dentro del rango del ANEXO B.4** | RN-22 (p. 29 y 33) | De 1,200 a 4,100 MXN al mes en total. Un stack que no quepa contradice el contrato |

RN-29 es la puerta menos evidente y la que más recorta el campo: obliga a que el framework móvil
permita montar un mapa nativo distinto por plataforma, no uno solo para las dos.

## Después: los criterios que sí se ponderan

Los siete del método. Los pesos son **propuesta de Claude**; los ajusta Nadir.

| # | Criterio | Peso propuesto | Por qué ese peso |
|---|---|---|---|
| 1 | **Familiaridad real del equipo** | **25** | El método la llama *"decisiva, y se dice en voz alta, no se esconde"*. Con un equipo de una persona más Claude, una tecnología desconocida se paga en semanas, y solo hay dieciséis |
| 2 | **Velocidad de entrega en 16 semanas** para el tamaño real del equipo | **20** | La fecha es contractual y el alcance es grande: cuatro módulos, landing, panel e indicadores |
| 3 | **Riesgo de proveedor único y ruta de salida** | **15** | Cláusula IX: todo se cede al cliente al pago íntegro. Un stack que amarre a un proveedor sin salida compromete algo que ya está prometido |
| 4 | **Tiempo real suficiente para los 60 s, sin sobreingeniería** | **15** | RN-05 da sesenta segundos, que es mucho. El riesgo aquí no es quedarse corto: es construir de más |
| 5 | **Costo de operación con volumen inicial bajo** | **15** | La puerta ya exige caber en el rango. Este peso premia al que sobra margen: el volumen del primer mes será bajo y el arranque es en frío (R-005) |
| 6 | **Multi-plaza en el modelo de datos desde el día uno** | **10** | O-26 es obligación, pero casi cualquier stack serio lo permite. Discrimina poco entre candidatos razonables |

Suma: **100**.

## Por qué estos pesos y no otros

Tres decisiones que conviene discutir antes de aceptarlas:

**Familiaridad por encima de todo lo demás.** Es el peso más alto y probablemente el más polémico.
El motivo es el calendario: dieciséis semanas no dan para aprender un stack y entregar cuatro
módulos. La alternativa —elegir lo técnicamente superior y aprenderlo sobre la marcha— es la forma
más común de llegar a la semana 10 con la mitad hecha. Si Nadir considera que su curva de
aprendizaje es más rápida de lo que supone este peso, **el peso debe bajar**, y para eso hace falta
DD-038.

**Costo con peso medio, no alto.** Porque ya hay una puerta eliminatoria que obliga a caber en el
rango del contrato. Pesarlo alto además sería castigar dos veces lo mismo.

**Multi-plaza con el peso más bajo, pese a ser obligación grado A.** Un peso alto no premia cumplir:
premia *diferenciarse*. Casi cualquier base de datos relacional soporta el modelo de
`modelo-multi-plaza.md` sin esfuerzo, así que el criterio no separa candidatos. La obligación se
cumple por diseño, no por elección de stack.

## Las doce decisiones que se cerrarán con ADR

Enumeradas por el método (`:288-291`). Ninguna existe hasta que tenga su ADR.

| ADR | Decisión | Qué la condiciona |
|---|---|---|
| `ADR-0002` | Framework móvil | Puertas RN-01 y RN-29. Absorbe las versiones mínimas de iOS y Android (BR-42) |
| `ADR-0003` | Backend y lenguaje | Criterios 1, 2 y 4 |
| `ADR-0004` | Base de datos | `modelo-multi-plaza.md`; puerta RN-22 |
| `ADR-0005` | Autenticación | RN-08, RN-17, RN-24. Verificar la regla 4.8 de la App Store (BR-14) |
| `ADR-0006` | Almacenamiento de imágenes | BR-29, BR-09; puerta RN-22 |
| `ADR-0007` | Mecanismo de tiempo real | RN-05. **Sin sobreingeniería** |
| `ADR-0008` | Notificaciones push | RN-30, RN-06. Los cinco momentos esperan DD-011 |
| `ADR-0009` | Mapas | `presupuesto-de-mapas.md`; puerta RN-29 |
| `ADR-0010` | Pasarela de pago | Puerta RN-28. Verificar vigencia de OXXO Pay antes del ADR |
| `ADR-0011` | Hosting de la landing | RN-25, A.4.2 con calificación mínima de 70 en PageSpeed móvil. **Desde el 2026-09-29 incluye evaluar una plantilla comprada (C-95)**. Condiciones: licencia que permita entregar el sitio a EL CLIENTE (Cláusula IX, BR-08), misma tecnología que el resto, las 12 secciones del A.2, PageSpeed con la medición cargada y edición de testimonios y prensa (BR-11). Detalle en `meta/actas/acta-2026-09-29-sesion-tecnica-y-plantilla-landing.md` |
| `ADR-0012` | Entornos de desarrollo y prueba | BR-06, BR-08, BR-38 |
| `ADR-0013` | Adaptador de tokens de la Fase 4 | `tokens.json`; ver `design-system.md` |
| `ADR-0014` | Correo automático | F-T13: confirmación del pedido y recuperación de contraseña (B.5). **Agregado el 2026-09-25**: la lista del método no lo trae y el inventario funcional lo exige |

## Lo que bloquea arrancar

> **Actualización del 2026-09-25.** DD-038 quedó respondida (C-89 a C-91) y DD-040 sigue abierta. Lo
> que falta para arrancar son **los pesos, que ajusta Nadir**. Ver la sección "Lo que el análisis
> funcional cambia", al final. Lo de abajo se conserva como estaba el 2026-09-11.

**DD-038 — la familiaridad real del equipo.** Es el criterio de mayor peso y hoy no tiene dato. Sin
él, puntuar candidatos sería inventar el 25 % de la matriz, que es exactamente lo que la regla 3 de
evidencia prohíbe.

Concretamente hace falta saber, sin adornos: con qué lenguajes y frameworks ha entregado Nadir
software que haya llegado a producción, y con cuáles solo ha experimentado. El método pide que esto
se diga en voz alta y no se esconda, porque un peso de 25 puntos apoyado en una impresión optimista
mueve la decisión entera.

**DD-040 — dónde vive el Módulo D.** Si administración es una aplicación web aparte, aparece un
candidato más que evaluar y `ADR-0011` deja de ser solo de la landing.

## Verificaciones grado E pendientes antes de puntuar

> **Hechas el 2026-09-28**, salvo la 5, que es de candidatos y espera a los pesos: ver
> `verificaciones-fase-6.md`. Cambian tres cosas: OXXO Pay ya no sería de Conekta (R-028, DD-056),
> la tienda de Apple exige una segunda forma de entrar, y una cuenta personal de Google Play necesita
> prueba cerrada antes de publicar (R-029).

Ninguna sube a grado C sin que Nadir la confirme en acta.

1. Vigencia de **OXXO Pay** en Conekta — lo exige la nota de RN-28 antes de `ADR-0010`.
2. Regla **4.8 de la App Store** sobre Sign in with Apple — `ADR-0005` (BR-14).
3. **Cuota de versiones** de iOS y Android en México — `ADR-0002` (BR-42).
4. **Precios de Google Maps Platform** tras el retiro del crédito gratuito — `ADR-0009`.
5. Precios de servidor, base de datos y almacenamiento — para acreditar la puerta RN-22.
6. **Tarjetas guardadas** en Conekta, sin que el número toque la plataforma — `ADR-0010` (BR-50, DD-055).
7. **Reglas de las tiendas** que el contrato no nombra: eliminar la cuenta desde la app y los textos de
   permiso de ubicación, cámara y avisos — `ADR-0002` y `ADR-0005` (inventario funcional, sección 4).

## Lo que el análisis funcional cambia (2026-09-25)

`contrato/inventario-funcional.md` desarmó el contrato en **76 funciones** y DD-038 quedó respondida
(C-89 a C-91). Esta nota sigue en `borrador`: **los pesos los ajusta Nadir**, y el orden sigue siendo
pesos, después candidatos, después ADRs.

**Lo que ya no es impresión:**

1. **El terreno.** Nadir tiene experiencia en web y servidor, ninguna en apps móviles (C-89). Hay 7
   capacidades propiamente móviles:
   - mapa nativo por plataforma;
   - ubicación con la app abierta;
   - recibir avisos;
   - cámara;
   - capturar la tarjeta en el teléfono;
   - iniciar sesión nativo;
   - publicar en las tiendas.

   Tocan 14 de las 76 funciones, pero **7 de las 15 grandes**. La mitad del trabajo pesado está en
   terreno nuevo.
2. **El tamaño.** Con la banda media del supuesto S-006, el alcance pide unas **390 horas** de Nadir. La
   capacidad declarada da **entre 135 y 270** en nueve semanas de desarrollo. **No está listo** en la
   banda media.
3. **El tiempo real es modesto.** Lo piden cuatro pantallas y el panel de indicadores, y el límite es de
   60 segundos. Con la app cerrada lo cubre el aviso.
4. **La forma del stack depende de una sola decisión abierta: DD-040.** Si la administración es web, sus
   7 funciones y los 11 indicadores caen en terreno conocido.

**Propuesta de Claude para los pesos**, a la luz de lo anterior. **Decide Nadir.**

| Criterio | Peso del 2026-09-11 | Propuesta del 2026-09-25 | Por qué se mueve |
|---|---|---|---|
| Familiaridad real del equipo | 25 | **30** | La experiencia ya es un dato (C-89), y el tamaño no cabe en la banda media: lo desconocido se paga en horas que no hay |
| Velocidad de entrega en 16 semanas | 20 | **25** | Misma razón. Premia al candidato que acorta las 15 funciones grandes, sobre todo las 7 móviles |
| Riesgo de proveedor único y ruta de salida | 15 | 15 | Sin cambio: la Cláusula IX cede todo al cliente al pago íntegro |
| Tiempo real para 60 s, sin sobreingeniería | 15 | **10** | El inventario muestra que es un requisito modesto y acotado |
| Costo de operación con volumen bajo | 15 | **10** | La puerta RN-22 ya obliga a caber en el rango; el margen extra discrimina poco |
| Multi-plaza desde el día uno | 10 | 10 | Sin cambio |
| **Suma** | **100** | **100** | |
