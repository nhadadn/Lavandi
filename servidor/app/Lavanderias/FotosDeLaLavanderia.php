<?php

namespace App\Lavanderias;

use App\Fotos\AlmacenDeFotos;
use App\Models\Lavanderia;
use App\Models\LavanderiaFoto;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use JsonException;

/**
 * Las fotos de la lavandería en el almacenamiento privado de fotos del entorno (App\Fotos\AlmacenDeFotos; ADR-0006). En
 * la base solo va su ruta, en lavanderia_fotos, con su orden; la app y el panel las ven con una URL firmada que vence.
 * La ruta nunca sale como campo de la API.
 *
 * El registro pide una foto (Módulo B #1): es la principal, la de menor orden. Cambiarla reemplaza esa misma fila y
 * borra el archivo anterior; las demás fotos de la lavandería, si las hay, no se tocan. El perfil que ve el usuario las
 * muestra todas, en su orden (perfil-lavanderia-E03).
 *
 * En la edición, la lavandería y el panel mantienen la lista completa y ordenada de sus fotos, de una al máximo de la
 * configuración (alta-lavanderias-E26 a E31; acta-2026-11-20-modelo-y-pasarela.md, decisión 44, DD-097): la lista que
 * llega reemplaza a la anterior, como los servicios y las ventanas.
 *
 * El panel y la API de la administración nombran cada foto por su lugar en la lectura con que se abrió la edición, y
 * la lista se rechaza si las fotos cambiaron desde entonces (reemplazarTodas() con las rutas leídas). El panel guarda
 * esas rutas en la página; la API las entrega con la ficha en una marca cifrada que la app devuelve con la lista
 * (marcaDeLaLectura() y rutasDeLaMarca(); administracion-app-E14).
 */
final class FotosDeLaLavanderia
{
    /** Una foto que ya tiene se nombra por su lugar en la lista de la última lectura y no existe ahí. */
    public const FOTO_QUE_NO_TIENE = 'Esa foto no está en el perfil de la lavandería.';

    /** La misma foto que ya tiene viene dos veces en la lista. */
    public const FOTO_REPETIDA = 'Esa foto ya está en la lista.';

    /** Las fotos cambiaron desde que el panel las leyó: sus lugares ya no nombran las mismas fotos. */
    public const FOTOS_QUE_CAMBIARON = 'Las fotos de la lavandería cambiaron desde que abriste la página. Vuelve a abrirla y haz el cambio otra vez.';

    public function __construct(private readonly AlmacenDeFotos $almacen) {}

    /**
     * Cuántas fotos puede tener el perfil de una lavandería (config/fotos.php; alta-lavanderias-E30).
     */
    public static function maximo(): int
    {
        return (int) config('fotos.maximo_por_lavanderia');
    }

    /**
     * Guarda la foto nueva como la principal de la lavandería. Devuelve la ruta del archivo que reemplazó, que se borra
     * cuando la transacción quede guardada (borrar()), o null si no había.
     *
     * @param  list<string>  $guardadas  Aquí se anota la ruta nueva, para borrarla si la transacción no se guarda.
     */
    public function reemplazarPrincipal(Lavanderia $lavanderia, UploadedFile $foto, array &$guardadas): ?string
    {
        $ruta = $this->almacen->guardar($foto, 'lavanderias/'.$lavanderia->id_publico);
        $guardadas[] = $ruta;

        $principal = $this->principal($lavanderia);

        if ($principal === null) {
            $lavanderia->fotos()->create(['ruta' => $ruta, 'orden' => 1]);

            return null;
        }

        $anterior = $principal->ruta;
        $principal->update(['ruta' => $ruta]);

        return $anterior;
    }

