<?php

namespace App\Filament\Resources\CustomisationResource\Pages;

use App\Filament\Resources\CustomisationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCustomisations extends ListRecords
{
    protected static string $resource = CustomisationResource::class;

    public function getSubheading(): ?string
    {
        return 'Make a choice group once (bread, sabji, add-ons) and use it on many dishes. Editing it here updates every dish.';
    }

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('New customisation')];
    }
}
