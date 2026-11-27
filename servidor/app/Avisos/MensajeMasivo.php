<?php

namespace App\Avisos;

use App\Models\AvisoMasivo;

/**
 * Arma el mensaje de Firebase Cloud Messaging de un aviso masivo de la administración: el objeto message de la API
 * HTTP v1, sin el token, que pone el canal (App\Avisos\Fcm\CanalDeFcm; administracion-E23).
 *
 * Es siempre un aviso visible, con la parte de Android y la de iPhone, cada una con el título y el texto que escribió
 * la administración, como los momentos del pedido (App\Avisos\MensajeDelPedido). En data lleva solo su tipo, para que
 * la app lo distinga de un cambio de estado: no hay pedido que consultar. FCM pide que cada valor de data sea texto.
 */
final class MensajeMasivo
{
    /** El tipo de mensaje en data. */
    public const AVISO_MASIVO = 'aviso_masivo';

    /**
     * @return array<string, mixed>
     */
    public static function armar(AvisoMasivo $aviso): array
    {
        $titulo = (string) $aviso->titulo;
        $texto = (string) $aviso->mensaje;

        return [
            'data' => ['tipo' => self::AVISO_MASIVO],
            'android' => [
                'priority' => 'high',
                'notification' => ['title' => $titulo, 'body' => $texto],
            ],
            'apns' => [
                'headers' => ['apns-push-type' => 'alert', 'apns-priority' => '10'],
                'payload' => [
                    'aps' => [
                        'alert' => ['title' => $titulo, 'body' => $texto],
                        'sound' => 'default',
                    ],
                ],
            ],
        ];
    }
}
