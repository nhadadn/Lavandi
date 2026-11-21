---
estado: vigente
grado: mixto
fuente: NSV-2026-001-v8.pdf:7-8:Cláusula IV y :20-21:ANEXO A, Sección A.4 (A); supuestos.md S-006 (S); acta-2026-11-17-fase-7-formas-de-trabajo.md C-155 (C)
fecha: 2026-11-17
dueño: Arthur Naranjo
---
# Definition of Ready y Definition of Done

Propuesta de Claude, aceptada por Arthur con "adelante, termina la fase" (C-155).

## Definition of Ready: cuándo una tarea puede entrar al sprint

1. **Tiene spec** en `openspec/changes/<cambio>/`, validada con `openspec validate`, con requisitos citados (A o D)
   y escenarios con identificador ([[ciclo-openspec-tdd]]).
2. **No depende de una respuesta que no existe.** Si toca una fila `abierta` de la deuda de decisión, entra solo la
   parte que no depende de ella, y lo demás queda fuera o configurable. Ejemplo: el cobro no entra mientras
   DD-012 siga abierta.
3. **Tiene diseño.** Si la tarea tiene pantalla, su componente existe en el design system, o la tarea incluye
   crearlo.
4. **Tiene tamaño.** S, M o L con las bandas de S-006: S de 1 a 3 h, M de 3 a 8 h, L de 8 a 20 h. **Una L se parte
   antes de entrar:** no cabe en un sprint de una semana con unas 11 h comprometidas ([[sprint-y-sesion-diaria]]).
5. **Se sabe qué criterio del A.4 toca,** si toca alguno ([[trazabilidad-criterio-test]]).
6. **Los datos de prueba están definidos:** fábricas y seeders de lo que el test necesita.

## Definition of Done: cuándo una tarea está terminada

1. **Cada escenario tiene su test,** escrito antes que el código, y en verde.
2. **La integración continua está en verde** en el pull request ([[integracion-continua]]).
3. **Se revisó:** el agente revisor (Fase 8) no deja hallazgos abiertos y Arthur aprueba.
4. **Sin marca ni dominio escritos a mano, y sin secretos.** Lo comprueba la integración continua.
5. **La spec está al día:** tareas marcadas, cada una con su test, y la tabla de trazabilidad actualizada.
6. **Hay evidencia del criterio,** si la tarea acredita uno del A.4: captura o video en Android y en iPhone cuando
   aplique, guardados en `vault/evidencias/<capacidad>/`.
7. **Las horas están registradas** en [[registro-de-horas]].
8. **Está en `main`** del fork de Arthur.

## Terminado no es aceptado

La DoD es el primero de cuatro escalones. Para el contrato solo cuenta el último:

| Escalón | Qué significa | Quién lo da | Evidencia |
|---|---|---|---|
| **Terminado** | Cumple la DoD | Arthur | El pull request fusionado en su `main` |
| **Entregado a Nadir** | Entró al repositorio de Nadir con el PR del sprint | Nadir | El PR semanal aceptado ([[repositorios-ramas-y-revision]]) |
| **Acreditado** | El criterio del A.4 se cumple en el entorno de prueba o en producción | Arthur, con evidencia | La fila de [[trazabilidad-criterio-test]] en `acreditado` |
| **Aceptado** | EL CLIENTE lo aceptó por escrito, o pasaron 10 días hábiles desde la notificación de entrega sin observaciones escritas y fundadas (A, Cláusula IV) | EL CLIENTE, o el plazo | La notificación archivada ([[protocolos-con-el-cliente]]) |

**El pago 2 tiene un camino alterno** (A, Cláusula IV): si la publicación en las tiendas se atrasa por causas
ajenas a EL PRESTADOR, basta la app compilada, desplegada en el ambiente de prueba y validada conforme al A.4.3.
Por eso la columna "acreditado" de la tabla de trazabilidad distingue entre el entorno de prueba y producción.
