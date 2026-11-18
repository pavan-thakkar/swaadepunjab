<?php

namespace App\Filament\Resources\FeaturedCardResource\Pages;

use App\Filament\Resources\FeaturedCardResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFeaturedCard extends CreateRecord
{
    protected static string $resource = FeaturedCardResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
