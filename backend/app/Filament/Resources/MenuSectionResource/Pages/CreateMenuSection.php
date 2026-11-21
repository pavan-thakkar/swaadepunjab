<?php

namespace App\Filament\Resources\MenuSectionResource\Pages;

use App\Filament\Resources\MenuSectionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMenuSection extends CreateRecord
{
    protected static string $resource = MenuSectionResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
