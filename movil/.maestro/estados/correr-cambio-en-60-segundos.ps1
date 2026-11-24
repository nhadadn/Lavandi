# Corre el flujo de Maestro estados-E17 (cambio-en-60-segundos.yaml) una o varias veces y junta los tiempos: por cada
# corrida prepara un pedido nuevo con preparar-pedido.ps1, corre el flujo contra el teléfono o el emulador y lee del
# registro de Maestro cuánto tardó Recolectado en verse (openspec/changes/estados-del-pedido, tarea 4.3; criterio
# A43-3; NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3).
#
# Deja en -Salida una carpeta por corrida con lo que escribe Maestro (registro, comandos, capturas y la grabación del
# cambio) y lo que imprimió, más resumen.csv con los tiempos. Termina con error si alguna corrida falla o pasa de 60 s.
#
# Antes: el servidor local corriendo, el APK instalado, "adb reverse tcp:8000 tcp:8000", JAVA_HOME con un JDK 17 y la
# CLI de Maestro (en el PATH o con -Maestro). El paquete y la API salen de movil\.env (APP_IDENTIFIER y
# EXPO_PUBLIC_API_URL) o de -AppId y -Api: ni la marca ni el dominio se escriben aquí (CLAUDE.md §5).
#
# Uso, desde la raíz del repositorio:
#   powershell -ExecutionPolicy Bypass -File movil\.maestro\estados\correr-cambio-en-60-segundos.ps1 `
#       -Servidor <carpeta servidor> -Corridas 3 -Salida <carpeta de la evidencia>
# Guía: vault/formas-de-trabajo/correr-la-app-en-local.md, sección 5-ter.

param(
    # La carpeta servidor/ cuyo artisan habla con la misma base que sirve la API.
    [Parameter(Mandatory = $true)][string]$Servidor,
    [int]$Corridas = 1,
    # Dónde queda la evidencia. Vacía: una carpeta nueva con la fecha y la hora en el directorio temporal.
    [string]$Salida = '',
    # Si viene, ahí se mueve el registro completo del teléfono de cada corrida (device-logcat.txt), que es grande y trae
    # el registro de todo el sistema; si no, se queda junto a lo demás.
    [string]$RegistrosDelTelefono = '',
    # La CLI de Maestro. Por omisión, la del PATH.
    [string]$Maestro = 'maestro',
    # El serial de adb del teléfono o del emulador. Vacío: el único conectado.
    [string]$Dispositivo = '',
    [string]$AppId = '',
    [string]$Api = '',
    [string]$EnvDeLaApp = '',
    [string]$UsuarioCorreo = 'usuario.de.prueba@ejemplo.com',
    [string]$UsuarioContrasena = 'contrasena-de-prueba',
    [string]$LavanderiaCorreo = 'lavanderia.centro@ejemplo.com',
    [string]$LavanderiaContrasena = 'lavanderia-de-prueba'
)

$ErrorActionPreference = 'Stop'
$aqui = Split-Path -Parent $MyInvocation.MyCommand.Path
$flujo = Join-Path $aqui 'cambio-en-60-segundos.yaml'
$LIMITE_MS = 60000

function LeerVariable([string]$nombre, [string]$archivo) {
    $delEntorno = [Environment]::GetEnvironmentVariable($nombre)
    if ($delEntorno) { return $delEntorno.Trim() }
    if (Test-Path $archivo) {
        foreach ($linea in Get-Content -Encoding UTF8 $archivo) {
            if ($linea -match "^\s*$nombre\s*=\s*(.*)$") { return $Matches[1].Trim() }
        }
    }
    return ''
}

if (-not $EnvDeLaApp) { $EnvDeLaApp = Join-Path $aqui '..\..\.env' }
if (-not $AppId) { $AppId = LeerVariable 'APP_IDENTIFIER' $EnvDeLaApp }
if (-not $Api) { $Api = LeerVariable 'EXPO_PUBLIC_API_URL' $EnvDeLaApp }
if (-not $AppId -or -not $Api) {
    throw "Faltan el paquete o la API: pasa -AppId y -Api, o pon APP_IDENTIFIER y EXPO_PUBLIC_API_URL en $EnvDeLaApp."
}
$Api = $Api.TrimEnd('/')
if (-not $Salida) { $Salida = Join-Path $env:TEMP ("estados-E17-" + (Get-Date -Format 'yyyy-MM-dd_HHmmss')) }
New-Item -ItemType Directory -Force $Salida | Out-Null
$Salida = (Resolve-Path $Salida).Path

# Sin la telemetría de Maestro ni sus anuncios en la salida.
$env:MAESTRO_CLI_NO_ANALYTICS = '1'
$env:MAESTRO_CLI_ANALYSIS_NOTIFICATION_DISABLED = 'true'

