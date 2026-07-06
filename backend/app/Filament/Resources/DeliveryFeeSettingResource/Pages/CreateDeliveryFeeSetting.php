<?php

namespace App\Filament\Resources\DeliveryFeeSettingResource\Pages;

use App\Filament\Resources\DeliveryFeeSettingResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateDeliveryFeeSetting extends CreateRecord
{
    protected static string $resource = DeliveryFeeSettingResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
