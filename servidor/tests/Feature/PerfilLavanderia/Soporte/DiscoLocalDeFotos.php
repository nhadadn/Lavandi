<?php

namespace Tests\Feature\PerfilLavanderia\Soporte;

use Illuminate\Contracts\Filesystem\Filesystem as Disco;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * El disco local privado de fotos de desarrollo (fotos_local, config/filesystems.php), con su raíz en un directorio
 * temporal que se borra al terminar la prueba.
 *
 * Es para las pruebas que revisan la firma de la URL: el disco falso de Storage::fake() no firma, y la firma la revisa
 * la ruta que sirve los archivos (/archivos/fotos/...). Las demás pruebas usan el falso, que tests/TestCase.php pone en
 * cada prueba para que ninguna escriba en el disco de fotos de la máquina.
 */
trait DiscoLocalDeFotos
{
    protected function usarDiscoLocalDeFotos(): Disco
    {
        $raiz = sys_get_temp_dir().DIRECTORY_SEPARATOR.'fotos-'.Str::random(8);
        config(['fotos.disco' => 'fotos_local', 'filesystems.disks.fotos_local.root' => $raiz]);
        Storage::forgetDisk('fotos_local');
        $this->beforeApplicationDestroyed(fn () => (new Filesystem)->deleteDirectory($raiz));

        return Storage::disk('fotos_local');
    }
}
