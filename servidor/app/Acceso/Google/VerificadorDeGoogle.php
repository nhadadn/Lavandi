<?php

namespace App\Acceso\Google;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use stdClass;
use Throwable;

/**
 * Verifica el idToken que la app recibe de Google al iniciar sesión (ADR-0005: "api valida el idToken contra Google").
 *
 * Acepta el token solo si:
 * - lo firmó Google con RS256, con una de las llaves públicas que publica en services.google.llaves;
 * - lo emitió accounts.google.com;
 * - su destinatario (aud) es uno de los clientes OAuth configurados en services.google.clientes (ADR-0012);
 * - no ha vencido, y trae el sujeto y el correo;
 * - Google da el correo por verificado. Si no, se rechaza siempre, haya o no cuenta con ese correo (registro-E17;
 *   C-228, DD-128 cerrada): así nadie abre ni liga una cuenta con un correo que no es suyo.
 *
 * Las llaves de Google se guardan en la caché el plazo que dice Google en Cache-Control. Una llave que no está en la
 * caché obliga a pedirlas otra vez, porque Google las cambia; a lo más una vez por minuto, para que nadie use esta
 * ruta para saturar a Google. Si esa petición falla, se quedan las llaves que había en la caché.
 */
final class VerificadorDeGoogle
{
    /** Los dos valores del reclamo iss que usa Google. */
    private const EMISORES = ['accounts.google.com', 'https://accounts.google.com'];

    /** El único algoritmo de firma que se acepta, el que usa Google. */
    private const ALGORITMO = 'RS256';

    private const LLAVE_DE_CACHE = 'acceso.google.llaves';

    private const LLAVE_DE_CACHE_DEL_REFRESCO = 'acceso.google.llaves.refrescadas';

    /** Si Google no dice cuánto guardar sus llaves, se guardan una hora. */
    private const SEGUNDOS_EN_CACHE_POR_OMISION = 3600;

    /** Entre dos peticiones de llaves por una llave desconocida pasa al menos un minuto. */
    private const SEGUNDOS_ENTRE_REFRESCOS = 60;

    /** La diferencia que se tolera entre el reloj del servidor y el de Google al revisar iat y exp. */
    private const TOLERANCIA_DEL_RELOJ_EN_SEGUNDOS = 60;

    private const SEGUNDOS_DE_ESPERA_A_GOOGLE = 5;

    /**
     * @throws IdTokenDeGoogleNoValido
     */
    public function verificar(string $idToken): IdentidadDeGoogle
    {
        $clientes = $this->clientes();

        if ($clientes === []) {
            throw IdTokenDeGoogleNoValido::porque('no hay clientes OAuth de Google configurados');
        }

        $reclamos = $this->decodificar($idToken);

        if (! in_array($reclamos->iss ?? null, self::EMISORES, true)) {
            throw IdTokenDeGoogleNoValido::porque('no lo emitió Google');
        }

        if (! is_string($reclamos->aud ?? null) || ! in_array($reclamos->aud, $clientes, true)) {
            throw IdTokenDeGoogleNoValido::porque('se emitió para otro cliente');
        }

        // La librería solo revisa el vencimiento si el reclamo existe; Google siempre lo pone.
        if (! isset($reclamos->exp)) {
            throw IdTokenDeGoogleNoValido::porque('no trae vencimiento');
        }

        if (! $this->esTextoConValor($reclamos->sub ?? null) || ! $this->esTextoConValor($reclamos->email ?? null)) {
            throw IdTokenDeGoogleNoValido::porque('no trae el sujeto o el correo');
        }

        // En el idToken es booleano; en otras respuestas de Google llega como texto.
        if (! in_array($reclamos->email_verified ?? false, [true, 'true'], true)) {
            throw IdTokenDeGoogleNoValido::porque('Google no da el correo por verificado');
        }

        return new IdentidadDeGoogle(sujeto: $reclamos->sub, correo: $reclamos->email);
    }

