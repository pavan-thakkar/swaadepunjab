<?php

namespace App\Filament\Resources\LoyaltyTransactionResource\Pages;

use App\Filament\Resources\LoyaltyTransactionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLoyaltyTransaction extends CreateRecord
{
    protected static string $resource = LoyaltyTransactionResource::class;

    protected static ?string $title = 'Add / Remove Points';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type'] = 'adjust';
        $data['note'] = 'Admin: ' . ($data['note'] ?? '');
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
