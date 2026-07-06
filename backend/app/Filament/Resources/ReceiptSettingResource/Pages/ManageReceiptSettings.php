<?php

namespace App\Filament\Resources\ReceiptSettingResource\Pages;

use App\Filament\Resources\ReceiptSettingResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageReceiptSettings extends ManageRecords
{
    protected static string $resource = ReceiptSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
