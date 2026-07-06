<?php

namespace App\Filament\Resources\MarketingCardResource\Pages;

use App\Filament\Resources\MarketingCardResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateMarketingCard extends CreateRecord
{
    protected static string $resource = MarketingCardResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
