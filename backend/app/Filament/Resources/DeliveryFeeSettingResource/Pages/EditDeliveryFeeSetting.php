<?php

namespace App\Filament\Resources\DeliveryFeeSettingResource\Pages;

use App\Filament\Resources\DeliveryFeeSettingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDeliveryFeeSetting extends EditRecord
{
    protected static string $resource = DeliveryFeeSettingResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
