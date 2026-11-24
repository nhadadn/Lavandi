<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    /**
     * Ninguna prueba escribe en el disco de fotos de la máquina (config/fotos.php): cada una empieza con el disco falso,
     * vacío. El sembrado de prueba guarda fotos (perfil-lavanderia-E03), y sin esto llenaría storage/app/private/fotos,
     * que es el mismo que sirve las fotos en local. Las pruebas que revisan la firma de la URL cambian al disco local con
     * su raíz en un directorio temporal (Tests\Feature\PerfilLavanderia\Soporte\DiscoLocalDeFotos).
     */
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake((string) config('fotos.disco'));
    }
}
