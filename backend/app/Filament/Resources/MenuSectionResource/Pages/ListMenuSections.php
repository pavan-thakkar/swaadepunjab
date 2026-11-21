<?php

namespace App\Filament\Resources\MenuSectionResource\Pages;

use App\Filament\Resources\MenuSectionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMenuSections extends ListRecords
{
    protected static string $resource = MenuSectionResource::class;

    public function getSubheading(): ?string
    {
        return 'Sections like "Breakfast 4 PM – 7 PM" that appear on the website home page only during their hours. Drag ⇅ to change their order.';
    }

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('New timed menu')];
    }
}
