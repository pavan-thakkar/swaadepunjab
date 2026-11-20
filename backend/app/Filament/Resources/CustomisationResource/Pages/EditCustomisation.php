<?php

namespace App\Filament\Resources\CustomisationResource\Pages;

use App\Filament\Resources\CustomisationResource;
use Filament\Resources\Pages\EditRecord;

class EditCustomisation extends EditRecord
{
    protected static string $resource = CustomisationResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
