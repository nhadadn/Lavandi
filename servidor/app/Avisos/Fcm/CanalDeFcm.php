<?php

namespace App\Avisos\Fcm;

use Firebase\JWT\JWT;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * El canal de Firebase Cloud Messaging: manda un mensaje a un teléfono por la API HTTP v1 de FCM, sin intermediarios
 * (ADR-0008; openspec/changes/avisos/design.md, "El canal de FCM").
 *
 * - Se autentica con la cuenta de servicio del entorno (CuentaDeServicio): firma con su llave un JWT para el alcance de
 *   FCM y lo cambia por un token de acceso de OAuth en el punto de tokens de Google. El token de acceso se guarda en
 *   la caché hasta cinco minutos antes de que venza; si FCM lo rechaza, se olvida.
 * - Un token que FCM dice que ya no sirve sale como TokenRechazado, para que quien manda el aviso lo borre (avisos-E06).
 * - Una falla de la red o de Google sale como FcmNoDisponible, nunca como otra excepción: quien manda el aviso decide
 *   qué hacer con ella, y el pedido no depende de él (avisos-E02).
 *
 * Nada del canal guarda ni escribe en la bitácora el token del teléfono, la llave ni el token de acceso.
 */
final class CanalDeFcm
{
    private const DIRECCION_DE_FCM = 'https://fcm.googleapis.com/v1/projects';

    private const ALCANCE = 'https://www.googleapis.com/auth/firebase.messaging';

    private const TIPO_DE_CONCESION = 'urn:ietf:params:oauth:grant-type:jwt-bearer';

    /** Lo que vive la aserción firmada, en segundos: el máximo que acepta Google. */
    private const VIDA_DE_LA_ASERCION = 3600;

    /** El token de acceso se deja de usar este tiempo, en segundos, antes de que venza. */
    private const MARGEN_ANTES_DE_VENCER = 300;

    /**
     * Manda el mensaje al teléfono de ese token.
     *
     * @param  array<string, mixed>  $mensaje  El objeto message de la API HTTP v1, sin el token.
     *
     * @throws TokenRechazado si FCM dice que el token ya no sirve
     * @throws FcmNoDisponible
     */
    public function mandar(string $token, array $mensaje): void
    {
        $cuenta = CuentaDeServicio::desdeLaConfiguracion();
        $acceso = $this->tokenDeAcceso($cuenta);

        try {
            $respuesta = Http::withToken($acceso)
                ->acceptJson()
                ->timeout($this->segundosDeEspera())
                ->post(self::DIRECCION_DE_FCM."/{$cuenta->proyecto}/messages:send", [
                    'message' => ['token' => $token, ...$mensaje],
                ]);
        } catch (ConnectionException $error) {
            throw FcmNoDisponible::porque('no se pudo llegar a FCM', $error);
        }

        if ($respuesta->successful()) {
            return;
        }

        if ($this->rechazaElToken($respuesta)) {
            throw new TokenRechazado($this->codigoDeError($respuesta));
        }

        if ($respuesta->status() === 401) {
            Cache::forget($this->llaveDeCache($cuenta));
        }

        throw $this->falla($respuesta);
    }

    /**
     * Si FCM dice que el token ya no sirve (avisos-E06), con los códigos de la API HTTP v1:
     *
     * - UNREGISTERED (404): la app se desinstaló o el token venció;
     * - SENDER_ID_MISMATCH (403): el token es de otro proyecto de Firebase;
     * - INVALID_ARGUMENT (400) cuando el mensaje de Google dice que es el token el que no es válido. Un INVALID_ARGUMENT
     *   por otra cosa, como un mensaje mal armado, no borra el token.
     */
    private function rechazaElToken(Response $respuesta): bool
    {
        return match ($this->codigoDeError($respuesta)) {
            'UNREGISTERED', 'SENDER_ID_MISMATCH' => true,
            'INVALID_ARGUMENT' => str_contains(strtolower((string) $respuesta->json('error.message')), 'registration token'),
            default => false,
        };
    }

