<?php

namespace App\Pedidos;

/**
 * El folio que recibe un pedido al pasar a Confirmado (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo A #11).
 *
 * Que no se repita en su plaza lo garantiza la base, con el único (id_plazas, folio); aquí solo se propone uno, y
 * App\Pedidos\ConfirmacionDePedido toma otro si la base lo rechaza (confirmacion-E06).
 *
 * El formato lo fija ADR-0020: ocho caracteres al azar del alfabeto de Crockford (sin I, L, O ni U, que se
 * confunden), como recomiendan la brecha (BR-21) y modelo-multi-plaza.md: un folio que no revela cuántos pedidos lleva
 * el negocio. Cambiar el formato es cambiar esta clase.
 *
 * No es final para que las pruebas den los folios que necesitan.
 */
class FolioDelPedido
{
    private const ALFABETO = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    private const LARGO = 8;

    public function nuevo(): string
    {
        $folio = '';

        for ($i = 0; $i < self::LARGO; $i++) {
            $folio .= self::ALFABETO[random_int(0, strlen(self::ALFABETO) - 1)];
        }

        return $folio;
    }
}
