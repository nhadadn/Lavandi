---
estado: vigente
grado: B
fuente: tres corridas del flujo de Maestro movil/.maestro/estados/cambio-en-60-segundos.yaml el 2026-11-22, en el emulador lavandi (Android 15) contra el servidor local; su salida en evidencias/estados/estados-e17-cambio-en-60-segundos-2026-11-22/
fecha: 2026-11-22
dueño: Arthur Naranjo
---
# Evidencia: Recolectado se ve en 60 segundos o menos en Android (estados-E17)

Tres corridas del flujo de Maestro de la tarea 4.3 de `openspec/changes/estados-del-pedido/tasks.md`. Las tres pasan:
el seguimiento del usuario mostró "Recolectado" entre **26.8 y 27.5 segundos** después de que la lavandería lo marcó.
El contrato pide 60 segundos o menos (`NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3`).

**Lo que acredita y lo que no.** Acredita el escenario estados-E17: el cambio que hace la lavandería, en Android, con la
app abierta en el seguimiento. **No acredita todavía el criterio A43-3**:
- según [[trazabilidad-criterio-test]], A43-3 pide 10 cambios cronometrados en teléfonos reales, y esto es un emulador;
- falta el cambio que hace el repartidor (cambio `repartidor`);
- falta iPhone, estados-E18, que espera DD-069.

## Las corridas

Cada corrida prepara un pedido nuevo, en Confirmado y aceptado por la lavandería, y corre el flujo completo desde la app
sin datos. Las horas son las de la máquina, que es la de la plaza (CST, UTC−6).

| Corrida | Folio | La lavandería marcó Recolectado | El seguimiento lo mostró | Tardó | Resultado |
|---|---|---|---|---|---|
| 1 | 5VR3SCP7 | 16:14:15.272 | 16:14:42.775 | **27.5 s** | pasa |
| 2 | PD7A07N6 | 16:16:11.626 | 16:16:38.674 | **27.0 s** | pasa |
| 3 | CNWY03Y3 | 16:18:04.999 | 16:18:31.796 | **26.8 s** | pasa |

Fuente de la tabla: `vault/evidencias/estados/estados-e17-cambio-en-60-segundos-2026-11-22/resumen.csv:2-4`, que sale de
las líneas `CRONOMETRO` de cada `logs/maestro.log`.

**Contra el servidor.** La hora de Recolectado que guardó el servidor en `pedido_eventos`, y que entrega el seguimiento
del usuario, es 16:14:15, 16:16:11 y 16:18:05: el mismo segundo en que el flujo anotó la marca, redondeado.

## Qué mide el tiempo

- **Empieza** justo antes de que salga el botón de la lavandería, `POST /api/lavanderia/pedidos/{id_publico}/estado` con
  su token (`movil/.maestro/estados/pasos/marcar-recolectado.js`). Así el tiempo incluye lo que tarda el servidor en
  guardar el cambio: entre 0.41 y 0.52 s en estas corridas.
- **Termina** cuando Maestro lee en la pantalla que el paso Recolectado es el estado actual, con la etiqueta que lee el
  lector de pantalla, "Recolectado, estado actual, HH:MM" (`movil/.maestro/estados/pasos/cronometrar.js`). Incluye lo
  que tarda Maestro en leer la pantalla, así que puede pasarse un poco del tiempo real, nunca quedarse corto.
- **Falla** si pasa de 60 000 ms, y la espera tiene además un tope de 60 s.

**Por qué los tres dan unos 27 s.** El flujo marca Recolectado unos 3 s después de abrir el seguimiento, que consulta al
abrirse y luego cada 30 s ([[ADR-0007-tiempo-real]]). El cambio se ve en la consulta siguiente, unos 27 s después. Es
casi el peor caso del mecanismo: si la marca cayera justo después de una consulta, tardaría unos 30 s más la red.
Contra los 60 s del contrato queda un margen de unos 30 s.

## El entorno

| Pieza | Cuál |
|---|---|
| Teléfono | El emulador `lavandi`, un Pixel 7 con Android 15, de [[correr-la-app-en-local]], sección 5 |
| App | El APK local `notas-locales\lavandi-usb.apk` que ya estaba instalado, con el paquete `mx.ejemplo.desarrollo` de `movil/.env` |
| Servidor | `php artisan serve` en `127.0.0.1:8000`, con la base `nsv_lavanderia`, de la copia de trabajo `integracion`; el emulador llega por `adb reverse tcp:8000 tcp:8000` |
| Pago | La pasarela simulada: `php artisan pago:cobro-de-prueba` y `pago:simular-aviso … aprobado` |
| Cuentas | `usuario.de.prueba@ejemplo.com` y `lavanderia.centro@ejemplo.com`, de los sembradores de desarrollo; la lavandería es "Lavandería de prueba Durango Centro" |
| Maestro | CLI **2.12.1**, del release `cli-2.12.1` de `mobile-dev-inc/maestro` en GitHub, con SHA-256 del `maestro.zip` `54dbb0716425626604973cbb374c585ca30272375d9ce15177a1152765ddd9d7`, comprobado contra el `checksums_sha256.txt` del release. Vive en `%LOCALAPPDATA%\lavandi-android\maestro`, fuera del repositorio |
| Java | Temurin 17.0.20.1, de `%LOCALAPPDATA%\ittob-android\jdk17` |

## Los archivos

En `estados-e17-cambio-en-60-segundos-2026-11-22/`:

- `resumen.csv`: las tres corridas con su pedido, sus horas en UTC−6, lo que tardó la respuesta del botón y el tiempo
  medido.
- `corrida-N/salida.txt`: lo que imprimió Maestro.
- `corrida-N/maestro.log`: el registro de la CLI.
- `corrida-N/estados-E17-cambio-en-60-segundos/`, lo que escribió Maestro de la corrida:
  - `logs/maestro.log`, con las líneas `CRONOMETRO`;
  - `commands.json`, cada paso con su resultado;
  - `takeScreenshot/1-seguimiento-en-confirmado.png` y `2-seguimiento-en-recolectado.png`;
  - `startRecording/cambio-de-confirmado-a-recolectado.mp4`, la grabación de la pantalla desde que la lavandería marca
    hasta que se ve Recolectado. Pesa unos 140 KB.

Todo junto pesa 1.5 MB; ningún archivo pasa de 5 MB.

**El registro completo del teléfono no se versiona.** Es `device-logcat.txt`, de 1.1 MB por corrida. Trae el registro de
todo el sistema y no hace falta para leer el tiempo. Quedó fuera del repositorio, en
`C:\np\Lavandi\notas-locales\evidencias\estados-e17-cambio-en-60-segundos-2026-11-22\corrida-N\`. El `manifest.json` de
cada corrida lo sigue nombrando.

En `commands.json` y en `maestro.log` aparecen las contraseñas de las cuentas de prueba, que el flujo escribe al iniciar
sesión. Son las ficticias de los sembradores de desarrollo, ya escritas en el repositorio. Los tokens no aparecen, y el
flujo cierra al final la sesión de la lavandería.

## Cómo se repite

[[correr-la-app-en-local]], sección 5-ter. Un solo comando prepara un pedido por corrida, corre el flujo y deja esta
misma carpeta con su `resumen.csv`.

**Lo que dejó en la base local**, por corrida: un pedido en Recolectado de la lavandería de prueba Durango Centro y una
dirección del usuario de prueba, que no es favorita.
