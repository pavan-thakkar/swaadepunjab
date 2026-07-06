<?php

namespace App\Filament\Resources\DeliveryFeeSettingResource\Pages;

use App\Filament\Resources\DeliveryFeeSettingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDeliveryFeeSettings extends ListRecords
{
    protected static string $resource = DeliveryFeeSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
