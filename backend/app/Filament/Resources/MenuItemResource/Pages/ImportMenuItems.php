<?php

namespace App\Filament\Resources\MenuItemResource\Pages;

use App\Filament\Resources\MenuItemResource;
use Filament\Resources\Pages\Page;

class ImportMenuItems extends Page
{
    protected static string $resource  = MenuItemResource::class;
    protected static ?string $title    = 'Import Menu Items';
    protected static string $view      = 'filament.resources.menu-item-resource.pages.import-menu-items-redirect';

    public function mount(): void
    {
        // Intentionally empty — view handles the redirect
    }
}
