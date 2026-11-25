<?php

namespace App\TextosLegales;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * El aviso de privacidad y los términos y condiciones de EL CLIENTE: cuáles son, su título y su texto vigente, que es
 * un archivo en el almacenamiento privado del entorno (config/textos_legales.php; ADR-0006). El texto se entrega tal
 * como está en el archivo, sin redactarlo ni cambiarlo (NSV-2026-001-v8.pdf:31:ANEXO B, Sección B.7;
 * textos-legales-E01).
 */
final class DocumentosLegales
{
    /**
     * Las llaves de los documentos, como las recibe el comando de carga: aviso-de-privacidad y terminos.
     *
     * @return list<string>
     */
    public function documentos(): array
    {
        return array_keys((array) config('textos_legales.documentos'));
    }

    public function existe(string $documento): bool
    {
        return in_array($documento, $this->documentos(), true);
    }

    public function titulo(string $documento): string
    {
        return (string) config("textos_legales.documentos.{$documento}.titulo");
    }

    /**
     * El texto vigente del documento, tal como está en el archivo. Ninguno si el archivo no está cargado, o está vacío o
     * solo con espacios (textos-legales-E02).
     */
    public function vigente(string $documento): ?string
    {
        $texto = $this->disco()->get($this->archivo($documento));

        return is_string($texto) && trim($texto) !== '' ? $texto : null;
    }

    /**
     * Deja el texto como la versión vigente del documento, en lugar de la anterior: desde ese momento su dirección lo
     * muestra (textos-legales-E04). No lo cambia. Falso si el almacenamiento no lo guardó: la versión anterior se queda.
     */
    public function cargar(string $documento, string $texto): bool
    {
        return $this->disco()->put($this->archivo($documento), $texto);
    }

    /**
     * La dirección pública del documento, la de su ruta. No cambia con una versión nueva.
     */
    public function direccion(string $documento): string
    {
        return route("textos-legales.{$documento}");
    }

    private function archivo(string $documento): string
    {
        return (string) config("textos_legales.documentos.{$documento}.archivo");
    }

    private function disco(): Filesystem
    {
        return Storage::disk((string) config('textos_legales.disco'));
    }
}
