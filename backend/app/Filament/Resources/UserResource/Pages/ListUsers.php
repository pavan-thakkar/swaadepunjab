<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    public function getSubheading(): ?string
    {
        return 'Give each person their own login. Counter staff see only orders and dish availability; you see everything.';
    }

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Add staff login')];
    }
}
