---
estado: vigente
grado: B
fuente: sesión de Arthur del 2026-11-21 (C-231); comprobado en la computadora de Arthur el 2026-11-21 con la rama feat/h1-acceso
fecha: 2026-11-21
dueño: Arthur Naranjo
---
# Cómo correr la app en local

Lo que hay hoy es el hito H1 del [[plan-de-sprints]]:
- la bienvenida y sus tres informativas;
- el registro y el inicio de sesión con correo y contraseña;
- la sesión que se conserva y el cierre de sesión;
- la recuperación de la contraseña.

Google no se puede probar todavía: falta el cliente OAuth de prueba (DD-072).

Son tres piezas: **la base de datos** en Docker, **el servidor** (Laravel) y **la app** (Expo). Hoy la app se ve en
Chrome con tamaño de teléfono. En el teléfono todavía no: ver la sección 5.

## 1. Lo que hace falta en la computadora

Versiones con las que se comprobó el 2026-11-21:

| Herramienta | Versión |
|---|---|
| Docker Desktop | 29.3 |
| PHP | 8.4.27 (la de XAMPP sirve) |
| Composer | 2.11 |
| Node.js | 22.23, con npm 10.9 |
| Git | 2.53 |
| Google Chrome | cualquiera reciente |

**El código** está en la rama `feat/h1-acceso`. Mientras no se una a `main`, vive en la copia de trabajo
`C:\np\Lavandi\.claude\worktrees\integracion`. Los comandos de abajo se corren desde la carpeta donde esté esa rama.

## 2. La base de datos

PostgreSQL 17 con PostGIS corre en el contenedor `nsv-lavanderia-postgis` (imagen `postgis/postgis:17-3.5`, puerto
5432). La base de trabajo es `nsv_lavanderia`, y la de pruebas, `nsv_lavanderia_pruebas`.

```powershell
docker start nsv-lavanderia-postgis
docker ps                     # debe decir "Up"
```

## 3. El servidor

En una terminal:

```powershell
cd <carpeta de la rama>\servidor
composer install              # la primera vez, o si cambió composer.lock
php artisan migrate           # crea las tablas que falten; no borra datos
php artisan serve --port 8000
```

- **Para comprobarlo:** `http://localhost:8000/up` debe responder.
- **La primera vez:** `servidor\.env` sale de `servidor\.env.example`, con los datos de la base del contenedor. El
  `.env` nunca se sube a git.
- **El nombre del producto** que ven las pantallas y el correo sale de `APP_NAME` en `servidor\.env`. En la app sale
  de `EXPO_PUBLIC_NOMBRE_APP` en `movil\.env`. Son configuración, no código (`CLAUDE.md` §5).
- **Los tests:** `php artisan test`. El 2026-11-21 pasaban 157.

## 4. La app en Chrome

La app es para Android e iPhone ([[ADR-0002-framework-movil]]). Para verla en el navegador, un script arma una
**copia aparte**:
- agrega el soporte web de Expo;
- cambia el almacén cifrado por el del navegador y desactiva Google.

Nada de eso toca `movil/` de la rama ni se versiona. **Es una vista previa, no la app del teléfono.**

**Una vez**, desde la carpeta de la rama:

```powershell
powershell -ExecutionPolicy Bypass -File herramientas\vista-web\preparar.ps1
```

Crea `C:\np\Lavandi\.claude\worktrees\vista-web` y tarda unos minutos.
- **Otra rama:** `-Rama <nombre>`.
- **Para rehacerla** cuando la rama avance, borra la copia y vuelve a correr el script:

  ```powershell
  git worktree remove --force C:\np\Lavandi\.claude\worktrees\vista-web
  ```

**Cada vez**, en otra terminal, con el servidor corriendo:

```powershell
cd C:\np\Lavandi\.claude\worktrees\vista-web\movil
npx expo start --web --port 8081
```

Abre `http://localhost:8081` en Chrome:
1. **F12,** y el botón del teléfono (Ctrl + Shift + M) para verla con tamaño de teléfono.
2. **Si la primera carga sale en blanco,** recarga: la primera vez tarda en armar el paquete.
3. **Para empezar como recién instalada,** usa una ventana de incógnito, o borra el almacenamiento del sitio en F12 →
   Aplicación.

**La app busca el servidor** en `EXPO_PUBLIC_API_URL` de `movil\.env`, que en local es `http://127.0.0.1:8000/api`.

### Qué probar