    /**
     * Reemplaza la lista de fotos de la lavandería por la que llega, completa y en orden (alta-lavanderias-E27 a E29).
     * Cada elemento es una foto que ya tiene, por su lugar en la lista de enOrden() (0, la primera), o un archivo nuevo.
     * Las que no vienen se quitan, las nuevas se guardan, y el orden se reescribe de 1 a n: la primera es la principal.
     *
     * Va dentro de la transacción de la edición, con la lavandería bloqueada. Si una foto que ya tiene no existe o viene
     * dos veces, no guarda nada y lo dice en su lugar de la lista (fotos.N). Si se pasan las rutas sobre las que se
     * contaron los lugares y las fotos ya no son esas, tampoco guarda nada y lo dice en la lista (fotos). Devuelve las
     * rutas de los archivos que quitó, que se borran cuando la transacción quede guardada (borrar()).
     *
     * @param  list<int|string|UploadedFile>  $fotos  Ya validadas con App\Lavanderias\ReglasDeLaLavanderia.
     * @param  list<string>  $guardadas  Aquí se anotan las rutas nuevas, para borrarlas si la transacción no se guarda.
     * @param  list<string>|null  $leidas  Las rutas, en su orden, sobre las que se contaron los lugares (el panel).
     * @return list<string>
     *
     * @throws ValidationException
     */
    public function reemplazarTodas(Lavanderia $lavanderia, array $fotos, array &$guardadas, ?array $leidas = null): array
    {
        $actuales = $this->enOrden($lavanderia);

        if ($leidas !== null && $actuales->pluck('ruta')->all() !== array_values($leidas)) {
            throw ValidationException::withMessages(['fotos' => self::FOTOS_QUE_CAMBIARON]);
        }

        $conservadas = $this->conservadas($actuales, $fotos);

        $quitadas = $actuales->reject(fn (LavanderiaFoto $foto, int $lugar): bool => isset($conservadas[$lugar]));
        $lavanderia->fotos()->whereKey($quitadas->modelKeys())->delete();

        foreach (array_values($fotos) as $indice => $foto) {
            $orden = $indice + 1;

            if ($foto instanceof UploadedFile) {
                $ruta = $this->almacen->guardar($foto, 'lavanderias/'.$lavanderia->id_publico);
                $guardadas[] = $ruta;
                $lavanderia->fotos()->create(['ruta' => $ruta, 'orden' => $orden]);

                continue;
            }

            $actuales[(int) $foto]->update(['orden' => $orden]);
        }

        return $quitadas->pluck('ruta')->values()->all();
    }

    /**
     * Las fotos de la lavandería como están ahora, en la marca que la ficha de la API de la administración entrega como
     * fotos_leidas (administracion-app-E14): sus rutas en su orden, cifradas con la llave de la app, así ninguna ruta
     * sale de la API y nadie la cambia. La app la devuelve con la lista de fotos, y la edición cuenta los lugares sobre
     * esas fotos, como el panel con las que leyó al abrir la página.
     */
    public function marcaDeLaLectura(Lavanderia $lavanderia): string
    {
        return Crypt::encryptString(json_encode($this->enOrden($lavanderia)->pluck('ruta')->all(), JSON_THROW_ON_ERROR));
    }

    /**
     * Las rutas que nombra una marca de marcaDeLaLectura(), para editar() y reemplazarTodas(). Una marca que no viene, o
     * que no salió de aquí, no nombra ninguna foto: la lista vacía, y reemplazarTodas() rechaza la lista de fotos de una
     * lavandería que sí las tiene.
     *
     * @return list<string>
     */
    public function rutasDeLaMarca(mixed $marca): array
    {
        if (! is_string($marca)) {
            return [];
        }

        try {
            $rutas = json_decode(Crypt::decryptString($marca), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            return [];
        }

        return is_array($rutas) && array_is_list($rutas) && array_all($rutas, fn (mixed $ruta): bool => is_string($ruta)) ? $rutas : [];
    }

    /**
     * Las fotos de la lavandería en su orden: la primera es la principal.
     *
     * @return Collection<int, LavanderiaFoto>
     */
    public function enOrden(Lavanderia $lavanderia): Collection
    {
        return $lavanderia->fotos()->orderBy('orden')->orderBy('id_lavanderia_fotos')->get()->values();
    }

    public function principal(Lavanderia $lavanderia): ?LavanderiaFoto
    {
        return $lavanderia->fotos()->orderBy('orden')->orderBy('id_lavanderia_fotos')->first();
    }

    /**
     * La URL firmada con que la app o el panel ven la foto, y cuándo vence, en UTC.
     *
     * @return array{url: string, vence_en: string}
     */
    public function url(LavanderiaFoto $foto): array
    {
        return $this->almacen->urlFirmada($foto->ruta);
    }

    /**
     * Borra los archivos, sin fallar si alguno ya no está.
     *
     * @param  list<string>  $rutas
     */
    public function borrar(array $rutas): void
    {
        $this->almacen->borrar($rutas);
    }

    /**
     * Los lugares de las fotos que ya tiene y que la lista conserva. Revisa todas antes de guardar nada.
     *
     * @param  Collection<int, LavanderiaFoto>  $actuales
     * @param  list<int|string|UploadedFile>  $fotos
     * @return array<int, true>
     *
     * @throws ValidationException
     */
    private function conservadas(Collection $actuales, array $fotos): array
    {
        $conservadas = [];
        $errores = [];

        foreach (array_values($fotos) as $indice => $foto) {
            if ($foto instanceof UploadedFile) {
                continue;
            }

            $lugar = (int) $foto;

            if (! $actuales->has($lugar)) {
                $errores["fotos.{$indice}"] = self::FOTO_QUE_NO_TIENE;
            } elseif (isset($conservadas[$lugar])) {
                $errores["fotos.{$indice}"] = self::FOTO_REPETIDA;
            }

            $conservadas[$lugar] = true;
        }

        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }

        return $conservadas;
    }
}
