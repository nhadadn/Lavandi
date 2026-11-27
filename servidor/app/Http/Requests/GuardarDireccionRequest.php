<?php

namespace App\Http\Requests;

use App\Direcciones\Punto;
use App\Models\Municipio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Una dirección nueva, escrita o marcada en el mapa (openspec/changes/direcciones/specs/direcciones/spec.md,
 * "Ingreso manual de la dirección", "Ingreso desde el mapa" y "Direcciones favoritas"). La forma exacta está en
 * App\Http\Controllers\Api\DireccionesController.
 *
 * - La colonia es obligatoria siempre (direcciones-E02; presupuesto-de-mapas.md, decisión 4).
 * - La escrita pide además calle, número exterior y municipio: sin ellos no se puede geocodificar (direcciones-E01).
 *   No lleva pin: su punto sale de la geocodificación.
 * - La del mapa pide el pin, latitud y longitud, y la colonia; las demás señas son opcionales, porque sin
 *   geocodificación inversa nadie las sugiere (direcciones-E04).
 * - El municipio es el código de uno del catálogo del territorio, en la escrita y en la del mapa si viene: otro, como
 *   Saltillo, se rechaza (direcciones-E17 y E18; C-238).
 * - El código postal se pide y no se exige; si viene, son cinco números. Ni él ni la colonia se comparan con el
 *   catálogo, que solo sugiere (direcciones-E20; design.md, decisiones 8 y 9).
 * - Una favorita lleva su nombre (direcciones-E06).
 *
 * Esta validación corre antes de consultar el servicio, así que una dirección incompleta no gasta ninguna consulta.
 */
class GuardarDireccionRequest extends FormRequest
{
    /** Escrita: calle y número, colonia y municipio, que el servidor geocodifica (direcciones-E01). */
    public const ESCRITA = 'escrita';

    /** Marcada en el mapa: el punto del pin, sin geocodificar (direcciones-E04). */
    public const MAPA = 'mapa';

    /** Cinco números, como los códigos postales del catálogo de Correos de México (direcciones-E20). */
    public const CODIGO_POSTAL = '/^[0-9]{5}$/';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'origen' => ['required', Rule::in([self::ESCRITA, self::MAPA])],
            'calle' => ['required_if:origen,'.self::ESCRITA, 'nullable', 'string', 'max:255'],
            'numero_exterior' => ['required_if:origen,'.self::ESCRITA, 'nullable', 'string', 'max:255'],
            'numero_interior' => ['nullable', 'string', 'max:255'],
            'colonia' => ['required', 'string', 'max:255'],
            // Si viene, cinco números como texto; no se compara con el catálogo (direcciones-E20).
            'codigo_postal' => ['bail', 'nullable', 'string', 'regex:'.self::CODIGO_POSTAL],
            // El código de un municipio del catálogo del territorio (direcciones-E17 y E18).
            'municipio' => [
                'bail',
                'required_if:origen,'.self::ESCRITA,
                'nullable',
                'string',
                Rule::exists(Municipio::class, 'codigo'),
            ],
            'referencias' => ['nullable', 'string', 'max:1000'],
            'latitud' => ['required_if:origen,'.self::MAPA, 'prohibited_unless:origen,'.self::MAPA, 'numeric', 'between:-90,90'],
            'longitud' => ['required_if:origen,'.self::MAPA, 'prohibited_unless:origen,'.self::MAPA, 'numeric', 'between:-180,180'],
            'es_favorita' => ['sometimes', 'boolean'],
            'alias' => ['required_if_accepted:es_favorita', 'nullable', 'string', 'max:255'],
        ];
    }

    public function esDelMapa(): bool
    {
        return $this->validated('origen') === self::MAPA;
    }

    /**
     * El pin de una dirección marcada en el mapa.
     */
    public function pin(): Punto
    {
        return new Punto((float) $this->validated('latitud'), (float) $this->validated('longitud'));
    }

    /**
     * El municipio del catálogo elegido, con su estado; null si no vino, lo que solo pasa en la del mapa. La validación
     * ya comprobó que está en el catálogo.
     */
    public function municipio(): ?Municipio
    {
        $codigo = $this->validated('municipio');

        return $codigo === null
            ? null
            : Municipio::query()->with('entidadFederativa')->where('codigo', $codigo)->sole();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'origen.required' => 'Falta decir si la dirección es escrita o del mapa.',
            'origen.in' => 'La dirección es escrita o del mapa.',
            'calle.required_if' => 'Falta la calle.',
            'numero_exterior.required_if' => 'Falta el número.',
            'colonia.required' => 'Falta la colonia.',
            'codigo_postal.string' => 'El código postal son cinco números.',
            'codigo_postal.regex' => 'El código postal son cinco números.',
            'municipio.required_if' => 'Falta el municipio.',
            'municipio.string' => 'Elige un municipio de la lista.',
            'municipio.exists' => 'Elige un municipio de la lista.',
            'latitud.required_if' => 'Falta la latitud del pin.',
            'longitud.required_if' => 'Falta la longitud del pin.',
            'latitud.prohibited_unless' => 'Una dirección escrita no lleva pin.',
            'longitud.prohibited_unless' => 'Una dirección escrita no lleva pin.',
            'latitud.numeric' => 'La latitud debe ser un número.',
            'longitud.numeric' => 'La longitud debe ser un número.',
            'latitud.between' => 'La latitud va de -90 a 90.',
            'longitud.between' => 'La longitud va de -180 a 180.',
            'es_favorita.boolean' => 'Favorita es sí o no.',
            'alias.required_if_accepted' => 'Falta el nombre de la favorita.',
            '*.string' => 'Este campo debe ser texto.',
            '*.max' => 'Este campo es demasiado largo.',
        ];
    }
}