| Paso | Qué debe pasar |
|---|---|
| Abrir por primera vez | Bienvenida, tres informativas y la entrada, con "Crear cuenta" e "Iniciar sesión" |
| Registrarse con datos malos | El error junto a cada campo, en español |
| Registrarse bien | Entra al inicio provisional |
| Recargar | Sigue dentro |
| Cerrar sesión | Vuelve a la entrada |
| Contraseña equivocada | "Las credenciales no son válidas." |
| Seis intentos en un minuto | El sexto dice que hay demasiados intentos |
| ¿Olvidaste tu contraseña? | "Revisa tu correo" |

**El enlace de recuperación.** En local el correo no se envía: queda escrito en
`servidor\storage\logs\laravel.log`. El enlace es la línea con `contrasena/restablecer`. Ábrelo en el navegador;
vence a los 60 minutos y sirve una vez.

**Cuentas de prueba:** usa correos `@ejemplo.com`. Quedan en `nsv_lavanderia`.

## 5. En un teléfono Android o en el emulador: el APK local

El APK se compila en la computadora, sin la nube, con `herramientas\apk-local\compilar.ps1` (C-236). Se instala a
mano; no va a ninguna tienda.

**Lo que ya quedó instalado en esta computadora:**
- **El SDK de Android de Lavandi:** `%LOCALAPPDATA%\lavandi-android\sdk`, aparte del de ittob, con:
  - la API 37.0, las build-tools 37 y el NDK 27.1;
  - CMake 3.22;
  - el emulador con la imagen de Android 15.
- **El JDK 17:** se usa el de `%LOCALAPPDATA%\ittob-android\jdk17`.
- **El emulador:** un dispositivo virtual llamado `lavandi`, un Pixel 7.

**Compilar** (desde la raíz del repositorio; la primera vez tarda, después unos minutos):

```powershell
powershell -ExecutionPolicy Bypass -File herramientas\apk-local\compilar.ps1
```

Deja el APK en `notas-locales\lavandi-usb.apk`. Con `-Modo wifi -Ip <la IP de la computadora>` sale
`lavandi-wifi.apk`.

**Por USB, sin tocar el firewall** (el modo de siempre):
1. El servidor, como en la sección 3: `php artisan serve` en `127.0.0.1:8000`.
2. En el teléfono: Ajustes › Acerca del teléfono, siete toques en "Número de compilación"; luego, en Opciones de
   desarrollador, activa la depuración por USB. Conéctalo y acepta el aviso de la computadora.
3. Instala el APK y conecta el puerto. `adb` está en `%LOCALAPPDATA%\lavandi-android\sdk\platform-tools`:

   ```powershell
   $adb = "$env:LOCALAPPDATA\lavandi-android\sdk\platform-tools\adb.exe"
   & $adb install -r notas-locales\lavandi-usb.apk
   & $adb reverse tcp:8000 tcp:8000
   ```

   `adb reverse` se repite cada vez que desconectas el teléfono.

**Por Wi-Fi:** el servidor tiene que escuchar en la red (`php artisan serve --host 0.0.0.0 --port 8000`). Windows
tiene que dejar pasar el puerto 8000; con la red marcada como pública, eso pide permiso de administrador. El teléfono va
en la misma red y el APK es el de `-Modo wifi`.

**En el emulador:**

```powershell
& "$env:LOCALAPPDATA\lavandi-android\sdk\emulator\emulator.exe" -avd lavandi
```

Cuando arranque, instala el APK y conecta el puerto con los dos comandos de `adb` de arriba.

Para ver las lavanderías de prueba de Durango, pon la ubicación del emulador en el centro de la ciudad (longitud
primero):

```powershell
& $adb emu geo fix -104.6532 24.0277
```

