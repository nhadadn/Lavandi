<?php

namespace Tests\Feature\Registro\Soporte;

use Illuminate\Support\Facades\Http;
use OpenSSLAsymmetricKey;
use RuntimeException;

/**
 * Imita a Google sin salir a la red: genera sus propias llaves RSA, publica la llave pública en la dirección de las
 * llaves configurada (con Http::fake) y firma idTokens con los reclamos de un inicio de sesión real.
 *
 * Las llaves se generan una vez por clase de prueba. La firma se hace con openssl_sign, sin la librería que usa el
 * servidor, para que la prueba no dependa de lo que prueba.
 */
trait ImitaAGoogle
{
    protected const CLIENTE_DE_PRUEBA = 'cliente-web-de-prueba.apps.googleusercontent.com';

    protected const OTRO_CLIENTE_CONFIGURADO = 'cliente-ios-de-prueba.apps.googleusercontent.com';

    protected const CLIENTE_AJENO = 'cliente-de-otra-app.apps.googleusercontent.com';

    protected const DIRECCION_DE_LLAVES = 'https://llaves-de-google.prueba.test/oauth2/v3/certs';

    protected const LLAVE_DE_PRUEBA = 'llave-de-prueba-1';

    protected const SUJETO_DE_PRUEBA = '110169484474386276334';

    private bool $googleEntregaSusLlaves = true;

    /** @var array<string, OpenSSLAsymmetricKey> */
    private static array $llavesPrivadas = [];

    /**
     * Configura los clientes y la dirección de las llaves, publica la llave pública de Google e impide cualquier otra
     * salida a la red.
     */
    protected function imitarAGoogle(): void
    {
        config([
            'services.google.clientes' => [self::CLIENTE_DE_PRUEBA, self::OTRO_CLIENTE_CONFIGURADO],
            'services.google.llaves' => self::DIRECCION_DE_LLAVES,
        ]);

        Http::preventStrayRequests();
        Http::fake([
            self::DIRECCION_DE_LLAVES => fn () => $this->googleEntregaSusLlaves
                ? Http::response(
                    ['keys' => [$this->llavePublica(self::LLAVE_DE_PRUEBA, $this->llavePrivada('google'))]],
                    200,
                    ['Cache-Control' => 'public, max-age=21600, must-revalidate, no-transform'],
                )
                : Http::response('Error del servidor', 500),
        ]);
    }

    /**
     * Desde aquí, la dirección de las llaves de Google responde con error.
     */
    protected function googleNoEntregaSusLlaves(): void
    {
        $this->googleEntregaSusLlaves = false;
    }

    /**
     * Un idToken firmado por la Google de prueba, válido para el cliente configurado, con los reclamos cambiados.
     * Un reclamo con valor null se quita.
     *
     * @param  array<string, mixed>  $reclamos
     * @param  array<string, mixed>  $cabecera
     */
    protected function idTokenDeGoogle(array $reclamos = [], string $firmante = 'google', array $cabecera = []): string
    {
        // El reloj de Laravel, para que las pruebas puedan viajar en el tiempo.
        $ahora = now()->getTimestamp();

        $carga = array_filter(array_merge([
            'iss' => 'https://accounts.google.com',
            'azp' => self::CLIENTE_DE_PRUEBA,
            'aud' => self::CLIENTE_DE_PRUEBA,
            'sub' => self::SUJETO_DE_PRUEBA,
            'email' => 'persona@ejemplo.com',
            'email_verified' => true,
            'iat' => $ahora - 60,
            'exp' => $ahora + 3540,
        ], $reclamos), fn ($valor): bool => $valor !== null);

        $cabecera = array_merge(['alg' => 'RS256', 'kid' => self::LLAVE_DE_PRUEBA, 'typ' => 'JWT'], $cabecera);

        $datos = $this->base64Url(json_encode($cabecera)).'.'.$this->base64Url(json_encode($carga));

        if (! openssl_sign($datos, $firma, $this->llavePrivada($firmante), OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('No se pudo firmar el idToken de prueba.');
        }

        return $datos.'.'.$this->base64Url($firma);
    }

    /**
     * Un token sin firma, con el algoritmo "none".
     *
     * @param  array<string, mixed>  $reclamos
     */
    protected function idTokenSinFirma(array $reclamos = []): string
    {
        $partes = explode('.', $this->idTokenDeGoogle($reclamos, cabecera: ['alg' => 'none']));

        return $partes[0].'.'.$partes[1].'.';
    }

    /**
     * La llave privada de un firmante; "google" es la que publica la Google de prueba, cualquier otro nombre es una
     * llave que Google no conoce.
     */
    private function llavePrivada(string $firmante): OpenSSLAsymmetricKey
    {
        return self::$llavesPrivadas[$firmante] ??= openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]) ?: throw new RuntimeException('No se pudo generar la llave RSA de prueba.');
    }

    /**
     * La llave pública en el formato JWK que publica Google.
     *
     * @return array<string, string>
     */
    private function llavePublica(string $identificador, OpenSSLAsymmetricKey $privada): array
    {
        $rsa = openssl_pkey_get_details($privada)['rsa'];

        return [
            'kty' => 'RSA',
            'alg' => 'RS256',
            'use' => 'sig',
            'kid' => $identificador,
            'n' => $this->base64Url($rsa['n']),
            'e' => $this->base64Url($rsa['e']),
        ];
    }

    private function base64Url(string $datos): string
    {
        return rtrim(strtr(base64_encode($datos), '+/', '-_'), '=');
    }
}
