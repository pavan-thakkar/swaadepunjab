<?php

namespace App\Filament\Resources\MenuItemResource\Pages;

use App\Filament\Resources\MenuItemResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMenuItems extends ListRecords
{
    protected static string $resource = MenuItemResource::class;

    public function getSubheading(): ?string
    {
        return 'Shown in the same order as the website (category order, then dish order). To reorder dishes: filter one category, then use the ⇅ button and drag.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('import')
                ->label('Import Menu')
                ->icon('heroicon-m-arrow-up-tray')
                ->color('info')
                ->url('/admin/menu-import'),
        ];
    }
}
