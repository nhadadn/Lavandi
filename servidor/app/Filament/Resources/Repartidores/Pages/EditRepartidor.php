<?php

namespace App\Filament\Resources\Repartidores\Pages;

use App\Filament\Resources\Repartidores\Actions\AccionesDeEstado;
use App\Filament\Resources\Repartidores\RepartidorResource;
use App\Models\Repartidor;
use App\Repartidores\EdicionDeRepartidor;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * La edición de un repartidor en el panel (repartidor-E08; NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #2,
 * "edición de socios repartidores"): su nombre, su teléfono, su correo, su foto y su tipo de vehículo, con las mismas
 * reglas del registro de la app (App\Http\Requests\RegistroDeRepartidorRequest), que aplica
 * App\Repartidores\EdicionDeRepartidor. El correo no choca con el suyo propio.
 *
 * La contraseña no se edita aquí. La foto solo cambia si se sube una nueva. Cada error vuelve junto al campo del
 * formulario que lo causó. El repartidor ve el cambio en su perfil de la app.
 */
class EditRepartidor extends EditRecord
{
    protected static string $resource = RepartidorResource::class;

    /**
     * Las acciones de estado: dar de alta, suspender, levantar la suspensión y dar de baja (repartidor-E05 a E07).
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
        /** @var Repartidor $repartidor */
        $repartidor = $this->getRecord();
        $cuenta = $repartidor->cuenta;

        return [
            'nombre' => $cuenta->nombre,
            'telefono' => $cuenta->telefono,
            'correo' => $cuenta->correo,
            'tipo_vehiculo' => $repartidor->tipo_vehiculo,
            'foto' => $cuenta->foto_ruta,
        ];
    }

    /**
     * La validación y el guardado son de App\Repartidores\EdicionDeRepartidor, que usa también la API de la
     * administración (ADR-0022, decisión 9). Aquí solo se pasan los datos del formulario y la foto nueva, y cada error
     * vuelve bajo data.<campo>, donde el formulario lo pone junto a su campo.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Repartidor $record */
        $datos = Arr::only($data, EdicionDeRepartidor::CAMPOS);
        $foto = $this->fotoNueva();

        try {
            app(EdicionDeRepartidor::class)->editar($record, $foto === null ? $datos : [...$datos, 'foto' => $foto]);
        } catch (ValidationException $error) {
            throw ValidationException::withMessages(collect($error->errors())
                ->mapWithKeys(fn (array $textos, string $campo): array => ["data.{$campo}" => $textos])
                ->all());
        }

        return $record->refresh();
    }

    /**
     * Después de guardar, el formulario vuelve a leer al repartidor: la foto nueva ya es la guardada.
     */
    protected function afterSave(): void
    {
        $this->fillForm();
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
}
