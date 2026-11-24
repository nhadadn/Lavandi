## Context

- El A.5 pide 11 métricas, cada una con su visualización, dentro de la administración
  (`NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5`). El panel es web, con Filament (ADR-0003; DD-040 cerrada por C-140).
- El glosario del contrato ya define tres de ellas: usuarios activos, retención y monto promedio por pedido
  (`NSV-2026-001-v8.pdf:46:ANEXO D, Sección D.5`). El inventario y BR-33 decían que "usuario activo" no estaba
  definido; lo que sigue abierto es solo qué cuenta como usar la aplicación.
- Casi todos los indicadores leen datos que crean otros cambios: pedidos y estados, pagos, direcciones con colonia,
  zonas, calificaciones y entregas. Ingresos y comisiones salen del mismo cálculo de `administracion`.

## Goals / Non-Goals

**Goals:**

- Los 11 indicadores con la visualización que nombra el A.5, calculados con datos de la base (A43-7).
- Una consulta por indicador, que reutiliza `exportacion` para que el archivo y el panel nunca difieran.
- Lo que depende de un valor del cliente, como dato de configuración: los cortes del semáforo y el intervalo de
  refresco.

**Non-Goals:**

- Indicadores fuera del A.5: costo de adquisición, valor del cliente, tasa de conversión
  (`NSV-2026-001-v8.pdf:25:ANEXO A, Sección A.8`).
- Filtros por plaza.
- Indicadores en la app.
- La descarga, que vive en `exportacion`.

## Decisions

No hay decisión técnica nueva. Se aplican los ADR vigentes:

- **Widgets:** barras, dona y línea con las gráficas que trae Filament; el indicador destacado con su widget de
  estadísticas; las tablas comparativas como tablas de Filament (ADR-0003).
- **Mapa de calor:** Google Maps JavaScript con puntos o zonas agregados en el servidor, sin la capa de calor del
  proveedor (ADR-0009). Las zonas y el punto de cada pedido se cruzan con PostGIS (ADR-0004).
- **Colonia:** sale del campo de la dirección; no hay geocodificación inversa (ADR-0009, `presupuesto-de-mapas.md`,
  decisión 4).
- **Horas:** cada fecha se presenta en la zona horaria de la plaza del pedido (`modelo-multi-plaza.md`).

**Lecturas del contrato que hizo el especificador.** Arthur las confirma al aprobar:

