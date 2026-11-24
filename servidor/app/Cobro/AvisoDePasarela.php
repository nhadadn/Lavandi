<?php

namespace App\Cobro;

/**
 * Un aviso de la pasarela ya traducido a un evento sin proveedor (openspec/changes/pago/design.md, decisión 2): su
 * identificador en la pasarela, su tipo (aprobado, rechazado, vencido u otro, las constantes de EventoPasarela), la
 * orden a la que se refiere, si es de una, y su contenido tal como llegó.
 */
final readonly class AvisoDePasarela
{
    /**
     * @param  string  $tipo  EventoPasarela::APROBADO, RECHAZADO, VENCIDO u OTRO
     * @param  array<array-key, mixed>  $contenido
     */
    public function __construct(
        public string $idEvento,
        public string $tipo,
        public ?string $idOrden,
        public array $contenido,
    ) {}

    public function esDe(string $tipo): bool
    {
        return $this->tipo === $tipo;
    }
}
