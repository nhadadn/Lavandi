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
 * La edición de una lavandería en el panel (alta-lavanderias-E13; NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo
 * D #1, "edición de socios"). Usa la misma edición y las mismas validaciones del registro que la app
 * (App\Lavanderias\EdicionDeLavanderia y App\Lavanderias\ReglasDeLaLavanderia; design.md, "Decisions").
 *
 * Como en el PATCH de la app, solo se manda lo que cambió desde que se abrió la página: así una lavandería de prueba sin
 * municipio, o con franjas largas de horario, se puede editar en lo demás. La foto solo cambia si se sube una nueva.
 * Cada error vuelve junto al campo del formulario que lo causó.
 */
class EditLavanderia extends EditRecord
{
    protected static string $resource = LavanderiaResource::class;

    /** Los campos del registro que se editan aquí, además de la foto. */
    private const CAMPOS = ['nombre', 'direccion', 'servicios', 'ventanas'];

    /**
     * Los datos como estaban al abrir la página, en la forma del registro, para mandar solo lo que cambió.
     *
     * @var array<string, mixed>
     */
    #[Locked]
    public array $inicial = [];

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

        return [
            ...$this->inicial,
            'foto' => app(FotosDeLaLavanderia::class)->principal($lavanderia)?->ruta,
        ];
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
        $foto = $this->fotoNueva();
        $aValidar = $foto === null ? $cambios : [...$cambios, 'foto' => $foto];

        try {
            $validados = Validator::make(
                $aValidar,
                ReglasDeLaLavanderia::para(array_keys($aValidar)),
                ReglasDeLaLavanderia::mensajes(),
            )->validate();

            app(EdicionDeLavanderia::class)->editar($record, Arr::except($validados, ['foto']), $foto);
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
     * La foto que se subió en el formulario, si se subió una: la que todavía no está guardada. La que ya tenía llega
     * como su ruta y no cuenta.
     */
    private function fotoNueva(): ?TemporaryUploadedFile
    {
        return collect(Arr::wrap($this->data['foto'] ?? []))
            ->first(fn (mixed $archivo): bool => $archivo instanceof TemporaryUploadedFile);
    }

    /**
     * Después de guardar, el formulario vuelve a leer la lavandería: la foto nueva ya es la guardada.
     */
    protected function afterSave(): void
    {
        $this->fillForm();
    }

    /**
     * La lavandería en la forma del registro de la app: nombre, dirección con el código del municipio, los servicios
     * activos con su precio y las ventanas en orden.
     *
     * @return array<string, mixed>
     */
    public static function datosDe(Lavanderia $lavanderia): array
    {
        $lavanderia = LavanderiaDelSocioResource::cargar($lavanderia);

        return [
            'nombre' => $lavanderia->nombre,
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
