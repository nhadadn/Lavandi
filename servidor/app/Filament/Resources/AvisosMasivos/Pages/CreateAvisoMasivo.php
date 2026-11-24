<?php

namespace App\Filament\Resources\AvisosMasivos\Pages;

use App\Avisos\MandarAvisoMasivo;
use App\Filament\Resources\AvisosMasivos\AvisoMasivoResource;
use App\Models\AvisoMasivo;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

/**
 * Escribir y mandar un aviso masivo (administracion-E23). Al guardarlo, el aviso queda con la cuenta que lo mandó, la
 * hora y sin plaza ni segmento, porque es global; su salida por FCM va en cola (App\Avisos\MandarAvisoMasivo), así
 * que el panel no espera a Google.
 */
class CreateAvisoMasivo extends CreateRecord
{
    protected static string $resource = AvisoMasivoResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [
            'titulo' => $data['titulo'],
            'mensaje' => $data['mensaje'],
            'id_plazas' => null,
            'segmento' => null,
            'id_usuarios' => Filament::auth()->id(),
            'enviado_en' => now(),
        ];
    }

    protected function afterCreate(): void
    {
        /** @var AvisoMasivo $aviso */
        $aviso = $this->getRecord();

        MandarAvisoMasivo::dispatch($aviso)->afterCommit();
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
