# Compila un APK de Android para probar la app en un teléfono o en el emulador, sin la nube (C-236).
#
# Arma una copia aparte del repositorio, genera el proyecto nativo con Expo (prebuild) y lo compila con Gradle en
# modo release, firmado con la llave de depuración: se instala a mano, no va a ninguna tienda. Lo que necesita la
# prueba local y no va en el producto vive solo en esa copia, nunca en movil/ de la rama de trabajo:
# - el tráfico HTTP sin cifrar, para hablar con el servidor local (en producción todo va por HTTPS, RN-07);
# - el logotipo del cliente en assets/marca/logotipo.png, que en el repositorio es el marcador neutro (CLAUDE.md §5);
# - el ícono de la app, con las capas de vault/marca/visual/icono-app/ en lugar de los marcadores de assets/.
#
# Dos formas de llegar al servidor local (php artisan serve):
# - usb  (por omisión): el teléfono, conectado por USB con la depuración activa, ve el servidor en 127.0.0.1 gracias
#        a "adb reverse tcp:8000 tcp:8000". No hay que abrir el firewall. Sirve igual para el emulador.
# - wifi: el teléfono llega por la IP de la máquina en la red local. El servidor tiene que escuchar en 0.0.0.0 y el
#        firewall de Windows dejar pasar el puerto 8000.
#
# Uso, desde la raíz del repositorio:
#   powershell -ExecutionPolicy Bypass -File herramientas\apk-local\compilar.ps1
#   powershell -ExecutionPolicy Bypass -File herramientas\apk-local\compilar.ps1 -Modo wifi -Ip 192.168.1.3
# Guía completa: vault/formas-de-trabajo/correr-la-app-en-local.md

param(
    [string]$Rama = 'feat/h2-lavanderias',
    # Una ruta corta a propósito: el C++ de react-native-worklets arma rutas de más de 260 caracteres, el límite de
    # Windows sin la opción de rutas largas, y la compilación falla. subst no basta, porque Gradle resuelve la ruta real.
    [string]$Destino = 'C:\lv',
    [string]$EnvDeLaApp = 'movil\.env',
    [ValidateSet('usb', 'wifi')][string]$Modo = 'usb',
    [string]$Ip = '',
    [string]$Logotipo = 'vault\marca\visual\logotipo\lavandi-logotipo-color.png',
    [string]$IconoApp = 'vault\marca\visual\icono-app',
    [string]$Jdk = "$env:LOCALAPPDATA\ittob-android\jdk17",
    [string]$Sdk = "$env:LOCALAPPDATA\lavandi-android\sdk",
    # El CMake con ninja 1.12 o más nuevo: el 3.22 que pide React Native trae un ninja que no sabe de rutas largas y
    # falla aunque Windows las tenga habilitadas (LongPathsEnabled).
    [string]$Cmake = "$env:LOCALAPPDATA\lavandi-android\sdk\cmake\3.31.6",
    [string]$Arquitecturas = 'arm64-v8a,x86_64'
)

$ErrorActionPreference = 'Stop'
$aqui = (git rev-parse --show-toplevel).Trim()
$principal = Split-Path -Parent ((git rev-parse --path-format=absolute --git-common-dir).Trim())
$envOrigen = if ([System.IO.Path]::IsPathRooted($EnvDeLaApp)) { $EnvDeLaApp } else { Join-Path (Get-Location) $EnvDeLaApp }
$logoOrigen = if ([System.IO.Path]::IsPathRooted($Logotipo)) { $Logotipo } else { Join-Path $aqui $Logotipo }
$iconoOrigen = if ([System.IO.Path]::IsPathRooted($IconoApp)) { $IconoApp } else { Join-Path $aqui $IconoApp }
$destinoCompleto = if ([System.IO.Path]::IsPathRooted($Destino)) { $Destino } else { Join-Path $principal $Destino }

foreach ($necesario in @($envOrigen, (Join-Path $Jdk 'bin\java.exe'), (Join-Path $Sdk 'platforms'))) {
    if (-not (Test-Path $necesario)) { throw "Falta $necesario. Revisa los parámetros -EnvDeLaApp, -Jdk y -Sdk." }
}
if ($Modo -eq 'wifi' -and $Ip -eq '') { throw 'Con -Modo wifi hay que pasar la IP de la máquina con -Ip.' }
$api = if ($Modo -eq 'usb') { 'http://127.0.0.1:8000/api' } else { "http://${Ip}:8000/api" }

if (Test-Path $destinoCompleto) {
    Write-Host "1/6 La copia aparte ya existe: se lleva a $Rama"
    # El daemon de Gradle de la compilación anterior retiene archivos de android\, que el prebuild limpio borra.
    $gradlewViejo = Join-Path $destinoCompleto 'movil\android\gradlew.bat'
    if (Test-Path $gradlewViejo) {
        $env:JAVA_HOME = $Jdk
        & $gradlewViejo -p (Split-Path -Parent $gradlewViejo) --stop | Out-Null
    }
    # La copia es desechable: lo que el prebuild y el logotipo cambiaron en ella se descarta antes de moverla.
    git -C $destinoCompleto reset --hard --quiet
    git -C $destinoCompleto checkout --detach $Rama
} else {
    Write-Host "1/6 Copia aparte de $Rama en $destinoCompleto"
    git worktree add --detach $destinoCompleto $Rama
}
if ($LASTEXITCODE -ne 0) { throw 'No se pudo preparar la copia aparte.' }

