<?php

namespace App\Filament\Resources\MarketingCardResource\Pages;

use App\Filament\Resources\MarketingCardResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMarketingCard extends EditRecord
{
    protected static string $resource = MarketingCardResource::class;

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