    /**
     * Comprueba la firma, el algoritmo y las fechas, y devuelve los reclamos.
     */
    private function decodificar(string $idToken): stdClass
    {
        // Primero la cabecera: un texto que no es JWT se rechaza sin pedir nada a Google.
        $identificador = $this->identificadorDeLlave($idToken);

        $llaves = $this->llaves();

        if (! isset($llaves[$identificador]) && Cache::add(self::LLAVE_DE_CACHE_DEL_REFRESCO, true, self::SEGUNDOS_ENTRE_REFRESCOS)) {
            $llaves = $this->refrescarLlaves($llaves);
        }

        $tolerancia = JWT::$leeway;
        $reloj = JWT::$timestamp;

        try {
            JWT::$leeway = self::TOLERANCIA_DEL_RELOJ_EN_SEGUNDOS;
            JWT::$timestamp = now()->getTimestamp();

            return JWT::decode($idToken, $llaves);
        } catch (Throwable $error) {
            throw IdTokenDeGoogleNoValido::porque($error->getMessage(), $error);
        } finally {
            JWT::$leeway = $tolerancia;
            JWT::$timestamp = $reloj;
        }
    }

    /**
     * El identificador de la llave (kid) de la cabecera, sin comprobar nada más.
     */
    private function identificadorDeLlave(string $idToken): string
    {
        $partes = explode('.', $idToken);

        try {
            $cabecera = count($partes) === 3 ? JWT::jsonDecode(JWT::urlsafeB64Decode($partes[0])) : null;
        } catch (Throwable $error) {
            throw IdTokenDeGoogleNoValido::porque('no es un JWT', $error);
        }

        if (! $cabecera instanceof stdClass || ! $this->esTextoConValor($cabecera->kid ?? null)) {
            throw IdTokenDeGoogleNoValido::porque('no es un JWT con llave');
        }

        return $cabecera->kid;
    }

    /**
     * Las llaves públicas de Google, de la caché o pedidas a Google.
     *
     * @return array<string, Key>
     */
    private function llaves(): array
    {
        $conjunto = Cache::get(self::LLAVE_DE_CACHE);

        if (! is_array($conjunto)) {
            [$conjunto, $segundos] = $this->pedirLlaves();
            Cache::put(self::LLAVE_DE_CACHE, $conjunto, $segundos);
        }

        try {
            return JWK::parseKeySet($conjunto, self::ALGORITMO);
        } catch (Throwable $error) {
            Cache::forget(self::LLAVE_DE_CACHE);

            throw IdTokenDeGoogleNoValido::porque('las llaves de Google no se pudieron leer', $error);
        }
    }

    /**
     * Pide las llaves otra vez porque llegó una que no está en la caché. Las nuevas reemplazan a las de la caché solo
     * si llegan y se pueden leer; si no, se quedan las que había, para que sigan entrando los idTokens firmados con
     * ellas (registro-E16).
     *
     * @param  array<string, Key>  $vigentes
     * @return array<string, Key>
     */
    private function refrescarLlaves(array $vigentes): array
    {
        try {
            [$conjunto, $segundos] = $this->pedirLlaves();
            $llaves = JWK::parseKeySet($conjunto, self::ALGORITMO);
        } catch (Throwable) {
            return $vigentes;
        }

        Cache::put(self::LLAVE_DE_CACHE, $conjunto, $segundos);

        return $llaves;
    }

    /**
     * @return array{0: array<string, mixed>, 1: int} El conjunto de llaves (JWKS) y los segundos que se guarda.
     */
    private function pedirLlaves(): array
    {
        try {
            $respuesta = Http::timeout(self::SEGUNDOS_DE_ESPERA_A_GOOGLE)
                ->acceptJson()
                ->get((string) config('services.google.llaves'));
        } catch (ConnectionException $error) {
            throw IdTokenDeGoogleNoValido::porque('no se pudo llegar a las llaves de Google', $error);
        }

        if (! $respuesta->successful() || ! is_array($respuesta->json('keys'))) {
            throw IdTokenDeGoogleNoValido::porque("las llaves de Google respondieron {$respuesta->status()}");
        }

        $segundos = preg_match('/max-age=(\d+)/', $respuesta->header('Cache-Control'), $partes)
            ? (int) $partes[1]
            : self::SEGUNDOS_EN_CACHE_POR_OMISION;

        return [$respuesta->json(), max($segundos, 1)];
    }

    /**
     * @return list<string>
     */
    private function clientes(): array
    {
        return array_values(array_filter(
            (array) config('services.google.clientes', []),
            fn ($cliente): bool => $this->esTextoConValor($cliente),
        ));
    }

    private function esTextoConValor(mixed $valor): bool
    {
        return is_string($valor) && $valor !== '';
    }
}
