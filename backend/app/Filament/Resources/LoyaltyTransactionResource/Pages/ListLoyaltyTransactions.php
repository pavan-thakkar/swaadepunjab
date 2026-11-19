<?php

namespace App\Filament\Resources\LoyaltyTransactionResource\Pages;

use App\Filament\Resources\LoyaltyTransactionResource;
use App\Services\Loyalty;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLoyaltyTransactions extends ListRecords
{
    protected static string $resource = LoyaltyTransactionResource::class;

    public function getSubheading(): ?string
    {
        $s = Loyalty::settings();
        return $s['enabled']
            ? "Loyalty is ON: {$s['earn_per_100']} points per ₹100, 1 point = ₹{$s['point_value']}. Change in Settings → Store & Receipt Config."
            : 'Loyalty is OFF. Turn it on in Settings → Store & Receipt Config.';
    }

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Add / Remove Points')];
    }
}
