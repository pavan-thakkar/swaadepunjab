<?php

namespace App\Filament\Resources\MenuSectionResource\Pages;

use App\Filament\Resources\MenuSectionResource;
use Filament\Resources\Pages\EditRecord;

class EditMenuSection extends EditRecord
{
    protected static string $resource = MenuSectionResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