# Convierte la hora UTC que escribe el flujo a la hora de esta máquina, con su desfase.
function HoraLocal([string]$iso) {
    return [DateTimeOffset]::Parse($iso, [Globalization.CultureInfo]::InvariantCulture).ToLocalTime().ToString(
        'yyyy-MM-ddTHH:mm:ss.fffzzz', [Globalization.CultureInfo]::InvariantCulture)
}

$resultados = @()
for ($corrida = 1; $corrida -le $Corridas; $corrida++) {
    Write-Host "== Corrida $corrida de $Corridas"
    $carpeta = Join-Path $Salida ("corrida-{0}" -f $corrida)
    New-Item -ItemType Directory -Force $carpeta | Out-Null

    $pedido = & (Join-Path $aqui 'preparar-pedido.ps1') -Servidor $Servidor -Api $Api `
        -UsuarioCorreo $UsuarioCorreo -UsuarioContrasena $UsuarioContrasena `
        -LavanderiaCorreo $LavanderiaCorreo -LavanderiaContrasena $LavanderiaContrasena
    Write-Host "Pedido $($pedido.pedido), folio $($pedido.folio), en Confirmado y aceptado por $($pedido.lavanderia)"

    $argumentos = @()
    if ($Dispositivo) { $argumentos += @('--device', $Dispositivo) }
    $argumentos += @('test', '--no-ansi', '--debug-output', $carpeta, '--flatten-debug-output',
        '-e', "APP_ID=$AppId", '-e', "API_URL=$Api", '-e', "PEDIDO=$($pedido.pedido)",
        '-e', "USUARIO_CORREO=$UsuarioCorreo", '-e', "USUARIO_CONTRASENA=$UsuarioContrasena",
        '-e', "LAVANDERIA_CORREO=$LavanderiaCorreo", '-e', "LAVANDERIA_CONTRASENA=$LavanderiaContrasena",
        $flujo)

    # Lo que Maestro escribe en stderr no es un error de este script: se junta con la salida.
    $ErrorActionPreference = 'Continue'
    $salidaDeMaestro = & $Maestro @argumentos 2>&1 | ForEach-Object { "$_" }
    $codigo = $LASTEXITCODE
    $ErrorActionPreference = 'Stop'
    $salidaDeMaestro | Out-File -Encoding utf8 (Join-Path $carpeta 'salida.txt')
    $salidaDeMaestro | Select-Object -Last 12 | ForEach-Object { Write-Host "   $_" }

    # Los tiempos, de las líneas CRONOMETRO que dejan marcar-recolectado.js y cronometrar.js en el registro de Maestro.
    $registro = Get-ChildItem -Recurse -File -Path $carpeta -Filter 'maestro.log' | Select-Object -First 1
    $marcado = $null
    $visto = $null
    if ($registro) {
        $marcado = Select-String -Path $registro.FullName -Pattern 'JsConsole: CRONOMETRO marcado .*marcado_en=(\S+) respuesta_ms=(\d+)' |
            Select-Object -Last 1
        $visto = Select-String -Path $registro.FullName -Pattern 'JsConsole: CRONOMETRO visto .*visto_en=(\S+) tardo_ms=(\d+)' |
            Select-Object -Last 1
    }
    $tardo = if ($visto) { [int]$visto.Matches[0].Groups[2].Value } else { $null }
    $paso = ($codigo -eq 0) -and ($null -ne $tardo) -and ($tardo -le $LIMITE_MS)

    if ($RegistrosDelTelefono) {
        $delTelefono = Get-ChildItem -Recurse -File -Path $carpeta -Filter 'device-logcat.txt' | Select-Object -First 1
        if ($delTelefono) {
            $destino = Join-Path $RegistrosDelTelefono ("corrida-{0}" -f $corrida)
            New-Item -ItemType Directory -Force $destino | Out-Null
            Move-Item -Force $delTelefono.FullName (Join-Path $destino 'device-logcat.txt')
        }
    }

    $resultados += [pscustomobject]@{
        corrida     = $corrida
        pedido      = $pedido.pedido
        folio       = $pedido.folio
        marcado_en  = if ($marcado) { HoraLocal $marcado.Matches[0].Groups[1].Value } else { '' }
        visto_en    = if ($visto) { HoraLocal $visto.Matches[0].Groups[1].Value } else { '' }
        respuesta_ms = if ($marcado) { [int]$marcado.Matches[0].Groups[2].Value } else { '' }
        tardo_ms    = $tardo
        segundos    = if ($null -ne $tardo) { [Math]::Round($tardo / 1000, 1) } else { '' }
        resultado   = if ($paso) { 'pasa' } else { 'falla' }
    }
}

$resultados | Export-Csv -NoTypeInformation -Encoding UTF8 (Join-Path $Salida 'resumen.csv')
$resultados | Format-Table corrida, folio, marcado_en, visto_en, segundos, resultado -AutoSize | Out-String | Write-Host
Write-Host "Evidencia en $Salida"
if ($resultados | Where-Object { $_.resultado -ne 'pasa' }) { exit 1 }
