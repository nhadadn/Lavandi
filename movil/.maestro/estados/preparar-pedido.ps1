# Prepara un pedido de prueba en Confirmado, ya aceptado por la lavandería, para el flujo de Maestro estados-E17
# (cambio-en-60-segundos.yaml; openspec/changes/estados-del-pedido, tarea 4.3). Sigue el camino real del pedido:
#
# 1. el usuario de prueba guarda una dirección marcada en el mapa (POST /api/direcciones, origen "mapa", sin
#    geocodificación) y crea el pedido a la lavandería de prueba con la primera ventana libre (POST /api/pedidos);
# 2. la pasarela simulada le crea el cobro y manda su aviso de pago aprobado (php artisan pago:cobro-de-prueba y
#    pago:simular-aviso, desde la carpeta servidor que atiende la API): el pedido pasa a Confirmado (C-188);
# 3. la lavandería lo acepta (POST /api/lavanderia/pedidos/{id_publico}/aceptar), y el usuario lo sigue viendo en
#    Confirmado (estados-E01), listo para que la lavandería marque Recolectado.
#
# Cierra las sesiones que abre (DELETE /api/sesion). Devuelve un objeto con el id_publico del pedido, su folio y su
# lavandería; con -Json, lo escribe como una línea de JSON.
#
# Solo corre contra un servidor local con la pasarela simulada y las cuentas de prueba sembradas (php artisan db:seed).
# Las cuentas por omisión son las ficticias de los sembradores de desarrollo (LavanderiasDePruebaSeeder y
# RepartidorDeDesarrolloSeeder): no son secretos.
#
# Uso, desde la raíz del repositorio:
#   powershell -ExecutionPolicy Bypass -File movil\.maestro\estados\preparar-pedido.ps1 -Servidor <carpeta servidor>
# Guía: vault/formas-de-trabajo/correr-la-app-en-local.md, sección 5-ter.

param(
    # La carpeta servidor/ cuyo artisan habla con la misma base que sirve la API (la de php artisan serve).
    [Parameter(Mandatory = $true)][string]$Servidor,
    # La dirección base de la API. Vacía: EXPO_PUBLIC_API_URL del entorno o de -EnvDeLaApp.
    [string]$Api = '',
    # El .env de la app, de donde sale la API si no viene. Vacío: movil\.env.
    [string]$EnvDeLaApp = '',
    [string]$UsuarioCorreo = 'usuario.de.prueba@ejemplo.com',
    [string]$UsuarioContrasena = 'contrasena-de-prueba',
    [string]$LavanderiaCorreo = 'lavanderia.centro@ejemplo.com',
    [string]$LavanderiaContrasena = 'lavanderia-de-prueba',
    # El punto de la recogida: tiene que caer en la zona de servicio de la plaza de la lavandería. Por omisión, el
    # centro de la plaza de prueba de Durango, el mismo que se le pone al emulador.
    [double]$Latitud = 24.0277,
    [double]$Longitud = -104.6532,
    # El servicio del pedido y el monto ficticio del cobro simulado, en pesos.
    [string]$Servicio = 'estandar',
    [string]$Monto = '150',
    [switch]$Json
)

$ErrorActionPreference = 'Stop'

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

if (-not $EnvDeLaApp) { $EnvDeLaApp = Join-Path (Split-Path -Parent $MyInvocation.MyCommand.Path) '..\..\.env' }
if (-not $Api) { $Api = LeerVariable 'EXPO_PUBLIC_API_URL' $EnvDeLaApp }
if (-not $Api) { throw "Falta la dirección de la API: pasa -Api o pon EXPO_PUBLIC_API_URL en $EnvDeLaApp." }
$Api = $Api.TrimEnd('/')
if (-not (Test-Path (Join-Path $Servidor 'artisan'))) { throw "No está artisan en $Servidor. Pasa la carpeta servidor que atiende la API." }

# Una petición a la API. Con un error HTTP, se detiene con el código y el cuerpo de la respuesta.
function Llamar([string]$metodo, [string]$ruta, [string]$token = '', $cuerpo = $null) {
    $encabezados = @{ Accept = 'application/json' }
    if ($token) { $encabezados.Authorization = "Bearer $token" }
    $parametros = @{ Method = $metodo; Uri = "$Api$ruta"; Headers = $encabezados; UseBasicParsing = $true }
    if ($null -ne $cuerpo) {
        $parametros.ContentType = 'application/json; charset=utf-8'
        $parametros.Body = [System.Text.Encoding]::UTF8.GetBytes(($cuerpo | ConvertTo-Json -Depth 5 -Compress))
    }
    try {
        $respuesta = Invoke-WebRequest @parametros
    } catch {
        $detalle = ''
        if ($_.Exception.Response) {
            $lector = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
            $detalle = $lector.ReadToEnd()
        }
        throw "$metodo $ruta falló: $($_.Exception.Message) $detalle"
    }
    if (-not $respuesta.Content) { return $null }
    $texto = [System.Text.Encoding]::UTF8.GetString($respuesta.RawContentStream.ToArray())
    return $texto | ConvertFrom-Json
}

