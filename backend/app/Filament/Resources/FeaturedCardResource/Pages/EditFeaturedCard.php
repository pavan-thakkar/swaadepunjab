<?php

namespace App\Filament\Resources\FeaturedCardResource\Pages;

use App\Filament\Resources\FeaturedCardResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFeaturedCard extends EditRecord
{
    protected static string $resource = FeaturedCardResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