1. **Pedido** es todo pedido que llegó a Confirmado (`NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3`, Módulo A #12).
   Cuenta en los totales, en los estados y en la retención, aunque después se cancele. Con C-188, un pedido llega a
   Confirmado solo cuando la pasarela confirma su pago (`pedidos.confirmado_en`); el que nunca se paga no cuenta.
2. **Usuarios activos** son personas distintas con cuenta de usuario. Lavanderías y repartidores se miden aparte, en
   la categoría Socios. Va en DD-111.
3. **Retención:** de los usuarios de un mes de registro que hicieron su primer pedido, qué porcentaje volvió a pedir,
   hasta hoy. Es una cifra por mes, no una matriz de meses.
4. **"Con tendencia":** el monto promedio contra el del periodo anterior de la misma duración.
5. **"Tabla comparativa":** cada periodo junto al anterior; en los repartidores, uno al lado del otro.

## Tablas y migración

Una sola migración, hacia adelante (ADR-0016, regla 9), en la tarea 0.1. Pasa la prueba de esquema de
`modelo-de-datos` (modelo-E01 a modelo-E17). No lleva el trigger `impedir_cambio()`, que es solo de `pedidos`,
`pedido_eventos`, `pagos` y `reembolsos`.

**Crea `actividad_usuarios`** (E06 y E07):

- Columnas: `id_usuarios` y `fecha`.
- Llave primaria compuesta `(id_usuarios, fecha)`: una fila por persona y día. Como no es autoincremental, no lleva
  `id_actividad_usuarios`.
- Llave foránea `id_usuarios` → `usuarios.id_usuarios`, con `ON DELETE RESTRICT`; su índice es el de la llave primaria,
  que empieza con ella (modelo-E10).
- Sin `id_plazas`, por diseño: está en la lista de tablas sin plaza de `servidor/config/modelo.php`.
- Sin `created_at` ni `updated_at`, como propuesta: no tiene llave propia (ver Open Questions, 9).
- Qué la escribe, y por eso qué cuenta como "usar la aplicación", es DD-111 (E07). Mientras, el test de E06 siembra
  sus filas.
- Su modelo de Eloquent sigue lo que decida `registro-e-inicio-de-sesion` para `rol_usuario`, la primera tabla con llave
  compuesta: Eloquent espera una llave de una sola columna.

**Los indicadores no tienen tablas propias** (`modelo-de-datos.md`). Se calculan en vivo de tablas que crean otros
cambios, sin escribirlas; si una consulta resulta lenta, se agrega una vista materializada, que no cambia ninguna tabla
(DD-116).

| Tabla | Para qué | La crea |
|---|---|---|
| `pedidos` y `estados_pedido` | Pedidos totales, por estado, retención y monto promedio (E01 a E05, E08 a E10, E21); la fecha del pedido es `confirmado_en` | `resumen-y-confirmacion` |
| `pedido_eventos` | El paso a Entregado: completados y entregas por repartidor (E04 y E15) | `resumen-y-confirmacion` (base) y `estados-del-pedido` |
| `pedido_direcciones` | La colonia y la `ubicacion` de la dirección del pedido, para el mapa de calor (E19 y E20) | `resumen-y-confirmacion` |
| `zonas` y `plazas` | Los polígonos del mapa por zona (E20) y la `zona_horaria` de cada plaza (E03) | `plazas-y-zonas` |
| `pagos` | Los ingresos brutos, con el mismo cálculo de `administracion` (E16) | `pago` |
| `calificaciones` | El ranking: las filas con `id_lavanderias` (E12 y E13) | `perfil-de-lavanderia` (base) y `historial-y-calificacion` |
| `lavanderias` | Activas frente a registradas (E11) y el nombre en el ranking | `perfil-de-lavanderia` y `alta-de-lavanderias` |
| `repartidores` | Las entregas por repartidor (E15) | `repartidor` |
| `usuarios` y `rol_usuario` | Las personas con cuenta de usuario (E06) y su mes de registro, de `usuarios.created_at` (E08) | `registro-e-inicio-de-sesion` |
| `comisiones` | Las comisiones por socio (E18, bloqueado) | `administracion` |

## Risks / Trade-offs

- [Las cohortes y el mapa de calor son las consultas más pesadas] → con el volumen de arranque se calculan en vivo.
  Si el panel tarda, se cachean o se precalculan, con ADR (BR-33).
- [8 de los 23 escenarios esperan una respuesta] → el panel puede salir con los demás, pero A44-1 pide los 11
  indicadores: no se acredita hasta que todos tengan su escenario en verde.
- [A43-7 pide datos reales] → hace falta una lavandería real (DD-021). La lavandería propia del cliente podría
  servir (DD-066, sin confirmar).
- [La colonia en texto libre] → la misma colonia escrita de dos formas se cuenta como dos. Depende de cómo la
  capture `direcciones`.
- [El plugin de mapas de Filament está en beta] → componente propio (ADR-0009).

## Implementación en el servidor

Lo que decidió el implementador dentro de esta spec y de los ADR, sin decisión técnica nueva:

- **La página** es un segundo tablero de Filament, `App\Filament\Pages\Indicadores`, en `/admin/indicadores`; el
  tablero de inicio no cambia. Sus widgets viven en `app/Filament/Widgets/Indicadores/`: Filament los registra como
  componentes de Livewire, pero no los pone en el tablero de inicio. Se dibujan al abrir la página, sin carga diferida.
- **La autorización** es `Autorizacion::puedeVerLosIndicadores()`, en el componente único (ADR-0019): la página y cada
  widget le preguntan, y un widget montado por otra cuenta responde 403.
- **Una consulta por indicador** en `app/Indicadores/`, que la gráfica, la tabla y después `exportacion` leen.
- **El rango se ajusta a periodos completos:** por semana, del lunes de la semana del primer día al domingo de la del
  último; por mes, del día 1 al último día. Así cada periodo se compara entero con el anterior. Sin filtros, es el mes
  en curso hasta hoy, por día.
- **"Hoy"** (el rango sin filtros) se lee en la zona horaria de la aplicación, porque la cuenta no tiene plaza; es la
  misma pregunta 8.
- **La dona** cuenta los pedidos confirmados en el rango por su estado actual: activos son `confirmado`, `aceptado`,
  `recolectado`, `en_proceso`, `listo` y `en_entrega` (ADR-0021 y su adenda); completados, `entregado`. Un pedido
  `rechazado` no es activo ni completado, y la dona no lo muestra mientras E05 espera DD-016.
- **Los usuarios activos** cuentan todas las filas de `actividad_usuarios`, sin filtrar por rol: quien escriba la tabla
  (E07, DD-111) decide quién cuenta, como dice "Tablas y migración". Hasta entonces nada la escribe, y en producción la
  línea da 0.
- **La retención** pone una fila por cada mes del rango, aunque ese mes nadie se haya registrado. El mes de registro se
  lee en la zona horaria de la aplicación (pregunta 8). Cuentan también las cuentas borradas después, porque su
  registro y sus pedidos son historial. El porcentaje lleva un decimal.
- **El ranking** promedia todas las calificaciones de cada lavandería, sin el rango de la página, con un decimal, como
  su perfil. Entran todas las lavanderías no borradas, de todas las plazas; con el mismo promedio, van por nombre.
- **El semáforo** lee sus dos cortes de `config/indicadores.php` (`INDICADORES_SEMAFORO_CORTE_ALTO` y `_BAJO`): verde
  desde el corte alto, amarillo desde el bajo y por debajo del alto, rojo por debajo del bajo. Un promedio igual a un
  corte alcanza el color de ese corte, y el color se toma del promedio con un decimal, el que se ve. Cada color va en
  una insignia con su texto (Verde, Amarillo, Rojo). Mientras DD-114 no dé los valores, los cortes van vacíos y la
  columna dice "Sin cortes configurados"; unos cortes fuera de 1 a 5, o con el bajo arriba del alto, cuentan como
  vacíos.
- **El monto promedio** (tarea 2.4, E09) es un indicador destacado, `MontoPromedioIndicador`, que lee
  `App\Indicadores\MontoPromedio`:
  - **El periodo** es el rango de la página, ya ajustado a periodos completos; **el anterior** son los mismos días justo
    antes (`Rango::anteriorDeLaMismaDuracion()`, lectura 4). Pedido del periodo es el confirmado en él, en el día de su
    plaza, como en los pedidos totales.
  - **Qué monto cuenta** espera DD-012 (E10, bloqueado). Mientras, la consulta usa la misma lectura que la ficha del
    usuario en el panel (`administracion`): el final si ya se pesó y, si no, el estimado
    (`coalesce(monto_final, monto_estimado)`, `servidor/app/Indicadores/MontoPromedio.php:24`). Es una lectura
    provisional, no una respuesta: indicadores-E10 sigue `SIN RESPUESTA`, y la puede cambiar **DD-012**, que fija el
    modelo de cobro. Cuando responda, cambia esa sola línea de `MontoPromedio`; la prueba de E09 no la afirma (cada
    pedido de prueba lleva el mismo monto en los dos campos). Hoy nada escribe esos montos, así que en producción el
    indicador dice "Sin monto".
  - Un pedido sin ningún monto no entra al promedio. El promedio va con dos decimales, en pesos, como texto.
  - **La tendencia** va con su texto, su flecha y su color: "Subió $50.00 frente al periodo anterior ($200.00)", "Bajó
    …" o "Igual que en el periodo anterior (…)". Sin pedidos con monto en el periodo anterior, dice que no hay con qué
    comparar.
- **Las entregas por repartidor** (tarea 3.5, E15) son una tabla, `EntregasPorRepartidorTabla`, que lee
  `App\Indicadores\EntregasPorRepartidor`:
  - **Una entrega es del repartidor que marcó Entregado:** el evento de `pedido_eventos` con su cuenta, la regla de
    `repartidor` (su `design.md`, lectura 5). Cuenta cada pedido una vez, si el pedido llegó a Confirmado.
  - **Su día** es el del paso a Entregado en la hora de la plaza del pedido, como en las estadísticas del repartidor.
  - **"Lado a lado"** (lectura 5) es una fila por repartidor, de más a menos entregas y, con las mismas, por nombre, con
    su plaza.
  - **Quién sale:** los repartidores dados de alta que no se dieron de baja, también con 0; uno pendiente o de baja,
    solo si tiene entregas en el rango, porque sus entregas se conservan (repartidor-E07).
- **Los ingresos brutos** (tarea 4.1, E16) son una gráfica de barras, `IngresosBrutosGrafica`, después de los
  indicadores de socios y antes del mapa, el orden del A.5:
  - **La misma consulta del reporte financiero:** lee `App\Finanzas\Ingresos::porPeriodo()`, la que da la tabla de
    ingresos por periodo de `/admin/ingresos` (`administracion`, administracion-E17). Cada barra es, por construcción, la
    cifra de ese reporte para su periodo; la prueba lo compara mes por mes.
  - **Qué cuenta:** los pagos que la pasarela confirmó por su aviso (`pagos.estado = 'pagado'`), por el día de la
    confirmación en la hora de la plaza, sin descontar reembolsos ni comisiones (administracion-E18 y E19, bloqueados).
    No salen de los montos del pedido, así que no dependen de DD-012 (ver el `design.md` de `administracion`).
  - **Las barras:** una sola serie, una barra por periodo del rango, sin leyenda. El A.5 pide barras agrupadas; por qué
    se agrupan espera a EL CLIENTE (E17, BR-31, DD-115). Cuando lo diga, cada grupo es otra serie de la misma gráfica.
  - Los montos llegan a la gráfica como números solo para dibujarla; la cifra exacta, con dos decimales, va en el texto
    alterno de la gráfica, en el reporte y en la descarga.
  - Se refresca como los demás widgets de la página (E21 y E22).
- **El mapa de calor** (tareas 5.1 y 5.2, E19 y E20) es el widget `MapaDeCalor`, con su vista
  `resources/views/filament/widgets/indicadores/mapa-de-calor.blade.php`:
  - **Por colonia** (E19) lee `App\Indicadores\PedidosPorColonia`: la colonia de la **dirección de recolección** del
    pedido (pregunta 4, la recomendación), como se capturó en `pedido_direcciones`, sin espacios a los lados, en su
    municipio y su plaza. El punto de cada colonia es el centro de las recolecciones de sus pedidos (`ST_Centroid` de
    PostGIS); no hay geocodificación inversa ni ninguna petición a Google desde el servidor.
  - **Por zona de servicio** (E20) lee `App\Indicadores\PedidosPorZona`: un pedido cuenta en la zona de su plaza que
    cubre el punto de su recolección (`ST_Covers` de PostGIS, con el borde dentro, como la zona de servicio de las
    direcciones), no en la de su entrega. Salen las zonas activas, también con 0, y una inactiva solo si tuvo pedidos
    en el rango. Cada zona lleva su polígono simplificado (`INDICADORES_MAPA_SIMPLIFICACION`, 0.0005 grados, como la
    cobertura de la portada) para pintarla. La administración cambia entre colonia y zona con el selector "Ver por"
    del widget.
  - **La intensidad** es el número de pedidos frente al de la colonia (o zona) con más, de 0 a 1 con dos decimales.
    El color sale de una rampa secuencial de un solo tono, el azul de la paleta validada de las gráficas, de claro a
    oscuro (`App\Indicadores\Intensidad`); el punto crece con la intensidad. Cada figura dice su nombre y su número al
    elegirla, y una tabla debajo trae las mismas cifras: la intensidad nunca va solo con el color.
  - **El mapa** es Google Maps JavaScript (ADR-0009, decisión 3), con círculos y polígonos propios, sin la capa de
    calor del proveedor. Se monta **una vez** en un contenedor que Livewire no toca (`wire:ignore`); el refresco y los
    filtros solo cambian el atributo `data-mapa-datos`, y el script redibuja las figuras sobre el mismo mapa. Así una
    página abierta cuenta como una carga de mapa, aunque sondee (`presupuesto-de-mapas.md`).
  - **La llave** es de navegador y sale de la configuración: `GOOGLE_MAPS_PANEL_LLAVE`, en
    `services.google_maps_panel.llave`, de cada entorno (ADR-0012). Sin ella, el widget no monta el mapa ni carga el
    script, y dice "El mapa no está disponible porque falta la llave de Google Maps del panel", como la app sin la llave
    de Android; la tabla se ve igual. La llave de producción va a nombre de EL CLIENTE (RN-20) y espera DD-072.
  - El dibujo del mapa en el navegador no tiene prueba automática: las pruebas revisan los datos que le llegan, la
    llave y el aviso. Se revisa a mano con una llave de prueba.
- **El refresco** es el sondeo de Livewire de cada widget (`wire:poll`), la consulta periódica de ADR-0007: la gráfica
  llama a `updateChartData` y la tabla se vuelve a dibujar, sin recargar la página. El intervalo es
  `INDICADORES_REFRESCO` en `config/indicadores.php`, como "30s" o "1500ms". **Por omisión son 30 s** (tarea 6.2,
  E22), como fija la adenda del 2026-11-22 de ADR-0007, que cierra DD-116; un valor vacío en el entorno también toma
  los 30 s, y cada entorno lo cambia sin tocar el código. Con un valor que no sirve, ningún widget sondea (también se
  quitan los 5 s que trae la gráfica de Filament) y la página dice que las cifras se actualizan al abrir o recargar.
  Con intervalo, la página dice cada cuánto.

## Open Questions

1. **Cuánto es "en tiempo real" para los indicadores.** El ADR-0007 fija 30 s para los pedidos activos, no para los
   indicadores. Necesita ADR, o ampliar el ADR-0007. Recomendación de Claude: los mismos 30 s. DD-116. **Resuelta** por
   la adenda del 2026-11-22 de ADR-0007 (decisión 33, C-236): 30 s; cierra DD-116.
2. **Qué fecha cuenta para un pedido:** la de su confirmación, que es la que usan los escenarios, o la de su
   creación. Arthur la confirma al aprobar. Con C-188 la diferencia importa: el pedido se crea al aceptar el resumen y
   se confirma cuando se paga.
3. **La semana va de lunes a domingo** (ISO 8601). Arthur la confirma.
4. **Qué dirección del pedido cuenta en el mapa de calor:** la de recolección, que es la recomendación, o la de
   entrega.
5. **La colonia: texto libre normalizado o catálogo.** Se decide en `direcciones`. Si es catálogo, necesita ADR.
6. **Consultas en vivo o precalculadas** (BR-33). Necesita ADR solo si las consultas en vivo no alcanzan.
7. **El mes de registro (E08): resuelta.** Es `usuarios.created_at`: todas las tablas del negocio con llave propia
   llevan `created_at` y `updated_at` en `timestamptz`.
8. **El día de `actividad_usuarios`.** La persona no tiene plaza, así que su `fecha` no tiene zona horaria propia. Si
   las plazas del territorio inicial comparten zona horaria (`SIN VERIFICAR`; sale de las filas semilla de DD-093), hoy
   no cambia nada; con plazas en zonas distintas, hay que decidir cuál vale.
9. **`actividad_usuarios` y las fechas de creación: resuelta.** No las lleva: `modelo-de-datos.md` dice "No los llevan
   `rol_usuario` ni `actividad_usuarios`, que no tienen llave propia y no se editan" (`vault/arquitectura/
   modelo-de-datos.md:90`), y `config/modelo.php` la pone en la lista de tablas de enlace. La migración de la tarea 0.1
   lo sigue.