Las lavanderías de prueba salen del sembrado: `php artisan db:seed` en `servidor\`, y nunca en producción.

**Los avisos al teléfono (Firebase)** solo entran al APK si el entorno trae el archivo de Firebase de Android:
- **La variable:** `GOOGLE_SERVICES_JSON` en `movil\.env`, con la ruta absoluta al `google-services.json`.
- **El paquete:** el del archivo tiene que ser el mismo `APP_IDENTIFIER` de `movil\.env`.
- **Sin la variable:** el APK sale como siempre, sin avisos, y el perfil no muestra la sección de notificaciones.
- **Al ponerla o quitarla:** hay que compilar completo con `compilar.ps1`, porque cambia el proyecto nativo.

El archivo nunca se sube a git (ADR-0012); lo da la cuenta de Firebase de prueba (DD-072).

**Lo que no funciona todavía en el APK:**
- Google, porque falta el cliente OAuth de prueba (DD-072): el botón no aparece.
- Los avisos al teléfono, mientras no exista la cuenta de Firebase de prueba (DD-072).
- Apple solo existirá en iPhone (DD-069).
- El correo de recuperación no sale: el enlace queda en `servidor\storage\logs\laravel.log`, como en la sección 4.

## 5-bis. En un iPhone, con Expo Go (C-240)

Mientras no exista la cuenta de Apple del cliente (DD-069) no hay compilación para iPhone. Lo que se puede hacer es
abrir la app en **Expo Go**, la app gratuita de Expo en la App Store. Expo Go trae casi todos los módulos que usa la
app; la entrada con Google no viene en él, así que ahí no aparece ese botón (base-E11).

**Desde otra red,** por ejemplo un amigo en su casa, el iPhone tiene que llegar a dos cosas de esta máquina:
- **Metro,** que sirve la app: con el túnel de Expo.
- **La API:** con un túnel HTTPS, porque iOS bloquea las direcciones http.

Publicar la API en internet, aunque sea con datos de prueba, lo aprueba Arthur antes.

Los pasos:
1. En el iPhone, instalar **Expo Go** desde la App Store (pide iOS 16.4 o posterior). La versión 58.0.5, del 22 de
   octubre, "adds support for SDK 58" (grado E, apps.apple.com/app/expo-go/id982107779, consultado el 2026-11-23).
2. Levantar la base y el servidor (sección 2) y publicar la API: `cloudflared tunnel --url http://127.0.0.1:8000`.
   Anotar la dirección `https://…trycloudflare.com` que muestra.
3. En `movil/.env`, poner `EXPO_PUBLIC_API_URL=https://<la dirección del túnel>/api`.
4. Entrar con una cuenta gratuita de Expo (no de Apple) en esta máquina, `npx expo login`, **y con la misma en Expo Go**
   del iPhone, desde el ícono de la esquina superior derecha. Desde el 2026-09-03, Expo Go en iOS solo abre un proyecto
   en desarrollo si las dos sesiones son de la misma cuenta: "you will need to be logged in to the same account on both
   the terminal and in Expo Go" (grado E, expo.dev/changelog/expo-go-57-login, consultado el 2026-11-23). Por eso,
   para que lo pruebe otra persona conviene una cuenta de Expo solo para pruebas, no la personal de nadie.
5. Desde `movil/`: `npx expo start --go --tunnel --clear`.
   - El `--go` hace que el QR abra Expo Go y no el development build.
   - El `--clear` vuelve a leer el `.env`.
6. En el iPhone, escanear el QR con la cámara, o mandarle la dirección `exp://` que muestra la terminal.

Al terminar se cierran Metro y los dos túneles, y `movil/.env` vuelve a su dirección local.

**Lo que no se prueba en Expo Go:**
- el texto del permiso de ubicación, porque muestra el de Expo Go;
- la entrada con Google;
- lo que depende de reinstalar la app (registro-E32).

## 5-ter. La prueba de los 60 segundos con Maestro (estados-E17)

El flujo `movil/.maestro/estados/cambio-en-60-segundos.yaml` cronometra, en el emulador o en un teléfono Android, cuánto
tarda el seguimiento del usuario en mostrar "Recolectado" desde que la lavandería lo marca. Falla si pasa de 60 s
(A.4.3). La evidencia del 2026-11-22 está en [[estados-e17-cambio-en-60-segundos-2026-11-22]].

**Lo que hace cada corrida:**
1. `preparar-pedido.ps1` crea un pedido por la API, como el usuario de prueba; lo paga con la pasarela simulada y la
   lavandería lo acepta. Queda en Confirmado.
2. El flujo borra los datos de la app, pasa la bienvenida e inicia sesión como el usuario de prueba. Concede la
   ubicación al abrir.
3. Abre el seguimiento con el enlace de la app, `<APP_IDENTIFIER>://pedido/seguimiento/<id_publico>`, y comprueba que
   dice Confirmado.
4. Marca Recolectado como la lavandería, por la API y con su token, y anota la hora.
5. Espera sin tocar nada, con un tope de 60 s, a que la pantalla diga Recolectado. Calcula el tiempo y termina en el
   inicio de la app.

