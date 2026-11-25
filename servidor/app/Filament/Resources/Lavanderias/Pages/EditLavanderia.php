<?php

namespace App\Filament\Resources\Lavanderias\Pages;

use App\Direcciones\GeocodificacionNoDisponible;
use App\Filament\Resources\Lavanderias\Actions\AccionesDeEstado;
use App\Filament\Resources\Lavanderias\LavanderiaResource;
use App\Http\Resources\LavanderiaDelSocioResource;
use App\Lavanderias\DireccionNoUbicable;
use App\Lavanderias\EdicionDeLavanderia;
use App\Lavanderias\FotosDeLaLavanderia;
use App\Lavanderias\ReglasDeLaLavanderia;
use App\Models\Lavanderia;
use App\Models\LavanderiaHorario;
use App\Models\LavanderiaServicio;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * La edición de una lavandería en el panel (alta-lavanderias-E13 y E31; NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3,
 * Módulo D #1, "edición de socios"). Usa la misma edición y las mismas validaciones del registro que la app
 * (App\Lavanderias\EdicionDeLavanderia y App\Lavanderias\ReglasDeLaLavanderia; design.md, "Decisions").
 *
 * Como en el PATCH de la app, solo se manda lo que cambió desde que se abrió la página: así una lavandería de prueba sin
 * municipio, o con franjas largas de horario, se puede editar en lo demás. Las fotos van como en la app: la lista
 * completa, cada una que ya tiene por su lugar y cada nueva como archivo, y solo si cambió. Los lugares se cuentan sobre
 * las fotos que se leyeron al abrir la página, y la edición rechaza la lista si las fotos cambiaron después, por
 * ejemplo desde la app (alta-lavanderias-E31). Cada error vuelve junto al campo del formulario que lo causó.
 */
class EditLavanderia extends EditRecord
{
    protected static string $resource = LavanderiaResource::class;

    /** Los campos de la edición que se comparan con lo que había al abrir, además de las fotos. */
    private const CAMPOS = ['nombre', 'direccion', 'servicios', 'ventanas', 'descripcion'];

    /**
     * Los datos como estaban al abrir la página, en la forma del registro, para mandar solo lo que cambió.
     *
     * @var array<string, mixed>
     */
    #[Locked]
    public array $inicial = [];

    /**
     * Las rutas de las fotos de la lavandería, en su orden, como estaban al abrir la página: los lugares de la lista se
     * cuentan sobre ellas.
     *
     * @var list<string>
     */
    #[Locked]
    public array $fotosAlAbrir = [];

    /**
     * Las acciones de estado: suspender y levantar la suspensión (alta-lavanderias-E14 y E15).
     *
     * @return list<Action>
     */
    protected function getHeaderActions(): array
    {
        return AccionesDeEstado::todas();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Lavanderia $lavanderia */
        $lavanderia = $this->getRecord();
        $this->inicial = self::datosDe($lavanderia);
        $this->fotosAlAbrir = app(FotosDeLaLavanderia::class)->enOrden($lavanderia)->pluck('ruta')->all();

        // Las fotos, en su orden, por su ruta: el selector de archivos del panel las muestra con su URL firmada.
        return [...$this->inicial, 'fotos' => $this->fotosAlAbrir];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Lavanderia $record */
        $nuevos = self::enLaFormaDelRegistro($data);
        $cambios = array_intersect_key($nuevos, array_filter(
            array_flip(self::CAMPOS),
            fn (string $campo): bool => $nuevos[$campo] != ($this->inicial[$campo] ?? null),
            ARRAY_FILTER_USE_KEY,
        ));
        [$fotos, $cambiaron] = $this->fotosDelFormulario();
        $aValidar = $cambiaron ? [...$cambios, 'fotos' => $fotos] : $cambios;

        try {
            $validados = Validator::make(
                $aValidar,
                ReglasDeLaLavanderia::para(array_keys($aValidar)),
                ReglasDeLaLavanderia::mensajes(),
            )->validate();

            app(EdicionDeLavanderia::class)->editar($record, $validados, fotosLeidas: $this->fotosAlAbrir);
        } catch (ValidationException $error) {
            throw $this->enElFormulario($error);
        } catch (DireccionNoUbicable $error) {
            throw ValidationException::withMessages(['data.direccion.calle' => $error->getMessage()]);
        } catch (GeocodificacionNoDisponible $error) {
            report($error);
            Notification::make()->danger()->title('No pudimos ubicar la dirección. Inténtalo más tarde.')->send();

            throw new Halt;
        }

        return $record->refresh();
    }

    /**
     * Las fotos del formulario en la forma de la edición de la app: cada una que ya tiene, por su lugar en la lista que
     * se leyó al abrir la página (la del selector llega como su ruta), y cada nueva como el archivo que se subió y todavía
     * no está guardado. Una ruta que no es de esta lavandería no se pasa como foto: queda en un lugar que no existe y las
     * reglas la rechazan. Dice además si la lista cambió desde que se abrió la página.
     *
     * @return array{0: list<int|TemporaryUploadedFile>, 1: bool}
     */
    private function fotosDelFormulario(): array
    {
        $rutas = $this->fotosAlAbrir;

        $fotos = collect(Arr::wrap($this->data['fotos'] ?? []))
            ->filter(fn (mixed $foto): bool => $foto instanceof TemporaryUploadedFile || is_string($foto))
            ->map(function (TemporaryUploadedFile|string $foto) use ($rutas): int|TemporaryUploadedFile {
                if ($foto instanceof TemporaryUploadedFile) {
                    return $foto;
                }

                $lugar = array_search($foto, $rutas, true);

                return $lugar === false ? -1 : (int) $lugar;
            })
            ->values()
            ->all();

        return [$fotos, $fotos !== array_keys($rutas)];
    }

    /**
     * Después de guardar, el formulario vuelve a leer la lavandería: las fotos nuevas ya son las guardadas.
     */
    protected function afterSave(): void
    {
        $this->fillForm();
    }

    /**
     * La lavandería en la forma de la edición de la app: nombre, descripción, dirección con el código del municipio, los
     * servicios activos con su precio y las ventanas en orden. Las fotos van aparte (fotosDelFormulario()).
     *
     * @return array<string, mixed>
     */
    public static function datosDe(Lavanderia $lavanderia): array
    {
        $lavanderia = LavanderiaDelSocioResource::cargar($lavanderia);

        return [
            'nombre' => $lavanderia->nombre,
            'descripcion' => $lavanderia->descripcion,
            'direccion' => [
                'calle' => $lavanderia->calle,
                'numero' => $lavanderia->numero,
                'colonia' => $lavanderia->colonia,
                'codigo_postal' => $lavanderia->codigo_postal,
                'municipio' => $lavanderia->municipio?->codigo,
            ],
            'servicios' => $lavanderia->servicios->map(fn (LavanderiaServicio $servicio): array => [
                'tipo' => $servicio->tipo->codigo,
                'precio' => $servicio->precio,
            ])->values()->all(),
            'ventanas' => $lavanderia->horarios->map(fn (LavanderiaHorario $ventana): array => [
                'dia_semana' => $ventana->dia_semana,
                'hora_inicio' => substr($ventana->hora_inicio, 0, 5),
                'hora_fin' => substr($ventana->hora_fin, 0, 5),
            ])->values()->all(),
        ];
    }

    /**
     * El estado del formulario en la forma del registro: las filas de los repetidores en lista, sin sus llaves.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function enLaFormaDelRegistro(array $data): array
    {
        $direccion = (array) ($data['direccion'] ?? []);

        // Las opciones del municipio van por su código, y PHP vuelve número la llave "10005": regresa a texto, como lo
        // manda la app. Un código con cero al frente, como "05017", nunca se volvió número.
        if (is_int($direccion['municipio'] ?? null)) {
            $direccion['municipio'] = (string) $direccion['municipio'];
        }

        return [
            'nombre' => $data['nombre'] ?? null,
            'descripcion' => $data['descripcion'] ?? null,
            'direccion' => $direccion,
            'servicios' => array_values((array) ($data['servicios'] ?? [])),
            'ventanas' => array_values(array_map(
                fn (array $ventana): array => [
                    ...$ventana,
                    'dia_semana' => is_numeric($ventana['dia_semana'] ?? null) ? (int) $ventana['dia_semana'] : ($ventana['dia_semana'] ?? null),
                ],
                (array) ($data['ventanas'] ?? []),
            )),
        ];
    }

    /**
     * Los errores de las reglas del registro, cada uno en su campo del formulario: servicios.0.precio es la primera
     * fila del repetidor de servicios, con la llave que le dio el formulario; el error de una ventana completa
     * (ventanas.N) va junto a su hora de fin.
     */
    private function enElFormulario(ValidationException $error): ValidationException
    {
        $mensajes = [];

        foreach ($error->errors() as $clave => $textos) {
            $campo = $this->campoDelFormulario($clave);
            $mensajes[$campo] = [...($mensajes[$campo] ?? []), ...$textos];
        }

        return ValidationException::withMessages($mensajes);
    }

    private function campoDelFormulario(string $clave): string
    {
        $partes = explode('.', $clave);

        // El selector de fotos es un solo campo: el error de una de ellas (fotos.N) va en él.
        if ($partes[0] === 'fotos') {
            return 'data.fotos';
        }

        if (in_array($partes[0], ['servicios', 'ventanas'], true) && isset($partes[1])) {
            $llaves = array_keys((array) ($this->data[$partes[0]] ?? []));
            $partes[1] = (string) ($llaves[(int) $partes[1]] ?? $partes[1]);

            if (count($partes) === 2) {
                $partes[] = $partes[0] === 'ventanas' ? 'hora_fin' : 'precio';
            }
        }

        if ($partes === ['direccion']) {
            $partes = ['direccion', 'calle'];
        }

        return 'data.'.implode('.', $partes);
    }
}
