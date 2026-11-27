<?php

namespace App\Lavanderias;

use App\Fotos\ReglasDeLaFoto;
use App\Http\Requests\GuardarDireccionRequest;
use App\Models\Municipio;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Las validaciones del registro de la lavandería, escritas una sola vez: las usan el registro de la app y la edición,
 * que pasa por App\Lavanderias\EdicionDeLavanderia::validar() en la app del socio, en la API de la administración y en
 * el panel (openspec/changes/alta-de-lavanderias/design.md, "Decisions"; NSV-2026-001-v8.pdf:17:
 * ANEXO A, Sección A.3, Módulo B #1 y #6; :19:Módulo D #1). La forma de cada campo está en
 * App\Http\Controllers\Api\LavanderiaDelSocioController.
 *
 * El registro pide los cinco datos del Módulo B #1: nombre, dirección, foto, servicios con su precio y horario
 * (alta-lavanderias-E02). La edición pide solo los que cambia, cada uno con la misma regla, y además admite la
 * descripción y la lista de fotos del perfil, que el registro no pide (alta-lavanderias-E26 a E31;
 * NSV-2026-001-v8.pdf:16:Módulo A #5, "Fotos, descripción"; acta-2026-11-20-modelo-y-pasarela.md, decisión 44).
 */
final class ReglasDeLaLavanderia
{
    /** Los datos del registro, por su campo (Módulo B #1). */
    public const CAMPOS = ['nombre', 'direccion', 'foto', 'servicios', 'ventanas'];

    /**
     * Los datos de la edición, en la app, en la API de la administración y en el panel: los del registro, la descripción
     * y la lista de fotos.
     */
    public const CAMPOS_DE_LA_EDICION = [...self::CAMPOS, 'descripcion', 'fotos'];

    /**
     * El largo máximo de la descripción, en caracteres. El contrato pide la descripción y no le pone largo: es un límite
     * técnico para proteger la tabla, el mismo del comentario de la calificación (config/pedidos.php,
     * comentario_de_calificacion_maximo).
     */
    public const DESCRIPCION_MAXIMA = 1000;

    /** Una hora del día, HH:MM, de 00:00 a 23:59. */
    public const HORA = '/^([01][0-9]|2[0-3]):[0-5][0-9]$/';

    /** La hora de fin de una ventana: como HORA, o 24:00, la medianoche del día siguiente. */
    public const HORA_DE_FIN = '/^(([01][0-9]|2[0-3]):[0-5][0-9]|24:00)$/';

    /** Un precio en pesos, sin signo y con hasta dos decimales, que cabe en numeric(12,2) (ADR-0016, regla 7). */
    public const PRECIO = '/^[0-9]{1,10}(\.[0-9]{1,2})?$/';

    /**
     * Las reglas de los campos que se pasan, todos obligatorios: el registro pide los cinco; la edición, los que trae.
     *
     * @param  list<string>  $campos  Algunos de CAMPOS_DE_LA_EDICION (el registro usa CAMPOS).
     * @return array<string, mixed>
     */
    public static function para(array $campos): array
    {
        $reglas = [
            'nombre' => [
                'nombre' => ['required', 'string', 'max:255'],
            ],
            'direccion' => [
                'direccion' => ['required', 'array'],
                'direccion.calle' => ['required', 'string', 'max:255'],
                'direccion.numero' => ['required', 'string', 'max:255'],
                'direccion.colonia' => ['required', 'string', 'max:255'],
                // Cinco números, como en las direcciones del usuario (direcciones-E20).
                'direccion.codigo_postal' => ['bail', 'required', 'string', 'regex:'.GuardarDireccionRequest::CODIGO_POSTAL],
                // El código de un municipio del catálogo del territorio (direcciones-E17 y E18).
                'direccion.municipio' => ['bail', 'required', 'string', Rule::exists(Municipio::class, 'codigo')],
            ],
            'foto' => [
                'foto' => ReglasDeLaFoto::reglas(),
            ],
            'servicios' => [
                'servicios' => ['required', 'array', 'list', 'min:1'],
                'servicios.*' => ['array'],
                // Uno de los tres del contrato, una sola vez (alta-lavanderias-E06; lavanderia_servicios es único por
                // lavandería y tipo).
                'servicios.*.tipo' => ['bail', 'required', 'string', 'max:50', 'distinct', new ServicioAdmitido],
                'servicios.*.precio' => ['bail', 'required', 'regex:'.self::PRECIO],
            ],
            'ventanas' => [
                'ventanas' => ['required', 'array', 'list', 'min:1', 'max:168'],
                // Cada una dura exactamente una hora (alta-lavanderias-E22).
                'ventanas.*' => ['array', new VentanaDeUnaHora],
                'ventanas.*.dia_semana' => ['bail', 'required', 'integer', 'between:1,7'],
                'ventanas.*.hora_inicio' => ['bail', 'required', 'string', 'regex:'.self::HORA],
                'ventanas.*.hora_fin' => ['bail', 'required', 'string', 'regex:'.self::HORA_DE_FIN],
            ],
            // Opcional: vacía, el perfil queda sin descripción (alta-lavanderias-E26).
            'descripcion' => [
                'descripcion' => ['nullable', 'string', 'max:'.self::DESCRIPCION_MAXIMA],
            ],
            // La lista completa y en orden, de una al máximo de la configuración: la última no se quita, porque el
            // registro pide una (alta-lavanderias-E28 y E30). No va junto con la foto principal sola.
            'fotos' => [
                'fotos' => ['bail', 'required', 'array', 'list', 'min:1', 'max:'.FotosDeLaLavanderia::maximo(), 'prohibits:foto'],
                // Cada una, una foto que ya tiene, por su lugar en la lista de la última lectura (0, la primera), o un
                // archivo nuevo con las reglas de toda foto. Que ese lugar exista lo revisa FotosDeLaLavanderia, con la
                // lavandería bloqueada.
                'fotos.*' => Rule::forEach(fn (mixed $foto): array => $foto instanceof UploadedFile
                    ? ReglasDeLaFoto::reglas()
                    : ['bail', 'required', 'integer', 'min:0']),
            ],
        ];

        return array_merge(...array_values(array_intersect_key($reglas, array_flip($campos))));
    }

    /**
     * Los mensajes de cada regla, para la persona que llena el registro.
     *
     * @return array<string, string>
     */
    public static function mensajes(): array
    {
        $tipos = implode(', ', ReglasDeLaFoto::tipos());

        return [
            'nombre.required' => 'Falta el nombre de la lavandería.',
            'direccion.required' => 'Falta la dirección de la lavandería.',
            'direccion.array' => 'La dirección lleva calle, número, colonia, código postal y municipio.',
            'direccion.calle.required' => 'Falta la calle.',
            'direccion.numero.required' => 'Falta el número.',
            'direccion.colonia.required' => 'Falta la colonia.',
            'direccion.codigo_postal.required' => 'Falta el código postal.',
            'direccion.codigo_postal.string' => 'El código postal son cinco números.',
            'direccion.codigo_postal.regex' => 'El código postal son cinco números.',
            'direccion.municipio.required' => 'Falta el municipio.',
            'direccion.municipio.string' => 'Elige un municipio de la lista.',
            'direccion.municipio.exists' => 'Elige un municipio de la lista.',
            'foto.required' => 'Falta la foto de la lavandería.',
            'foto.file' => "La foto debe ser una imagen: {$tipos}.",
            'foto.image' => "La foto debe ser una imagen: {$tipos}.",
            'foto.mimes' => "La foto debe ser una imagen: {$tipos}.",
            'foto.max' => 'La foto pesa más de :max KB.',
            'servicios.required' => 'Falta al menos un servicio con su precio.',
            'servicios.array' => 'Los servicios van en una lista.',
            'servicios.list' => 'Los servicios van en una lista.',
            'servicios.min' => 'Falta al menos un servicio con su precio.',
            'servicios.*.array' => 'Cada servicio lleva su tipo y su precio.',
            'servicios.*.tipo.required' => 'Falta el tipo del servicio.',
            'servicios.*.tipo.distinct' => 'Ese servicio ya está en la lista.',
            'servicios.*.tipo.max' => 'Ese servicio no se admite.',
            'servicios.*.precio.required' => 'Falta el precio del servicio.',
            'servicios.*.precio.regex' => 'El precio va en pesos, sin signo y con hasta dos decimales.',
            'ventanas.required' => 'Falta el horario: al menos una ventana de una hora.',
            'ventanas.array' => 'El horario va en una lista de ventanas.',
            'ventanas.list' => 'El horario va en una lista de ventanas.',
            'ventanas.min' => 'Falta el horario: al menos una ventana de una hora.',
            'ventanas.max' => 'El horario no puede tener más de :max ventanas.',
            'ventanas.*.array' => 'Cada ventana lleva su día, su hora de inicio y su hora de fin.',
            'ventanas.*.dia_semana.required' => 'Falta el día de la ventana.',
            'ventanas.*.dia_semana.integer' => 'El día va del 1, el lunes, al 7, el domingo.',
            'ventanas.*.dia_semana.between' => 'El día va del 1, el lunes, al 7, el domingo.',
            'ventanas.*.hora_inicio.required' => 'Falta la hora de inicio de la ventana.',
            'ventanas.*.hora_inicio.regex' => 'La hora va como HH:MM.',
            'ventanas.*.hora_fin.required' => 'Falta la hora de fin de la ventana.',
            'ventanas.*.hora_fin.regex' => 'La hora va como HH:MM.',
            'ventanas.*.hora_inicio.string' => 'La hora va como HH:MM.',
            'ventanas.*.hora_fin.string' => 'La hora va como HH:MM.',
            'servicios.*.tipo.string' => 'Ese servicio no se admite.',
            'descripcion.string' => 'La descripción debe ser texto.',
            'descripcion.max' => 'La descripción puede tener hasta :max caracteres.',
            'fotos.required' => 'La lavandería necesita al menos una foto.',
            'fotos.min' => 'La lavandería necesita al menos una foto.',
            'fotos.array' => 'Las fotos van en una lista.',
            'fotos.list' => 'Las fotos van en una lista.',
            'fotos.max' => 'La lavandería puede tener hasta :max fotos.',
            'fotos.prohibits' => 'Manda la foto principal o la lista de fotos, no las dos.',
            'fotos.*.required' => 'Cada foto es una que ya tiene, por su lugar en la lista, o una foto nueva.',
            'fotos.*.integer' => 'Cada foto es una que ya tiene, por su lugar en la lista, o una foto nueva.',
            'fotos.*.min' => 'Cada foto es una que ya tiene, por su lugar en la lista, o una foto nueva.',
            'fotos.*.file' => "La foto debe ser una imagen: {$tipos}.",
            'fotos.*.image' => "La foto debe ser una imagen: {$tipos}.",
            'fotos.*.mimes' => "La foto debe ser una imagen: {$tipos}.",
            'fotos.*.max' => 'La foto pesa más de :max KB.',
            'direccion.*.string' => 'Este campo debe ser texto.',
            'direccion.*.max' => 'Este campo es demasiado largo.',
            '*.string' => 'Este campo debe ser texto.',
            '*.max' => 'Este campo es demasiado largo.',
        ];
    }
}
