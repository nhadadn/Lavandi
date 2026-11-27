<?php

namespace App\Filament\Resources\AvisosMasivos\Pages;

use App\Avisos\CreacionDeAvisoMasivo;
use App\Filament\Resources\AvisosMasivos\AvisoMasivoResource;
use App\Models\Usuario;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Escribir y mandar un aviso masivo (administracion-E23). Lo crea App\Avisos\CreacionDeAvisoMasivo, la misma clase que
 * usa la API de la administración (administracion-app-E11): el aviso queda con la cuenta que lo mandó, la hora y sin
 * plaza ni segmento, porque es global, y su salida por FCM va en cola (App\Avisos\MandarAvisoMasivo), así que el panel
 * no espera a Google.
 */
class CreateAvisoMasivo extends CreateRecord
{
    protected static string $resource = AvisoMasivoResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var Usuario $cuenta */
        $cuenta = Filament::auth()->user();

        return app(CreacionDeAvisoMasivo::class)->crear($cuenta, $data['titulo'], $data['mensaje']);
    }

    public function getTitle(): string
    {
        return 'Escribir aviso masivo';
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'El aviso quedó en cola para salir a los teléfonos registrados.';
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Mandar aviso');
    }
}
