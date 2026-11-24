<?php

namespace Tests\Feature\Avisos\Soporte;

use App\Models\Dispositivo;
use App\Models\Usuario;
use Closure;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Firebase Cloud Messaging simulado: ninguna prueba de avisos sale a la red ni necesita llaves de Firebase (DD-072).
 *
 * - La cuenta de servicio es de prueba: una llave RSA que se genera al correr y un archivo JSON temporal, fuera del
 *   repositorio, que se borra al terminar la prueba. Ninguna llave se versiona (ADR-0012).
 * - Http::fake() hace de Google: el punto de tokens de OAuth responde un token de acceso de prueba y el envío de FCM
 *   responde lo que pida la prueba. Http::preventStrayRequests() hace fallar cualquier otra petición.
 * - mensajesAFcm() devuelve lo que el servidor mandó a FCM, en el orden en que lo mandó.
 */
trait FcmSimulado
{
    protected const PROYECTO_DE_PRUEBA = 'proyecto-de-prueba';

    protected const CORREO_DE_LA_CUENTA_DE_SERVICIO = 'avisos@proyecto-de-prueba.iam.example.test';

    protected const DIRECCION_DE_TOKENS = 'https://oauth.example.test/token';

    protected const DIRECCION_DE_ENVIO = 'https://fcm.googleapis.com/v1/projects/proyecto-de-prueba/messages:send';

    protected const TOKEN_DE_ACCESO = 'token-de-acceso-de-prueba';

    /** La llave RSA de la cuenta de servicio de prueba: se genera una vez por proceso, porque generarla tarda. */
    private static ?string $llavePrivadaDePrueba = null;

    private static ?string $llavePublicaDePrueba = null;

    /** Lo que FCM responde a cada envío; null, que lo acepta. */
    private mixed $respuestaAlEnvioDeFcm = null;

    private bool $fcmSimulado = false;

    /**
     * La cuenta de servicio de prueba en un archivo temporal y FCM simulado. Sin respuesta, FCM acepta todo envío;
     * con una, responde eso a cada envío (una respuesta de Http::response(), Http::failedConnection() o un closure que
     * recibe la petición). Llamarla otra vez en la misma prueba cambia la respuesta: Http::fake() acumula sus
     * simulacros y gana el primero, así que se registra una sola vez.
     */
    protected function simularFcm(mixed $respuestaAlEnvio = null): void
    {
        $this->respuestaAlEnvioDeFcm = $respuestaAlEnvio;

        if ($this->fcmSimulado) {
            return;
        }

        $this->fcmSimulado = true;
        $this->configurarCuentaDeServicio();

        Http::preventStrayRequests();
        Http::fake([
            self::DIRECCION_DE_TOKENS => Http::response([
                'access_token' => self::TOKEN_DE_ACCESO,
                'expires_in' => 3599,
                'token_type' => 'Bearer',
            ]),
            self::DIRECCION_DE_ENVIO => fn (Request $peticion) => $this->respuestaDeFcmA($peticion),
        ]);
    }

    private function respuestaDeFcmA(Request $peticion): mixed
    {
        $respuesta = $this->respuestaAlEnvioDeFcm;

        if ($respuesta instanceof Closure) {
            return $respuesta($peticion);
        }

        return $respuesta ?? Http::response(['name' => 'projects/'.self::PROYECTO_DE_PRUEBA.'/messages/0:1']);
    }

    /**
     * FCM sin responder: la conexión al envío falla.
     */
    protected function simularFcmSinResponder(): void
    {
        $this->simularFcm(Http::failedConnection());
    }

    /**
     * FCM responde que el token ya no está registrado, como cuando la app se desinstaló (avisos-E06).
     */
    protected function simularTokenRechazado(): void
    {
        $this->simularFcm(Http::response([
            'error' => [
                'code' => 404,
                'message' => 'Requested entity was not found.',
                'status' => 'NOT_FOUND',
                'details' => [[
                    '@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError',
                    'errorCode' => 'UNREGISTERED',
                ]],
            ],
        ], 404));
    }

    /**
     * Lo que el servidor mandó a FCM: el objeto message de cada envío, en orden.
     *
     * @return list<array<string, mixed>>
     */
    protected function mensajesAFcm(): array
    {
        return Http::recorded(fn (Request $peticion): bool => $peticion->url() === self::DIRECCION_DE_ENVIO)
            ->map(fn (array $par): array => $par[0]->data()['message'])
            ->values()
            ->all();
    }

    /**
     * Los mensajes que llegaron al token de ese teléfono.
     *
     * @return list<array<string, mixed>>
     */
    protected function mensajesAlToken(string $token): array
    {
        return array_values(array_filter(
            $this->mensajesAFcm(),
            fn (array $mensaje): bool => $mensaje['token'] === $token,
        ));
    }

    /**
     * Las peticiones al punto de tokens de OAuth.
     *
     * @return list<Request>
     */
    protected function peticionesDeTokenDeAcceso(): array
    {
        return Http::recorded(fn (Request $peticion): bool => $peticion->url() === self::DIRECCION_DE_TOKENS)
            ->map(fn (array $par): Request => $par[0])
            ->values()
            ->all();
    }

    /**
     * Un teléfono registrado de la cuenta, con un token nuevo, como lo deja el registro de la app.
     */
    protected function telefonoDe(Usuario $cuenta, string $plataforma = Dispositivo::ANDROID): Dispositivo
    {
        return Dispositivo::create([
            'id_usuarios' => $cuenta->getKey(),
            'token_fcm' => 'token-fcm-'.Str::ulid(),
            'plataforma' => $plataforma,
            'ultimo_uso_en' => now(),
        ]);
    }

    protected function llavePublicaDePrueba(): string
    {
        $this->generarLlaveDePrueba();

        return (string) self::$llavePublicaDePrueba;
    }

    /**
     * El archivo JSON de la cuenta de servicio, como lo descarga la consola de Firebase, en un archivo temporal que se
     * borra al terminar la prueba. Su ruta va en avisos.fcm.credenciales, como FCM_CREDENCIALES en el .env.
     *
     * @param  array<string, mixed>  $cambios
     */
    protected function configurarCuentaDeServicio(array $cambios = []): string
    {
        $this->generarLlaveDePrueba();

        $ruta = (string) tempnam(sys_get_temp_dir(), 'fcm');
        file_put_contents($ruta, json_encode([
            'type' => 'service_account',
            'project_id' => self::PROYECTO_DE_PRUEBA,
            'private_key_id' => 'llave-de-prueba',
            'private_key' => self::$llavePrivadaDePrueba,
            'client_email' => self::CORREO_DE_LA_CUENTA_DE_SERVICIO,
            'token_uri' => self::DIRECCION_DE_TOKENS,
            ...$cambios,
        ], JSON_THROW_ON_ERROR));

        $this->beforeApplicationDestroyed(function () use ($ruta): void {
            if (is_file($ruta)) {
                unlink($ruta);
            }
        });

        config(['avisos.fcm.credenciales' => $ruta]);

        return $ruta;
    }

    private function generarLlaveDePrueba(): void
    {
        if (self::$llavePrivadaDePrueba !== null) {
            return;
        }

        $llave = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($llave, $privada);

        self::$llavePrivadaDePrueba = $privada;
        self::$llavePublicaDePrueba = openssl_pkey_get_details($llave)['key'];
    }
}