$movil = Join-Path $destinoCompleto 'movil'
# El .env de la copia: el de la máquina, con la dirección del servidor según el modo. El aviso de privacidad y los
# términos los sirve el mismo servidor local (textos-legales-E05): sin ellos, la app no muestra la sección "Legal".
$servidor = $api -replace '/api$', ''
$lineas = Get-Content $envOrigen | Where-Object {
    $_ -notmatch '^EXPO_PUBLIC_(API_URL|AVISO_DE_PRIVACIDAD_URL|TERMINOS_URL)='
}
$lineas += "EXPO_PUBLIC_API_URL=$api"
$lineas += "EXPO_PUBLIC_AVISO_DE_PRIVACIDAD_URL=$servidor/aviso-de-privacidad"
$lineas += "EXPO_PUBLIC_TERMINOS_URL=$servidor/terminos"
Set-Content -Path (Join-Path $movil '.env') -Value $lineas -Encoding utf8

Push-Location $movil
try {
    Write-Host '2/6 Dependencias de la app (npm ci)'
    npm ci --no-audit --no-fund
    if ($LASTEXITCODE -ne 0) { throw 'npm ci falló.' }

    Write-Host '3/6 Logotipo y ícono del cliente en la copia'
    $logoDestino = Join-Path $movil 'assets\marca\logotipo.png'
    if ((Test-Path $logoOrigen) -and (Test-Path (Split-Path -Parent $logoDestino))) {
        Copy-Item $logoOrigen $logoDestino -Force
    } else {
        Write-Host '   Sin logotipo o sin assets\marca en esta rama: se queda el marcador neutro.'
    }
    # Las capas del ícono (vault/marca/visual/icono-app/README.md) reemplazan a los marcadores de movil\assets con el
    # mismo nombre; el prebuild genera con ellas los íconos de Android.
    if (Test-Path $iconoOrigen) {
        foreach ($capa in @('icon.png', 'android-icon-foreground.png', 'android-icon-background.png', 'android-icon-monochrome.png')) {
            $origenCapa = Join-Path $iconoOrigen $capa
            if (Test-Path $origenCapa) { Copy-Item $origenCapa (Join-Path $movil "assets\$capa") -Force }
        }
    } else {
        Write-Host '   Sin las capas del ícono en esta rama: se queda el ícono de Expo.'
    }

    Write-Host '4/6 Proyecto nativo de Android (expo prebuild)'
    $env:CI = '1'
    npx expo prebuild --platform android --clean --no-install
    if ($LASTEXITCODE -ne 0) { throw 'expo prebuild falló.' }
}
finally {
    Pop-Location
}

Write-Host '5/6 Ajustes solo de la prueba local'
$android = Join-Path $movil 'android'
$manifiesto = Join-Path $android 'app\src\main\AndroidManifest.xml'
$texto = [System.IO.File]::ReadAllText($manifiesto)
if ($texto -notmatch 'usesCleartextTraffic') {
    $texto = $texto -replace '<application ', '<application android:usesCleartextTraffic="true" '
    [System.IO.File]::WriteAllText($manifiesto, $texto)
}
# Sin minificar: con R8, el release se cierra al arrancar porque desaparece la base interna de WorkManager, que llega
# con las dependencias de la ubicación. Para la prueba local basta apagarlo; la compilación de las tiendas necesita
# sus reglas de conservación (pendiente de publicacion).
$propiedadesGradle = Join-Path $android 'gradle.properties'
$textoGradle = [System.IO.File]::ReadAllText($propiedadesGradle)
$textoGradle = $textoGradle -replace 'android\.enableMinifyInReleaseBuilds=true', 'android.enableMinifyInReleaseBuilds=false'
[System.IO.File]::WriteAllText($propiedadesGradle, $textoGradle)
# Con barras normales: en un .properties la barra invertida es un escape y la ruta quedaría rota.
$propiedades = @("sdk.dir=" + ($Sdk -replace '\\', '/'))
if (Test-Path $Cmake) { $propiedades += "cmake.dir=" + ($Cmake -replace '\\', '/') }
Set-Content -Path (Join-Path $android 'local.properties') -Value $propiedades -Encoding ascii

Write-Host "6/6 Compilación con Gradle ($Arquitecturas). La primera vez tarda: baja Gradle y sus dependencias."
$env:JAVA_HOME = $Jdk
$env:ANDROID_HOME = $Sdk
$env:ANDROID_SDK_ROOT = $Sdk
$env:Path = "$Jdk\bin;$env:Path"
$env:NODE_ENV = 'production'
Push-Location $android
try {
    & .\gradlew.bat assembleRelease "-PreactNativeArchitectures=$Arquitecturas"
    if ($LASTEXITCODE -ne 0) { throw 'La compilación de Gradle falló.' }
}
finally {
    Pop-Location
}

$apk = Join-Path $android 'app\build\outputs\apk\release\app-release.apk'
$salida = Join-Path $principal 'notas-locales'
New-Item -ItemType Directory -Force $salida | Out-Null
$final = Join-Path $salida "lavandi-$Modo.apk"
Copy-Item $apk $final -Force
Write-Host "Listo: $final"
Write-Host 'Para instalarlo con el teléfono conectado por USB:'
Write-Host "  $Sdk\platform-tools\adb.exe install -r `"$final`""
if ($Modo -eq 'usb') { Write-Host "  $Sdk\platform-tools\adb.exe reverse tcp:8000 tcp:8000" }
