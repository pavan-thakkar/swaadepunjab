<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCategories extends ListRecords
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('fillImages')
                ->label('Fill missing images from dish photos')
                ->icon('heroicon-o-photo')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Use dish photos for categories?')
                ->modalDescription('Every category without an image gets the best photo from its own dishes (bestsellers first). Categories that already have an image are not changed.')
                ->action(function () {
                    $done = 0; $none = [];
                    foreach (\App\Models\Category::all() as $cat) {
                        if (filled($cat->image)) continue;
                        $photos = array_keys($cat->dishPhotos(5));
                        $ok = false;
                        foreach ($photos as $p) {
                            if ($cat->useDishPhoto($p)) { $ok = true; break; }
                        }
                        $ok ? $done++ : $none[] = $cat->name;
                    }
                    \Filament\Notifications\Notification::make()
                        ->title("{$done} categor" . ($done === 1 ? 'y' : 'ies') . ' updated')
                        ->body($none ? 'No dish photos yet in: ' . implode(', ', array_slice($none, 0, 8)) . (count($none) > 8 ? '…' : '') : null)
                        ->success()->send();
                }),
            Actions\CreateAction::make(),
        ];
    }
}