    /**
     * El error de FCM como falla: reintentable si puede pasar solo (401, 429 y 5xx), sin remedio si es del mensaje.
     */
    private function falla(Response $respuesta): FcmNoDisponible
    {
        $estado = $respuesta->status();
        $motivo = "FCM respondió {$estado} ".$this->codigoDeError($respuesta);

        return $estado === 401 || $estado === 429 || $estado >= 500
            ? FcmNoDisponible::porque($motivo)
            : FcmNoDisponible::sinRemedio($motivo);
    }

    /**
     * El código de error de FCM (errorCode del detalle FcmError) o, si no lo trae, el estado del error de Google.
     */
    private function codigoDeError(Response $respuesta): string
    {
        foreach ((array) $respuesta->json('error.details', []) as $detalle) {
            if (is_array($detalle) && is_string($detalle['errorCode'] ?? null)) {
                return $detalle['errorCode'];
            }
        }

        $estado = $respuesta->json('error.status');

        return is_string($estado) ? $estado : 'sin código';
    }

    /**
     * El token de acceso de OAuth para FCM, de la caché o pedido a Google con la aserción firmada por la cuenta de
     * servicio.
     *
     * @throws FcmNoDisponible
     */
    private function tokenDeAcceso(CuentaDeServicio $cuenta): string
    {
        $llave = $this->llaveDeCache($cuenta);
        $guardado = Cache::get($llave);

        if (is_string($guardado) && $guardado !== '') {
            return $guardado;
        }

        try {
            $respuesta = Http::asForm()
                ->acceptJson()
                ->timeout($this->segundosDeEspera())
                ->post($cuenta->direccionDeTokens, [
                    'grant_type' => self::TIPO_DE_CONCESION,
                    'assertion' => $this->asercion($cuenta),
                ]);
        } catch (ConnectionException $error) {
            throw FcmNoDisponible::porque('no se pudo pedir el token de acceso a Google', $error);
        }

        $acceso = $respuesta->json('access_token');

        if (! $respuesta->successful() || ! is_string($acceso) || $acceso === '') {
            $motivo = "el punto de tokens respondió {$respuesta->status()}";

            throw $respuesta->status() === 429 || $respuesta->status() >= 500
                ? FcmNoDisponible::porque($motivo)
                : FcmNoDisponible::sinRemedio($motivo);
        }

        $vida = (int) $respuesta->json('expires_in', self::VIDA_DE_LA_ASERCION);
        Cache::put($llave, $acceso, max(60, $vida - self::MARGEN_ANTES_DE_VENCER));

        return $acceso;
    }

    /**
     * El JWT que la cuenta de servicio firma con su llave para pedir el token de acceso.
     *
     * @throws FcmNoDisponible si la llave no sirve para firmar
     */
    private function asercion(CuentaDeServicio $cuenta): string
    {
        $ahora = now()->getTimestamp();

        try {
            return JWT::encode([
                'iss' => $cuenta->correo,
                'scope' => self::ALCANCE,
                'aud' => $cuenta->direccionDeTokens,
                'iat' => $ahora,
                'exp' => $ahora + self::VIDA_DE_LA_ASERCION,
            ], $cuenta->llavePrivada, 'RS256', $cuenta->idDeLlave);
        } catch (Throwable $error) {
            throw FcmNoDisponible::sinRemedio('la llave de la cuenta de servicio no sirve para firmar', $error);
        }
    }

    /**
     * Un token de acceso por cuenta de servicio, por si el entorno cambia de cuenta.
     */
    private function llaveDeCache(CuentaDeServicio $cuenta): string
    {
        return 'avisos.fcm.token-de-acceso.'.sha1($cuenta->proyecto.'|'.$cuenta->correo);
    }

    private function segundosDeEspera(): int
    {
        return (int) config('avisos.fcm.segundos_de_espera', 10);
    }
}
