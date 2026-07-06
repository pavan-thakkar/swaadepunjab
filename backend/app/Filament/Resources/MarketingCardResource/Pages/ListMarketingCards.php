<?php

namespace App\Filament\Resources\MarketingCardResource\Pages;

use App\Filament\Resources\MarketingCardResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMarketingCards extends ListRecords
{
    protected static string $resource = MarketingCardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