function AbrirSesion([string]$correo, [string]$contrasena) {
    return (Llamar 'Post' '/sesion' '' @{ correo = $correo; password = $contrasena }).token
}

function CerrarSesion([string]$token) {
    if ($token) { Llamar 'Delete' '/sesion' $token | Out-Null }
}

function Artisan([string[]]$argumentos) {
    Push-Location $Servidor
    try {
        $salida = & php artisan @argumentos 2>&1
        if ($LASTEXITCODE -ne 0) { throw "php artisan $($argumentos -join ' ') falló: $salida" }
    } finally {
        Pop-Location
    }
}

$tokenDelUsuario = ''
$tokenDeLaLavanderia = ''
try {
    $tokenDeLaLavanderia = AbrirSesion $LavanderiaCorreo $LavanderiaContrasena
    $lavanderias = (Llamar 'Get' '/socio/lavanderias' $tokenDeLaLavanderia).lavanderias
    if (-not $lavanderias) { throw "La cuenta $LavanderiaCorreo no administra ninguna lavandería. ¿Falta php artisan db:seed?" }
    $lavanderia = $lavanderias[0]

    $tokenDelUsuario = AbrirSesion $UsuarioCorreo $UsuarioContrasena

    # Una dirección nueva, sin favorita, para no tocar la lista de favoritas del usuario de prueba.
    $direccion = (Llamar 'Post' '/direcciones' $tokenDelUsuario @{
            origen    = 'mapa'
            latitud   = $Latitud
            longitud  = $Longitud
            colonia   = 'Colonia de prueba'
            calle     = 'Calle de prueba'
            referencias = 'Dirección de la prueba de los 60 segundos (estados-E17)'
        }).direccion

    # La primera ventana libre, desde hoy hasta el último día que deja agendar la plaza.
    $ventana = $null
    $fecha = Get-Date
    for ($dia = 0; $dia -lt 14 -and $null -eq $ventana; $dia++) {
        $delDia = Llamar 'Get' "/lavanderias/$($lavanderia.id_publico)/ventanas?fecha=$($fecha.AddDays($dia).ToString('yyyy-MM-dd'))" $tokenDelUsuario
        if ($delDia.ventanas) { $ventana = $delDia.ventanas[0] }
        elseif ($delDia.ultimo_dia -and $fecha.AddDays($dia).ToString('yyyy-MM-dd') -ge $delDia.ultimo_dia) { break }
    }
    if ($null -eq $ventana) { throw "La lavandería $($lavanderia.nombre) no ofrece ninguna ventana en los días que se pueden agendar." }

    $pedido = (Llamar 'Post' '/pedidos' $tokenDelUsuario @{
            lavanderia = $lavanderia.id_publico
            servicio   = $Servicio
            ventana    = @{ inicio = $ventana.inicio; fin = $ventana.fin }
            recogida   = $direccion.id_publico
        }).pedido

    # El pago, por la pasarela simulada: el pedido pasa a Confirmado.
    Artisan @('pago:cobro-de-prueba', $pedido.id_publico, $Monto)
    Artisan @('pago:simular-aviso', $pedido.id_publico, 'aprobado')

    $aceptado = (Llamar 'Post' "/lavanderia/pedidos/$($pedido.id_publico)/aceptar" $tokenDeLaLavanderia).pedido
    if ($aceptado.estado.codigo -ne 'aceptado') { throw "La lavandería no aceptó el pedido: quedó en $($aceptado.estado.codigo)." }

    # Lo que ve el usuario: Confirmado, con el siguiente paso, Recolectado, pendiente.
    $visto = (Llamar 'Get' "/pedidos/$($pedido.id_publico)" $tokenDelUsuario).pedido
    if ($visto.estado.codigo -ne 'confirmado') { throw "El usuario ve el pedido en $($visto.estado.codigo), no en Confirmado." }

    $resultado = [pscustomobject]@{
        pedido     = $visto.id_publico
        folio      = $visto.folio
        lavanderia = $lavanderia.nombre
        ventana    = "$($ventana.fecha) $($ventana.hora_inicio)-$($ventana.hora_fin)"
    }
} finally {
    CerrarSesion $tokenDelUsuario
    CerrarSesion $tokenDeLaLavanderia
}

if ($Json) { $resultado | ConvertTo-Json -Compress } else { $resultado }
