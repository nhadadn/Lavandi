# Arma una copia aparte del repositorio para ver la app en Chrome, con tamaño de teléfono (C-231).
#
# La app es para Android e iPhone (ADR-0002). Para verla en el navegador, esta copia agrega el soporte web de Expo y
# sustituye las dos piezas nativas que el navegador no tiene: el almacén cifrado (por el localStorage del navegador)
# y la librería de Google (desactivada). Nada de eso toca movil/ de la rama de trabajo ni se versiona.
#
# Uso, desde la raíz del repositorio:
#   powershell -ExecutionPolicy Bypass -File herramientas\vista-web\preparar.ps1
#   powershell -ExecutionPolicy Bypass -File herramientas\vista-web\preparar.ps1 -Rama feat/h1-acceso -Destino ..\vista-web
# Guía completa: vault/formas-de-trabajo/correr-la-app-en-local.md

param(
    [string]$Rama = 'feat/h1-acceso',
    [string]$Destino = '.claude\worktrees\vista-web',
    [string]$EnvDeLaApp = 'movil\.env'
)

$ErrorActionPreference = 'Stop'
# La plantilla sale de la copia desde la que se corre; la copia aparte va junto al repositorio principal, aunque el
# script se corra desde otra copia de trabajo.
$aqui = (git rev-parse --show-toplevel).Trim()
$principal = Split-Path -Parent ((git rev-parse --path-format=absolute --git-common-dir).Trim())
$plantilla = Join-Path $aqui 'herramientas\vista-web\plantilla'
$envOrigen = if ([System.IO.Path]::IsPathRooted($EnvDeLaApp)) { $EnvDeLaApp } else { Join-Path (Get-Location) $EnvDeLaApp }
$destinoCompleto = if ([System.IO.Path]::IsPathRooted($Destino)) { $Destino } else { Join-Path $principal $Destino }

if (-not (Test-Path $envOrigen)) {
    throw "No encuentro el .env de la app en $envOrigen. Pasa la ruta con -EnvDeLaApp."
}
if (Test-Path $destinoCompleto) {
    throw "Ya existe $destinoCompleto. Para rehacerla: git worktree remove --force `"$destinoCompleto`""
}

Write-Host "1/5 Copia aparte de $Rama en $destinoCompleto"
git worktree add --detach $destinoCompleto $Rama
if ($LASTEXITCODE -ne 0) { throw 'No se pudo crear la copia aparte.' }

$movil = Join-Path $destinoCompleto 'movil'
Copy-Item $envOrigen (Join-Path $movil '.env')

Write-Host '2/5 Dependencias de la app (npm ci)'
Push-Location $movil
try {
    npm ci --no-audit --no-fund
    if ($LASTEXITCODE -ne 0) { throw 'npm ci falló.' }

    Write-Host '3/5 Soporte web de Expo'
    npx expo install react-native-web @expo/metro-runtime
    if ($LASTEXITCODE -ne 0) { throw 'No se pudo instalar el soporte web.' }
}
finally {
    Pop-Location
}

Write-Host '4/5 Sustituciones para el navegador'
Copy-Item (Join-Path $plantilla 'metro.config.js') $movil
Copy-Item (Join-Path $plantilla 'vista-web') $movil -Recurse

Write-Host '5/5 La plataforma web en la configuración de la copia'
$config = Join-Path $movil 'app.config.ts'
$texto = [System.IO.File]::ReadAllText($config)
$nuevo = $texto.Replace("platforms: ['ios', 'android'],", "platforms: ['ios', 'android', 'web'],")
if ($nuevo -eq $texto) { throw "No encontré la lista de plataformas en $config." }
[System.IO.File]::WriteAllText($config, $nuevo, (New-Object System.Text.UTF8Encoding($false)))

Write-Host ''
Write-Host 'Lista. Para verla:'
Write-Host "  cd `"$movil`""
Write-Host '  npx expo start --web --port 8081'
Write-Host 'y abre http://localhost:8081 en Chrome (F12 y el modo de teléfono). El servidor tiene que estar corriendo.'