**Lo que hace falta:**
- **El emulador o el teléfono** con el APK, el servidor y `adb reverse tcp:8000 tcp:8000`, como en la sección 5.
- **El JDK 17** en `JAVA_HOME`: el de `%LOCALAPPDATA%\ittob-android\jdk17`.
- **La CLI de Maestro 2.12.1**, en `%LOCALAPPDATA%\lavandi-android\maestro`, fuera del repositorio. Ya está instalada en
  esta computadora. En otra:
  1. baja `maestro.zip` y `checksums_sha256.txt` del release `cli-2.12.1` de `mobile-dev-inc/maestro` en GitHub;
  2. comprueba el SHA-256 con `Get-FileHash`;
  3. descomprime la carpeta `maestro` en `%LOCALAPPDATA%\lavandi-android\`.
- **`movil\.env`** con `APP_IDENTIFIER` y `EXPO_PUBLIC_API_URL`. Si la rama no lo tiene, pasa el de otra copia con
  `-EnvDeLaApp`, o los valores con `-AppId` y `-Api`.

**Para correrla** (desde la raíz del repositorio; tarda unos dos minutos por corrida):

```powershell
$env:JAVA_HOME = "$env:LOCALAPPDATA\ittob-android\jdk17"
powershell -ExecutionPolicy Bypass -File movil\.maestro\estados\correr-cambio-en-60-segundos.ps1 `
    -Servidor C:\np\Lavandi\.claude\worktrees\integracion\servidor `
    -Maestro "$env:LOCALAPPDATA\lavandi-android\maestro\bin\maestro.bat" `
    -Corridas 3 `
    -Salida vault\evidencias\estados\estados-e17-cambio-en-60-segundos-<fecha> `
    -RegistrosDelTelefono C:\np\Lavandi\notas-locales\evidencias\estados-e17-cambio-en-60-segundos-<fecha>
```

- **`-Servidor`** es la carpeta `servidor` cuyo `artisan` usa la misma base que la API: la de `php artisan serve`.
- **`-Salida`** recibe una carpeta por corrida y `resumen.csv` con los tiempos. Sin `-Salida`, va al directorio temporal.
- **`-RegistrosDelTelefono`** recibe el registro completo del teléfono (`device-logcat.txt`), que no se versiona.
- **Con más de un teléfono conectado,** se elige uno con `-Dispositivo emulator-5554`.

El script termina con error si una corrida falla o pasa de 60 s.

**Para correr solo el flujo** con un pedido que ya está en Confirmado y aceptado:

```powershell
powershell -ExecutionPolicy Bypass -File movil\.maestro\estados\preparar-pedido.ps1 -Servidor <carpeta servidor>
& "$env:LOCALAPPDATA\lavandi-android\maestro\bin\maestro.bat" test `
    -e APP_ID=<APP_IDENTIFIER> -e API_URL=http://127.0.0.1:8000/api -e PEDIDO=<id_publico> `
    -e USUARIO_CORREO=usuario.de.prueba@ejemplo.com -e USUARIO_CONTRASENA=contrasena-de-prueba `
    -e LAVANDERIA_CORREO=lavanderia.centro@ejemplo.com -e LAVANDERIA_CONTRASENA=lavanderia-de-prueba `
    movil\.maestro\estados\cambio-en-60-segundos.yaml
```

**Lo que cambia en el teléfono y en la base:**
- **La app:** el flujo borra sus datos, así que cierra la sesión que hubiera. Al terminar queda abierta en el inicio, con
  la sesión del usuario de prueba y la ubicación concedida.
- **La base:** cada corrida deja en `nsv_lavanderia` un pedido en Recolectado y una dirección del usuario de prueba, que
  no es favorita.
- **Las sesiones:** las de la API que abre la prueba se cierran al final. La de la app queda abierta.

## 6. Problemas comunes

| Síntoma | Causa y arreglo |
|---|---|
| `SQLSTATE[08006]` o "connection refused" | El contenedor no está corriendo: `docker start nsv-lavanderia-postgis` |
| "Address already in use" en el puerto 8000 | Ya hay un servidor ahí. Si es de Lavandi, úsalo; si no, `--port 8010` y cambia `EXPO_PUBLIC_API_URL` |
| "Faltan variables de entorno de la app" | Falta `movil\.env`: copia `movil\.env.example` y llénalo |
| La app no conecta con el servidor | Revisa `EXPO_PUBLIC_API_URL` y que `http://localhost:8000/up` responda |
| "running scripts is disabled" | Corre el script con `powershell -ExecutionPolicy Bypass -File …`, como arriba |
| Maestro: "Illegal char <:>" | El `name` de un flujo lleva dos puntos: Maestro lo usa como carpeta y Windows no los acepta |
| Maestro no encuentra el teléfono | `adb devices` debe listarlo; con más de uno, pasa `-Dispositivo` |
| `preparar-pedido.ps1`: "no ofrece ninguna ventana" | La lavandería de prueba no tiene ventanas en los días que deja agendar la plaza: revisa su horario, que siembra `LavanderiasDePruebaSeeder`, y la agenda de la plaza en `parametros_plaza` |

## 7. Para apagar

Ctrl + C en la terminal del servidor y en la de la app. El contenedor puede quedarse prendido, o
`docker stop nsv-lavanderia-postgis`.
